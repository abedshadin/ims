<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Auth
{
    private const SESSION_TOKEN_KEY = 'user_token';
    private const SESSION_NAME_KEY = 'user_name';
    private const CIPHER = 'aes-256-cbc';
    private const SECRET_KEY = 'ims_application_secret_key_2024';
    private const SECRET_IV = 'ims_application_secret_iv';

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function encryptionKey(): string
    {
        return hash('sha256', self::SECRET_KEY, true);
    }

    private static function encryptionIv(): string
    {
        return substr(hash('sha256', self::SECRET_IV, true), 0, 16);
    }

    private static function encryptUserId(int $userId): string
    {
        $encrypted = openssl_encrypt(
            (string) $userId,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            self::encryptionIv()
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt user identifier.');
        }

        return base64_encode($encrypted);
    }

    private static function decryptUserId(string $token): ?int
    {
        $decoded = base64_decode($token, true);

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

        return (int) $decrypted;
    }

    public static function attempt(string $email, string $password): bool
    {
        self::ensureSessionStarted();

        $email = trim($email);
        $password = trim($password);

        if ($email === '' || $password === '') {
            return false;
        }

        $pdo = Database::getConnection();
        $statement = $pdo->prepare('SELECT id, name, password FROM users WHERE email = :email LIMIT 1');
        $statement->execute([':email' => $email]);

        $user = $statement->fetch();

        if ($user === false || !password_verify($password, (string) $user['password'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION[self::SESSION_TOKEN_KEY] = self::encryptUserId((int) $user['id']);
        $_SESSION[self::SESSION_NAME_KEY] = (string) $user['name'];

        return true;
    }

    public static function register(string $name, string $email, string $password): array
    {
        $name = trim($name);
        $email = trim($email);

        if ($name === '' || $email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'All fields are required.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please provide a valid email address.',
            ];
        }

        if (strlen($password) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long.',
            ];
        }

        $pdo = Database::getConnection();

        $existingUserStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existingUserStatement->execute([':email' => $email]);

        if ($existingUserStatement->fetch()) {
            return [
                'success' => false,
                'message' => 'An account with this email already exists.',
            ];
        }

        $statement = $pdo->prepare(
            'INSERT INTO users (name, email, password, created_at) VALUES (:name, :email, :password, NOW())'
        );

        $statement->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return [
            'success' => true,
            'message' => 'Account created successfully. You can now log in.',
        ];
    }

    public static function check(): bool
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_TOKEN_KEY])) {
            return false;
        }

        return self::userId() !== null;
    }

    public static function requireLogin(string $redirectPath = '/auth/login.php'): void
    {
        if (self::check()) {
            return;
        }

        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        $location = $redirectPath;

        if ($currentUrl !== '') {
            $separator = str_contains($redirectPath, '?') ? '&' : '?';
            $location .= $separator . 'redirect=' . urlencode($currentUrl);
        }

        header('Location: ' . $location);
        exit;
    }

    public static function userId(): ?int
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_TOKEN_KEY])) {
            return null;
        }

        return self::decryptUserId((string) $_SESSION[self::SESSION_TOKEN_KEY]);
    }

    public static function userName(): ?string
    {
        self::ensureSessionStarted();

        return isset($_SESSION[self::SESSION_NAME_KEY])
            ? (string) $_SESSION[self::SESSION_NAME_KEY]
            : null;
    }

    public static function logout(): void
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::SESSION_TOKEN_KEY], $_SESSION[self::SESSION_NAME_KEY]);

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
