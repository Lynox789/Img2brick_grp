<?php

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class UserManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Registration
    public function register(string $username, string $email, string $password) {
        // If email exists but is NOT verified, delete it to start fresh
        $check = $this->pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $check->execute([$email]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['is_verified'] == 0) {
                $this->deleteUser($existing['id']);
            } else {
                // Existing active account, block registration
                return false; 
            }
        }

        //Create the new account
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        //Using 'is_verified' set to 0 by default
        $sql = "INSERT INTO users (username, email, password, is_verified) VALUES (:u, :e, :p, 0)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                ':u' => $username, 
                ':e' => $email, 
                ':p' => $hash
            ]);

            if ($success) {
                return $this->pdo->lastInsertId();
            }
            return false;

        } catch (PDOException $e) {
            return false; 
        }
    }

    // Login
    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 0) {
                 return false; 
            }
            return $user;
        }
        return false;
    }

    //Account validation
    public function confirmAccount($userId) {
        try {
            //Using is_verified
            $stmt = $this->pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    //Function to delete the User
    public function deleteUser($userId) {
        try {
            //Delete tokens linked to this account
            $delTokens = $this->pdo->prepare("DELETE FROM tokens WHERE id_client = ?");
            $delTokens->execute([$userId]);

            //Delete user account
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    //Function used to send email
    public function sendEmail(string $to, string $subject, string $body): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = '';
            $mail->SMTPAuth   = true;
            $mail->Username   = '';
            $mail->Password   = ''; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = "587";
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );
            $mail->setFrom('', 'Img2Brick');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function sendPasswordResetLink($email) {
        //Check if email exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Return true even if email doesn't exist to avoid revealing who is registered
            return true; 
        }

        // Generate a secure token (32 bytes -> 64 hex characters)
        $token = bin2hex(random_bytes(32));
        
        // Store the token HASH in database
        $token_hash = hash('sha256', $token);
        
        $expiry = date('Y-m-d H:i:s', time() + 60);

        // update the user for setting a new reset token hash
        $sql = "UPDATE users SET reset_token_hash = ?, reset_expires_at = ? WHERE email = ?";
        $update = $this->pdo->prepare($sql);
        $update->execute([$token_hash, $expiry, $email]);

        // Link preparation to send in the email, the user will have to clik to this link to be send in reset_password page
        $link = "?token=" . $token;

        $subject = "Réinitialisation de votre mot de passe";
        $body = "Cliquez sur ce lien pour réinitialiser votre mot de passe (valide 1 min) : <br><br> <a href='$link'>$link</a>";

        return $this->sendEmail($email, $subject, $body);
    }

    // Verifies the token and changes the password
    public function resetPasswordWithToken($token, $newPassword) {
        $token_hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        // Find the user who has this token AND who has not expired
        $sql = "SELECT id FROM users WHERE reset_token_hash = ? AND reset_expires_at > ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$token_hash, $now]);
        $user = $stmt->fetch();

        if (!$user) {
            return false; // Invalid or expired token
        }

        // Update password
        $new_hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        $update = $this->pdo->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_expires_at = NULL WHERE id = ?");
        return $update->execute([$new_hash, $user['id']]);
    }
}
?>