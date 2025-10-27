<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';

Auth::logout();

$redirectTo = $_GET['redirect'] ?? 'login.php';

if ($redirectTo === '' || preg_match('/^https?:/i', $redirectTo) || str_starts_with($redirectTo, '//')) {
    $redirectTo = 'login.php';
}

header('Location: ' . $redirectTo);
exit;
