<?php
require "config.php";
require_once "classes/Security.php"; // Required to decrypt address

// Connection check
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: inscription.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Retrieve orders with join to get target_size
// Sort by descending date (newest first)
$stmt = $db->prepare("
    SELECT c.*, i.target_size 
    FROM commandes c
    JOIN images i ON c.image_id = i.id
    WHERE c.user_id = ?
    ORDER BY c.date_commande DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Utility function to format status
function getStatusBadge($status) {
    switch($status) {
        case 'payée': return '<span class="badge badge-success">Payée - En préparation</span>';
        case 'expédiée': return '<span class="badge badge-info">Expédiée</span>';
        case 'livrée': return '<span class="badge badge-primary">Livrée</span>';
        case 'annulée': return '<span class="badge badge-danger">Annulée</span>';
        default: return '<span class="badge badge-warning">En attente</span>';
    }
}

include "header.php"; 
?>

<style>
    /* Specific styles for order dashboard */
    :root {
        --primary: #2563eb;
        --text-dark: #1e293b;
        --bg-light: #f8fafc;
    }

    body { background-color: var(--bg-light); color: var(--text-dark); }

    .dashboard-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .header-text h1 { font-size: 2rem; margin-bottom: 5px; }
    .header-text p { color: #64748b; }

    .btn-new-order {
        background-color: var(--primary);
        color: white;
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: 0.2s;
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    }
    .btn-new-order:hover { background-color: #1d4ed8; transform: translateY(-2px); }

    /* Order grid */
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 30px;
    }

    /* Order Card */
    .order-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: 0.2s;
        display: flex;
        flex-direction: column;
    }

    .order-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .order-header {
        padding: 15px 20px;
        background: #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
    }

    .order-body {
        padding: 20px;
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .order-thumb {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        background: #eee;
        image-rendering: pixelated; /* To keep the pixel art effect sharp */
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .order-info { flex: 1; }
    .order-info h3 { margin: 0 0 5px 0; font-size: 1.1rem; }
    .order-info p { margin: 0; color: #64748b; font-size: 0.9rem; }
    .order-price { font-weight: 700; color: var(--primary); font-size: 1.1rem; margin-top: 5px; display: block;}

    .order-footer {
        padding: 15px 20px;
        border-top: 1px solid #e2e8f0;
        text-align: right;
    }

    .btn-details {
        color: var(--primary);
        background: #eff6ff;
        padding: 8px 20px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: 0.2s;
    }
    .btn-details:hover { background: #dbeafe; }

    /* Badges */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-info { background: #e0f2fe; color: #075985; }
    .badge-danger { background: #fee2e2; color: #991b1b; }

    /* Modal */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 1000;
        display: none; justify-content: center; align-items: center;
        opacity: 0; transition: opacity 0.3s;
    }
    .modal-overlay.active { display: flex; opacity: 1; }

    .modal-box {
        background: white; width: 600px; max-width: 95%;
        border-radius: 12px; overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        transform: translateY(20px); transition: transform 0.3s;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }

    .modal-header {
        background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-header h2 { margin: 0; font-size: 1.2rem; }
    .close-modal { cursor: pointer; font-size: 1.5rem; color: #94a3b8; border:none; background:none;}

    .modal-content { padding: 25px; }
    
    .detail-group { margin-bottom: 20px; }
    .detail-group h4 { margin: 0 0 10px 0; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 5px;}
    .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem; }
    
    .downloads-section { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
    .btn-download {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;
        text-decoration: none; color: #334155; font-size: 0.9rem; font-weight: 500;
        transition: 0.2s;
    }
    .btn-download:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .btn-download.disabled { opacity: 0.5; cursor: not-allowed; }

    .support-link {
        display: block; text-align: center; margin-top: 20px; color: #94a3b8; font-size: 0.9rem; text-decoration: none;
    }
    .support-link:hover { color: var(--primary); text-decoration: underline; }

</style>

<div class="dashboard-container">
    
    <div class="dashboard-header">
        <div class="header-text">
            <h1><?= msg('page_title') ?></h1>
            <p><?= msg('page_subtitle') ?></p>
        </div>
        <a href="upload.php" class="btn-new-order"><?= msg('btn_new') ?></a>
    </div>

    <div class="orders-grid">
        
        <?php if (empty($orders)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: white; border-radius: 12px; color: #64748b;">
                <p><?= msg('empty_msg') ?></p>
                <a href="upload.php" style="color: var(--primary);"><?= msg('empty_link') ?></a>
            </div>
        <?php else: ?>
            
            <?php foreach ($orders as $order): ?>
                <?php 
                    // Convert BLOB to base64 for display
                    $imgData = base64_encode($order['final_image_blob']);
                    $imgSrc = 'data:image/png;base64,' . $imgData;
                    
                    // Decryption of sensitive data
                    $addressDecrypted = Security::decrypt($order['delivery_address']);
                    $phoneDecrypted = Security::decrypt($order['delivery_phone']);
                    
                    // Date formatting
                    $date = new DateTime($order['date_commande']);
                    $orderRef = 'CMD-' . $date->format('Y') . '-' . str_pad($order['id'], 5, '0', STR_PAD_LEFT);
                ?>

                <div class="order-card">
                    <div class="order-header">
                        <span><?= $orderRef ?></span>
                        <?= getStatusBadge($order['statut']) ?>
                    </div>
                    <div class="order-body">
                        <img src="<?= $imgSrc ?>" alt="Mosaïque" class="order-thumb">
                        <div class="order-info">
                            <h3><?= msg('label_style') ?> <?= ucfirst($order['selected_style']) ?></h3>
                            <p><?= msg('label_size') ?> : <?= $order['target_size'] ?>x<?= $order['target_size'] ?> <?= msg('label_tenons') ?></p>
                            <span class="order-price"><?= $order['total_price'] ?> €</span>
                        </div>
                    </div>
                    <div class="order-footer">
                        <a href="#" class="btn-details" onclick="openModal('modal-<?= $order['id'] ?>'); return false;"><?= msg('btn_details') ?></a>
                    </div>
                </div>

                <div id="modal-<?= $order['id'] ?>" class="modal-overlay">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2><?= msg('modal_title') ?> <?= $orderRef ?></h2>
                            <button class="close-modal" onclick="closeModal('modal-<?= $order['id'] ?>')">&times;</button>
                        </div>
                        <div class="modal-content">
                            
                            <div class="detail-group">
                                <h4><?= msg('group_general') ?></h4>
                                <div class="detail-row"><span><?= msg('label_date') ?></span> <strong><?= $date->format('d/m/Y à H:i') ?></strong></div>
                                <div class="detail-row"><span><?= msg('label_status') ?></span> <?= getStatusBadge($order['statut']) ?></div>
                                <div class="detail-row"><span><?= msg('label_amount') ?></span> <strong><?= $order['total_price'] ?> €</strong></div>
                            </div>

                            <div class="detail-group">
                                <h4><?= msg('group_delivery') ?></h4>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #334155;">
                                    <?= htmlspecialchars($addressDecrypted) ?><br>
                                    <?php if(isset($order['delivery_zip'])) echo htmlspecialchars($order['delivery_zip']) . " "; ?>
                                    <?php if(isset($order['delivery_city'])) echo htmlspecialchars($order['delivery_city']); ?><br>
                                    <?= isset($order['delivery_country']) ? htmlspecialchars($order['delivery_country']) : '' ?><br>
                                    <small style="color:#64748b"><?= msg('label_phone') ?> <?= htmlspecialchars($phoneDecrypted) ?></small>
                                </p>
                            </div>

                            <div class="detail-group">
                                <h4>Documents</h4>
                                <div class="downloads-section">
                                    <?php if($order['statut'] === 'payée' || $order['statut'] === 'livrée'): ?>
                                        <a href="generate_facture.php?order_id=<?= $order['id'] ?>" target="_blank" class="btn-download">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                            </svg>
                                            Télécharger la Facture
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-download disabled" disabled>
                                            Facture non disponible
                                        </button>
                                    <?php endif; ?>
                                </div> 
                            </div>
                            <a href="#" class="support-link" onclick="alert('Test d\'envoi de mail, mock (n\'enverras rien'); return false;"><?= msg('link_support') ?></a>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>

<script>
    // Simple modal management
    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Close if clicked outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
</script>