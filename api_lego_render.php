<?php
require "config.php";
header('Content-Type: application/json');

// Verify if an image has been sent
if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(["error" => "Aucune image reçue"]);
    exit;
}

$file = $_FILES['image'];
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = "api_upload_" . uniqid() . "." . $extension;
$targetPath = "uploads/" . $filename;

// Move image to uploads dir
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de l'enregistrement de l'image"]);
    exit;
}

try {
    
    // Get first user ID for foreign key
    $stmtUser = $db->query("SELECT id FROM users LIMIT 1");
    $systemUserId = $stmtUser->fetchColumn();
    
    // Fallback to null if no users exist
    if (!$systemUserId) {
        $systemUserId = null; 
    }

    $stmt = $db->prepare("INSERT INTO images (filename, user_id) VALUES (?, ?)");
    $stmt->execute([$filename, $systemUserId]);
    $imageId = $db->lastInsertId();

    // Job for Java uses
    $resolution = "48x48";
    $strategy = "API_RENDER_BILINEAR";
    
    $stmt = $db->prepare("INSERT INTO mosaic_proposals (image_id, strategy, resolution, total_bricks_count) VALUES (?, ?, ?, 0)");
    $stmt->execute([$imageId, $strategy, $resolution]);
    $jobId = $db->lastInsertId();

    // Wait for Java to process the image
    $maxTries = 40; // Max 20 seconds (40 * 0.5s)
    $isDone = false;
    $status = 0;

    for ($i = 0; $i < $maxTries; $i++) {
        usleep(500000); // Wait 0.5s 
        $stmt = $db->prepare("SELECT total_bricks_count FROM mosaic_proposals WHERE id = ?");
        $stmt->execute([$jobId]);
        $status = $stmt->fetchColumn();

        // Check status
        if ($status != 0) {
            $isDone = true;
            break;
        }
    }

    if (!$isDone || $status == -2) {
        http_response_code(500);
        echo json_encode(["error" => "Timeout ou erreur lors de la génération par Java"]);
        exit;
    }

    $previewFile = "uploads/api_render_" . $jobId . ".png";
    $maquetteFile = "uploads/api_maquette_" . $jobId . ".txt";

    if (file_exists($previewFile) && file_exists($maquetteFile)) {
        
        // encoding in base64
        $imageBase64 = base64_encode(file_get_contents($previewFile));
        $txtContent = file_get_contents($maquetteFile);

        echo json_encode([
            "success" => true,
            "jobId" => $jobId,
            "imageData" => $imageBase64,
            "txtContent" => $txtContent
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Fichiers introuvables après traitement par Java"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>