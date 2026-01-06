<?php
require "config.php";

// Security and Data Retrieval checking session existence
if (!isset($_SESSION['temp_image_data']) || !isset($_SESSION['target_size'])) {
    header("Location: upload.php");
    exit;
}

$imageData = $_SESSION['temp_image_data'];
$realSize = intval($_SESSION['target_size']); 

// Modification to make bricks appear larger visually
// We reduce density for display only to create the chunky pixel look
$visualCols = max(16, floor($realSize / 1.5)); 

// Pricing logic based on size
$pricing = [
    32 => 20,
    48 => 35,
    64 => 55,
    96 => 85
];
$price = isset($pricing[$realSize]) ? $pricing[$realSize] : 35;
$pieceCount = $realSize * $realSize;

include "header.php"; 
?>

<style>
    :root {
        --bg-color: #f1f5f9;
        --card-bg: #ffffff;
        --primary: #2563eb;
        --primary-hover: #1d4ed8;
        --text-main: #0f172a;
        --brick-line-color: rgba(255, 255, 255, 0.25);
    }

    body {
        background-color: var(--bg-color);
        color: var(--text-main);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .results-container {
        max-width: 1400px;
        margin: 40px auto 120px auto;
        padding: 0 20px;
    }

    .header-section {
        text-align: center;
        margin-bottom: 50px;
    }
    .header-section h1 { font-size: 2.5rem; margin-bottom: 10px; }
    
    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 40px;
        justify-content: center;
    }

    .option-card {
        background: var(--card-bg);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        border: 4px solid transparent;
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }

    .option-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.2);
    }

    .option-card.selected {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.25);
    }

    .image-preview-wrapper {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1; 
        background-color: #000;
        overflow: hidden;
    }

    .preview-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        image-rendering: pixelated; 
    }

    .brick-wall-overlay {
        position: absolute;
        top: 0; left: 0; bottom: 0; right: 0;
        z-index: 10;
        pointer-events: none;
        --cols: <?= $visualCols ?>;
        --brick-w: calc(100% / var(--cols));
        --brick-h: calc((100% / var(--cols)) * 0.65);
        background-image: 
            linear-gradient(to bottom, var(--brick-line-color) 1px, transparent 1px),
            linear-gradient(to right, var(--brick-line-color) 1px, transparent 1px);
        background-size: var(--brick-w) var(--brick-h);
        box-shadow: inset 0 0 30px rgba(0,0,0,0.6);
    }
    .filter-blue { 
        filter: grayscale(100%) brightness(0.9) sepia(100%) hue-rotate(190deg) saturate(400%) contrast(1.2); 
    }

    .filter-red { 
        filter: grayscale(100%) brightness(0.8) sepia(100%) hue-rotate(-50deg) saturate(500%) contrast(1.3); 
    }

    .filter-bw { 
        filter: grayscale(100%) contrast(1.3) brightness(1.1); 
    }

    .card-details {
        padding: 25px;
        text-align: center;
        background: white;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-top: 1px solid #f1f5f9;
    }

    .style-title {
        font-size: 1.4rem;
        font-weight: 800;
        margin-bottom: 5px;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .style-desc { color: #64748b; font-size: 1rem; margin-bottom: 15px; }

    .price-badge {
        font-weight: 800;
        font-size: 1.5rem;
        color: var(--primary);
    }
    .actions-bar {
        position: fixed;
        bottom: 0; left: 0; width: 100%;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(10px);
        padding: 20px;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
        display: flex; justify-content: center; gap: 20px;
        z-index: 100; border-top: 1px solid #e2e8f0;
    }

    .btn-submit {
        background: var(--primary); color: white;
        padding: 14px 50px; border-radius: 50px;
        font-weight: 700; font-size: 1.2rem; border: none;
        cursor: pointer; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        transition: 0.2s;
    }
    .btn-submit:hover { background: var(--primary-hover); transform: scale(1.02); }
    
    .btn-back {
        padding: 14px 30px; border-radius: 50px;
        border: 2px solid #e2e8f0; color: #64748b;
        font-weight: 600; text-decoration: none;
        display: flex; align-items: center;
    }
    .btn-back:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }

</style>

<div class="results-container">
    
    <div class="header-section">
        <h1><?= msg('results_title') ?></h1>
        <p><?= msg('results_kit_complete') ?> <strong><?= $realSize ?>x<?= $realSize ?></strong> <?= msg('results_studs') ?> (<?= number_format($pieceCount, 0, ',', ' ') ?> <?= msg('results_pieces') ?>)</p>
    </div>

    <form action="cart.php" method="POST" id="selectionForm">
        <input type="hidden" name="selected_size" value="<?= $realSize ?>">
        <input type="hidden" name="selected_price" value="<?= $price ?>">
        
        <div class="options-grid">

            <label class="option-card selected" onclick="selectCard(this)">
                <input type="radio" name="selected_style" value="blue" checked style="display:none">
                
                <div class="image-preview-wrapper">
                    <img src="<?= $imageData ?>" class="preview-img filter-blue" alt="Rendu Bleu">
                    <div class="brick-wall-overlay"></div>
                </div>

                <div class="card-details">
                    <div class="style-title" style="color: #2563eb;"><?= msg('style_blue_title') ?></div>
                    <div class="style-desc"><?= msg('style_blue_desc') ?></div>
                    <div><span class="price-badge"><?= $price ?> €</span></div>
                </div>
            </label>

            <label class="option-card" onclick="selectCard(this)">
                <input type="radio" name="selected_style" value="red" style="display:none">
                
                <div class="image-preview-wrapper">
                    <img src="<?= $imageData ?>" class="preview-img filter-red" alt="Rendu Rouge">
                    <div class="brick-wall-overlay"></div>
                </div>

                <div class="card-details">
                    <div class="style-title" style="color: #dc2626;"><?= msg('style_red_title') ?></div>
                    <div class="style-desc"><?= msg('style_red_desc') ?></div>
                    <div><span class="price-badge"><?= $price ?> €</span></div>
                </div>
            </label>

            <label class="option-card" onclick="selectCard(this)">
                <input type="radio" name="selected_style" value="bw" style="display:none">
                
                <div class="image-preview-wrapper">
                    <img src="<?= $imageData ?>" class="preview-img filter-bw" alt="Rendu N&B">
                    <div class="brick-wall-overlay"></div>
                </div>

                <div class="card-details">
                    <div class="style-title" style="color: #334155;"><?= msg('style_bw_title') ?></div>
                    <div class="style-desc"><?= msg('style_bw_desc') ?></div>
                    <div><span class="price-badge"><?= $price ?> €</span></div>
                </div>
            </label>

        </div>

        <div class="actions-bar">
            <a href="crop.php" class="btn-back"><?= msg('btn_modify') ?></a>
            <button type="submit" class="btn-submit"><?= msg('btn_order') ?> (<?= $price ?> €)</button>
        </div>
    </form>
</div>

<script>
    function selectCard(card) {
        document.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
    }
</script>

<?php include "footer.php"; ?>