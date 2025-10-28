<?php

declare(strict_types=1);

final class IdCipher
{
    private const CIPHER = 'aes-256-cbc';
    private const SECRET_KEY = 'ims_entity_id_secret_key_2024';
    private const SECRET_IV = 'ims_entity_id_secret_iv';

    private static function encryptionKey(): string
    {
        return hash('sha256', self::SECRET_KEY, true);
    }

    private static function encryptionIv(): string
    {
        return substr(hash('sha256', self::SECRET_IV, true), 0, 16);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;

        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($padded, true);
    }

    public static function encode(int $id): string
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifier must be a positive integer.');
        }

        $encrypted = openssl_encrypt(
            (string) $id,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            self::encryptionIv()
        );

        if ($encrypted === false) {
            throw new RuntimeException('Unable to encode identifier.');
        }

        return self::base64UrlEncode($encrypted);
    }

    public static function decode(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $decoded = self::base64UrlDecode($token);

        if ($decoded === false) {
            return null;
        }

        $decrypted = openssl_decrypt(
            $decoded,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            self::encryptionIv()
        );

        if ($decrypted === false || !ctype_digit($decrypted)) {
            return null;
        }

        $id = (int) $decrypted;

        return $id > 0 ? $id : null;
    }
}
