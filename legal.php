<?php
require "config.php";
include 'header.php';
?>

<head>
    <style>
        /* General Reset */
        * { box-sizing: border-box; }

        /* Main Body Style */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex; 
            flex-direction: column; 
            margin: 0;
            padding-top: 80px;
        }

        /* Layout Containers */
        .legal-container {
            flex: 1; 
            display: flex;
            align-items: flex-start;
            justify-content: center; 
            width: 100%;
            padding: 40px 20px; 
        }

        .legal-wrapper {
            background: #fff;
            width: 800px;
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 15px 20px rgba(0,0,0,0.1);
            padding: 40px;
            position: relative;
        }

        /* Typography */
        h1 {
            font-size: 30px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        h2 {
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 10px;
        }

        p {
            line-height: 1.6;
            color: #555;
            margin-bottom: 15px;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        /* Back Button */
        .back-btn {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background-color: #333;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        .back-btn:hover {
            background-color: #555;
            text-decoration: none;
        }
    </style>
</head>

<div class="legal-container">
    <div class="legal-wrapper">
        <h1><?= msg('legal_title') ?></h1>

        <h2><?= msg('legal_1_title') ?></h2>
        <p><?= msg('legal_1_text') ?></p>
        <p><?= msg('legal_1_director') ?></p>
        <p><?= msg('legal_1_contact') ?></p>

        <h2><?= msg('legal_2_title') ?></h2>
        <p>
            <?= msg('legal_2_intro') ?><br>
            <strong>Hostinger International Ltd.</strong><br>
            <?= msg('legal_2_addr') ?><br>
            <?= msg('legal_2_web') ?>
        </p>

        <h2><?= msg('legal_3_title') ?></h2>
        <p><?= msg('legal_3_text1') ?></p>
        <p><?= msg('legal_3_text2') ?></p>

        <h2><?= msg('legal_4_title') ?></h2>
        <p><?= msg('legal_4_text1') ?></p>
        <p><?= msg('legal_4_text2') ?></p>

        <h2><?= msg('legal_5_title') ?></h2>
        <p><?= msg('legal_5_text') ?></p>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn"><?= msg('btn_back_home') ?></a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>