<?php

namespace App\Services\Master;

use Illuminate\Support\Str;

class TotpService
{
    /**
     * Generate a random base32 TOTP secret.
     */
    public function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate a list of recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::random(5) . '-' . Str::random(5);
        }
        return $codes;
    }

    /**
     * Get OTPAuth URL for Google Authenticator QR Code.
     */
    public function getQrCodeUrl(string $email, string $secret): string
    {
        $issuer = rawurlencode('ERP-Prime');
        $label = rawurlencode("ERP-Prime:{$email}");
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a TOTP code.
     * Allows 1 time slice before or after (30s discrepancy).
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->calculateCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate code for a given time slice.
     */
    protected function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        
        // Time slice to 64-bit binary timestamp
        $time = chr(0).chr(0).chr(0).chr(0).pack('N', $timeSlice);

        // HMAC-SHA1 hash
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord($hmac[19]) & 0xf;
        $value = (((ord($hmac[$offset]) & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) << 8) |
            (ord($hmac[$offset + 3]) & 0xff));

        $otp = $value % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32 string into raw bytes.
     */
    protected function base32Decode(string $base32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $map = array_flip(str_split($chars));
        $base32 = strtoupper($base32);
        
        $binary = '';
        foreach (str_split($base32) as $char) {
            if (isset($map[$char])) {
                $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
            }
        }
        
        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        
        return $bytes;
    }
}
