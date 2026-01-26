<?php

namespace App\Security;

use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class TwoFactorAuthenticator implements TotpAuthenticatorInterface
{
    public function checkCode(UserInterface $user, string $code): bool
    {
        // Implement TOTP validation
        $secret = $user->getTwoFactorSecret();

        // Use Google Authenticator compatible validation
        return $this->verifyCode($secret, $code);
    }

    public function getQRContent(UserInterface $user): string
    {
        $secret = $user->getTwoFactorSecret();
        $issuer = 'MailServer Dashboard';
        $accountName = $user->getEmail();

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $secret,
            rawurlencode($issuer)
        );
    }

    public function generateSecret(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function verifyCode(string $secret, string $code): bool
    {
        // Implement TOTP verification
        $timestamp = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $expectedCode = $this->generateCode($secret, $timestamp + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateCode(string $secret, int $timestamp): string
    {
        $hash = hash_hmac('sha1', pack('J', $timestamp), hex2bin($secret), true);
        $offset = ord($hash[19]) & 0xf;

        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, 6);

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
}
