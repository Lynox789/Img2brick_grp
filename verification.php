<?php
require 'config.php';

// Session verification
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['auth_mode'])) {
    header("Location: inscription.php");
    exit;
}

$userId = $_SESSION['pending_user_id'];
$mode = $_SESSION['auth_mode'];
$message = "";

// Cancellation management
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    if ($mode === 'REGISTER') {
        $userMgr->deleteUser($userId);
    }
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['auth_mode']);
    header("Location: inscription.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['code'] ?? '';

    if ($tokenMgr->verify2FACode($userId, $code)) {
        if ($mode === 'REGISTER') {
            $userMgr->confirmAccount($userId);
        }
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['auth_mode']);

        if (isset($_SESSION['redirect_after_auth']) && $_SESSION['redirect_after_auth'] === 'cart.php') {
            header("Location: cart.php");
            exit;
        }
        header("Location: upload.php"); 
        exit;
    } else {
        $message = msg('verif_error_code');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= msg('verif_page_title') ?></title>
    <style>
        /* --- Global Styles --- */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(180deg, #FFFFFF 0%, #EFF6FF 100%);
            color: #1f2937;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .verification-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1rem;
        }

        p.instruction {
            color: #4b5563;
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .error {
            color: #dc2626;
            background-color: #fef2f2;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border: 1px solid #fee2e2;
        }

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            width: 100%;
            max-width: 400px;
            margin-bottom: 1rem; 
        }

        input[type="text"] {
            font-size: 1.25rem;
            text-align: center;
            letter-spacing: 0.5em;
            padding: 12px 16px;
            width: 200px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: #fff;
            color: #374151;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        /* --- BUTTON STYLES --- */
        
        /* Base class for common shape */
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 10px 30px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: background-color 0.2s;
            width: auto;
            min-width: 150px; 
            box-sizing: border-box;
        }

        /* Primary Button */
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        /* Cancel Button */
        .btn-cancel {
            background-color: #4b5563; 
            color: white;
            margin-top: 0.5rem; 
        }
        .btn-cancel:hover {
            background-color: #374151; 
        }

        header, footer { flex-shrink: 0; }
    </style>
</head>
<body>

    <?php include "header.php";?>

    <main class="verification-container">
        
        <h2><?= msg('verif_main_title') ?></h2>
        
        <p class="instruction"><?= msg('verif_instruction') ?></p>
        
        <?php if($message): ?>
            <p class="error"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="code" placeholder="000000" maxlength="6" required autocomplete="off" autofocus>
            
            <button type="submit" class="btn btn-primary"><?= msg('verif_btn_validate') ?></button>
        </form>
        
        <a class="btn btn-cancel" href="verification.php?action=cancel" onclick="return confirm('<?= ($mode === 'REGISTER') ? msg('verif_confirm_del') : msg('verif_confirm_logout') ?>');">
            <?= msg('verif_link_cancel') ?>
        </a>

    </main>

    <?php include "footer.php";?>
    
</body>
</html>