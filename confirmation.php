<?php 
require "config.php";
include "header.php"; 
?>

<div style="max-width: 600px; margin: 80px auto; text-align: center; background: white; padding: 50px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    
    <svg viewBox="0 0 24 24" width="80" height="80" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>

    <h1 style="color: #333; margin-bottom: 10px;"><?= msg('conf_title') ?></h1>
    
    <p style="color: #64748b; font-size: 1.1rem; line-height: 1.6;">
        <?= msg('conf_desc') ?>
    </p>

    <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center;">
        <a href="upload.php" style="text-decoration: none; background: var(--accent); color: white; padding: 12px 25px; border-radius: 8px; font-weight: 500;">
            <?= msg('conf_btn_new') ?>
        </a>
        <a href="panier.php" style="text-decoration: none; background: #f1f5f9; color: #475569; padding: 12px 25px; border-radius: 8px; font-weight: 500;">
            <?= msg('conf_btn_cart') ?>
        </a>
    </div>

</div>

<?php include "footer.php"; ?>