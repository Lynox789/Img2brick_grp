<?php
require "config.php";
require_once "classes/TwoFactorAuthLight.php";

// Session verification
if (session_status() === PHP_SESSION_NONE) session_start();

// Security: We verify that the user comes from a valid password submission
if (!isset($_SESSION['pending_user_id'])) {
    // If it is not pending, we send it back to the login page
    header("Location: inscription.php");
    exit;
}

$pendingUserId = $_SESSION['pending_user_id'];
$statusMessage = "";
$lang = $_SESSION['lang'] ?? 'fr';

// We retrieve the TOTP secret and the user’s backup codes
$stmt = $db->prepare("SELECT totp_secret, backup_codes FROM users WHERE id = ?");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['totp_secret'])) {
    // the user is marked "TOTP" but does not have a secret in the database
    unset($_SESSION['pending_user_id']);
    header("Location: inscription.php");
    exit;
}

// Treatment of the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    $submittedCode = trim($_POST['totp_code']);
    $tfa = new TwoFactorAuthLight();
    $isValid = false;

    // Is it a valid Authenticator code (6 digits)?
    if (is_numeric($submittedCode) && strlen($submittedCode) == 6) {
        $isValid = $tfa->verifyCode($user['totp_secret'], $submittedCode);
    }

    // If it's not an Authenticator code, is it a Backup Code?
    if (!$isValid && !empty($user['backup_codes'])) {
        $backupCodes = json_decode($user['backup_codes'], true);
        if (is_array($backupCodes)) {
            foreach ($backupCodes as $index => $hashedCode) {
                // We check the hash with password_verify
                if (password_verify($submittedCode, $hashedCode)) {
                    $isValid = true;
                    // We delete this backup code so that it is for single use only.
                    unset($backupCodes[$index]);
                    $newBackupCodesJson = json_encode(array_values($backupCodes));
                    
                    $upd = $db->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
                    $upd->execute([$newBackupCodesJson, $pendingUserId]);
                    break;
                }
            }
        }
    }

    // Conclusion of the validation
    if ($isValid) {
        // We officially log in the user!
        $_SESSION['user_id'] = $pendingUserId;
        
        // We clean the waiting session
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['auth_mode']);

        // Redirection to the home or requested page
        $redirect = $_SESSION['redirect_after_auth'] ?? 'index.php';
        header("Location: " . $redirect);
        exit;
    } else {
        $statusMessage = ($lang == 'fr') ? "Code invalide. Veuillez réessayer." : "Invalid code. Please try again.";
    }
}

include 'header.php';
?>

<head>
    <style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        display: flex; flex-direction: column; margin: 0; padding-top: 80px; 
    }
    .login-container {
        flex: 1; display: flex; align-items: center; justify-content: center; 
        width: 100%; padding: 20px; 
    }
    .wrapper {
        background: #fff;
        width: 400px;
        max-width: 100%;
        border-radius: 20px;
        box-shadow: 0 15px 20px rgba(0,0,0,0.1);
        padding: 30px;
    }
    .title-text {
        font-size: 22px; font-weight: 600; text-align: center;
        margin-bottom: 20px; color: #333;
    }
    .field { height: 50px; width: 100%; margin-top: 15px; position: relative; }
    .field input {
        height: 100%; width: 100%; outline: none; padding-left: 15px;
        border-radius: 5px; border: 1px solid lightgrey; font-size: 18px;
        letter-spacing: 2px; text-align: center; transition: all 0.3s ease;
    }
    .field input:focus { border-color: #4A90E2; box-shadow: 0 0 5px rgba(74, 144, 226, 0.3); }
    button[type="submit"] {
        margin-top: 25px; width: 100%; height: 50px; border: none;
        border-radius: 5px; color: #fff; font-size: 18px; font-weight: 500;
        cursor: pointer; transition: all 0.3s ease; background: #4A90E2; 
    }
    button[type="submit"]:hover { opacity: 0.9; }
    .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
    .info-text { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
    </style>
</head>

<div class="login-container">
    <div class="wrapper">
        <div class="title-text">
            <?= ($lang == 'fr') ? "Double Authentification" : "Two-Factor Authentication" ?>
        </div>
        
        <?php if($statusMessage): ?>
            <div class="error"><?= htmlspecialchars($statusMessage) ?></div>
        <?php endif; ?>

        <p class="info-text">
            <?= ($lang == 'fr') ? "Ouvrez l'application Google Authenticator ou FreeOTP et saisissez le code à 6 chiffres." : "Open your Google Authenticator or FreeOTP app and enter the 6-digit code." ?>
        </p>

        <form method="post" action="">
            <div class="field">
                <input type="text" name="totp_code" required autocomplete="off" placeholder="123 456" autofocus>
            </div>
            
            <button type="submit"><?= ($lang == 'fr') ? "Valider la connexion" : "Verify and Login" ?></button>
        </form>

        <p class="info-text" style="margin-top: 20px; font-size: 12px; color: #94a3b8;">
            <?= ($lang == 'fr') ? "Vous avez perdu votre téléphone ? Utilisez l'un de vos codes de secours (8 caractères)." : "Lost your phone? Enter one of your 8-character backup codes." ?>
        </p>
    </div>
</div>

<?php include "footer.php"; ?>