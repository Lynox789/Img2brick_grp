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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            box-sizing: border-box;
            margin: 0;
        }
        .fp-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .fp-header {
            background: #3b82f6;
            padding: 32px 36px 28px;
            text-align: center;
        }
        .fp-logo { font-size: 22px; font-weight: 800; color: white; margin: 0 0 4px; }
        .fp-logo-sub { font-size: 13px; color: rgba(255,255,255,0.75); margin: 0; }
        .fp-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 16px auto 0;
        }
        .fp-body { padding: 32px 36px; }
        .fp-title { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
        .fp-desc { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0 0 24px; }
        .fp-label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
        .fp-input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: 14px; color: #1e293b;
            box-sizing: border-box; outline: none; transition: border-color 0.2s;
        }
        .fp-input:focus { border-color: #3b82f6; }
        .fp-btn {
            width: 100%; background: #3b82f6; color: white;
            border: none; padding: 12px; border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: 15px; font-weight: 600;
            cursor: pointer; margin-top: 20px; transition: background 0.2s;
        }
        .fp-btn:hover { background: #2563eb; }
        .fp-alert {
            padding: 12px 14px; border-radius: 8px;
            font-size: 13px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .fp-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .fp-error   { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .fp-footer  { text-align: center; padding: 0 36px 28px; }
        .fp-link    { font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 500; }
        .fp-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="fp-card">
    <div class="fp-header">
        <p class="fp-logo">Img2brick</p>
        <p class="fp-logo-sub"><?= msg('site_slogan') ?></p>
        <div class="fp-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
    </div>

    <div class="fp-body">
        <h2 class="fp-title"><?= msg('forgot_title') ?></h2>
        <p class="fp-desc"><?= msg('forgot_instruction') ?></p>

        <?php if ($success): ?>
            <div class="fp-alert fp-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $success ?>
            </div>
        <?php elseif ($message): ?>
            <div class="fp-alert fp-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <label class="fp-label"><?= msg('placeholder_email') ?></label>
            <input class="fp-input" type="email" name="email" placeholder="<?= msg('placeholder_email_example') ?>" required>
            <button class="fp-btn" type="submit"><?= msg('forgot_btn_send') ?></button>
        </form>
    </div>

    <div class="fp-footer">
        <a href="inscription.php" class="fp-link">← <?= msg('link_back_login') ?></a>
    </div>
</div>
</body>
</html>