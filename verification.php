<?php
require 'config.php';

// Existing session check
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['auth_mode'])) {
    header("Location: inscription.php");
    exit;
}

$userId = $_SESSION['pending_user_id'];
$mode = $_SESSION['auth_mode']; // Registration or Login
$message = "";

// Cancellation Management
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    
    // Registration cancellation, delete the account
    if ($mode === 'REGISTER') {
        $userMgr->deleteUser($userId);
    }

    // Session cleanup  
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['auth_mode']);

    header("Location: inscription.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['code'] ?? '';

    if ($tokenMgr->verify2FACode($userId, $code)) {

        // If Registration, activate account
        if ($mode === 'REGISTER') {
            $userMgr->confirmAccount($userId);
        }

        // Log the user in
        $_SESSION['user_id'] = $userId;

        // Remove temporary session variables
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['auth_mode']);

        // Check for redirection to cart (Visitor -> Registration -> Cart)
        if (isset($_SESSION['redirect_after_auth']) && $_SESSION['redirect_after_auth'] === 'cart.php') {
            header("Location: cart.php");
            exit;
        }

        // Default redirection
        header("Location: upload.php"); 
        exit;
    } else {
        $message = msg('verif_error_code');
    }
}
?>

<head>
    <meta charset="UTF-8">
    <title><?= msg('verif_page_title') ?></title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; }
        .error { color: red; }
        input { font-size: 20px; text-align: center; letter-spacing: 5px; padding: 10px; width: 150px; }
        button { padding: 10px 20px; background: #2563eb; color: white; border: none; cursor: pointer; margin-top: 10px;}
    </style>
</head>
<body>
    <h2><?= msg('verif_main_title') ?></h2>
    <p><?= msg('verif_instruction') ?></p>
    
    <?php if($message): ?>
        <p class="error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="code" placeholder="000000" maxlength="6" required autocomplete="off">
        <br>
        <button type="submit"><?= msg('verif_btn_validate') ?></button>
    </form>
    
    <br><br>
    
    <a href="verification.php?action=cancel" onclick="return confirm('<?= ($mode === 'REGISTER') ? msg('verif_confirm_del') : msg('verif_confirm_logout') ?>');">
        <?= msg('verif_link_cancel') ?>
    </a>

</body>
</html>