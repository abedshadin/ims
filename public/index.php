<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Redirect.php';

$redirectTarget = sanitize_redirect_target($_GET['redirect'] ?? '');

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
