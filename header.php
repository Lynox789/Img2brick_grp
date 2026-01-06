<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="<?= $_SESSION['lang'] ?? 'en'?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Img2brick</title>
    <style>
        :root {
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding-top: 80px;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 70px;
            background: var(--card);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .brand {
            font-weight: 800;
            font-size: 20px;
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--accent);
        }

        .language-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 24px;
            border-left: 1px solid #e2e8f0;
        }

        .flag-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            opacity: 0.6;
            transition: all 0.2s ease;
        }

        .flag-btn:hover, .flag-btn.active {
            opacity: 1;
            transform: scale(1.1);
        }

        .flag-btn img {
            display: block;
            width: 24px;
            height: auto;
            border-radius: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

    </style>
</head>
<body>

<header>
    <a href="upload.php" class="brand">
        Img2brick
    </a>

    <div class="nav-right">
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="upload.php"><?= msg('nav_create') ?></a>
                <span style="color:#e2e8f0; margin:0 10px;">|</span>
                <a href="account.php"><?= msg('nav_account') ?></a>
                <span style="color:#e2e8f0; margin:0 10px;">|</span>
                <a href="panier.php"><?= msg('nav_orders') ?></a>
                <span style="color:#e2e8f0; margin:0 10px;">|</span>
                <a href="deconnexion.php" style="color:#ef4444;"><?= msg('nav_logout') ?></a>
            <?php else: ?>
                <a href="upload.php"><?= msg('nav_home') ?></a>
            <?php endif; ?>
        </div>

        <div class="language-selector">
            <a href="?lang=en" class="flag-btn <?= (($_SESSION['lang'] ?? 'fr') == 'en') ? 'active' : '' ?>" title="English">
                <img src="https://flagcdn.com/w40/gb.png" alt="English">
            </a>
            
            <a href="?lang=fr" class="flag-btn <?= (($_SESSION['lang'] ?? 'fr') == 'fr') ? 'active' : '' ?>" title="Français">
                <img src="https://flagcdn.com/w40/fr.png" alt="Français">
            </a>
        </div>
    </div>
</header>