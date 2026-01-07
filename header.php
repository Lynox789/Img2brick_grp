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

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Inter', system-ui, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding-top: 80px;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        footer, .site-footer {
            margin-top: auto !important;
            width: 100%;
        }
    
        .checkout-container, .main-container {
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
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
            z-index: 1001; 
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-links {
            display: flex;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
            white-space: nowrap; /* Prevents the text from breaking */
        }

        .nav-links a:hover {
            color: var(--accent);
        }

        .separator {
            color: #e2e8f0; 
            margin: 0 10px;
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


        .hamburger {
            display: none; 
            cursor: pointer;
            background: none;
            border: none;
            padding: 10px;
            z-index: 1001;
        }
        
        .hamburger span {
            display: block;
            width: 25px;
            height: 3px;
            background-color: var(--text);
            margin: 5px 0;
            border-radius: 3px;
            transition: 0.3s;
        }

        @media (max-width: 768px) {
            header {
                padding: 0 20px; 
            }

            .hamburger {
                display: block; 
            }

            /* Transformation of the button into a cross when active */
            .hamburger.active span:nth-child(1) { transform: rotate(-45deg) translate(-5px, 6px); }
            .hamburger.active span:nth-child(2) { opacity: 0; }
            .hamburger.active span:nth-child(3) { transform: rotate(45deg) translate(-5px, -6px); }

            .nav-right {
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                height: calc(100vh - 70px); 
                background: var(--card);
                flex-direction: column; 
                justify-content: flex-start;
                padding-top: 40px;
                gap: 30px;
                transform: translateX(100%); 
                transition: transform 0.3s ease-in-out;
                border-top: 1px solid #e2e8f0;
            }

            .nav-right.active {
                transform: translateX(0); 
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
                gap: 20px;
            }

            .nav-links a {
                font-size: 18px; 
                padding: 10px;
                width: 100%;
                text-align: center;
            }

            .separator {
                display: none; 
            }

            .language-selector {
                width: 100%;
                justify-content: center;
                border-left: none;
                padding-left: 0;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
            }
            
            .flag-btn img {
                width: 32px; 
            }
        }
    </style>
</head>
<body>

<header>
    <a href="upload.php" class="brand">
        Img2brick
    </a>

    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="nav-right" id="navMenu">
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="upload.php"><?= msg('nav_create') ?></a>
                <span class="separator">|</span>
                <a href="account.php"><?= msg('nav_account') ?></a>
                <span class="separator">|</span>
                <a href="panier.php"><?= msg('nav_orders') ?></a>
                <span class="separator">|</span>
                <a href="deconnexion.php" style="color:#ef4444;"><?= msg('nav_logout') ?></a>
            <?php else: ?>
                <a href="upload.php"><?= msg('nav_home') ?></a>
                <span class="separator">|</span>
                <a href="inscription.php"><?= msg('nav_login_signup') ?></a>
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

<script>
    // script to manage the opening/closing of the mobile menu
    const hamburger = document.getElementById('hamburgerBtn');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
        
        // Prevent body scroll when menu is open 

        if (navMenu.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
    });

    // Close the menu if you click on a link
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    });
</script>