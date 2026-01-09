<?php
require "config.php";

// Session verification
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['final_proposal_id'])) {
    // If we arrive here without going through results.php, return to the upload
    header("Location: upload.php");
    exit;
}

$propId = $_SESSION['final_proposal_id'];
$userId = $_SESSION['user_id'] ?? null;

// Recovery of the Job state
$stmt = $db->prepare("
    SELECT mp.*, i.filename 
    FROM mosaic_proposals mp 
    JOIN images i ON mp.image_id = i.id 
    WHERE mp.id = ?
");
$stmt->execute([$propId]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    die("Erreur : Commande introuvable.");
}

// Display logic (Wait vs. Result)
$isReady = ($proposal['total_bricks_count'] > 0);
$isError = ($proposal['total_bricks_count'] == -2); // Si tu as géré le cas d'erreur dans Java

// Price calculation (If Java has put it in the database, we take it; otherwise, we calculate it)
$price = $proposal['estimated_cost'] > 0 ? $proposal['estimated_cost'] : ($proposal['total_bricks_count'] * 0.10);

?>
<?php include "header.php"; ?>

<div class="cart-container" style="max-width: 800px; margin: 40px auto; padding: 20px;">

    <?php if ($isError): ?>
        
        <div class="alert error" style="background:#fee2e2; color:#991b1b; padding:20px; border-radius:8px; text-align:center;">
            <h3><?= msg('cart_error_title') ?></h3>
            <p><?= msg('cart_error_desc') ?></p>
            <a href="upload.php" class="btn"><?= msg('btn_restart') ?></a>
        </div>

    <?php elseif (!$isReady): ?>

        <div class="loading-state" style="text-align:center; padding: 50px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <div class="spinner"></div>
            <h2 style="color: var(--accent); margin-top: 20px;"><?= msg('cart_loading_title') ?></h2>
            <p style="color: #64748b;"><?= msg('cart_loading_desc') ?></p>
            
            <script>
                setTimeout(function(){
                    window.location.reload();
                }, 3000); // Reload the page every 3 seconds
            </script>
        </div>

    <?php else: ?>

        <div class="cart-success" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            
            <div style="background: var(--accent); color: white; padding: 20px; text-align: center;">
                <h2 style="margin:0;"><?= msg('cart_success_title') ?></h2>
            </div>

            <div style="padding: 30px; display: flex; flex-wrap: wrap; gap: 30px; align-items: center;">
                
                <div style="flex: 1; min-width: 250px; text-align: center;">
                    <img src="uploads/preview_<?= $propId ?>.png" alt="Mosaïque" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                </div>

                <div style="flex: 1; min-width: 250px;">
                    <ul style="list-style: none; padding: 0; font-size: 1.1rem;">
                        <li style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong><?= msg('lbl_dimensions') ?></strong> <?= $proposal['resolution'] ?> <?= msg('unit_studs') ?>
                        </li>
                        <li style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong><?= msg('lbl_total_bricks') ?></strong> <span style="color: var(--accent); font-weight: bold;"><?= $proposal['total_bricks_count'] ?></span> <?= msg('unit_pieces') ?>
                        </li>
                        <li style="margin-bottom: 15px; font-size: 1.4rem; color: #166534;">
                            <strong><?= msg('lbl_estimated_price') ?></strong> <?= number_format($price, 2) ?> €
                        </li>
                    </ul>

                    <div style="margin-top: 30px; display: grid; gap: 10px;">
                        <a href="checkout.php" class="btn-primary" style="text-align: center; text-decoration: none; padding: 15px; background: #166534; color: white; border-radius: 8px; font-weight: bold;">
                            <?= msg('btn_proceed_checkout') ?>
                        </a>
                        <a href="results.php" style="text-align: center; color: #64748b; text-decoration: none; font-size: 0.9rem;">
                            <?= msg('link_modify_choice') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include "footer.php"; ?>

<style>
    .spinner {
        width: 50px; height: 50px; 
        border: 5px solid #f3f3f3; 
        border-top: 5px solid var(--accent); 
        border-radius: 50%; 
        animation: spin 1s linear infinite; 
        margin: 0 auto;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>