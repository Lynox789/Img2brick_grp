<?php
require "config.php";
include 'header.php';
?>

<head>
    <style>
        /* --- BASE CSS --- */
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
            width: 1000px; 
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 15px 20px rgba(0,0,0,0.1);
            padding: 40px;
            position: relative;
        }

        h1 {
            font-size: 30px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 16px;
        }

        /* --- GRILLE ÉQUIPE --- */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            justify-content: center;
        }

        .team-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #b3d7ff;
        }

        .card-img-wrapper {
            height: 200px;
            background-color: #e9ecef;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: rgba(0,0,0,0.1); 
        }

        .card-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.3s;
        }

        .card-body {
            padding: 20px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        .member-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
            margin: 0 0 5px 0;
        }

        .member-role {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .social-links {
            margin-top: auto;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        /* --- BOUTONS SOCIAUX MEMBRES (Classe renommée pour éviter conflit footer) --- */
        .member-social-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background-color: #f7fafc;
            color: #4a5568;
            transition: 0.2s;
            text-decoration: none;
        }

        .member-social-btn:hover {
            background-color: #0077b5; /* Bleu LinkedIn officiel */
            color: white;
        }

        /* --- FOOTER BUTTONS (Page actions) --- */
        .footer-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }
        .action-btn:hover { opacity: 0.9; }

        .btn-home {
            background: #4A90E2;
            color: white;
        }

        /* Couleurs spécifiques arrière-plans */
        .card-1 .card-img-wrapper { background-color: #E2E8F0; }
        .card-2 .card-img-wrapper { background-color: #E9D8FD; }
        .card-3 .card-img-wrapper { background-color: #C6F6D5; }
        .card-4 .card-img-wrapper { background-color: #BEE3F8; }
        .card-5 .card-img-wrapper { background-color: #FEEBC8; }

    </style>
</head>

<div class="legal-container">
    <div class="legal-wrapper">
        
        <h1>La Team</h1>
        <p class="subtitle">L'équipe du projet Img2bricks</p>

        <div class="team-grid">

            <div class="team-card card-2">
                <div class="card-img-wrapper">
                    <img src="../pfp/val.jpeg" alt="Val" onerror="this.style.display='none'; this.parentElement.innerText='VC'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Valentin Carchon</h3>
                    <div class="member-role">Scrum Master / Dev</div>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/valentin-carchon-811786338/" target="_blank" class="member-social-btn" title="LinkedIn">
                            <svg style="width:18px;height:18px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="team-card card-3">
                <div class="card-img-wrapper">
                    <img src="../pfp/seb.jpeg" alt="Seb" onerror="this.style.display='none'; this.parentElement.innerText='SD'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Sébastien Dumur</h3>
                    <div class="member-role">Tech Lead / Dev</div>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/s%C3%A9bastien-dumur-ab3011339/" target="_blank" class="member-social-btn" title="LinkedIn">
                            <svg style="width:18px;height:18px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="team-card card-4">
                <div class="card-img-wrapper">
                    <img src="../pfp/Titi.png" alt="Thino" onerror="this.style.display='none'; this.parentElement.innerText='TP'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Thinojan Pulendran</h3>
                    <div class="member-role">Lead Dev / Dev</div>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/thinojan-p/" target="_blank" class="member-social-btn" title="LinkedIn">
                            <svg style="width:18px;height:18px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="team-card card-5">
                <div class="card-img-wrapper">
                    <img src="../pfp/gab.jpeg" alt="Gab" onerror="this.style.display='none'; this.parentElement.innerText='GMV'">
                </div>
                <div class="card-body">
                    <h3 class="member-name">Gabriel Martin--Victorine</h3>
                    <div class="member-role">Développeur</div>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/gabriel-martin-victorine-173016318/" target="_blank" class="member-social-btn" title="LinkedIn">
                            <svg style="width:18px;height:18px" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z" /></svg>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <div class="footer-actions">
            <a href="index.php" class="action-btn btn-home">Retour à l'accueil</a>
            </div>
    </div>
</div>

<?php include "footer.php"; ?>