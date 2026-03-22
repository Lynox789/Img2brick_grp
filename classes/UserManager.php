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
    public function register(string $username, string $email, string $password, array $profileData = []) {
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

        $encFirstname = Security::encrypt($profileData['firstname'] ?? '');
        $encLastname  = Security::encrypt($profileData['lastname'] ?? '');
        $encPhone     = Security::encrypt($profileData['phone'] ?? '');
        $encAddress   = Security::encrypt($profileData['address'] ?? '');

        $zipcode = $profileData['zipcode'] ?? '';
        $city    = $profileData['city'] ?? '';
        $country = $profileData['country'] ?? '';

        //Using 'is_verified' set to 0 by default
        $sql = "INSERT INTO users (
                    username, email, password, is_verified, firstname, lastname, phone, address, zipcode, city, country, created_at) 
                    VALUES (:u, :e, :p, 0, :fn, :ln, :ph, :ad, :zp, :ci, :co, NOW()
                )";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                ':u' => $username, 
                ':e' => $email, 
                ':p' => $hash,
                ':fn' => $encFirstname,
                ':ln' => $encLastname,
                ':ph' => $encPhone,
                ':ad' => $encAddress,
                ':zp' => $zipcode,
                ':ci' => $city,
                ':co' => $country
            ]);

            if ($success) {
                $newId = $this->pdo->lastInsertId();

                $realLastname = $profileData['lastname'] ?? '';
                $realFirstname = $profileData['firstname'] ?? '';
                // Ensure billing customer exists
                $this->ensureClientExists($newId, $username, $email, $realLastname, $realFirstname);
                return $newId;
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

            //If the user exists but does not have a customer file (old account), it is created now.
            // We prepare the default variables (in case they are null)
            $decryptedLastname = null;
            $decryptedFirstname = null;

            // If the name is filled in (and therefore encrypted), it is decrypted
            if (!empty($user['lastname'])) {
                $decryptedLastname = Security::decrypt($user['lastname']); 
            }
            if (!empty($user['firstname'])) {
                $decryptedFirstname = Security::decrypt($user['firstname']);
            }

            $this->ensureClientExists(
                $user['id'], 
                $user['username'], 
                $user['email'], 
                $decryptedLastname,  
                $decryptedFirstname 
            );
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
            $mail->Host       = $_ENV['MAIL_HOST'] ?? '';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? ''; 
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
            $mail->setFrom($_ENV['MAIL_FROM'] ?? '', 'Img2Brick');
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
        $link = "http://localhost/Img2brick_grp/reset_password.php?token=" . $token;

        $subject = "Réinitialisation de votre mot de passe";
        $body = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0; padding:0; background-color:#f1f5f9; font-family: Poppins, Arial, sans-serif;">

            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">
                            
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 40px 30px; border-radius: 16px 16px 0 0;">
                                    <h1 style="margin:0; color:white; font-size:28px; font-weight:800; letter-spacing:-0.5px;">
                                        Img2brick
                                    </h1>
                                    <p style="margin:8px 0 0; color:rgba(255,255,255,0.8); font-size:14px;">
                                        Transformez vos images en mosaïques
                                    </p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="background:white; padding: 40px 40px 30px;">
                                    
                                    <h2 style="margin:0 0 12px; color:#1e293b; font-size:22px; font-weight:700;">
                                        Réinitialisation de mot de passe
                                    </h2>
                                    <p style="margin:0 0 24px; color:#64748b; font-size:15px; line-height:1.6;">
                                        Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.
                                    </p>

                                    <!-- Button -->
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center" style="padding: 10px 0 30px;">
                                                <a href="' . $link . '" 
                                                style="display:inline-block; background:#3b82f6; color:white; text-decoration:none; padding:14px 36px; border-radius:8px; font-size:15px; font-weight:600;">
                                                    Réinitialiser mon mot de passe
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Warning box -->
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="background:#fef9c3; border:1px solid #fde68a; border-radius:8px; padding:14px 16px;">
                                                <p style="margin:0; color:#92400e; font-size:13px; line-height:1.5;">
                                                    ATTENTION : Ce lien est valable <strong>1 minute</strong> seulement. Si vous n\'avez pas fait cette demande, ignorez cet email.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Fallback link -->
                                    <p style="margin:24px 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                                        <a href="' . $link . '" style="color:#3b82f6; word-break:break-all;">' . $link . '</a>
                                    </p>

                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background:#f8fafc; padding:20px 40px; border-radius:0 0 16px 16px; border-top:1px solid #e2e8f0;">
                                    <p style="margin:0; color:#94a3b8; font-size:12px; text-align:center; line-height:1.6;">
                                        © ' . date('Y') . ' img2brick — Tous droits réservés<br>
                                        Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>

        </body>
        </html>';
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

    // PRIVATE METHOD FOR LINKING BILLING
    private function ensureClientExists($userId, $username, $email, $lastname = null, $firstname = null) {
        try {
            // Determine the name to display on the invoice
            if (!empty($lastname) && !empty($firstname)) {

                $nomAffiche = mb_strtoupper($lastname, 'UTF-8') . " " . ucfirst(mb_strtolower($firstname, 'UTF-8'));
            } else {

                $nomAffiche = "Client Web " . $username;
            }
            // Customer Code Generation
            $codeClient = "WEB" . str_pad($userId, 6, '0', STR_PAD_LEFT);

            // Insert or update if it already exists (ON DUPLICATE KEY UPDATE)
            $sql = "INSERT INTO client (code_client, user_id, nom, email_fact) 
                    VALUES (:code, :uid, :nom, :email)
                    ON DUPLICATE KEY UPDATE user_id = :uid, nom = :nom";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':code', $codeClient);
            $stmt->bindParam(':uid', $userId);
            $stmt->bindParam(':nom', $nomAffiche);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Error creating billing customer for user $userId : " . $e->getMessage());
        }
    }
}
?>