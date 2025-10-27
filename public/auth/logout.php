<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';

Auth::logout();

$redirectTo = $_GET['redirect'] ?? 'login.php';

if (!is_string($redirectTo)) {
    $redirectTo = '';
} else {
    $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $redirectTo);
    $redirectTo = $sanitized !== null ? trim($sanitized) : '';
}

if ($redirectTo === '' || preg_match('/^https?:/i', $redirectTo) || str_starts_with($redirectTo, '//')) {
    $redirectTo = 'login.php';
}

header('Location: ' . $redirectTo);
exit;
