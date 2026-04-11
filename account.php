<?php
require "config.php";
require_once "classes/Security.php"; 
require_once "vendor/autoload.php";
require_once "classes/TwoFactorAuthLight.php"; 
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel; 

// Session check
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: inscription.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = "";
$msgType = ""; 

// Language definition
$lang = $_SESSION['lang'] ?? 'en'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURE DISABLING OF TOTP 
    if (isset($_POST['disable_totp'])) {
        try {
            $passwordSaisi = $_POST['password_confirm'] ?? '';
            
            // We retrieve the user’s real hashed password
            $stmtPwd = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmtPwd->execute([$userId]);
            $hash = $stmtPwd->fetchColumn();

            // We verify if what they entered matches
            if (password_verify($passwordSaisi, $hash)) {
                // This is the correct user. We deactivate the Authenticator.
                $stmt = $db->prepare("UPDATE users SET totp_secret = NULL, backup_codes = NULL, 2fa_method = 'email' WHERE id = ?");
                $stmt->execute([$userId]);

                $message = ($lang == 'fr') ? "L'authenticator a été désactivé avec succès." : "Authenticator successfully disabled.";
                $msgType = "success";
            } else {
                // Wrong password: spoofing attempt blocked
                $message = ($lang == 'fr') ? "Mot de passe incorrect. Impossible de désactiver la sécurité." : "Incorrect password. Cannot disable security.";
                $msgType = "error";
            }
        } catch (Exception $e) {
            $message = (($lang == 'fr') ? "Erreur : " : "Error: ") . $e->getMessage();
            $msgType = "error";
        }
    }
    
    // Identity update
    if (isset($_POST['update_identity'])) {
        try {
            $newEmail = $_POST['email'];
            
            // Simple check. Note: if database emails are encrypted, this check might miss duplicates
            $checkEmail = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmail->execute([$newEmail, $userId]);
            if ($checkEmail->fetch()) {
                throw new Exception(($lang == 'fr') ? "Cet email est déjà utilisé." : "This email is already in use.");
            }

            // ENCRYPTION BEFORE SAVING
            $encFirstname = Security::encrypt($_POST['firstname']);
            $encLastname  = Security::encrypt($_POST['lastname']);
            
            // Email encryption for consistency
            $cleanEmail = $newEmail; 

            $stmt = $db->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
            $stmt->execute([$encFirstname, $encLastname, $cleanEmail, $userId]);

            
            $sujet = msg('email_subj_update_info');
            $corps = '
            <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
            <body style="margin:0;padding:0;background:#f1f5f9;font-family:Poppins,Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
            <tr><td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                <tr><td style="background:#3b82f6;padding:40px 30px;border-radius:16px 16px 0 0;text-align:center;">
                    <h1 style="margin:0;color:white;font-size:28px;font-weight:800;">Img2brick</h1>
                    <p style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">' . msg('email_slogan') . '</p>
                </td></tr>
                <tr><td style="background:white;padding:40px 40px 30px;">
                    <h2 style="margin:0 0 12px;color:#1e293b;font-size:22px;font-weight:700;">' . msg('email_update_h2') . '</h2>
                    <p style="margin:0 0 24px;color:#64748b;font-size:15px;line-height:1.6;">
                        ' . msg('email_update_desc') . '
                    </p>
                    <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;">
                        <p style="margin:0;color:#92400e;font-size:13px;">' . msg('email_update_warning') . '</p>
                    </div>
                </td></tr>
                <tr><td style="background:#f8fafc;padding:20px 40px;border-radius:0 0 16px 16px;border-top:1px solid #e2e8f0;text-align:center;">
                    <p style="margin:0;color:#94a3b8;font-size:12px;">© ' . date('Y') . ' img2brick ' . msg('email_footer_copyright') . '</p>
                </td></tr>
            </table>
            </td></tr></table>
            </body></html>';
            
            // Send email (Assuming userMgr exists)
            if(isset($userMgr)) {
                $userMgr->sendEmail($newEmail, $sujet, $corps);
            }

            $message = ($lang == 'fr') ? "Informations mises à jour." : "Information updated.";
            $msgType = "success";

        } catch (Exception $e) {
            $message = (($lang == 'fr') ? "Erreur : " : "Error: ") . $e->getMessage();
            $msgType = "error";
        }
    }

    if (isset($_POST['update_address'])) {
        try {

            $encAddress = Security::encrypt($_POST['address']);
            $encPhone   = Security::encrypt($_POST['phone']);
            // Zipcode is usually not sensitive enough to break searchability, but let's encrypt it if you wish, 
            // or keep it plain. Here I keep it plain like City/Country based on your previous code style, 
            // but you can encrypt it using Security::encrypt($_POST['zipcode']) if needed.
            $cleanZip   = $_POST['zipcode']; 
            $cleanCity  = $_POST['city'];
            $cleanCountry = $_POST['country'];

            // Added zipcode to query
            $stmt = $db->prepare("UPDATE users SET address = ?, zipcode = ?, city = ?, country = ?, phone = ? WHERE id = ?");
            $stmt->execute([$encAddress, $cleanZip, $cleanCity, $cleanCountry, $encPhone, $userId]);


            // Notification logic...
            $stmtEmail = $db->prepare("SELECT email FROM users WHERE id = ?");
            $stmtEmail->execute([$userId]);
            $currentEmail = $stmtEmail->fetchColumn();
            
            if ($currentEmail && isset($userMgr)) {
                 // Note: If email was encrypted in DB, decrypt here. If plain, use as is.
                 // $userEmail = Security::decrypt($currentEmail); 
                 $userEmail = $currentEmail; 

                $sujet = msg('email_subj_update_contact');
                $corps = '
                <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
                <body style="margin:0;padding:0;background:#f1f5f9;font-family:Poppins,Arial,sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
                <tr><td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                    <tr><td style="background:#3b82f6;padding:40px 30px;border-radius:16px 16px 0 0;text-align:center;">
                        <h1 style="margin:0;color:white;font-size:28px;font-weight:800;">Img2brick</h1>
                        <p style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">' . msg('email_slogan') . '</p>
                    </td></tr>
                    <tr><td style="background:white;padding:40px 40px 30px;">
                        <h2 style="margin:0 0 12px;color:#1e293b;font-size:22px;font-weight:700;">' . msg('email_contact_h2') . '</h2>
                        <p style="margin:0 0 24px;color:#64748b;font-size:15px;line-height:1.6;">
                            ' . msg('email_contact_desc') . '
                        </p>
                        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;">
                            <p style="margin:0;color:#92400e;font-size:13px;">' . msg('email_update_warning') . '</p>
                        </div>
                    </td></tr>
                    <tr><td style="background:#f8fafc;padding:20px 40px;border-radius:0 0 16px 16px;border-top:1px solid #e2e8f0;text-align:center;">
                        <p style="margin:0;color:#94a3b8;font-size:12px;">© ' . date('Y') . ' img2brick ' . msg('email_footer_copyright') . '</p>
                    </td></tr>
                </table>
                </td></tr></table>
                </body></html>';
                $userMgr->sendEmail($userEmail, $sujet, $corps);
            }

            $message = ($lang == 'fr') ? "Coordonnées mises à jour." : "Contact details updated.";
            $msgType = "success";

        } catch (Exception $e) {
            $message = (($lang == 'fr') ? "Erreur : " : "Error: ") . $e->getMessage();
            $msgType = "error";
        }
    }
}

// --- Data retrieval for Display ---
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// DECRYPTION FOR DISPLAY
// We use the null coalescing operator (??) to handle cases where data might be null
$decryptedAddress   = Security::decrypt($user['address'] ?? '');
$decryptedPhone     = Security::decrypt($user['phone'] ?? '');
$decryptedFirstname = Security::decrypt($user['firstname'] ?? ''); 
$decryptedLastname  = Security::decrypt($user['lastname'] ?? '');  

// Non-encrypted fields
$displayEmail   = $user['email'] ?? '';
$displayZip     = $user['zipcode'] ?? '';
$displayCity    = $user['city'] ?? '';
$displayCountry = $user['country'] ?? '';

// TOTP LOGIC 
$isTotpActive = isset($user['2fa_method']) && $user['2fa_method'] === 'totp';
$qrCodeImageBase64 = '';
$secret = '';

if (!$isTotpActive) {
    $tfa = new TwoFactorAuthLight();
    if (empty($_SESSION['temp_totp_secret'])) {
        $_SESSION['temp_totp_secret'] = $tfa->createSecret();
    }
    $secret = $_SESSION['temp_totp_secret'];
    
    // Generation of the QR Code
    $uri = $tfa->getQRCodeUrl('Img2Brick', $displayEmail, $secret);
    $options = new QROptions([
        'version'      => 5,
        'eccLevel'     => EccLevel::L,
    ]);
    $qrcode = new QRCode($options);
    $qrCodeImageBase64 = $qrcode->render($uri);
}

include "header.php";
?>

<style>
    :root { --bg-color: #f8fafc; --card-bg: #ffffff; --primary: #2563eb; --text-main: #1e293b; --border: #e2e8f0; }
    body { background-color: var(--bg-color); color: var(--text-main); }
    .account-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 250px 1fr; gap: 40px; }
    .sidebar-menu { display: flex; flex-direction: column; gap: 10px; }
    .menu-item { padding: 12px 20px; border-radius: 8px; color: #64748b; text-decoration: none; font-weight: 500; transition: 0.2s; }
    .menu-item:hover { background: #eff6ff; color: var(--primary); }
    .menu-item.active { background: var(--primary); color: white; }
    .content-area h1 { margin-top: 0; font-size: 2rem; margin-bottom: 30px; }
    .card { background: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 30px; border: 1px solid var(--border); }
    .card h2 { margin-top: 0; font-size: 1.2rem; color: #0f172a; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-width { grid-column: 1 / -1; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #475569; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; color: #1e293b; transition: border-color 0.2s; }
    .form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
    .btn-save { background: var(--primary); color: white; padding: 10px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.2s; }
    .btn-save:hover { background: #1d4ed8; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    @media (max-width: 768px) { .account-container { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="account-container">
    
    <div class="sidebar-menu">
        <a href="account.php" class="menu-item active"> <?= ($lang == 'fr') ? "Mon Profil" : "My Profile" ?></a>
        <a href="panier.php" class="menu-item"> <?= ($lang == 'fr') ? "Mes Commandes" : "My Orders" ?></a>
        <a href="deconnexion.php" class="menu-item" style="color:#ef4444;"> <?= ($lang == 'fr') ? "Déconnexion" : "Logout" ?></a>
    </div>

    <div class="content-area">
        <h1><?= ($lang == 'fr') ? "Mon Espace" : "My Account" ?></h1>

        <?php if($message): ?>
            <div class="alert alert-<?= $msgType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?= ($lang == 'fr') ? "Informations Personnelles" : "Personal Information" ?></h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($displayEmail) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Prénom" : "First Name" ?></label>
                        <input type="text" name="firstname" value="<?= htmlspecialchars($decryptedFirstname) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Nom" : "Last Name" ?></label>
                        <input type="text" name="lastname" value="<?= htmlspecialchars($decryptedLastname) ?>">
                    </div>
                </div>
                <button type="submit" name="update_identity" class="btn-save"><?= ($lang == 'fr') ? "Enregistrer" : "Save Changes" ?></button>
            </form>
        </div>

        <div class="card">
            <h2><?= ($lang == 'fr') ? "Adresse & Contact" : "Address & Contact" ?></h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label><?= ($lang == 'fr') ? "Adresse postale" : "Address" ?></label>
                        <input type="text" name="address" value="<?= htmlspecialchars($decryptedAddress) ?>" placeholder="10 rue des Lilas...">
                    </div>
                    
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Code Postal" : "Zip Code" ?></label>
                        <input type="text" name="zipcode" value="<?= htmlspecialchars($displayZip) ?>">
                    </div>

                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Ville" : "City" ?></label>
                        <input type="text" name="city" value="<?= htmlspecialchars($displayCity) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Pays" : "Country" ?></label>
                        <input type="text" name="country" value="<?= htmlspecialchars($displayCountry) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label><?= ($lang == 'fr') ? "Téléphone" : "Phone" ?></label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($decryptedPhone) ?>" placeholder="06 12 34 56 78">
                    </div>
                </div>
                <button type="submit" name="update_address" class="btn-save"><?= ($lang == 'fr') ? "Mettre à jour" : "Update" ?></button>
            </form>
        </div>

        <div class="card">
            <h2><?= ($lang == 'fr') ? "Sécurité" : "Security" ?></h2>
            <p style="margin-bottom: 20px;">
                <a href="forgot_password.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">
                    <?= ($lang == 'fr') ? "Changer mon mot de passe" : "Change my password" ?>
                </a>
            </p>

            <h3 style="margin-top: 30px; font-size: 1.1rem; border-top: 1px solid var(--border); padding-top: 15px;">Double Authentification</h3>
            
            <?php if ($isTotpActive): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <strong> <?= ($lang == 'fr') ? "Activé" : "Enabled" ?></strong> : <?= ($lang == 'fr') ? "Votre compte est sécurisé par l'application Google Authenticator." : "Your account is secured by Google Authenticator." ?>
                    </div>
                    
                    <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px;">
                        <h4 style="margin-top: 0; color: #991b1b; font-size: 1rem;">
                            <?= ($lang == 'fr') ? "Désactiver l'Authenticator" : "Disable Authenticator" ?>
                        </h4>
                        <p style="font-size: 0.9rem; color: #7f1d1d; margin-bottom: 15px;">
                            <?= ($lang == 'fr') ? "Pour confirmer la désactivation, veuillez saisir votre mot de passe actuel." : "To confirm deactivation, please enter your current password." ?>
                        </p>
                        
                        <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="password" name="password_confirm" required placeholder="<?= ($lang == 'fr') ? 'Votre mot de passe' : 'Your password' ?>" style="padding: 10px; border: 1px solid #f87171; border-radius: 6px; flex: 1; min-width: 200px; max-width: 250px;">
                            <button type="submit" name="disable_totp" style="background: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;">
                                <?= ($lang == 'fr') ? "Désactiver" : "Disable" ?>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                <p style="font-size: 0.95rem; color: #475569;">
                    <?= ($lang == 'fr') ? "Sécurisez votre compte en utilisant une application comme Google Authenticator ou FreeOTP." : "Secure your account using an app like Google Authenticator or FreeOTP." ?>
                </p>
                
                <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; background: #f8fafc; padding: 20px; border-radius: 8px; margin-top: 15px;">
                            
                    <img src="<?= $qrCodeImageBase64 ?>" alt="QR Code" style="border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 30%; height: auto;">
                    
                    <div style="flex: 1; min-width: 250px;">
                        <p style="margin:0 0 10px 0; font-weight: bold;">1. <?= ($lang == 'fr') ? "Scannez le QR Code" : "Scan the QR Code" ?></p>
                        
                        <p style="margin:0 0 15px 0; font-size: 0.85rem; color: #64748b; word-break: break-all;">
                            <?= ($lang == 'fr') ? "Clé manuelle :" : "Manual key:" ?> <strong style="color: #1e293b;"><?= htmlspecialchars($secret) ?></strong>
                        </p>
                        
                        <p style="margin:0 0 10px 0; font-weight: bold;">2. <?= ($lang == 'fr') ? "Validez le code" : "Enter the code" ?></p>
                        
                        <form action="verify_totp_setup.php" method="POST" style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <input type="text" name="totp_code" required pattern="[0-9]{6}" maxlength="6" placeholder="123456" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; width: 120px; text-align: center; letter-spacing: 2px;">
                            <button type="submit" class="btn-save" style="margin: 0;"><?= ($lang == 'fr') ? "Activer" : "Enable" ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>