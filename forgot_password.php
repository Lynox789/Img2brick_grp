<?php
require 'config.php';

$message = "";
$success = "";

// Handle form submission
if (isset($_POST['email'])) {
    $email = $_POST['email'];

    // Attempt to send the reset link using the User Manager
    // Uses the msg() helper for localized/translated strings
    if ($userMgr->sendPasswordResetLink($email)) {
        $success = msg('forgot_success');
    } else {
        $message = msg('forgot_error');
    }
}
?>
<?php include 'header.php'; ?>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; }
        .success { color: green; font-weight: bold; margin: 15px 0; }
        .error { color: red; font-weight: bold; margin: 15px 0; }
        input { padding: 10px; width: 250px; }
        button { padding: 10px 20px; cursor: pointer; }
        .page-forgot { max-width: 500px; margin: 0 auto; }
    </style>
</head>

<div class="page-forgot">
    <h2><?= msg('forgot_title') ?></h2>
    
    <?php if ($success): ?>
        <p class="success"><?= $success ?></p>
    <?php elseif ($message): ?>
        <p class="error"><?= $message ?></p>
    <?php endif; ?>

    <p><?= msg('forgot_instruction') ?></p>
    
    <form method="post">
        <input type="email" name="email" placeholder="<?= msg('placeholder_email') ?>" required>
        <br><br>
        <button type="submit"><?= msg('forgot_btn_send') ?></button>
    </form>
    
    <br>
    <a href="inscription.php"><?= msg('link_back_login') ?></a>
</div>

<?php include 'footer.php'; ?>