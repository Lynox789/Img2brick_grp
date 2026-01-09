<?php
require "config.php";
include 'header.php';
?>

<head>
    <style>
        /* General Reset */
        * { box-sizing: border-box; }

        /* Main Body Style - Same as login/legal pages */
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
            font-weight: 600;
            color: #4A90E2;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        p, ul {
            font-size: 15px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        ul { padding-left: 20px; }
        li { margin-bottom: 5px; }

        /* Links */
        a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 500;
        }
        a:hover { text-decoration: underline; }

        /* Buttons */
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #4A90E2;
            color: white;
            border-radius: 5px;
            text-align: center;
            transition: opacity 0.3s ease;
        }
        .back-btn:hover {
            opacity: 0.9;
            text-decoration: none;
        }
    </style>
</head>

<div class="legal-container">
    <div class="legal-wrapper">
        
        <h1><?= msg('terms_title') ?></h1>

        <h2><?= msg('terms_1_title') ?></h2>
        <p><?= msg('terms_1_text1') ?></p>
        <p><?= msg('terms_1_text2') ?></p>

        <h2><?= msg('terms_2_title') ?></h2>
        <p><?= msg('terms_2_intro') ?></p>
        <ul>
            <li><?= msg('terms_2_li1') ?></li>
            <li><?= msg('terms_2_li2') ?></li>
            <li><?= msg('terms_2_li3') ?></li>
        </ul>
        <p><?= msg('terms_2_free') ?></p>

        <h2><?= msg('terms_3_title') ?></h2>
        <p><?= msg('terms_3_text1') ?></p>
        <p><?= msg('terms_3_text2') ?></p>
        <ul>
            <li><?= msg('terms_3_li1') ?></li>
            <li><?= msg('terms_3_li2') ?></li>
            <li><?= msg('terms_3_li3') ?></li>
        </ul>
        <p><?= msg('terms_3_text3') ?></p>

        <h2><?= msg('terms_4_title') ?></h2>
        <p><?= msg('terms_4_text1') ?></p>
        <p><?= msg('terms_4_text2') ?></p>

        <h2><?= msg('terms_5_title') ?></h2>
        <p><?= msg('terms_5_text1') ?></p>
        <p><?= msg('terms_5_text2') ?></p>

        <h2><?= msg('terms_6_title') ?></h2>
        <p><?= msg('terms_6_text') ?></p>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn"><?= msg('btn_back_home') ?></a>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>