<?php
require "config.php";
require_once "classes/Security.php";
require_once "classes/LoyaltyManager.php"; // Inclus le manager de fidélité

if (file_exists("classes/Security.php")) require_once "classes/Security.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// CONTROL CHECK
if (!isset($_SESSION['final_proposal_id'])) {
    header("Location: index.php");
    exit;
}

// Initialisation de la fidélité
$loyalty = new LoyaltyManager($db, 'http://localhost:3001/api');
$userId = $_SESSION['user_id'] ?? null;
$propId = $_SESSION['final_proposal_id'];

// RETRIEVAL OF INFORMATION FROM THE DATABASE
$stmt = $db->prepare("SELECT * FROM mosaic_proposals WHERE id = ?");
$stmt->execute([$propId]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    error_log("Erreur Checkout: Proposition #$propId introuvable en BDD.");
    header("Location: index.php");
    exit;
}

$imageId = $proposal['image_id']; 
$imagePath = "uploads/preview_" . $propId . ".png"; 
$cartStyle = str_replace('ALGO_', 'Algo ', $proposal['strategy']); 
$basePrice = $proposal['total_bricks_count'] * 0.10; // Prix de base sans réduction
$error = "";
$isLogged = isset($_SESSION['user_id']);

if (empty($imageId)) {
    error_log("Erreur Critique Checkout: image_id NULL pour la proposition #$propId");
    header("Location: index.php");
    exit;
}

if (!$isLogged) {
    $_SESSION['redirect_after_auth'] = 'checkout.php';
}

$userData = [];
$balanceInfo = ['points' => 0, 'euros' => 0];
$discountInfo = ['maxPointsAllowed' => 0, 'maxDiscountEuros' => 0];

if ($isLogged) {
    $stmtUser = $db->prepare("SELECT email, address, zipcode, city, country, phone FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!empty($userData['address'])) $userData['address'] = Security::decrypt($userData['address']);
    if (!empty($userData['phone']))   $userData['phone']   = Security::decrypt($userData['phone']);
    
    $realBalance = $loyalty->getBalanceForCheckout($userId);
    $balanceInfo = [
        'points' => $realBalance['points'],
        'euros'  => $realBalance['euros'],
    ];
    $discountInfo = $loyalty->getMaxDiscount($userId, $basePrice);
    $discountInfo['maxPointsAllowed'] = $discountInfo['pointsForMaxDiscount'];
    $discountInfo['maxDiscountEuros'] = number_format($discountInfo['maxDiscountEuros'], 2, ',', ' ');
}

// PROCESSING OF THE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $paypalOrderId = $_POST['paypal_order_id'] ?? null;
    $paypalStatus  = $_POST['paypal_status'] ?? null;

    if (!$paypalOrderId || $paypalStatus !== 'COMPLETED') {
        echo json_encode(['success' => false, 'error' => 'Paiement non confirmé par PayPal.']);
        exit;
    }

    $db->beginTransaction();

    try {
        // --- GESTION DE LA RÉDUCTION FIDÉLITÉ (SÉCURITÉ SERVEUR) ---
        $pointsToUse = isset($_POST['loyalty_points']) ? (int)$_POST['loyalty_points'] : 0;
        $discountValue = 0;

        if ($pointsToUse > 0 && $isLogged) {
            $checkDiscount = $loyalty->getMaxDiscount($userId, $basePrice);
            if ($pointsToUse > $checkDiscount['maxPointsAllowed']) {
                $pointsToUse = $checkDiscount['maxPointsAllowed']; // On bloque la triche
            }
            $discountValue = $pointsToUse * 0.01; // 100 points = 1€
        }

        $finalPrice = $basePrice - $discountValue; // Le VRAI prix à payer et facturer

        $fullAddress = Security::encrypt($_POST['address'] . ", " . $_POST['zipcode'] . " " . $_POST['city'] . ", " . $_POST['country']);
        $phone = Security::encrypt($_POST['phone']);

        // On insère finalPrice au lieu de cartPrice
        $sqlOrder = "INSERT INTO commandes (user_id, image_id, final_image_path, selected_style, total_price, statut, date_commande, delivery_address, delivery_phone) VALUES (?, ?, ?, ?, ?, 'payée', NOW(), ?, ?)";
        $stmtOrder = $db->prepare($sqlOrder);
        $stmtOrder->execute([$userId, $imageId, $imagePath, $cartStyle, $finalPrice, $fullAddress, $phone]);
        
        $orderId = $db->lastInsertId();

        // Si des points ont été utilisés, on les consomme via l'API et on l'enregistre en BDD
        if ($pointsToUse > 0) {
            $loyalty->consumePoints($userId, $pointsToUse, $orderId);
        }
        
        // Management of the Client
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

        $db->query("INSERT IGNORE INTO commercial (code_commercial, nom_commercial) VALUES ('WEB', 'Site Internet')");

        $sqlFacture = "INSERT INTO facture (
            commande_id, code_client, code_commercial, 
            type_document, etat_facture, validation, 
            date_document, nom_client, 
            adresse_fact, cp_fact, ville_fact
        ) VALUES (?, ?, 'WEB', 'FACTURE', 'BROUILLON', 0, CURDATE(), ?, ?, ?, ?)";

        $stmtFact = $db->prepare($sqlFacture);
        $stmtFact->execute([$orderId, $codeClient, "Client Web", $_POST['address'], $_POST['zipcode'], $_POST['city']]);
        
        $stmtGetFactId = $db->prepare("SELECT id_facture FROM facture WHERE commande_id = ?");
        $stmtGetFactId->execute([$orderId]);
        $factureRow = $stmtGetFactId->fetch(PDO::FETCH_ASSOC);
        $factureId = $factureRow['id_facture'];

        // Calcul avec le finalPrice
        $prixTTC = floatval($finalPrice);
        $tvaRate = 1.20; 
        $prixHT = $prixTTC / $tvaRate;

        $imageBinaryData = null;
        if (file_exists($imagePath)) {
            $imageBinaryData = file_get_contents($imagePath);
        }

        $taille = intval($proposal['resolution']);
        $articleId = "KIT_MOSAIQUE"; // Simplifié pour l'exemple
        $db->query("INSERT IGNORE INTO article (id_article, description, prix_unitaire_ht, taux_tva) VALUES ('$articleId', 'Kit Mosaïque Lego Personnalisé', 0, 20.00)");

        $idLigne = uniqid(); 
        $sqlLigne = "INSERT INTO ligne_facture (id_ligne_facture, num_ligne, id_facture, id_article, designation_article_cache, quantite, prix_unitaire_ht, pourcentage_remise_ligne, snapshot_img) VALUES (?, 1, ?, ?, ?, 1, ?, 0, ?)";
        
        $descArticle = "Kit Mosaïque " . $proposal['resolution'] . "x" . $proposal['resolution'] . " - " . ucfirst($cartStyle);
        $stmtLigne = $db->prepare($sqlLigne);
        $stmtLigne->execute([$idLigne, $factureId, $articleId, $descArticle, $prixHT, $imageBinaryData]);

        $stmtValide = $db->prepare("UPDATE facture SET etat_facture = 'VALIDEE', validation = 1 WHERE id_facture = ?");
        $stmtValide->execute([$factureId]);

        $db->commit();
        
        // Save the address
        $stmtCheck = $db->prepare("SELECT address FROM users WHERE id = ? AND (address IS NULL OR address = '')");
        $stmtCheck->execute([$userId]);
        if ($stmtCheck->fetch()) {
            $stmtUpdate = $db->prepare("UPDATE users SET address = ?, zipcode = ?, city = ?, country = ?, phone = ? WHERE id = ?");
            $stmtUpdate->execute([
                Security::encrypt($_POST['address']), $_POST['zipcode'], $_POST['city'], $_POST['country'],
                Security::encrypt($_POST['phone']), $userId
            ]);
        }

        unset($_SESSION['final_proposal_id']);
        unset($_SESSION['current_image_id']);
        unset($_SESSION['redirect_after_auth']);
        
        echo json_encode(['success' => true, 'order_id' => $orderId]);
        exit;

    } catch (Exception $e) {
        $db->rollBack(); 
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        error_log($e->getMessage());
        exit;
    }
}

include "header.php";
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
    /* Garde tout ton CSS ici... */
</style>

<div class="checkout-container" style="max-width: 1100px; margin: 40px auto; display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">
    <div class="main-form">
        <h1><?= msg('checkout_title') ?></h1>
        <?php if ($error): ?><div class="error-msg"> <?= $error ?></div><?php endif; ?>

        <form method="POST" id="checkout-form">
            <div class="section-box" style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
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
                <?php else: ?>
                    <div style="background:#eff6ff; padding:20px; border-radius:10px; text-align:center;">
                        <p style="margin:0 0 14px; color:#1e293b; font-size:15px; font-weight:500;">
                            <?= msg('checkout_login_required') ?>
                        </p>
                        <a href="inscription.php?redirect=checkout.php" style="display:inline-block; background:#3b82f6; color:white; padding:11px 28px; border-radius:8px; text-decoration:none;">
                            <?= msg('btn_login_or_register') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-box" style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
                <h2><?= msg('section_shipping') ?></h2>
                <div class="form-group">
                    <label><?= msg('lbl_address_secure') ?></label>
                    <input type="text" name="address" value="<?= htmlspecialchars($userData['address'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div><label><?= msg('lbl_zipcode') ?></label><input type="text" name="zipcode" value="<?= htmlspecialchars($userData['zipcode'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required></div>
                    <div><label><?= msg('lbl_city') ?></label><input type="text" name="city" value="<?= htmlspecialchars($userData['city'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required></div>
                </div>
                <div class="form-group"><label><?= msg('lbl_country') ?></label><input type="text" name="country" value="<?= htmlspecialchars($userData['country'] ?? 'France') ?>" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required></div>
                <div class="form-group"><label><?= msg('lbl_phone_secure') ?></label><input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;" required></div>
            </div>

            <div class="section-box" style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
                <h2><?= msg('section_payment') ?></h2>
                <div id="paypal-button-container" style="display: none;"></div>
            </div>
        </form>
    </div>

    <div class="sidebar">
        <div style="background:#f1f5f9; padding:20px; border-radius:12px; position:sticky; top:20px;">
            <h3><?= msg('sidebar_summary') ?></h3>

            <div style="text-align:center; margin-bottom:15px;">
                <img src="uploads/preview_<?= $propId ?>.png" style="max-width:100%; border-radius:8px;">
            </div>

            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_style') ?></span>
                <strong><?= ucfirst($cartStyle) ?></strong>
            </div>

            <?php if ($isLogged && $balanceInfo['points'] > 0): ?>
                <div style="background:#fff; padding:15px; border-radius:8px; margin:15px 0; border:1px solid #e2e8f0;">
                    <h4 style="margin:0 0 10px; color:#2563eb; font-size:15px;">Vos points de fidélité</h4>
                    <p style="font-size:13px; color:#64748b; margin-bottom:10px;">
                        Vous possédez <strong><?= $balanceInfo['points'] ?> pts</strong> (<?= $balanceInfo['euros'] ?> €).
                    </p>
                    <label style="font-size:12px; font-weight:bold;">Points à utiliser :</label>
                    <input type="number" id="loyalty_input" form="checkout-form" name="loyalty_points" min="0" max="<?= $discountInfo['maxPointsAllowed'] ?>" value="0" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:5px;">
                    <small style="color:#94a3b8; font-size:11px;">Max : <?= $discountInfo['maxPointsAllowed'] ?> pts (<?= $discountInfo['maxDiscountEuros'] ?> €)</small>
                </div>
            <?php endif; ?>

            <div style="border-top:1px solid #cbd5e1; padding-top:10px; margin-top:10px;">
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; color:#64748b; margin-bottom:5px;">
                    <span>Sous-total</span>
                    <span><?= number_format($basePrice, 2) ?> €</span>
                </div>
                <div id="discount-row" style="display:flex; justify-content:space-between; font-size:0.9rem; color:#10b981; margin-bottom:5px; font-weight:bold; display:none;">
                    <span>Réduction fidélité</span>
                    <span id="discount-amount">- 0.00 €</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:bold; color:#2563eb; margin-top:10px;">
                    <span><?= msg('lbl_total') ?></span>
                    <span id="display-total"><?= number_format($basePrice, 2) ?> €</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://www.paypal.com/sdk/js?client-id=<?= $_ENV['PAYPAL_CLIENT_ID'] ?>&currency=EUR"></script>
<?php include "footer.php"; ?>

<script>
    // Variables Javascript pour le prix dynamique
    let baseTotal = <?= $basePrice ?>;
    let currentTotal = baseTotal;

    // Gestion du changement de points de fidélité
    const loyaltyInput = document.getElementById('loyalty_input');
    const displayTotal = document.getElementById('display-total');
    const discountRow = document.getElementById('discount-row');
    const discountAmount = document.getElementById('discount-amount');

    if (loyaltyInput) {
        loyaltyInput.addEventListener('input', function() {
            let pts = parseInt(this.value) || 0;
            let maxPts = parseInt(this.getAttribute('max')) || 0;
            
            if(pts > maxPts) { pts = maxPts; this.value = pts; }
            if(pts < 0) { pts = 0; this.value = pts; }

            let discount = pts * 0.01;
            currentTotal = baseTotal - discount;
            
            // Sécurité anti-prix négatif
            if (currentTotal < 0.01) currentTotal = 0.01;

            displayTotal.innerText = currentTotal.toFixed(2) + ' €';

            if (discount > 0) {
                discountRow.style.display = 'flex';
                discountAmount.innerText = '- ' + discount.toFixed(2) + ' €';
            } else {
                discountRow.style.display = 'none';
            }
        });
    }

    // PayPal utilisant le currentTotal dynamique !
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{ amount: { value: currentTotal.toFixed(2).toString() } }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                const formData = new FormData(document.getElementById('checkout-form'));
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

    function unlockButton() {
        document.getElementById('paypal-button-container').style.display = 'block';
    }
</script>