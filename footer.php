<style>
    .site-footer {
        background-color: #1e293b; 
        color: #94a3b8; 
        padding: 60px 0 20px;
        margin-top: auto; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 0.95rem;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
    }

    .footer-col h3 {
        color: #ffffff;
        font-size: 1.1rem;
        margin-bottom: 20px;
        font-weight: 600;
        position: relative;
    }

    /* Petite ligne décorative sous les titres */
    .footer-col h3::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -8px;
        width: 40px;
        height: 3px;
        background-color: #2563eb; /* Bleu primaire */
        border-radius: 2px;
    }

    .footer-col p {
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 12px;
    }

    .footer-links a {
        color: #94a3b8;
        text-decoration: none;
        transition: color 0.3s ease, padding-left 0.3s ease;
        display: inline-block;
    }

    .footer-links a:hover {
        color: #ffffff;
        padding-left: 5px; /* Petit effet de glissement */
    }

    /* Bouton GitHub */
    .social-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.05);
        padding: 10px 15px;
        border-radius: 8px;
        color: white;
        text-decoration: none;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: 0.3s;
    }

    .social-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: #fff;
    }

    .footer-bottom {
        border-top: 1px solid #334155;
        margin-top: 50px;
        padding-top: 20px;
        text-align: center;
        font-size: 0.85rem;
    }

    /* Petit logo en briques (CSS pur pour le fun) */
    .brick-logo {
        font-weight: 800;
        color: white;
        letter-spacing: 1px;
        font-size: 1.5rem;
        margin-bottom: 15px;
        display: inline-block;
    }
    .brick-logo span { color: #2563eb; }
</style>

<footer class="site-footer">
    <div class="footer-container">
        
        <div class="footer-col">
            <div class="brick-logo">Img2<span>Brick</span></div>
            <p>
                <?= msg('footer_desc') ?>
            </p>
        </div>

        <div class="footer-col">
            <h3><?= msg('footer_nav_title') ?></h3>
            <ul class="footer-links">
                <li><a href="index.php"><?= msg('nav_home') ?></a></li>
                <li><a href="upload.php"><?= msg('nav_create_mosaic') ?></a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li><a href="panier.php"><?= msg('nav_my_orders') ?></a></li>
                    <li><a href="logout.php"><?= msg('nav_logout') ?></a></li>
                <?php else: ?>
                    <li><a href="inscription.php"><?= msg('nav_login_signup') ?></a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h3><?= msg('footer_project_title') ?></h3>
            <ul class="footer-links">
                <li><a href="#"><?= msg('footer_link_team') ?></a></li>
                <li><a href="terms.php"><?= msg('footer_link_terms') ?></a></li>
                <li><a href="legal.php"><?= msg('footer_link_legal') ?></a></li>
            </ul>
            
            <div style="margin-top: 20px;">
                <a href="https://github.com/Lynox789/Img2brick_grp" target="_blank" class="social-btn">
                    <svg height="24" width="24" viewBox="0 0 16 16" fill="white">
                        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                    </svg>
                    <?= msg('footer_btn_github') ?>
                </a>
            </div>
        </div>

    </div>

    <div class="footer-bottom">
        <div class="footer-container" style="display:block;">
            &copy; <?= date('Y') ?> <?= msg('footer_copyright') ?>
        </div>
    </div>
</footer>