<?php
//File called everyday by the android application
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../connexion/Database.php';
$pdo = Database::getInstance()->getConnection();

//notification time every week
define('LOYALTY_THRESHOLD_DAYS', 7);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $deviceId = $data['deviceId'] ?? null;
    $userId = $data['userId'] ?? null;
    $loyaltyId = $data['loyaltyId'] ?? null;
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'error' => 'deviceId manquant']);
        exit;
    }
    
    //Update the last_seen and associate the user_id
    if ($userId) {
        $stmt = $pdo->prepare('
            UPDATE app_installations 
            SET last_seen = NOW(), user_id = ? 
            WHERE device_id = ?
        ');
        $stmt->execute([$userId, $deviceId]);
    } else {
        $stmt = $pdo->prepare('UPDATE app_installations SET last_seen = NOW() WHERE device_id = ?');
        $stmt->execute([$deviceId]);
    }
    
    //Check Loyalty
    $showLoyaltyNotif = false;
    $loyaltyMessage = '';
    
    if ($userId) {
        // Last command
        $stmt = $pdo->prepare('SELECT MAX(date_commande) as last_order FROM commandes WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $daysSinceOrder = null;
        if ($row && $row['last_order']) {
            $daysSinceOrder = (int)((time() - strtotime($row['last_order'])) / 86400);
        }
        
        // If no order since the threshold (or never ordered)
        if ($daysSinceOrder === null || $daysSinceOrder > LOYALTY_THRESHOLD_DAYS) {
            $showLoyaltyNotif = true;
                
            if ($daysSinceOrder === null) {
                $loyaltyMessage = "Vous n'avez pas encore passé de commande ! Découvrez nos tableaux Lego et gagnez des points en jouant.";
            } elseif ($daysSinceOrder > 30) {
                $loyaltyMessage = "Vous nous manquez ! Ça fait " . $daysSinceOrder . " jours. Revenez jouer et profitez de vos points de fidélité !";
            } else {
                $loyaltyMessage = "Ça fait " . $daysSinceOrder . " jours ! Venez jouer et accumulez des points pour votre prochaine commande.";
            }
        }
    }
    
    $activeStmt = $pdo->query("SELECT COUNT(*) as active FROM app_installations WHERE last_seen > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $activeCount = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    echo json_encode([
        'success' => true,
        'showLoyaltyNotif' => $showLoyaltyNotif,
        'loyaltyMessage' => $loyaltyMessage,
        'activeDevices' => (int)$activeCount,
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
