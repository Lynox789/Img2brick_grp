<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../includes/db_connect.php';

try {
    $userId = $_GET['userId'] ?? null;
    
    if (!$userId) {
        echo json_encode(['notifications' => []]);
        exit;
    }
    
    // Retrieve recent orders (last 48 hours) not yet notified
    $stmt = $pdo->prepare("
        SELECT c.id, c.statut, c.total_price, c.date_commande
        FROM commandes c
        WHERE c.user_id = ?
          AND c.date_commande > DATE_SUB(NOW(), INTERVAL 2 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM mobile_notifications_sent mns 
              WHERE mns.commande_id = c.id 
                AND mns.user_id = c.user_id
                AND mns.notif_type = CONCAT('order_', c.statut)
          )
        ORDER BY c.date_commande DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notifications = [];
    
    foreach ($commandes as $cmd) {
        $orderId = $cmd['id'];
        $statut = $cmd['statut'];
        $total = number_format((float)$cmd['total_price'], 2, ',', ' ');
        
        // Construct the title according to status
        switch ($statut) {
            case 'en_attente':
                $title = "Commande #{$orderId} en attente de paiement";
                break;
            case 'payée':
                $title = "Commande #{$orderId} confirmée !";
                break;
            case 'livrée':
                $title = "Commande #{$orderId} expédiée !";
                break;
            default:
                $title = "Commande #{$orderId} mise à jour";
        }
        
        $notifications[] = [
            'orderId' => (string)$orderId,
            'title' => $title,
            'detail' => $total . ' €',
            'status' => $statut,
            'date' => $cmd['date_commande'],
        ];
        
        // Mark as sent
        $insertStmt = $pdo->prepare("
            INSERT IGNORE INTO mobile_notifications_sent (user_id, commande_id, notif_type) 
            VALUES (?, ?, ?)
        ");
        $insertStmt->execute([$userId, $orderId, 'order_' . $statut]);
    }
    
    echo json_encode(['notifications' => $notifications]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['notifications' => [], 'error' => $e->getMessage()]);
}
