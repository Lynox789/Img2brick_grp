<?php
/**
 * includes/LoyaltyManager.php
 *
 * Classe de gestion de la fidélité côté site PHP.
 * Communique avec le backend Node.js pour :
 *   - Récupérer le solde de points d'un client
 *   - Consommer des points lors d'une commande
 *   - Générer/récupérer le loyaltyId d'un utilisateur
 *   - Rattacher un compte guest
 *
 */

class LoyaltyManager {
    
    /** URL du backend Node.js (à configurer) */
    private string $nodeApiUrl;
    
    /** Connexion PDO à la BDD MySQL */
    private PDO $pdo;
    
    /** Valeur monétaire d'un point (en euros) */
    private float $pointValue = 0.01;
    
    public function __construct(PDO $pdo, string $nodeApiUrl = 'http://localhost:3001/api') {
        $this->pdo = $pdo;
        $this->nodeApiUrl = $nodeApiUrl;
    }
    
    // LOYALTY ID
    /**
     * Récupère le loyaltyId d'un utilisateur. Le crée s'il n'existe pas.
     */

    public function getLoyaltyId(int $userId): string {
        $stmt = $this->pdo->prepare('SELECT loyalty_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $loyaltyId = $stmt->fetchColumn();
        
        if (!$loyaltyId) {
            // Générer un nouveau loyaltyId
            $loyaltyId = 'php-' . $userId . '-' . substr(md5(uniqid()), 0, 8);
            $stmt = $this->pdo->prepare('UPDATE users SET loyalty_id = ? WHERE id = ?');
            $stmt->execute([$loyaltyId, $userId]);
        }
        
        return $loyaltyId;
    }
    
    /**
     * Rattache un compte guest à un utilisateur PHP.
     * Appelé quand un visiteur crée un compte après avoir joué.
     */

    public function linkGuestAccount(int $userId, string $guestLoyaltyId): bool {
        $phpLoyaltyId = $this->getLoyaltyId($userId);
        
        $response = $this->callNodeApi('POST', '/loyalty/link', [
            'guestLoyaltyId' => $guestLoyaltyId,
            'phpLoyaltyId' => $phpLoyaltyId,
        ]);
        
        return $response['success'] ?? false;
    }
    
    // SOLDE

    public function getBalance(int $userId): array {
        $loyaltyId = $this->getLoyaltyId($userId);
        $response = $this->callNodeApi('GET', "/loyalty/{$loyaltyId}/balance");
        
        return [
            'totalAvailable' => $response['totalAvailable'] ?? 0,
            'transactions' => $response['transactions'] ?? [],
            'monetaryValue' => ($response['totalAvailable'] ?? 0) * $this->pointValue,
        ];
    }
    
    /**
     * Récupère le solde formaté pour l'affichage dans le processus de commande.
     */

    public function getBalanceForCheckout(int $userId): array {
        $balance = $this->getBalance($userId);
        
        return [
            'points' => $balance['totalAvailable'],
            'euros' => number_format($balance['monetaryValue'], 2, ',', ' '),
            'pointValue' => $this->pointValue,
            'maxDiscount' => $balance['monetaryValue'],
        ];
    }
    
    // CONSOMMATION

    public function consumePoints(int $userId, int $points, ?int $commandeId = null): array {
        $loyaltyId = $this->getLoyaltyId($userId);
        
        $response = $this->callNodeApi('POST', "/loyalty/{$loyaltyId}/consume", [
            'points' => $points,
        ]);
        
        if (!($response['success'] ?? false)) {
            return [
                'success' => false,
                'consumed' => 0,
                'discount' => 0,
                'error' => $response['error'] ?? 'Erreur lors de la consommation des points',
            ];
        }
        
        $discount = $points * $this->pointValue;
        
        // Enregistrer le bon d'achat dans la BDD PHP (suivi)
        if ($commandeId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO loyalty_vouchers (user_id, points_used, voucher_amount, commande_id) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $points, $discount, $commandeId]);
        }
        
        return [
            'success' => true,
            'consumed' => $response['consumed'],
            'discount' => $discount,
            'remainingBalance' => $response['remainingBalance'],
        ];
    }
    
    /**
     * Calcule la réduction maximale applicable sur une commande.
     */

    public function getMaxDiscount(int $userId, float $orderTotal): array {
        $balance = $this->getBalance($userId);
        $maxDiscountEuros = $balance['monetaryValue'];
        
        // La réduction ne peut pas dépasser le total de la commande
        $applicableDiscount = min($maxDiscountEuros, $orderTotal);
        $pointsNeeded = (int) ceil($applicableDiscount / $this->pointValue);
        
        return [
            'availablePoints' => $balance['totalAvailable'],
            'maxDiscountEuros' => $applicableDiscount,
            'pointsForMaxDiscount' => $pointsNeeded,
            'pointValue' => $this->pointValue,
        ];
    }
    
    // HISTORIQUE
    
    /**
     * Récupère l'historique des parties et points d'un utilisateur.
     */

    public function getHistory(int $userId): array {
        $loyaltyId = $this->getLoyaltyId($userId);
        $response = $this->callNodeApi('GET', "/loyalty/{$loyaltyId}/history");
        return $response['history'] ?? [];
    }
    
    // COMMUNICATION AVEC LE BACKEND NODE.JS
    
    /**
     * Appel HTTP vers l'API du backend Node.js.
     */

    private function callNodeApi(string $method, string $endpoint, ?array $data = null): array {
        $url = $this->nodeApiUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'timeout' => 10,
            ],
        ];
        
        if ($data !== null && $method !== 'GET') {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("[LoyaltyManager] Erreur appel Node.js : {$method} {$url}");
            return ['success' => false, 'error' => 'Backend Node.js inaccessible'];
        }
        
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'Réponse invalide'];
    }
}
