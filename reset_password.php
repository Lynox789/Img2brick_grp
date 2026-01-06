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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau mot de passe</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; }
        .error { color: red; margin-bottom: 15px; display: block; }
        .success { color: green; font-weight: bold; }
        input { padding: 10px; width: 250px; margin-bottom: 10px; }
        
        button { 
            padding: 10px 20px; 
            cursor: pointer; 
            background-color: #2563eb; 
            color: white; 
            border: none; 
            border-radius: 5px; 
        }
        button:hover { 
            background-color: #1d4ed8; 
        }
    </style>
</head>
<body>

    <?php if ($success): ?>
        <div class="success">
            Mot de passe modifié avec succès !<br><br>
            <a href="inscription.php">Cliquez ici pour vous connecter</a>
        </div>
    <?php else: ?>
        
        <h2>Choisissez un nouveau mot de passe</h2>
        
        <?php if ($message): ?>
            <span class="error"><?= htmlspecialchars($message) ?></span>
        <?php endif; ?>

        <form method="post">
            <input type="password" name="new_password" placeholder="Nouveau mot de passe" required>
            <br>
            <input type="password" name="confirm_password" placeholder="Confirmez le mot de passe" required>
            <br><br>
            <button type="submit">Valider</button>
        </form>
        <br>
        <a href="inscription.php">Retour inscription</a>
    <?php endif; ?>

</body>

<?php include "footer.php";?>

</html>

