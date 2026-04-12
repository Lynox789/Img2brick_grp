<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Connexion to the database
require_once __DIR__ . '/../../connexion/Database.php';
$pdo = Database::getInstance()->getConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['deviceId'])) {
        echo json_encode(['success' => false, 'error' => 'deviceId manquant']);
        exit;
    }
    
    $deviceId = $data['deviceId'];
    $appVersion = $data['appVersion'] ?? '1.0';
    
    // INSERT or UPDATE if the device already exists
    $stmt = $pdo->prepare('
        INSERT INTO app_installations (device_id, app_version, installed_at, last_seen) 
        VALUES (?, ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE last_seen = NOW(), app_version = VALUES(app_version)
    ');
    $stmt->execute([$deviceId, $appVersion]);

    $countStmt = $pdo->query('SELECT COUNT(*) as total FROM app_installations');
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Installation enregistrée',
        'totalInstallations' => (int)$total,
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
