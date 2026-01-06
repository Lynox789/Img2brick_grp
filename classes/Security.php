<?php
class Security {
    private const TURNSTILE_SECRET = '';
    private const ENCRYPTION_KEY = ''; 
    private const CIPHER_METHOD = 'AES-256-CBC';

    // Verify Cloudflare Turnstile
    public static function verifyCaptcha($token) {
        if (empty($token)) return false;

        $data = [
            'secret'   => self::TURNSTILE_SECRET,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);

        if ($response === false) return false;

        $result = json_decode($response, true);
        return isset($result['success']) && $result['success'] === true;
    }

    // Validate Password Strength
    public static function isPasswordStrong($password) {
        // Min 12 chars, 1 upper, 1 lower, 1 number, 1 special
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/';
        return preg_match($regex, $password);
    }
    // Function to encrypt data
    public static function encrypt($data) {
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, self::ENCRYPTION_KEY, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    // Function to decrypt the data
    public static function decrypt($data) {
        $decoded = base64_decode($data);
        if (strpos($decoded, '::') === false) return $data;

        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted_data, self::CIPHER_METHOD, self::ENCRYPTION_KEY, 0, $iv);
    }
}
?>