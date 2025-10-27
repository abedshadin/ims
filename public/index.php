<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

$redirectTarget = $_GET['redirect'] ?? '';

if ($redirectTarget !== '' && (preg_match('/^https?:/i', $redirectTarget) || str_starts_with($redirectTarget, '//'))) {
    $redirectTarget = '';
}

if (Auth::check()) {
    $destination = $redirectTarget !== '' ? $redirectTarget : 'dashboard.php';
} else {
    $destination = 'auth/login.php';

    if ($redirectTarget !== '') {
        $separator = str_contains($destination, '?') ? '&' : '?';
        $destination .= $separator . 'redirect=' . urlencode($redirectTarget);
    }
}

header('Location: ' . $destination);
exit;
