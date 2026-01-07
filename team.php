<?php
require "config.php";
include 'header.php';
?>

<head>
    <style>
        /* --- RESET & GLOBAL STYLE (Issue de votre charte actuelle) --- */
        * { box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex; 
            flex-direction: column; 
            margin: 0;
            padding-top: 80px;
        }

        /* --- LAYOUT CONTAINER (Adapté pour la grille) --- */
        .team-container {
            flex: 1; 
            display: flex;
            align-items: flex-start;
            justify-content: center; 
            width: 100%;
            padding: 40px 20px; 
        }

        /* Le "Wrapper" blanc, mais plus large pour accueillir la grille */
        .team-wrapper {
            background: #fff;
            width: 1200px; /* Plus large que les mentions légales */
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 15px 20px rgba(0,0,0,0.1);
            padding: 40px;
            position: relative;
        }

        /* --- TYPOGRAPHY --- */
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-header h1 {
            font-size: 30px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .page-header p {
            font-size: 16px;
            color: #666;
            margin: 0;
        }

        /* --- GRID SYSTEM (Inspiré de votre ancien CSS) --- */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            justify-content: center;
        }

        /* --- MEMBER CARD DESIGN --- */
        .team-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            border: 1px solid #f0f0f0; /* Bordure subtile */
        }

        .team-card:hover {
            transform: translateY(-10px); /* Effet de levée */
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.1);
            border-color: #4A90E2; /* Bordure bleue au survol */
        }

        /* Zone image avec couleur de fond dynamique */
        .card-img-wrapper {
            height: 220px;
            background-color: #e9ecef;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Placeholder style si l'image manque (le texte 'LT', 'VC'...) */
        .card-img-wrapper {
            font-size: 3rem;
            font-weight: bold;
            color: rgba(0,0,0,0.1);
        }

        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        /* Zoom sur la photo au survol */
        .team-card:hover .card-img-wrapper img {
            transform: scale(1.05);
        }

        /* --- TEXTE & CONTENU --- */
        .card-body {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .member-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin: 0 0 5px 0;
        }

        .member-role {
            font-size: 0.9rem;
            color: #4A90E2; /* Couleur thème */
            font-weight: 600;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- SOCIAL ICONS --- */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: auto;
        }

        .social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%; /* Ronds au lieu de carrés */
            background-color: #f5f7fa;
            color: #4a5568;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-btn:hover {
            background-color: #4A90E2; /* Bleu thème */
            color: white;
            transform: rotate(360deg); /* Petit effet sympa */
        }

        /* --- COULEURS DE FOND PERSONNALISÉES (Optional: Pastels harmonisés) --- */
        /* J'ai ajusté les couleurs pour qu'elles soient moins vives et plus pro */
        .card-1 .card-img-wrapper { background-color: #E3F2FD; } /* Bleu très clair */
        .card-2 .card-img-wrapper { background-color: #F3E5F5; } /* Violet très clair */
        .card-3 .card-img-wrapper { background-color: #E8F5E9; } /* Vert très clair */
        .card-4 .card-img-wrapper { background-color: #E1F5FE; } /* Cyan très clair */
        .card-5 .card-img-wrapper { background-color: #FFF3E0; } /* Orange très clair */

        /* Bouton retour */
        .back-link {
            text-align: center;
            margin-top: 40px;
        }
        .back-link a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<div class="team-container">
    <div class="team-wrapper">
        
        <div class="page-header">
            <h1>L'Équipe</h1>
            <p>Les créateurs du projet Img2bricks</p>
        </div>

        <div class="team-grid">

            <!-- Valentin -->
            <div class="team-card card-2">
                <div class="card-img-wrapper">
                    <img src="../pfp/val.jpeg" alt="Val" onerror="this.style.display='none'; this.parentElement.innerText='VC'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Valentin Carchon</h3>
                    <div class="member-role">Scrum Master / Dev</div>
                    <div class="social-links">
                        <a href="#" class="social-btn" title="LinkedIn">
                            <svg style="width:20px;height:20px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sébastien -->
            <div class="team-card card-3">
                <div class="card-img-wrapper">
                    <img src="../pfp/seb.jpeg" alt="Seb" onerror="this.style.display='none'; this.parentElement.innerText='SD'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Sébastien Dumur</h3>
                    <div class="member-role">Tech Lead / Dev</div>
                    <div class="social-links">
                        <a href="#" class="social-btn" title="LinkedIn">
                            <svg style="width:20px;height:20px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Thinojan -->
            <div class="team-card card-4">
                <div class="card-img-wrapper">
                    <img src="../pfp/Titi.png" alt="Thino" onerror="this.style.display='none'; this.parentElement.innerText='TP'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Thinojan Pulendran</h3>
                    <div class="member-role">Lead Dev / Dev</div>
                    <div class="social-links">
                        <a href="#" class="social-btn" title="LinkedIn">
                            <svg style="width:20px;height:20px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Gabriel -->
            <div class="team-card card-5">
                <div class="card-img-wrapper">
                    <img src="../pfp/gab.jpeg" alt="Gab" onerror="this.style.display='none'; this.parentElement.innerText='GMV'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Gabriel Martin--Victorine</h3>
                    <div class="member-role">Développeur</div>
                    <div class="social-links">
                        <a href="#" class="social-btn" title="LinkedIn">
                            <svg style="width:20px;height:20px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <div class="back-link">
            <a href="index.php">Retour à l'accueil</a>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>