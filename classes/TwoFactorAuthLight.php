<?php
class TwoFactorAuthLight {
    private $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Generates a random Base32 secret
    public function createSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $this->base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    // Generates the URI for the Authenticator application
    public function getQRCodeUrl($companyName, $userEmail, $secret) {
        return 'otpauth://totp/' . rawurlencode($companyName) . ':' . rawurlencode($userEmail) 
             . '?secret=' . $secret . '&issuer=' . rawurlencode($companyName);
    }

    // Verifies the entered code with a tolerance of 30 seconds
    public function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, str_pad($code, 6, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    private function getCode($secret, $timeSlice) {
        $secretKey = $this->base32Decode($secret);
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        $value = unpack('N', $hashPart);
        $value = $value[1] & 0x7FFFFFFF;
        return str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode($secret) {
        if (empty($secret)) return '';
        $base32chars = $this->base32Chars;
        $base32charsFlipped = array_flip(str_split($base32chars));
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], str_split($base32chars))) return false;
            for ($j = 0; $j < 8; $j++) {
                if (!isset($secret[$i + $j])) continue;
                $x .= str_pad(decbin($base32charsFlipped[$secret[$i + $j]]), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                if (strlen($eightBits[$z]) != 8) continue;
                $binaryString .= (($y = chr(bindec($eightBits[$z]))) || ord($y) == 0) ? $y : '';
            }
        }
        return $binaryString;
    }
}
?>