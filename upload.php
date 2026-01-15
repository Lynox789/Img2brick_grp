<?php
require "config.php";

// Configuration for file constraints
$uploadedFile = null;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$maxFileSize = 2 * 1024 * 1024; // 2MB
$minResolution = 512;
$errorMessage = "";

// Handle the file upload via POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    
    $uploadedFile = $_FILES['image'];
    $fileName = $uploadedFile['name'];
    $fileTmpPath = $uploadedFile['tmp_name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate file extension against allowed list
    if (!in_array($fileExtension, $allowedExtensions)) {
        $errorMessage = msg('error_format');
    } else {
        
        // Validate file size
        if ($uploadedFile['size'] > $maxFileSize) {
            $errorMessage = msg('error_size');
        } else {
            
            // Validate image dimensions using getimagesize
            $dimensionInfo = getimagesize($uploadedFile['tmp_name']);
            
            if ($dimensionInfo) {
                list($imageWidth, $imageHeight) = $dimensionInfo;
            } else {
                $imageWidth = 0;
                $imageHeight = 0;
            }

            // Check if resolution meets minimum requirements
            if ($imageWidth < $minResolution || $imageHeight < $minResolution) {
                $errorMessage = msg('error_resolution');
            } else {

                // Fetch username to organize uploads into user-specific folders
                $userStatement = $db->prepare("SELECT username FROM users WHERE id = ?");
                $userStatement->execute([$userId]);
                $currentUsername = $userStatement->fetchColumn();
                
                $targetDirectory = "images/" . $currentUsername . "/";
                
                // Create directory if it does not exist
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0777, true);
                }

                // Read file content and prepare Base64 string
                $binaryData = file_get_contents($fileTmpPath);
                $mimeType = $dimensionInfo['mime'];
                $base64Data = 'data:' . $mimeType . ';base64,' . base64_encode($binaryData);

                // Store image data in Session to pass it to the cropping page
                $_SESSION['temp_image_data'] = $base64Data;
                $_SESSION['temp_image_name'] = $fileName;
                $_SESSION['temp_image_ext']  = $fileExtension;
                
                Logger::log($db, 'UPLOAD_INIT', "Fichier: $fileName");
                header("Location: crop.php");
                exit;
                
            }
        }
    }
}
?>

<?php include "header.php"; ?>
<head>
    <meta charset="utf-8" />
    <title>img2brick - Upload</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif; 
    
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            
            min-height: 100vh;            
            background-attachment: fixed; 
            
            margin: 0;
            padding-top: 80px; 
        }
        .uploader { 
            display: flex;
            gap: 100px;
            margin: 40px auto; 
        }
    
        .drop-btn {
            display: flex; 
            flex-direction: column; 
            align-items: center;    
            justify-content: center; 
            text-align: center;      
            gap: 15px; 
            padding: 100px 20px;      
            border-radius: 12px; 
            border: 2px dashed #cbd5e1; 
            background: white;
            cursor: pointer; 
            transition: all 0.2s; 
            user-select: none;
            min-height: 300px;       
        }
        .drop-btn:hover, .drop-btn.dragover {
            border-color: var(--accent);
            background-color: #eff6ff;
        }
        
        .upload-icon {
            width: 48px;
            height: 48px;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .drop-btn:hover .upload-icon {
            color: var(--accent);
        }
        
        .content { 
            flex: 1; 
        }
        .title { 
            font-weight: 600; 
            font-size: 16px; 
            color: var(--text); 
        }
        .subtitle { 
            font-size: 13px; 
            color: #64748b; 
            margin-top: 4px; 
        }
        
        button[type="submit"] {
            background: var(--accent); 
            color: white; 
            padding: 12px 20px;
            border-radius: 8px; 
            border: 0; 
            cursor: pointer; 
            float: right;
            margin-top: 15px; 
            font-weight: 500; 
            font-size: 14px;
            transition: background 0.2s;
        }
        button[type="submit"]:hover { 
            background: var(--accent-hover); 
        }

        .message-box {
            margin-top: 15px;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
        }
        .message-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .message-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .hero-section {
            background: linear-gradient(180deg, #5aa7f5 0%, #e2e8f0 100%);
            padding: 60px 20px;
            text-align: center;
            border-bottom: 1px solid #cbd5e1;
            border-radius: 50px;
            max-width: 600px;
        }

        .hero-title {
            font-size: 2.5rem;
            color: #1e293b;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #64748b;
            margin-bottom: 50px;
        }

        /* Example container */
        .comparison-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            max-width: 900px;
            margin: 0 auto;
        }

        .img-wrapper {
            position: relative;
            flex: 1;
            background: white;
            padding: 10px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: rotate(-2deg); 
            transition: transform 0.3s;
        }
        .img-wrapper:last-child {
            transform: rotate(2deg); 
        }
        .img-wrapper:hover {
            transform: scale(1.05) rotate(0deg); 
            z-index: 10;
        }

        .demo-img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            display: block;
        }
        
        .pixelated {
            image-rendering: pixelated;
        }

        .img-label {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #334155;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* the arrow */
        .arrow-box {
            color: var(--accent); 
            width: 50px;
            height: 50px;
            animation: bounceX 1.5s infinite;
        }

        @keyframes bounceX {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(10px); }
        }

        /* The Button */
        .btn-cta {
            display: inline-block;
            background: var(--accent); 
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* Responsive mobile */
        @media (max-width: 768px) {
            .comparison-container {
                flex-direction: column; 
                gap: 20px;
            }

            .arrow-box {
                transform: rotate(90deg); /* The arrow points down */
                animation: bounceY 1.5s infinite; /* Vertical animation */
            }
            
            @keyframes bounceY {
                0%, 100% { transform: rotate(90deg) translateY(0); }
                50% { transform: rotate(90deg) translateX(-10px); }
            }

            .hero-title { font-size: 1.8rem; }
            .hero-section { 
                padding: 40px 15px;
                max-width: 400px;
            }

            .img-wrapper {
                position: relative;
                flex: 1;
                background: white;
                padding: 10px;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                transform: rotate(-2deg); 
                transition: transform 0.3s;
                max-width: 200px;
        }
        }
        @media (max-width: 1140px) {
            .uploader {
                flex-direction: column;
                gap: 40px;
            }
        }
    </style>
</head>
<div class="uploader">

    <section class="hero-section">
        <div class="hero-content">
            
            <h1 class="hero-title"><?= msg('home_hero_title') ?></h1>
            <p class="hero-subtitle"><?= msg('home_hero_subtitle') ?></p>
            
            <div class="comparison-container">
                
                <div class="img-wrapper">
                    <span class="img-label"><?= msg('label_original') ?></span>
                    <img src="demo_original.png" alt="Original" class="demo-img"> 
                </div>

                <div class="arrow-box">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </div>

                <div class="img-wrapper">
                    <span class="img-label" style="background:var(--accent);"><?= msg('label_mosaic') ?></span>
                    <img src="demo_lego.png" alt="Lego" class="demo-img pixelated">
                </div>

            </div>

        </div>
    </section>
    
    <?php if ($errorMessage): ?>
        <div class="message-box message-error" style="display:block;">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>
    
    <section>
    <h2><?= msg('upload_step_title') ?></h2>
    <form id="uploadFormElement" method="post" enctype="multipart/form-data">
        <label class="drop-btn" id="dropAreaElement">
            
            <svg class="upload-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <div class="content">
                <div class="title"><?= msg('upload_drop_title') ?></div>
                <div class="subtitle"><?= msg('upload_drop_subtitle') ?></div>
            </div>
            <input id="fileInputElement" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="display:none" required>
        </label>

        <div id="jsMessageContainer" class="message-box"></div>

        <div id="previewAreaElement" style="display:none; margin-top:15px; text-align:center;">
            <img id="previewImageElement" style="max-height:150px; border-radius:8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div id="fileNameElement" style="margin-top:5px; font-size:0.9em; color:#64748b;"></div>
        </div>

        <button id="submitBtn" type="submit"><?= msg('btn_continue') ?></button>
    </form>
    </section>
    
</div>

    <?php include "footer.php"; ?>

<script>
    const dropAreaElement = document.getElementById('dropAreaElement');
    const fileInputElement = document.getElementById('fileInputElement');
    const previewAreaElement = document.getElementById('previewAreaElement');
    const previewImageElement = document.getElementById('previewImageElement');
    const fileNameElement = document.getElementById('fileNameElement');
    const messageContainer = document.getElementById('jsMessageContainer');
    const submitBtn = document.getElementById('submitBtn');

    // Constants must match PHP configuration
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

    // fix to message 
    // We define the messages here in a secure way to avoid breaking the JS
    <?php $lang = $_SESSION['lang'] ?? 'en'; // Default value if not defined ?>
    
    const messages = {
        format: "<?= ($lang == 'fr') ? 'Format non supporté (JPG, PNG, WEBP uniquement).' : 'Unsupported format (JPG, PNG, WEBP only).' ?>",
        size: "<?= ($lang == 'fr') ? 'Le fichier est trop lourd (Max 2Mo).' : 'File is too large (Max 2MB).' ?>",
        success: "<?= ($lang == 'fr') ? 'Image prête ! Cliquez sur Continuer.' : 'Image ready! Click Continue.' ?>"
    };

    // Trigger removed to prevent double click (label handles it natively)
    // dropAreaElement.addEventListener('click', () => fileInputElement.click());
    
    // Handle file selection from standard input
    fileInputElement.addEventListener('change', (event) => handleFileSelection(event.target.files[0]));

    // Visual feedback for Drag events
    ['dragenter','dragover'].forEach(eventType => {
        dropAreaElement.addEventListener(eventType, event => { 
            event.preventDefault(); 
            dropAreaElement.classList.add('dragover'); 
        });
    });

    ['dragleave','drop'].forEach(eventType => {
        dropAreaElement.addEventListener(eventType, event => { 
            event.preventDefault(); 
            dropAreaElement.classList.remove('dragover'); 
        });
    });

    // Handle dropped files
    dropAreaElement.addEventListener('drop', event => {
        event.preventDefault();
        const droppedFile = event.dataTransfer.files[0];
        if (droppedFile) {
            fileInputElement.files = event.dataTransfer.files; 
            handleFileSelection(droppedFile);
        }
    });

    // Main validation and preview logic
    function handleFileSelection(file) {
        // Reset interface state
        messageContainer.style.display = 'none';
        messageContainer.className = 'message-box'; 
        previewAreaElement.style.display = 'none';
        submitBtn.style.display = 'none';

        if (!file) return;

        // Check file type (Client-side)
        if (!ALLOWED_TYPES.includes(file.type)) {
            showMessage(messages.format, 'error');
            return;
        }

        // Check file size (Client-side)
        if (file.size > MAX_SIZE) {
            showMessage(messages.size, 'error');
            return;
        }

        // Generate preview URL and show success state
        previewImageElement.src = URL.createObjectURL(file);
        fileNameElement.textContent = file.name;
        previewAreaElement.style.display = 'block';
        
        // Utilisation du message sécurisé défini plus haut
        showMessage(messages.success, 'success');
        submitBtn.style.display = 'block';
    }

    // Helper to toggle message visibility and styling
    function showMessage(text, type) {
        messageContainer.textContent = text;
        if (type === 'error') {
            messageContainer.classList.add('message-error');
            messageContainer.classList.remove('message-success');
        } else {
            messageContainer.classList.add('message-success');
            messageContainer.classList.remove('message-error');
        }
        messageContainer.style.display = 'block';
    }
</script>