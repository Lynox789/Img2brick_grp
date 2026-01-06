<?php
require "config.php";
require_once "classes/Security.php";

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

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
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

            $encFirstname = Security::encrypt($_POST['firstname']);
            $encLastname  = Security::encrypt($_POST['lastname']);
            
            // Email encryption for consistency
            $encEmail = $newEmail;

            $stmt = $db->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
            $stmt->execute([$encFirstname, $encLastname, $encEmail, $userId]);

            Logger::log($db, 'PROFILE_UPDATE', "Modification identité");
            
            // Email notification
            if ($lang == 'fr') {
                $sujet = "Mise à jour de vos informations personnelles";
                $corps = "Bonjour,<br><br>Vos informations personnelles (Nom, Prénom ou Email) ont été modifiées.<br>Si vous n'êtes pas à l'origine de cette action, contactez-nous.";
            } else {
                $sujet = "Update of your personal information";
                $corps = "Hello,<br><br>Your personal information (Name, First Name, or Email) has been updated.<br>If you did not initiate this action, please contact us.";
            }
            
            $message = ($lang == 'fr') ? "Informations mises à jour." : "Information updated.";
            $msgType = "success";
            
            $userMgr->sendEmail($newEmail, $sujet, $corps);

        } catch (Exception $e) {
            $message = (($lang == 'fr') ? "Erreur : " : "Error: ") . $e->getMessage();
            $msgType = "error";
        }
    }

    // Address and contact update
    if (isset($_POST['update_address'])) {
        try {
            $encAddress = Security::encrypt($_POST['address']);
            $encPhone   = Security::encrypt($_POST['phone']);

            $stmt = $db->prepare("UPDATE users SET address = ?, city = ?, country = ?, phone = ? WHERE id = ?");
            $stmt->execute([$encAddress, $_POST['city'], $_POST['country'], $encPhone, $userId]);

            Logger::log($db, 'ADDRESS_UPDATE', "Modification adresse/téléphone");

            // Notification to current email (decrypted from DB)
            $stmtEmail = $db->prepare("SELECT email FROM users WHERE id = ?");
            $stmtEmail->execute([$userId]);
            $encryptedUserEmail = $stmtEmail->fetchColumn();
            
            if ($encryptedUserEmail) {
                $userEmail = Security::decrypt($encryptedUserEmail);
                
                if ($lang == 'fr') {
                    $sujet = "Mise à jour de vos coordonnées";
                    $corps = "Bonjour,<br><br>Votre adresse de livraison ou votre téléphone a été mis à jour.";
                } else {
                    $sujet = "Update of your contact details";
                    $corps = "Hello,<br><br>Your delivery address or phone number has been updated.";
                }

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

// Data retrieval
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$decryptedAddress   = Security::decrypt($user['address'] ?? '');
$decryptedPhone     = Security::decrypt($user['phone'] ?? '');
$decryptedFirstname = Security::decrypt($user['firstname'] ?? ''); 
$decryptedLastname  = Security::decrypt($user['lastname'] ?? '');  
$decryptedEmail     = Security::decrypt($user['email'] ?? '');

include "header.php";
?>

<style>
    :root {
        --bg-color: #f8fafc;
        --card-bg: #ffffff;
        --primary: #2563eb;
        --text-main: #1e293b;
        --border: #e2e8f0;
    }
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
    .form-group input:disabled { background: #f1f5f9; cursor: not-allowed; }
    .btn-save { background: var(--primary); color: white; padding: 10px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 10px; transition: 0.2s; }
    .btn-save:hover { background: #1d4ed8; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    @media (max-width: 768px) { .account-container { grid-template-columns: 1fr; } .form-grid { grid-template-columns: 1fr; } }
</style>

<div class="account-container">
    
    <div class="sidebar-menu">
        <a href="account.php" class="menu-item active"><?= ($lang == 'fr') ? "Mon Profil" : "My Profile" ?></a>
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
                        <input type="email" name="email" value="<?= htmlspecialchars($decryptedEmail ?: $user['email']) ?>" required>
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
                        <input type="text" name="address" value="<?= htmlspecialchars($decryptedAddress) ?>" placeholder="<?= ($lang == 'fr') ? "Votre adresse complète" : "Your full address" ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Ville" : "City" ?></label>
                        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><?= ($lang == 'fr') ? "Pays" : "Country" ?></label>
                        <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? 'France') ?>">
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
            <p style="margin-bottom: 10px;">
                <a href="inscription.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">
                    <?= ($lang == 'fr') ? "Changer mon mot de passe" : "Change my password" ?>
                </a>
            </p>
            <p style="color: #64748b; font-size: 0.9rem;">
                <?= ($lang == 'fr') 
                    ? "Redirection vers la page d'inscription, appuyez sur le bouton 'Mot de passe oublié'." 
                    : "Redirects to the registration page, please click on the 'Forgot Password' button." ?>
            </p>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>