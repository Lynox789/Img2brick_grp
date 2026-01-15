<?php
require "config.php";

// 1. Security Session
if (!isset($_SESSION['temp_image_data'])) {
    header("Location: upload.php");
    exit;
}

$userId = null;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $tempId = intval($_SESSION['user_id']);
    if ($tempId > 0) $userId = $tempId;
}

$imageSource = $_SESSION['temp_image_data'];

// 2. Processing of the Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Basic CSRF check via presence of files
    if (isset($_FILES['croppedImage']) && isset($_POST['size'])) {

        try {
            // Size validation (Whitelist)
            $allowedSizes = [32, 48, 64, 96];
            $targetSize = intval($_POST['size']);

            if (!in_array($targetSize, $allowedSizes)) {
                throw new Exception("Taille non autorisée.");
            }

            // Image validation (MIME Type strict)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['croppedImage']['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($mime, $allowedMimes)) {
                throw new Exception("Format de fichier invalide ou corrompu.");
            }

            // Physical backup
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // Random and secure filename
            $filename = "crop_" . ($userId ?? "guest") . "_" . bin2hex(random_bytes(8)) . ".jpg";
            $targetFilePath = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['croppedImage']['tmp_name'], $targetFilePath)) {
                throw new Exception("Erreur lors de la sauvegarde sur le disque.");
            }

            // Retrieve actual dimensions
            list($width, $height) = getimagesize($targetFilePath);
            $weight = filesize($targetFilePath);

            // Insertion BDD - Table Images
            $stmtImg = $db->prepare("INSERT INTO images (filename, user_id, extension, poids, largeur, hauteur, target_size) VALUES (?, ?, 'jpg', ?, ?, ?, ?)");
            $stmtImg->execute([$filename, $userId, $weight, $width, $height, $targetSize]);
            $imgId = $db->lastInsertId();

            // Creation of the 3 PREVIEW requests for Java
            // We prepare the 3 variants for the user to choose VISUALLY
            $stmtProp = $db->prepare("INSERT INTO mosaic_proposals (image_id, strategy, resolution, total_bricks_count, estimated_cost, is_stock_sufficient) VALUES (?, ?, ?, 0, 0.00, 1)");
            $res = $targetSize . "x" . $targetSize;

            // Resampling variants
            $stmtProp->execute([$imgId, 'PREVIEW_BICUBIC', $res]);
            $stmtProp->execute([$imgId, 'PREVIEW_BILINEAR', $res]);
            $stmtProp->execute([$imgId, 'PREVIEW_NEAREST', $res]);

            // Save in session for the next page
            $_SESSION['current_image_id'] = $imgId;

            echo "OK";
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            error_log($e->getMessage());
            echo "Erreur serveur lors du traitement.";
            exit;
        }
    } else {
        http_response_code(400);
        echo "Données manquantes.";
        exit;
    }
}
?>
<?php include "header.php"; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<style>
    .main-wrapper {
        display: flex;
        /* On PC: horizontal alignment */
        flex-direction: row; 
        min-height: calc(100vh - 80px);
        width: 100%;
        position: relative;
    }

    .image-stage {
        flex: 1;
        background: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        /* On PC: takes all the available height */
        height: auto; 
        min-height: 500px; 
    }

    #imageElement {
        display: block;
        max-width: 100%;
    }

    .sidebar {
        width: 320px; 
        background: white;
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        border-left: 1px solid #e2e8f0;
        z-index: 10;
    }

    .btn-primary {
        width: 100%;
        padding: 14px;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s;
    }
    .btn-primary:hover {
        opacity: 0.9;
    }

    .info-box {
        background: #f1f5f9;
        padding: 15px;
        border-radius: 8px;
        font-size: 0.9rem;
        color: #475569;
        line-height: 1.5;
    }

    .control-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text);
    }

    select {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 1rem;
        background-color: white;
    }

    /* Loader */
    #loading {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.95);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }

    .spinner {
        width: 40px; height: 40px;
        border: 4px solid #e2e8f0;
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Responsive (Mobile & Tablette < 900px) */
    @media (max-width: 900px) {
        .main-wrapper {
            flex-direction: column; 
            min-height: auto;
        }

        .image-stage {
            width: 100%;
            height: 60vh; 
            min-height: 300px;
        }

        .sidebar {
            width: 100%; 
            border-left: none;
            border-top: 1px solid #e2e8f0; 
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            padding: 20px;
        }

        #imageElement {
            max-height: 60vh;
        }
    }
</style>

<div id="loading">
    <div class="spinner"></div>
    <p><?= msg('loading_text') ?></p>
</div>

<div class="main-wrapper">
    <div class="image-stage">
        <img id="imageElement" src="<?= $imageSource ?>" alt="Image à recadrer">
    </div>
    <div class="sidebar">
        <h2><?= msg('crop_sidebar_title') ?></h2>
        <div class="info-box"><?= msg('crop_instruction') ?></div>
        <div class="control-group">
            <label><?= msg('label_size') ?></label>
            <select id="sizeSelectElement">
                <option value="32"><?= msg('opt_size_small') ?></option>
                <option value="48" selected><?= msg('opt_size_medium') ?></option>
                <option value="64"><?= msg('opt_size_large') ?></option>
                <option value="96"><?= msg('opt_size_xlarge') ?></option>
            </select>
        </div>
        <div class="actions">
            <button id="btnGenerate" class="btn-primary"><?= msg('btn_generate') ?></button>
            <a href="upload.php" style="display:block; text-align:center; margin-top:15px; color:#64748b;"><?= msg('link_cancel') ?></a>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
    const imageElement = document.getElementById('imageElement');
    const sizeSelectElement = document.getElementById('sizeSelectElement');
    const generateButton = document.getElementById('btnGenerate');
    const loadingScreen = document.getElementById('loading');

    const cropperInstance = new Cropper(imageElement, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 0.8
    });

    generateButton.addEventListener('click', () => {
        loadingScreen.style.display = 'flex';
        cropperInstance.getCroppedCanvas().toBlob((blob) => {
            const formData = new FormData();
            formData.append('croppedImage', blob, 'cropped.jpg');
            formData.append('size', sizeSelectElement.value);

            fetch('crop.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.text())
                .then(res => {
                    if (res.trim() === 'OK') window.location.href = 'results.php';
                    else {
                        alert("Erreur: " + res);
                        loadingScreen.style.display = 'none';
                    }
                })
                .catch(() => {
                    alert("Erreur connexion");
                    loadingScreen.style.display = 'none';
                });
        }, 'image/jpeg');
    });
</script>