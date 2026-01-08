<?php
require "config.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirection si non connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: upload.php"); // Ou login.php
    exit;
}

$userId = $_SESSION['user_id'];

// Récupération des commandes
// On joint avec la table 'images' pour récupérer la taille cible (target_size)
$stmt = $db->prepare("
    SELECT c.*, i.target_size 
    FROM commandes c
    JOIN images i ON c.image_id = i.id
    WHERE c.user_id = ?
    ORDER BY c.date_commande DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction utilitaire pour les badges de statut
function getStatusBadge($status) {
    switch(strtolower($status)) { // strtolower pour être sûr
        case 'payée': return '<span class="badge badge-success">Payée - En préparation</span>';
        case 'expédiée': return '<span class="badge badge-info">Expédiée</span>';
        case 'livrée': return '<span class="badge badge-primary">Livrée</span>';
        case 'annulée': return '<span class="badge badge-danger">Annulée</span>';
        default: return '<span class="badge badge-warning">' . htmlspecialchars($status) . '</span>';
    }
}

include "header.php"; 
?>

<style>
    /* Styles spécifiques Dashboard */
    :root { --primary: #2563eb; --text-dark: #1e293b; --bg-light: #f8fafc; }
    body { background-color: var(--bg-light); color: var(--text-dark); }
    .dashboard-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    
    /* En-tête */
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 20px; }
    .header-text h1 { font-size: 2rem; margin-bottom: 5px; }
    .header-text p { color: #64748b; }
    .btn-new-order { background-color: var(--primary); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2); }
    .btn-new-order:hover { background-color: #1d4ed8; transform: translateY(-2px); }

    /* Grille */
    .orders-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }

    /* Carte Commande */
    .order-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; transition: 0.2s; display: flex; flex-direction: column; }
    .order-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
    
    .order-header { padding: 15px 20px; background: #f1f5f9; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; font-weight: 600; color: #475569; }
    
    .order-body { padding: 20px; display: flex; gap: 20px; align-items: center; }
    .order-thumb { width: 80px; height: 80px; border-radius: 8px; object-fit: contain; background: #0f172a; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    
    .order-info { flex: 1; }
    .order-info h3 { margin: 0 0 5px 0; font-size: 1.1rem; }
    .order-info p { margin: 0; color: #64748b; font-size: 0.9rem; }
    .order-price { font-weight: 700; color: var(--primary); font-size: 1.1rem; margin-top: 5px; display: block;}
    
    .order-footer { padding: 15px 20px; border-top: 1px solid #e2e8f0; text-align: right; }
    .btn-details { color: var(--primary); background: #eff6ff; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
    .btn-details:hover { background: #dbeafe; }

    /* Badges */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-info { background: #e0f2fe; color: #075985; }
    .badge-danger { background: #fee2e2; color: #991b1b; }

    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s; }
    .modal-overlay.active { display: flex; opacity: 1; }
    .modal-box { background: white; width: 600px; max-width: 95%; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: translateY(20px); transition: transform 0.3s; }
    .modal-overlay.active .modal-box { transform: translateY(0); }
    .modal-header { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { margin: 0; font-size: 1.2rem; }
    .close-modal { cursor: pointer; font-size: 1.5rem; color: #94a3b8; border:none; background:none;}
    .modal-content { padding: 25px; }
    .detail-group { margin-bottom: 20px; }
    .detail-group h4 { margin: 0 0 10px 0; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 5px;}
    .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem; }
</style>

<div class="dashboard-container">
    
    <div class="dashboard-header">
        <div class="header-text">
            <h1>Mes Commandes</h1>
            <p>Retrouvez l'historique de vos créations Lego.</p>
        </div>
        <a href="upload.php" class="btn-new-order">Nouvelle création</a>
    </div>

    <div class="orders-grid">
        
        <?php if (empty($orders)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 12px; color: #64748b;">
                <p>Vous n'avez pas encore passé de commande.</p>
                <a href="upload.php" style="color: var(--primary); font-weight:bold;">Commencer ma première mosaïque</a>
            </div>
        <?php else: ?>
            
            <?php foreach ($orders as $order): ?>
                <?php 
                    // 1. Gestion de l'image (Chemin Fichier)
                    $imgSrc = 'assets/placeholder.png'; // Fallback
                    if (!empty($order['final_image_path'])) {
                        // On ajoute un timestamp pour éviter le cache navigateur si l'image change
                        $imgSrc = $order['final_image_path'] . '?t=' . time();
                    }
                    
                    // 2. Formatage Date
                    $date = new DateTime($order['date_commande']);
                    $orderRef = 'CMD-' . $date->format('Y') . '-' . str_pad($order['id'], 5, '0', STR_PAD_LEFT);

                    // 3. Données Adresse (Texte Clair)
                    // On utilise htmlspecialchars pour la sécurité XSS
                    $addressDisplay = htmlspecialchars($order['delivery_address']);
                    $phoneDisplay = htmlspecialchars($order['delivery_phone']);
                ?>

                <div class="order-card">
                    <div class="order-header">
                        <span><?= $orderRef ?></span>
                        <?= getStatusBadge($order['statut']) ?>
                    </div>
                    <div class="order-body">
                        <img src="<?= $imgSrc ?>" alt="Aperçu Mosaïque" class="order-thumb">
                        <div class="order-info">
                            <h3><?= ucfirst($order['selected_style']) ?></h3>
                            <p>Taille : <?= $order['target_size'] ?>x<?= $order['target_size'] ?> tenons</p>
                            <span class="order-price"><?= number_format($order['total_price'], 2) ?> €</span>
                        </div>
                    </div>
                    <div class="order-footer">
                        <a href="#" class="btn-details" onclick="openModal('modal-<?= $order['id'] ?>'); return false;">Détails</a>
                    </div>
                </div>

                <div id="modal-<?= $order['id'] ?>" class="modal-overlay">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2>Commande <?= $orderRef ?></h2>
                            <button class="close-modal" onclick="closeModal('modal-<?= $order['id'] ?>')">&times;</button>
                        </div>
                        <div class="modal-content">
                            
                            <div class="detail-group">
                                <h4>Informations Générales</h4>
                                <div class="detail-row"><span>Date</span> <strong><?= $date->format('d/m/Y à H:i') ?></strong></div>
                                <div class="detail-row"><span>Statut</span> <?= getStatusBadge($order['statut']) ?></div>
                                <div class="detail-row"><span>Montant Total</span> <strong><?= number_format($order['total_price'], 2) ?> €</strong></div>
                            </div>

                            <div class="detail-group">
                                <h4>Livraison</h4>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #334155; background: #f8fafc; padding: 10px; border-radius: 6px;">
                                    <?= nl2br($addressDisplay) ?> <br>
                                    <small style="color:#64748b; display:block; margin-top:5px;">
                                        Tél: <?= $phoneDisplay ?>
                                    </small>
                                </p>
                            </div>

                            <div class="detail-group" style="margin-bottom:0;">
                                <a href="generate_facture.php?order_id=<?= $order['id'] ?>" class="btn-new-order" style="display:block; text-align:center; background:#cbd5e1;">Télécharger la Facture</a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden'; 
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
</script>