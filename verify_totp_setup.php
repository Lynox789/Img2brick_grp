<?php
require "config.php"; 
require_once "classes/TwoFactorAuthLight.php"; 

// Additional security in case
if (session_status() === PHP_SESSION_NONE) session_start();

$tfa = new TwoFactorAuthLight();
$lang = $_SESSION['lang'] ?? 'fr';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'], $_SESSION['temp_totp_secret'])) {
    $code = $_POST['totp_code'];
    $secret = $_SESSION['temp_totp_secret'];
    $userId = $_SESSION['user_id']; 

    if ($tfa->verifyCode($secret, $code)) {
        // The code is good! We generate 5 backup codes
        $backupCodesPlain = [];
        $backupCodesHashed = [];

        for ($i = 0; $i < 5; $i++) {
            $bCode = bin2hex(random_bytes(4)); 
            $backupCodesPlain[] = $bCode;
            $backupCodesHashed[] = password_hash($bCode, PASSWORD_BCRYPT);
        }

        $stmt = $db->prepare("UPDATE users SET totp_secret = ?, backup_codes = ?, 2fa_method = 'totp' WHERE id = ?");
        $stmt->execute([
            $secret,
            json_encode($backupCodesHashed),
            $userId
        ]);

        // We delete the temporary secret
        unset($_SESSION['temp_totp_secret']);

        // Displaying the success message
        echo "<div style='font-family: Poppins, sans-serif; text-align: center; margin-top: 50px;'>";
        echo "<h2 style='color: #166534;'>" . (($lang == 'fr') ? "Succès ! L'Authenticator est activé." : "Success! Authenticator is enabled.") . "</h2>";
        echo "<p style='color: #dc2626; font-weight: bold;'>URGENT : " . (($lang == 'fr') ? "Copiez ces codes de secours. Ils ne s'afficheront plus jamais." : "Copy these backup codes. They will never be shown again.") . "</p>";
        
        echo "<div style='background: #f1f5f9; padding: 20px; display: inline-block; border-radius: 8px; text-align: left;'>";
        echo "<ul style='list-style: none; padding: 0; font-family: monospace; font-size: 18px;'>";
        foreach ($backupCodesPlain as $codePlain) {
            echo "<li style='margin-bottom: 10px;'>" . htmlspecialchars($codePlain) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        echo "<br><br><a href='account.php' style='background: #4A90E2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>" . (($lang == 'fr') ? "Retour à mon compte" : "Back to my account") . "</a>";
        echo "</div>";

    } else {
        // Redirected to the account with an error (or direct display)
        echo "<script>alert('" . (($lang == 'fr') ? "Code invalide. Veuillez réessayer." : "Invalid code. Please try again.") . "'); window.location.href='account.php';</script>";
    }
} else {
    // If we access the page without posting a form
    header("Location: account.php");
    exit;
}
?>