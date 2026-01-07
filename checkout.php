<?php
require "config.php";
require_once "classes/Security.php"; 

if (session_status() === PHP_SESSION_NONE) session_start();

// Cart Check
if (!isset($_SESSION['pending_cart']) || !isset($_SESSION['temp_image_data'])) {
    header("Location: upload.php");
    exit;
}

$error = "";
$cart = $_SESSION['pending_cart'];
$isLogged = isset($_SESSION['user_id']);

// Key: Preparing for return after external registration
if (!$isLogged) {
    // If user clicks registration link, verification system will send them back to cart
    $_SESSION['redirect_after_auth'] = 'cart.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // START OF THE TRANSACTION (BDD Security)
    $db->beginTransaction();

    try {
        $userId = 0;

        // Authentication Management (If not logged in)
        if (!$isLogged) {
            // Login
            if (isset($_POST['has_account']) && $_POST['has_account'] == '1') {
                $userData = $userMgr->login($_POST['login_email'], $_POST['login_password']);
                if (!$userData) throw new Exception(msg('error_login_fail'));
                $_SESSION['user_id'] = $userData['id'];
                $userId = $userData['id'];
            } 
            // Quick Registration (Inline)
            else {
                if (!Security::isPasswordStrong($_POST['password'])) throw new Exception(msg('error_pwd_weak'));
                if (!Security::verifyCaptcha($_POST['cf-turnstile-response'] ?? null)) throw new Exception(msg('error_captcha'));

                // Email encryption for storage
                $encryptedEmail = Security::encrypt($_POST['email']);
                
                // Username generation
                $emailParts = explode('@', $_POST['email']);
                $baseUsername = substr($emailParts[0], 0, 15);
                $username = $baseUsername . '_' . rand(100,999);
                
                $newId = $userMgr->register($username, $encryptedEmail, $_POST['password']);
                if (!$newId) throw new Exception(msg('error_duplicate'));
                
                $_SESSION['user_id'] = $newId;
                $userId = $newId;
            }
        } else {
            $userId = $_SESSION['user_id'];
        }

        //Data Encryption
        $encAddress = Security::encrypt($_POST['address']);
        $encPhone   = Security::encrypt($_POST['phone']);
        $encEmail   = Security::encrypt($_POST['email'] ?? '');

        // User Profile Update
        // Update address, city, country, and phone in users table
        $stmtUpdate = $db->prepare("
            UPDATE users 
            SET address=?, city=?, country=?, phone=?, email=?
            WHERE id=?
        ");
        
        if (!empty($_POST['email'])) {
             $stmtUpdate->execute([$encAddress, $_POST['city'], $_POST['country'], $encPhone, $encEmail, $userId]);
        } else {
             $stmtUpdateAddr = $db->prepare("UPDATE users SET address=?, city=?, country=?, phone=? WHERE id=?");
             $stmtUpdateAddr->execute([$encAddress, $_POST['city'], $_POST['country'], $encPhone, $userId]);
        }

        //Mock Payment
        $cardNumber = str_replace(' ', '', $_POST['card_number']);
        if ($cardNumber !== '4242424242424242' || $_POST['cvc'] !== '123') {
            throw new Exception("Paiement refusé. Utilisez la carte de test.");
        }

        //Image Generation & Order
        
        // Image retrieval
        $sourceImageBase64 = $_SESSION['temp_image_data'];
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $sourceImageBase64));
        $sourceImg = imagecreatefromstring($imageData);
        if (!$sourceImg) throw new Exception("Erreur image source");

        // Resizing
        $targetSize = $cart['size'];
        $destImg = imagecreatetruecolor($targetSize, $targetSize);
        imageantialias($destImg, false);
        imagecopyresampled($destImg, $sourceImg, 0, 0, 0, 0, $targetSize, $targetSize, imagesx($sourceImg), imagesy($sourceImg));

        // Filters
        imagefilter($destImg, IMG_FILTER_GRAYSCALE);
        if ($cart['style'] === 'blue') {
            imagefilter($destImg, IMG_FILTER_COLORIZE, 0, 10, 80); 
            imagefilter($destImg, IMG_FILTER_CONTRAST, -15);
        } elseif ($cart['style'] === 'red') {
            imagefilter($destImg, IMG_FILTER_COLORIZE, 90, 0, 0);
            imagefilter($destImg, IMG_FILTER_CONTRAST, -15);
        } else {
            imagefilter($destImg, IMG_FILTER_CONTRAST, -25);
        }

        // Blob
        ob_start();
        imagepng($destImg);
        $finalBlob = ob_get_clean();
        imagedestroy($sourceImg);
        imagedestroy($destImg);

        //Save Image
        $stmtImg = $db->prepare("INSERT INTO images (user_id, extension, target_size, largeur, hauteur, poids, filename) VALUES (?, 'png', ?, ?, ?, 0, 'generated_mosaic')");
        $stmtImg->execute([$userId, $targetSize, $targetSize, $targetSize]);
        $newImageId = $db->lastInsertId();

        //Save Order
        $stmtCmd = $db->prepare("
            INSERT INTO commandes (
                user_id, image_id, final_image_blob, selected_style, total_price, statut, date_commande,
                delivery_address, delivery_phone
            ) 
            VALUES (?, ?, ?, ?, ?, 'payée', NOW(), ?, ?)
        ");
        
        $stmtCmd->execute([
            $userId, 
            $newImageId, 
            $finalBlob, 
            $cart['style'], 
            $cart['price'],
            $encAddress,  // Encrypted Address
            $encPhone     // Encrypted Phone
        ]);

        $commandeId = $db->lastInsertId();

        //AUTOMATIC GENERATION OF INVOICE
        // We retrieve the CLIENT CODE generated by UserManager
        $stmtClient = $db->prepare("SELECT code_client FROM client WHERE user_id = ?");
        $stmtClient->execute([$userId]);
        $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);

        if (!$clientRow) {
            // Security: If no client (rare bug), we create a temporary one to avoid blocking
            $codeClient = "WEB" . str_pad($userId, 6, '0', STR_PAD_LEFT);
            $stmtFix = $db->prepare("INSERT INTO client (code_client, user_id, nom, email_fact) VALUES (?, ?, 'Client Web', ?)");
            $emailFact = $_POST['email'] ?? 'unknown@email.com';
            $stmtFix->execute([$codeClient, $userId, Security::encrypt($emailFact)]);
        }else {
            $codeClient = $clientRow['code_client'];
        }
        // Preparation of CLEAR data for the invoice
        $adresseClaire = Security::decrypt($encAddress);
        $phoneClaire = Security::decrypt($encPhone);
        $villeClaire   = $_POST['city'] ?? 'Ville inconnue';
        $cpClaire = "00000";

        $db->query("INSERT IGNORE INTO commercial (code_commercial, nom_commercial) VALUES ('WEB', 'Vente Site Internet')");

        // creating an invoice
        $sqlFacture = "INSERT INTO facture (
            commande_id, code_client, code_commercial, 
            type_document, etat_facture, validation,
            date_document, nom_client, 
            adresse_fact, cp_fact, ville_fact
        ) VALUES (
            ?, ?, 'WEB', 
            'FACTURE', 'BROUILLON', 0, 
            CURDATE(), ?, 
            ?, ?, ?
        )";

        $nomClientFacture = "Client " . $codeClient; 

        $stmtFact = $db->prepare($sqlFacture);
        $stmtFact->execute([
            $commandeId, 
            $codeClient, 
            $nomClientFacture,
            $adresseClaire, 
            $cpClaire, 
            $villeClaire
        ]);

        // Retrieval of the generated invoice ID (FA2026...)
        $stmtGetId = $db->prepare("SELECT id_facture FROM facture WHERE commande_id = ?");
        $stmtGetId->execute([$commandeId]);
        $factureRow = $stmtGetId->fetch(PDO::FETCH_ASSOC);
        $idFacture = $factureRow['id_facture'];


        // Adding the Invoice Line
        $prixTTC = floatval($cart['price']);
        $prixHT = $prixTTC / 1.2;

        $idLigne = uniqid(); 

        $sqlLigne = "INSERT INTO ligne_facture (
            id_ligne_facture, num_ligne, 
            id_facture, id_article, 
            designation_article_cache, 
            quantite, prix_unitaire_ht, pourcentage_remise_ligne
        ) VALUES (
            ?, 1, 
            ?, 'KIT_MOSAIQUE', 
            ?, 
            1, ?, 0
        )";

        $designation = "Kit Mosaïque " . $cart['size'] . "x" . $cart['size'] . " (" . ucfirst($cart['style']) . ")";

        $db->query("INSERT IGNORE INTO article (id_article, description, prix_unitaire_ht, taux_tva) VALUES ('KIT_MOSAIQUE', 'Kit Mosaïque Lego', 0, 20.00)");

        $stmtLigne = $db->prepare($sqlLigne);
        $stmtLigne->execute([
            $idLigne,
            $idFacture,
            $designation,
            $prixHT
        ]);

        // invoice validation
        $db->prepare("UPDATE facture SET etat_facture = 'VALIDEE', validation = 1 WHERE id_facture = ?")->execute([$idFacture]);

        Logger::log($db, 'ORDER_PAID', "Commande $commandeId validée et facturée($idFacture) User $userId");
        $db->commit();
        unset($_SESSION['pending_cart']);
        unset($_SESSION['redirect_after_auth']); // Cleanup
        
        header("Location: confirmation.php");
        exit;

    } catch (Exception $e) {
        $db->rollBack(); // In case of error, we cancel everything (no order, no invoice, nothing)
        $error = $e->getMessage();
    }
}

include "header.php";
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style>
    .checkout-container { max-width: 1100px; margin: 40px auto; padding: 20px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
    .section-box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
    h2 { font-size: 1.3rem; margin-bottom: 20px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .form-group { margin-bottom: 15px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: 500; color: #475569; }
    input[type="text"], input[type="email"], input[type="password"], input[type="tel"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
    .mock-payment { background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; }
    .btn-confirm { background: #10b981; color: white; width: 100%; padding: 15px; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
    
    .btn-register-alt {
        display: block;
        width: 100%;
        text-align: center;
        background: white;
        color: #2563eb;
        border: 2px solid #2563eb;
        padding: 10px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 15px;
        transition: 0.2s;
    }
    .btn-register-alt:hover { background: #eff6ff; }
    
    .error-msg { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px; }
    .hidden { display: none; }
    
    .divider { text-align: center; margin: 20px 0; color: #94a3b8; font-size: 0.9rem; position: relative; }
    .divider::before, .divider::after { content: ""; position: absolute; top: 50%; width: 40%; height: 1px; background: #e2e8f0; }
    .divider::before { left: 0; }
    .divider::after { right: 0; }
</style>

<div class="checkout-container">
    <div class="main-form">
        <h1><?= msg('checkout_title') ?></h1>
        <?php if($error): ?><div class="error-msg"> <?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div class="section-box">
                <h2><?= msg('section_account') ?></h2>
                <?php if($isLogged): ?>
                    <div style="background:#dcfce7; padding:10px; border-radius:6px; color:#166534;">
                        <?= msg('status_connected') ?>
                    </div>
                <?php else: ?>
                    <label style="background:#eff6ff; padding:10px; border-radius:6px; margin-bottom:15px; display:block; cursor:pointer;">
                        <input type="checkbox" name="has_account" value="1" onchange="toggleAuth(this)"> <?= msg('lbl_have_account') ?>
                    </label>
                    
                    <div id="register-fields">
                        <div class="form-group"><label><?= msg('lbl_email') ?></label><input type="email" name="email" required></div>
                        <div class="form-group"><label><?= msg('lbl_password') ?></label><input type="password" name="password" required></div>
                        <div class="cf-turnstile" data-sitekey=""></div>
                        
                        <div class="divider"><?= msg('separator_or') ?></div>
                        <p style="text-align:center; font-size:0.9rem; color:#64748b; margin-bottom:10px;">
                            <?= msg('prompt_register_full') ?>
                        </p>
                        <a href="inscription.php" class="btn-register-alt"><?= msg('btn_register_full') ?></a>
                    </div>

                    <div id="login-fields" class="hidden">
                        <div class="form-group"><label><?= msg('lbl_email') ?></label><input type="email" name="login_email"></div>
                        <div class="form-group"><label><?= msg('lbl_password') ?></label><input type="password" name="login_password"></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-box">
                <h2><?= msg('section_shipping') ?></h2>
                <div class="form-group">
                    <label><?= msg('lbl_address_secure') ?></label>
                    <input type="text" name="address" placeholder="10 rue de la Mosaïque" required>
                </div>
                <div class="form-group">
                    <label><?= msg('lbl_city') ?></label>
                    <input type="text" name="city" required>
                </div>
                <div class="form-group">
                    <label><?= msg('lbl_country') ?></label>
                    <input type="text" name="country" value="France" required>
                </div>
                <div class="form-group">
                    <label><?= msg('lbl_phone_secure') ?></label>
                    <input type="tel" name="phone" placeholder="06..." required>
                </div>
            </div>

            <div class="section-box">
                <h2><?= msg('section_payment') ?></h2>
                <div class="mock-payment">
                    <div class="form-group"><label><?= msg('lbl_card') ?></label><input type="text" name="card_number" value="4242 4242 4242 4242"></div>
                    <div class="form-row">
                        <div><label><?= msg('lbl_expiry') ?></label><input type="text" name="expiry" value="12/34"></div>
                        <div><label><?= msg('lbl_cvc') ?></label><input type="text" name="cvc" value="123"></div>
                    </div>
                    <p style="font-size:0.8rem; color:#666; margin-top:5px;"><?= msg('notice_simulation') ?></p>
                </div>
            </div>

            <button type="submit" class="btn-confirm"><?= msg('btn_pay_order') ?></button>
        </form>
    </div>

    <div class="sidebar">
        <div style="background:#f1f5f9; padding:20px; border-radius:12px; position:sticky; top:20px;">
            <h3><?= msg('sidebar_summary') ?></h3>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_style') ?></span><strong><?= ucfirst($cart['style']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_size') ?></span><strong><?= $cart['size'] ?>x<?= $cart['size'] ?></strong>
            </div>
            <div style="border-top:1px solid #cbd5e1; padding-top:10px; font-size:1.2rem; font-weight:bold; color:#2563eb;">
                <?= msg('lbl_total') ?>: <?= $cart['price'] ?> €
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
function toggleAuth(cb) {
    const reg = document.getElementById('register-fields');
    const log = document.getElementById('login-fields');
    if(cb.checked) {
        reg.classList.add('hidden');
        log.classList.remove('hidden');
        reg.querySelectorAll('input').forEach(i => i.required = false);
    } else {
        reg.classList.remove('hidden');
        log.classList.add('hidden');
        reg.querySelectorAll('input').forEach(i => i.required = true);
    }
}
</script>