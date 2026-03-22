<?php
require "config.php";
require_once "classes/Security.php";
// We include your classes if they exist, otherwise we will use native to avoid crashes
if (file_exists("classes/Security.php")) require_once "classes/Security.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// CONTROL CHECK
if (!isset($_SESSION['final_proposal_id'])) {
    header("Location: index.php");
    exit;
}

$propId = $_SESSION['final_proposal_id'];

// RETRIEVAL OF INFORMATION FROM THE DATABASE
// We no longer rely on the session 'pending_cart' but on the reliable database
$stmt = $db->prepare("SELECT * FROM mosaic_proposals WHERE id = ?");
$stmt->execute([$propId]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    error_log("Erreur Checkout: Proposition #$propId introuvable en BDD.");
    header("Location: index.php"); // Return to square one
    exit;
}

$imageId = $proposal['image_id']; // The source image ID
$imagePath = "uploads/preview_" . $propId . ".png"; // The path of the generated image
// Calculation of variables for display (Mapping old cart -> BDD)
$cartStyle = str_replace('ALGO_', 'Algo ', $proposal['strategy']); // Ex: Algo 5
$cartPrice = $proposal['total_bricks_count'] * 0.10;
$error = "";
$isLogged = isset($_SESSION['user_id']);

if (empty($imageId)) {
    // We note the error for the developer in the server’s error.log file
    error_log("Erreur Critique Checkout: image_id NULL pour la proposition #$propId");
    
    // We politely redirect the user to the upload without displaying technical details
    header("Location: index.php");
    exit;
}

// If the user is not logged in
if (!$isLogged) {
    $_SESSION['redirect_after_auth'] = 'checkout.php';
}

$userData = [];
if ($isLogged) {
    $userId = $_SESSION['user_id'];
    $stmtUser = $db->prepare("SELECT email, address, zipcode, city, country, phone FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!empty($userData['address'])) $userData['address'] = Security::decrypt($userData['address']);
    if (!empty($userData['phone']))   $userData['phone']   = Security::decrypt($userData['phone']);

}


// PROCESSING OF THE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $paypalOrderId = $_POST['paypal_order_id'] ?? null;
    $paypalStatus  = $_POST['paypal_status'] ?? null;

    if (!$paypalOrderId || $paypalStatus !== 'COMPLETED') {
        echo json_encode(['success' => false, 'error' => 'Paiement non confirmé par PayPal.']);
        exit;
    }

    // We start a transaction so that everything is recorded (Order + Invoice) or nothing at all
    $db->beginTransaction();

    try {

    $userId = $_SESSION['user_id'];

    // MOCK PAYMENT MANAGEMENT
    // We build the complete address
    $fullAddress = Security::encrypt($_POST['address'] . ", " . $_POST['zipcode'] . " " . $_POST['city'] . ", " . $_POST['country']);
    $phone = Security::encrypt($_POST['phone']);

    $sqlOrder = "INSERT INTO commandes (user_id, image_id, final_image_path, selected_style, total_price, statut, date_commande, delivery_address, delivery_phone) VALUES (?, ?, ?, ?, ?, 'payée', NOW(), ?, ?)";
    $stmtOrder = $db->prepare($sqlOrder);
    $stmtOrder->execute([$userId, $imageId, $imagePath, $cartStyle, $cartPrice, $fullAddress, $phone]);
    
    $orderId = $db->lastInsertId();
    

    // Management of the Client (Table 'client')
    $stmtClient = $db->prepare("SELECT code_client FROM client WHERE user_id = ?");
    $stmtClient->execute([$userId]);
    $existingClient = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if ($existingClient) {
        $codeClient = $existingClient['code_client'];
    } else {
        $codeClient = "CLT-" . str_pad($userId, 5, '0', STR_PAD_LEFT);
        $clientName = "Client Web " . $userId;
        
        $stmtNewClt = $db->prepare("INSERT INTO client (code_client, user_id, nom, email_fact) VALUES (?, ?, ?, ?)");
        $userEmailQuery = $db->prepare("SELECT email FROM users WHERE id = ?");
        $userEmailQuery->execute([$userId]);
        $uEmail = $userEmailQuery->fetchColumn();
        
        $stmtNewClt->execute([$codeClient, $userId, $clientName, $uEmail]);
    }

        // Commercial
        $db->query("INSERT IGNORE INTO commercial (code_commercial, nom_commercial) VALUES ('WEB', 'Site Internet')");

        // Creation of the Invoice (CORRECTION: We create it in DRAFT first)
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

        // Note: We set 'DRAFT' and validation = 0 to be able to add lines right after

        $stmtFact = $db->prepare($sqlFacture);
        $stmtFact->execute([
            $orderId, 
            $codeClient, 
            "Client Web", 
            $_POST['address'], 
            $_POST['zipcode'], 
            $_POST['city']
        ]);
        
        // Secure invoice ID recovery
        $stmtGetFactId = $db->prepare("SELECT id_facture FROM facture WHERE commande_id = ?");
        $stmtGetFactId->execute([$orderId]);
        $factureRow = $stmtGetFactId->fetch(PDO::FETCH_ASSOC);

        if (!$factureRow) {
            throw new Exception("Erreur critique : La facture a été créée mais son ID est introuvable.");
        }
        $factureId = $factureRow['id_facture'];


        // 4. Invoice Line
        $prixTTC = floatval($cartPrice);
        $tvaRate = 1.20; 
        $prixHT = $prixTTC / $tvaRate;

        // Immunization we retrieve the binary content of the image 
        $imagePath = "uploads/preview_" . $proposal['id'] . ".png";
        $imageBinaryData = null;
        if (file_exists($imagePath)) {
            $imageBinaryData = file_get_contents($imagePath);
        }

        $taille = intval($proposal['resolution']); // Ex: 32, 48, 64
        $articleId = "KIT_MOSAIQUE";

        switch($taille){
            case 32:
                $articleId = "KIT_32";
                break;
            case 48:
                $articleId = "KIT_48";
                break;
            case 64:
                $articleId = "KIT_64";
                break;
            case 96:
                $articleId = "KIT_96";
                break;
            default:
                $articleId = "KIT_MOSAIQUE";
                break;
        }
        
        // Creation of the article if non-existent
        $db->query("INSERT IGNORE INTO article (id_article, description, prix_unitaire_ht, taux_tva) VALUES ('$articleId', 'Kit Mosaïque Lego Personnalisé', 0, 20.00)");

        $idLigne = uniqid(); // Generates a unique ID (ex: 65a4f8...)

        $sqlLigne = "INSERT INTO ligne_facture (
            id_ligne_facture,   
            num_ligne, id_facture, id_article, 
            designation_article_cache, quantite, 
            prix_unitaire_ht, pourcentage_remise_ligne,
            snapshot_img
        ) VALUES (
            ?,                  
            1, ?, ?, 
            ?, 1, 
            ?, 0, 
            ?
        )";

        $descArticle = "Kit Mosaïque " . $proposal['resolution'] . "x" . $proposal['resolution'] . " - " . ucfirst($cartStyle);
        
        $stmtLigne = $db->prepare($sqlLigne);
        
        // We pass $idLigne as the first parameter

        $stmtLigne->execute([$idLigne, $factureId, $articleId, $descArticle, $prixHT, $imageBinaryData]);

        // Now that the lines are added, we can lock the invoice
        $stmtValide = $db->prepare("UPDATE facture SET etat_facture = 'VALIDEE', validation = 1 WHERE id_facture = ?");
        $stmtValide->execute([$factureId]);


        // Validation finale
        $db->commit();
        // Save the address in the profile if empty
        $stmtCheck = $db->prepare("SELECT address FROM users WHERE id = ? AND (address IS NULL OR address = '')");
        $stmtCheck->execute([$userId]);
        if ($stmtCheck->fetch()) {
            $stmtUpdate = $db->prepare("UPDATE users SET address = ?, zipcode = ?, city = ?, country = ?, phone = ? WHERE id = ?");
            $stmtUpdate->execute([
                Security::encrypt($_POST['address']),
                $_POST['zipcode'],
                $_POST['city'],
                $_POST['country'],
                Security::encrypt($_POST['phone']),
                $userId
            ]);
        }


        // Nettoyage session
        unset($_SESSION['final_proposal_id']);
        unset($_SESSION['current_image_id']);
        unset($_SESSION['redirect_after_auth']);
        
        echo json_encode(['success' => true, 'order_id' => $orderId]);
        // Order confirmation email
        $userEmailQuery = $db->prepare("SELECT email FROM users WHERE id = ?");
        $userEmailQuery->execute([$userId]);
        $userEmail = $userEmailQuery->fetchColumn();

        $lang = $_SESSION['lang'] ?? 'fr';
        $emailBody = '
        <!DOCTYPE html><html lang="' . $lang . '"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f1f5f9;font-family:Poppins,Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
            <tr><td style="background:#3b82f6;padding:40px 30px;border-radius:16px 16px 0 0;text-align:center;">
                <h1 style="margin:0;color:white;font-size:28px;font-weight:800;">Img2brick</h1>
                <p style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">Transformez vos images en mosaïques</p>
            </td></tr>
            <tr><td style="background:white;padding:40px 40px 30px;">
                <h2 style="margin:0 0 12px;color:#1e293b;font-size:22px;font-weight:700;">
                    ' . ($lang == 'fr' ? 'Commande confirmée !' : 'Order confirmed!') . '
                </h2>
                <p style="margin:0 0 24px;color:#64748b;font-size:15px;line-height:1.6;">
                    ' . ($lang == 'fr' ? 'Merci pour votre commande ! Voici le récapitulatif :' : 'Thank you for your order! Here is your summary:') . '
                </p>

                <!-- Récapitulatif -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:12px;padding:20px;margin-bottom:24px;">
                    <tr>
                        <td style="padding:8px 16px;font-size:14px;color:#64748b;">' . ($lang == 'fr' ? 'Référence commande' : 'Order reference') . '</td>
                        <td style="padding:8px 16px;font-size:14px;font-weight:600;color:#1e293b;text-align:right;">#' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 16px;font-size:14px;color:#64748b;">' . ($lang == 'fr' ? 'Style' : 'Style') . '</td>
                        <td style="padding:8px 16px;font-size:14px;font-weight:600;color:#1e293b;text-align:right;">' . ucfirst($cartStyle) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 16px;font-size:14px;color:#64748b;">' . ($lang == 'fr' ? 'Nombre de pièces' : 'Brick count') . '</td>
                        <td style="padding:8px 16px;font-size:14px;font-weight:600;color:#1e293b;text-align:right;">' . $proposal['total_bricks_count'] . '</td>
                    </tr>
                    <tr style="border-top:1px solid #e2e8f0;">
                        <td style="padding:12px 16px;font-size:16px;font-weight:700;color:#1e293b;">Total</td>
                        <td style="padding:12px 16px;font-size:16px;font-weight:700;color:#3b82f6;text-align:right;">' . number_format($cartPrice, 2) . ' €</td>
                    </tr>
                </table>

                <div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;">
                    <p style="margin:0;color:#166534;font-size:13px;">
                        ' . ($lang == 'fr' ? 'Votre kit est en cours de préparation. Vous recevrez un email dès l\'expédition.' : 'Your kit is being prepared. You will receive an email once shipped.') . '
                    </p>
                </div>
            </td></tr>
            <tr><td style="background:#f8fafc;padding:20px 40px;border-radius:0 0 16px 16px;border-top:1px solid #e2e8f0;text-align:center;">
                <p style="margin:0;color:#94a3b8;font-size:12px;">© ' . date('Y') . ' img2brick — ' . ($lang == 'fr' ? 'Tous droits réservés' : 'All rights reserved') . '</p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>';

        $emailSubject = ($lang == 'fr') ? 'Confirmation de votre commande #' . str_pad($orderId, 6, '0', STR_PAD_LEFT) : 'Order confirmation #' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $userMgr->sendEmail($userEmail, $emailSubject, $emailBody);
        exit;

    } catch (Exception $e) {
        $db->rollBack(); // Total cancellation in case of error (no defective order without invoice)
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        error_log($e->getMessage());
        exit;
    }
}

include "header.php";
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style>
    .checkout-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 20px;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 40px;
    }

    .section-box {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    h2 {
        font-size: 1.3rem;
        margin-bottom: 20px;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #475569;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="tel"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
    }

    .mock-payment {
        background: #f8fafc;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .btn-confirm {
        background: #10b981;
        color: white;
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
    }

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

    .btn-register-alt:hover {
        background: #eff6ff;
    }

    .error-msg {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    .hidden {
        display: none;
    }

    .divider {
        text-align: center;
        margin: 20px 0;
        color: #94a3b8;
        font-size: 0.9rem;
        position: relative;
    }

    .divider::before,
    .divider::after {
        content: "";
        position: absolute;
        top: 50%;
        width: 40%;
        height: 1px;
        background: #e2e8f0;
    }

    .divider::before {
        left: 0;
    }

    .divider::after {
        right: 0;
    }

    /* Responsive for mobile */
    @media(max-width: 768px) {
        .checkout-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="checkout-container">
    <div class="main-form">
        <h1><?= msg('checkout_title') ?></h1>
        <?php if ($error): ?><div class="error-msg"> <?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div class="section-box">
                <h2><?= msg('section_account') ?></h2>

                <?php if ($isLogged): ?>
                    <div style="background:#dcfce7; padding:12px 14px; border-radius:8px; color:#166534; font-size:14px; display:flex; align-items:center; gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= msg('status_connected') ?>
                    </div>
                    <div class="cf-turnstile" style="margin-top:16px;"
                        data-sitekey="<?= htmlspecialchars($_ENV['TURNSTILE_SITEKEY'] ?? '') ?>"
                        data-callback="unlockButton">
                    </div>
                    <p style="font-size:12px; color:#94a3b8; margin-top:8px;"><?= msg('captcha_unlock_hint') ?></p>

                <?php else: ?>
                    <div style="background:#eff6ff; padding:20px; border-radius:10px; text-align:center;">
                        <p style="margin:0 0 14px; color:#1e293b; font-size:15px; font-weight:500;">
                            <?= msg('checkout_login_required') ?>
                        </p>
                        <a href="inscription.php?redirect=checkout.php"
                        style="display:inline-block; background:#3b82f6; color:white; padding:11px 28px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; transition:background 0.2s;"
                        onmouseover="this.style.background='#2563eb'"
                        onmouseout="this.style.background='#3b82f6'">
                            <?= msg('btn_login_or_register') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-box">
                <h2><?= msg('section_shipping') ?></h2>
                <div class="form-group">
                    <label><?= msg('lbl_address_secure') ?></label>
                    <input type="text" name="address" 
                        value="<?= htmlspecialchars($userData['address'] ?? '') ?>" 
                        placeholder="10 rue de la Mosaïque" required>
                </div>

                <div class="form-row">
                    <div>
                        <label><?= msg('lbl_zipcode') ?></label>
                        <input type="text" name="zipcode" 
                            value="<?= htmlspecialchars($userData['zipcode'] ?? '') ?>" 
                            placeholder="75000" required>
                    </div>
                    <div>
                        <label><?= msg('lbl_city') ?></label>
                        <input type="text" name="city" 
                            value="<?= htmlspecialchars($userData['city'] ?? '') ?>" 
                            required>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= msg('lbl_country') ?></label>
                    <input type="text" name="country" 
                        value="<?= htmlspecialchars($userData['country'] ?? 'France') ?>" 
                        required>
                </div>
                <div class="form-group">
                    <label><?= msg('lbl_phone_secure') ?></label>
                    <input type="tel" name="phone" 
                        value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" 
                        placeholder="06..." required>                
                </div>
            </div>

            <div class="section-box">
                <h2><?= msg('section_payment') ?></h2>
                <div id="paypal-button-container" style="display: none;"></div>
                <p style="font-size:0.8rem; color:#666; margin-top:5px;"><?= msg('notice_simulation') ?></p>
            </div>

            <button type="submit" class="btn-confirm" id="btnRegister" disabled style="display:none;">
                <?= msg('btn_pay_order') ?>
            </button>
        </form>
    </div>

    <div class="sidebar">
        <div style="background:#f1f5f9; padding:20px; border-radius:12px; position:sticky; top:20px;">
            <h3><?= msg('sidebar_summary') ?></h3>

            <div style="text-align:center; margin-bottom:15px;">
                <img src="uploads/preview_<?= $propId ?>.png" style="max-width:100%; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            </div>

            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_style') ?></span>
                <strong><?= ucfirst($cartStyle) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>Pièces</span>
                <strong><?= $proposal['total_bricks_count'] ?></strong>
            </div>

            <div style="border-top:1px solid #cbd5e1; padding-top:10px; font-size:1.2rem; font-weight:bold; color:#2563eb;">
                <?= msg('lbl_total') ?>: <?= number_format($cartPrice, 2) ?> €
            </div>
        </div>
    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=<?= $_ENV['PAYPAL_CLIENT_ID'] ?>&currency=EUR"></script>
<?php include "footer.php"; ?>

<script>
    let totalCommande = "<?= number_format($cartPrice, 2, '.', '') ?>";

    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{ amount: { value: totalCommande } }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                const formData = new FormData(document.querySelector('form'));
                formData.append('paypal_order_id', data.orderID);
                formData.append('paypal_status', details.status);

                fetch('checkout.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            window.location.href = "confirmation.php?order_id=" + result.order_id;
                        } else {
                            alert('Erreur : ' + result.error);
                        }
                    });
            });
        },
        onCancel: function() { alert('Paiement annulé.'); },
        onError: function(err) { console.error(err); alert('Erreur PayPal.'); }
    }).render('#paypal-button-container');
</script>

<script>
function unlockButton() {
    document.getElementById('paypal-button-container').style.display = 'block';
}
</script>

