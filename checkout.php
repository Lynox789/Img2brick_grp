<?php
require "config.php";
// We include your classes if they exist, otherwise we will use native to avoid crashes
if (file_exists("classes/Security.php")) require_once "classes/Security.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// CONTROL CHECK
if (!isset($_SESSION['final_proposal_id'])) {
    header("Location: upload.php");
    exit;
}

$propId = $_SESSION['final_proposal_id'];

// RETRIEVAL OF INFORMATION FROM THE DATABASE
// We no longer rely on the session 'pending_cart' but on the reliable database
$stmt = $db->prepare("SELECT * FROM mosaic_proposals WHERE id = ?");
$stmt->execute([$propId]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    // If the proposal does not exist more
    header("Location: upload.php");
    exit;
}

// Calculation of variables for display (Mapping old cart -> BDD)
$cartStyle = str_replace('ALGO_', 'Algo ', $proposal['strategy']); // Ex: Algo 5
$cartSize  = $proposal['resolution']; // Ex: 48x48
$cartPrice = $proposal['estimated_cost'] > 0 ? $proposal['estimated_cost'] : ($proposal['total_bricks_count'] * 0.10); // Calcul prix

$error = "";
$isLogged = isset($_SESSION['user_id']);

// If the user is not logged in
if (!$isLogged) {
    $_SESSION['redirect_after_auth'] = 'checkout.php';
}

// PROCESSING OF THE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $userId = 0;

        // AUTHENTICATION MANAGEMENT (Login or Quick Registration)
        if (!$isLogged) {

            // CASE 1: Connection
            if (isset($_POST['has_account']) && $_POST['has_account'] == '1') {
                $email = $_POST['login_email'];
                $pass  = $_POST['login_password'];

                $stmtUser = $db->prepare("SELECT id, password FROM users WHERE email = ?");
                $stmtUser->execute([$email]);
                $user = $stmtUser->fetch();

                if ($user && password_verify($pass, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $userId = $user['id'];
                } else {
                    throw new Exception("Email ou mot de passe incorrect.");
                }
            }
            // CASE 2 : Quick Registration
            else {
                $email = $_POST['email'];
                $pass  = $_POST['password'];

                // Duplicate check
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->rowCount() > 0) throw new Exception("Cet email est déjà utilisé.");

                // User creation (Simplified logic to adapt to your users table)
                $hash = password_hash($pass, PASSWORD_DEFAULT);

                // We extract a username from the email
                $username = explode('@', $email)[0] . '_' . rand(100, 999);

                $stmtReg = $db->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
                $stmtReg->execute([$username, $email, $hash]);

                $userId = $db->lastInsertId();
                $_SESSION['user_id'] = $userId;
            }
        } else {
            $userId = $_SESSION['user_id'];
        }

        // MOCK PAYMENT MANAGEMENT
        $cardNumber = str_replace(' ', '', $_POST['card_number']);
        // We accept 4242... or the default value of the form
        if (substr($cardNumber, 0, 4) !== '4242' || $_POST['cvc'] !== '123') {
            throw new Exception("Paiement refusé. Utilisez la carte de test (4242...).");
        }

        // MOCK PAYMENT MANAGEMENT
        // We build the complete address
        $fullAddress = $_POST['address'] . ", " . $_POST['zipcode'] . " " . $_POST['city'] . ", " . $_POST['country'];
        $phone = $_POST['phone'];

        // Insertion
        $fullAddress = $_POST['address'] . ", " . $_POST['zipcode'] . " " . $_POST['city'] . ", " . $_POST['country'];
        $phone = $_POST['phone'];
        
        // SQL QUERY
        // We insert $imagePath in the final_image_path column
        $sqlOrder = "INSERT INTO commandes (
            user_id, 
            image_id, 
            final_image_path, 
            selected_style, 
            total_price, 
            statut, 
            date_commande, 
            delivery_address, 
            delivery_phone
        ) VALUES (?, ?, ?, ?, ?, 'payée', NOW(), ?, ?)";

        $stmtOrder = $db->prepare($sqlOrder);
        $stmtOrder->execute([
            $userId, 
            $imageId, 
            $imagePath,       
            $cartStyle, 
            $cartPrice, 
            $fullAddress, 
            $phone
        ]);
        
        $orderId = $db->lastInsertId();
        // CLEANING AND REDIRECTION
        unset($_SESSION['final_proposal_id']);
        unset($_SESSION['current_image_id']);
        unset($_SESSION['redirect_after_auth']);

        // Redirect to confirmation
        header("Location: confirmation.php?order_id=" . $orderId);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
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
                    <div style="background:#dcfce7; padding:10px; border-radius:6px; color:#166534;">
                        <?= msg('status_connected') ?>
                    </div>
                <?php else: ?>
                    <label style="background:#eff6ff; padding:10px; border-radius:6px; margin-bottom:15px; display:block; cursor:pointer;">
                        <input type="checkbox" name="has_account" value="1" onchange="toggleAuth(this)"> <?= msg('lbl_have_account') ?>
                    </label>

                    <div id="register-fields">
                        <div class="form-group"><label><?= msg('lbl_email') ?></label><input type="email" name="email"></div>
                        <div class="form-group"><label><?= msg('lbl_password') ?></label><input type="password" name="password"></div>
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

                <div class="form-row">
                    <div>
                        <label><?= msg('lbl_zipcode') ?></label>
                        <input type="text" name="zipcode" placeholder="75000" required>
                    </div>
                    <div>
                        <label><?= msg('lbl_city') ?></label>
                        <input type="text" name="city" required>
                    </div>
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

            <div style="text-align:center; margin-bottom:15px;">
                <img src="uploads/preview_<?= $propId ?>.png" style="max-width:100%; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
            </div>

            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_style') ?></span>
                <strong><?= ucfirst($cartStyle) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span><?= msg('lbl_size') ?></span>
                <strong><?= $cartSize ?> tenons</strong>
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

<?php include "footer.php"; ?>

<script>
    function toggleAuth(cb) {
        const reg = document.getElementById('register-fields');
        const log = document.getElementById('login-fields');
        if (cb.checked) {
            reg.classList.add('hidden');
            log.classList.remove('hidden');
            // Remove the required from the hidden fields to avoid blocking the form
            reg.querySelectorAll('input').forEach(i => i.required = false);
            log.querySelectorAll('input').forEach(i => i.required = true);
        } else {
            reg.classList.remove('hidden');
            log.classList.add('hidden');
            reg.querySelectorAll('input').forEach(i => i.required = true);
            log.querySelectorAll('input').forEach(i => i.required = false);
        }
    }
</script>