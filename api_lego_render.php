<?php
require "config.php";
header('Content-Type: application/json');

// Verify if an image has been send
if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(["error" => "Aucune image reçue"]);
    exit;
}

$file = $_FILES['image'];
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = "api_upload_" . uniqid() . "." . $extension;
$targetPath = "uploads/" . $filename;

//Move image to uploads dir
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Erreur lors de l'enregistrement de l'image"]);
    exit;
}

try {
    
    $stmt = $db->prepare("INSERT INTO images (filename, user_id) VALUES (?, 0)");
    $stmt->execute([$filename]);
    $imageId = $db->lastInsertId();

    //Job for Java uses
    $resolution = "96x96";
    $strategy = "API_RENDER_BICUBIC";
    
    $stmt = $db->prepare("INSERT INTO mosaic_proposals (image_id, strategy, resolution, total_bricks_count) VALUES (?, ?, ?, 0)");
    $stmt->execute([$imageId, $strategy, $resolution]);
    $jobId = $db->lastInsertId();

    // Wait java to treat the image
    $maxTries = 40; // Max 20 seconds (40 * 0,5)
    $isDone = false;
    $status = 0;

    for ($i = 0; $i < $maxTries; $i++) {
        usleep(500000); // wait 0,5s 
        $stmt = $db->prepare("SELECT total_bricks_count FROM mosaic_proposals WHERE id = ?");
        $stmt->execute([$jobId]);
        $status = $stmt->fetchColumn();

        // Wait for statut
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

    // Send the new image
    $previewFile = "uploads/api_render_" . $jobId . ".png";
    if (file_exists($previewFile)) {
        // Conversion on base64 to NodeJs
        $imageData = base64_encode(file_get_contents($previewFile));
        $src = 'data:image/png;base64,' . $imageData;

        echo json_encode([
            "success" => true,
            "jobId" => $jobId,
            "image" => $src
        ]);

    } else {
        http_response_code(404);
        echo json_encode(["error" => "Image pavée introuvable après traitement"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>