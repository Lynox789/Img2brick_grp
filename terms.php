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
        
        <h1>Conditions Générales d'Utilisation (CGU)</h1>

        <h2>1. Objet</h2>
        <p>Les présentes Conditions Générales d'Utilisation ont pour objet de définir les modalités de mise à disposition des services du site <strong>Img2bricks</strong> et les conditions d'utilisation du service par l'utilisateur.</p>
        <p>Tout accès et/ou utilisation du site suppose l'acceptation et le respect de l'ensemble des termes des présentes conditions.</p>

        <h2>2. Description des services</h2>
        <p>Le site Img2bricks est un projet pédagogique permettant aux utilisateurs de :</p>
        <ul>
            <li>Créer un compte utilisateur.</li>
            <li>Télécharger (uploader) des images personnelles.</li>
            <li>Convertir ces images en plans de construction de type mosaïque (style briques de construction).</li>
        </ul>
        <p>Le service est fourni à titre gratuit dans le cadre universitaire.</p>

        <h2>3. Responsabilité de l'utilisateur</h2>
        <p>L'utilisateur est responsable des risques liés à l'utilisation de son identifiant et mot de passe. Le mot de passe de l'utilisateur doit rester secret.</p>
        <p>L'utilisateur s'engage à ne télécharger que des images dont il détient les droits ou qui sont libres de droits. Il est strictement interdit de télécharger :</p>
        <ul>
            <li>Des contenus à caractère violent, pornographique ou haineux.</li>
            <li>Des contenus portant atteinte à la vie privée d'autrui.</li>
            <li>Des contenus protégés par des droits d'auteur sans autorisation.</li>
        </ul>
        <p>L'éditeur se réserve le droit de supprimer sans préavis tout contenu ne respectant pas ces règles ou de suspendre le compte de l'utilisateur concerné.</p>

        <h2>4. Propriété intellectuelle</h2>
        <p>Les images générées (plans de mosaïque) restent la propriété de l'utilisateur pour les images sources qu'il a fournies.</p>
        <p>La structure générale du site, les textes, graphiques et le code source sont la propriété de l'éditeur (cadre pédagogique), sauf mention contraire.</p>

        <h2>5. Responsabilité de l'éditeur</h2>
        <p>Img2bricks étant un projet étudiant, l'éditeur ne saurait être tenu responsable des dysfonctionnements du site, des interruptions de service, ou de la perte de données.</p>
        <p>Les résultats de la conversion (plans de montage) sont fournis à titre indicatif sans garantie de faisabilité technique parfaite avec des briques réelles.</p>

        <h2>6. Évolution des conditions</h2>
        <p>L'éditeur se réserve le droit de modifier les clauses de ces conditions générales d'utilisation à tout moment et sans justification.</p>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn">Retour à l'accueil</a>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>