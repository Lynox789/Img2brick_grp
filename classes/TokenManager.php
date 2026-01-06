<?php
class TokenManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Generates a 6-digit code valid for 1 minute
    public function generate2FACode(int $userId): string {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Expiration: 1min
        $expiration = date('Y-m-d H:i:s', strtotime('+1 minute'));

        // Insert the token
        $sql = "INSERT INTO tokens (token_code, expiration, est_utilise, id_client) 
                VALUES (:code, :exp, 0, :uid)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':code' => $code,
            ':exp' => $expiration,
            ':uid' => $userId
        ]);

        return $code;
    }

    // Verifies the code
    public function verify2FACode(int $userId, string $code): bool {
        // Retrieves the last valid token for this user
        $sql = "SELECT id_token, expiration, est_utilise 
                FROM tokens 
                WHERE id_client = :uid AND token_code = :code 
                ORDER BY expiration DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':code' => $code]);
        $token = $stmt->fetch();

        if (!$token) return false; // Code not found

        // Check if already used
        if ($token['est_utilise'] == 1) return false;

        // Check if expired
        $now = new DateTime();
        $exp = new DateTime($token['expiration']);
        
        if ($now > $exp) {
            return false; // 1min limit exceeded 
        }

        // Mark as used
        $update = $this->pdo->prepare("UPDATE tokens SET est_utilise = 1 WHERE id_token = ?");
        $update->execute([$token['id_token']]);

        return true;
    }
}
?>