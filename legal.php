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

        /* Links */
        a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 500;
        }

        a:hover { text-decoration: underline; }

        /* Back Button Style */
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
        
        <h1>Mentions Légales</h1>

        <h2>1. Éditeur</h2>
        <p>Le site <strong>Img2bricks</strong> est réalisé dans le cadre d'un projet pédagogique (SAE) à l'IUT de Marne-la-Vallée (Université Gustave Eiffel).</p>
        <p>
            <strong>Responsable de la publication :</strong> CARCHON Valentin<br>
            <strong>Contact :</strong> <a href="mailto:valentin.carchon@edu.univ-eiffel.fr">valentin.carchon@edu.univ-eiffel.fr</a><br>
            <strong>Promotion :</strong> BUT Informatique 2ème année
        </p>

        <h2>2. Hébergement</h2>
        <p>
            Le site est hébergé par :<br>
            <strong>Hostinger International Ltd.</strong><br>
            Siège social : 61 Lordou Vironos Street, 6023 Larnaca, Chypre.<br>
            Site web : <a href="https://www.hostinger.fr" target="_blank">https://www.hostinger.fr</a>
        </p>

        <h2>3. Données Personnelles</h2>
        <p>Les informations recueillies (Nom, Prénom, Adresse, Photo à convertir) sont nécessaires pour le traitement de votre commande de mosaïque Lego. Elles sont enregistrées dans un fichier informatisé sécurisé.</p>
        <p>Conformément au RGPD, vous pouvez exercer votre droit d'accès aux données vous concernant et les faire rectifier en contactant : <a href="mailto:valentin.carchon@edu.univ-eiffel.fr">valentin.carchon@edu.univ-eiffel.fr</a>.</p>

        <h2>4. Propriété intellectuelle</h2>
        <p>L’ensemble de ce site relève de la législation française et internationale sur le droit d’auteur et la propriété intellectuelle.</p>
        <p>La marque <em>Lego</em> est une marque déposée du groupe LEGO. Ce site est un projet pédagogique indépendant et n'est pas sponsorisé ou autorisé par le groupe LEGO.</p>

        <h2>5. Cookies</h2>
        <p>Ce site utilise des cookies techniques strictement nécessaires au bon fonctionnement de l'authentification et de la navigation.</p>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn">Retour à l'accueil</a>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>