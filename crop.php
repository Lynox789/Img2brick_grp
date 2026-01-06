<?php
require "config.php";

// Initialize variables 
$imageId = 0;
$userId = 0;
$imageData = null;
$username = "";
$imagePath = "";
$targetSize = 0;
$newWidth = 0;
$newHeight = 0;
$uploadDirectory = "";
$targetFilePath = "";

//Security with image in $_SESSION 
if (!isset($_SESSION['temp_image_data'])) {
    header("Location: upload.php");
    exit;
}

//get the source image in base64
$imageSource = $_SESSION['temp_image_data'];


//Crop treatment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['croppedImage']) && isset($_POST['size'])) {
        
        $croppedContent = file_get_contents($_FILES['croppedImage']['tmp_name']);
        
        if ($croppedContent === false) {
             http_response_code(400);
             echo "Erreur lors de la lecture du fichier envoyé.";
             exit;
        }

        $newBase64 = 'data:image/jpeg;base64,' . base64_encode($croppedContent);
        $_SESSION['temp_image_data'] = $newBase64;
        $_SESSION['target_size'] = intval($_POST['size']);
        Logger::log($db, 'CROP_ACTION', "Taille choisie: " . $_SESSION['target_size']);
    
        echo "OK";
        exit;

    } else {
        http_response_code(400);
        echo msg('error_missing_data');
        exit;
    }
}
?>
<?php include "header.php"; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<style>
    .main-wrapper { 
        display: flex; 
        height: calc(100vh - 80px);
        overflow: hidden;
        margin-top: 0;
    }

    .image-stage { 
        flex: 1; 
        background: #0f172a;
        display: flex; 
        align-items: center; 
        justify-content: center; 
        overflow: hidden; 
        position: relative; 
    }

    #imageElement { 
        max-width: 100%; 
        max-height: 85vh; 
        display: block; 
    }

    .sidebar { 
        width: 320px; 
        background: white; 
        padding: 25px; 
        box-shadow: -4px 0 15px rgba(0,0,0,0.05); 
        display: flex; 
        flex-direction: column; 
        gap: 20px; 
        z-index: 10;
        overflow-y: auto;
        border-left: 1px solid #e2e8f0;
    }

    h2 { margin-top: 0; font-size: 1.2rem; color: var(--text); }

    .control-group { margin-bottom: 15px; }

    label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 0.9rem; color: #475569; }

    select { 
        width: 100%; padding: 10px; border-radius: 8px; 
        border: 1px solid #cbd5e1; font-size: 1rem; 
        background-color: #f8fafc; color: var(--text); cursor: pointer;
    }

    .info-box { 
        background: #eff6ff; border-left: 4px solid var(--accent); 
        padding: 12px; font-size: 0.85rem; color: #1e40af; 
        border-radius: 4px; line-height: 1.4;
    }

    .actions { margin-top: auto; display: flex; flex-direction: column; gap: 10px; }

    .btn-primary { 
        width: 100%; padding: 14px; background: var(--accent); color: white; 
        border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; 
        cursor: pointer; transition: background 0.2s; 
    }
    .btn-primary:hover { background: var(--accent-hover); }

    #loading { 
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(255,255,255,0.9); z-index: 2000;
        align-items: center; justify-content: center; flex-direction: column; 
    }

    .spinner { 
        width: 40px; height: 40px; border: 4px solid #e2e8f0; 
        border-top-color: var(--accent); border-radius: 50%; 
        animation: spin 1s linear infinite; margin-bottom: 15px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 768px) {
        .main-wrapper { flex-direction: column; height: auto; }
        .image-stage { height: 50vh; }
        .sidebar { width: 100%; height: auto; border-top: 1px solid #e2e8f0; }
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
        
        <div class="info-box">
            <?= msg('crop_instruction') ?>
        </div>

        <div class="control-group">
            <label for="sizeSelectElement"><?= msg('label_size') ?></label>
            <select id="sizeSelectElement">
                <option value="32"><?= msg('opt_size_small') ?></option>
                <option value="48" selected><?= msg('opt_size_medium') ?></option>
                <option value="64"><?= msg('opt_size_large') ?></option>
                <option value="96"><?= msg('opt_size_xlarge') ?></option>
            </select>
        </div>

        <div class="control-group">
            <label><?= msg('label_format') ?></label>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="setAspectRatio(1)" style="flex:1; padding:8px; cursor:pointer; border:1px solid #ddd; background:white; border-radius:6px;"><?= msg('btn_square') ?></button>
                <button type="button" onclick="setAspectRatio(NaN)" style="flex:1; padding:8px; cursor:pointer; border:1px solid #ddd; background:white; border-radius:6px;"><?= msg('btn_free') ?></button>
            </div>
        </div>

        <div class="actions">
            <button id="btnGenerate" class="btn-primary"><?= msg('btn_generate') ?></button>
            <a href="upload.php" style="display:block; text-align:center; margin-top:15px; color:#64748b; text-decoration:none; font-size:0.9rem;"><?= msg('link_cancel') ?></a>
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
    let cropperInstance = null;

    cropperInstance = new Cropper(imageElement, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.8,
        guides: true,
        background: false,
    });

    window.setAspectRatio = function(ratio) {
        if (cropperInstance) cropperInstance.setAspectRatio(ratio);
    };

    generateButton.addEventListener('click', () => {
        if (loadingScreen) loadingScreen.style.display = 'flex';

        const canvas = cropperInstance.getCroppedCanvas();

        if (canvas) {
            canvas.toBlob((blob) => {
                if (!blob) {
                    alert("<?= msg('js_error_crop') ?>");
                    loadingScreen.style.display = 'none';
                    return;
                }

                const formData = new FormData();
                formData.append('croppedImage', blob, 'cropped.jpg');
                formData.append('size', sizeSelectElement.value);

                fetch('crop.php?id=<?= $imageId ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(result => {
                    if (result === 'OK') {
                        window.location.href = 'results.php';
                    } else {
                        alert("<?= msg('js_error_server') ?>" + result);
                        loadingScreen.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("<?= msg('js_error_connection') ?>");
                    loadingScreen.style.display = 'none';
                });

            }, 'image/jpeg', 0.95);
        } else {
            alert("<?= msg('js_error_canvas') ?>");
            loadingScreen.style.display = 'none';
        }
    });
</script>