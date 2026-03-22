<?php
require 'config.php';

$token = $_GET['token'] ?? '';
$message = "";
$success = false;

if (!$token) {
    die("Token manquant.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    
    //Verifies if the password matches
    if ($newPass !== $confirmPass) {
        $message = "Les mots de passe ne correspondent pas.";
    } 
    //Verify the complexity 
    elseif (!class_exists('Security') || !Security::isPasswordStrong($newPass)) {
        $message = "Mot de passe trop faible (Min 12 car, Maj, Min, Chiffre, Spécial).";
    } 
    //We update
    else {
        if ($userMgr->resetPasswordWithToken($token, $newPass)) {
            $success = true;
        } else {
            $message = "Ce lien est invalide ou a expiré.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 40px 20px;
            box-sizing: border-box;
            margin: 0;
        }
        .rp-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .rp-header {
            background: #3b82f6;
            padding: 32px 36px 28px;
            text-align: center;
        }
        .rp-logo { font-size: 22px; font-weight: 800; color: white; margin: 0 0 4px; }
        .rp-logo-sub { font-size: 13px; color: rgba(255,255,255,0.75); margin: 0; }
        .rp-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 16px auto 0;
        }
        .rp-body { padding: 32px 36px; }
        .rp-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
        .rp-desc { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        .rp-label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin: 0 0 6px; }
        .rp-input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: 14px; color: #1e293b;
            box-sizing: border-box; outline: none; transition: border-color 0.2s;
            margin-bottom: 16px;
        }
        .rp-input:focus { border-color: #3b82f6; }
        .rp-btn {
            width: 100%; background: #3b82f6; color: white;
            border: none; padding: 12px; border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: 15px; font-weight: 600;
            cursor: pointer; margin-top: 8px; transition: background 0.2s;
        }
        .rp-btn:hover { background: #2563eb; }
        .rp-alert {
            padding: 12px 14px; border-radius: 8px;
            font-size: 13px; margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 8px; line-height: 1.5;
        }
        .rp-error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .rp-hint {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 12px 14px;
            font-size: 12px; color: #64748b; line-height: 1.6; margin-bottom: 20px;
        }
        .rp-hint strong { color: #475569; display: block; margin-bottom: 4px; font-size: 13px; }
        .rp-success-block { text-align: center; padding: 8px 0 16px; }
        .rp-success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: #dcfce7; border: 2px solid #bbf7d0;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .rp-footer { text-align: center; padding: 0 36px 28px; }
        .rp-link { font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 500; }
        .rp-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="rp-card">
        <div class="rp-header">
            <p class="rp-logo">🧱 img2brick</p>
            <p class="rp-logo-sub">Transformez vos images en mosaïques</p>
            <div class="rp-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
        </div>

        <div class="rp-body">
            <?php if ($success): ?>
                <div class="rp-success-block">
                    <div class="rp-success-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <h2 class="rp-title">Mot de passe modifié !</h2>
                    <p class="rp-desc">Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter.</p>
                    <a href="inscription.php" class="rp-btn" style="display:block; text-decoration:none; text-align:center;">Se connecter</a>
                </div>
            <?php else: ?>
                <h2 class="rp-title">Nouveau mot de passe</h2>
                <p class="rp-desc">Choisissez un mot de passe sécurisé pour votre compte.</p>

                <?php if ($message): ?>
                    <div class="rp-alert rp-error">
                        <svg width="16" height="16" style="flex-shrink:0; margin-top:1px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="rp-hint">
                    <strong>Exigences du mot de passe</strong>
                    Minimum 12 caractères · 1 majuscule · 1 minuscule · 1 chiffre · 1 caractère spécial
                </div>

                <form method="post">
                    <label class="rp-label">Nouveau mot de passe</label>
                    <input class="rp-input" type="password" name="new_password" placeholder="••••••••••••" required>
                    <label class="rp-label">Confirmer le mot de passe</label>
                    <input class="rp-input" type="password" name="confirm_password" placeholder="••••••••••••" required>
                    <button class="rp-btn" type="submit">Valider le nouveau mot de passe</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="rp-footer">
            <a href="inscription.php" class="rp-link">← Retour à la connexion</a>
        </div>
    </div>
</body>
</html>