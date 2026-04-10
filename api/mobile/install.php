<?php
/**
 * api/mobile/install.php
 *
 * Endpoint appelé au premier lancement de l'application Android.
 * Enregistre l'installation dans la table app_installations.
 *
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Connexion à la base de données (adapter selon votre configuration)
require_once __DIR__ . '/../../connexion/Database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['deviceId'])) {
        echo json_encode(['success' => false, 'error' => 'deviceId manquant']);
        exit;
    }
    
    $deviceId = $data['deviceId'];
    $appVersion = $data['appVersion'] ?? '1.0';
    
    // INSERT ou UPDATE si le device existe déjà
    $stmt = $pdo->prepare('
        INSERT INTO app_installations (device_id, app_version, installed_at, last_seen) 
        VALUES (?, ?, NOW(), NOW()) 
        ON DUPLICATE KEY UPDATE last_seen = NOW(), app_version = VALUES(app_version)
    ');
    $stmt->execute([$deviceId, $appVersion]);
    
    // Compter le total d'installations pour les stats admin
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
