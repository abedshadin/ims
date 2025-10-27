<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';

$destination = Auth::check() ? 'vendor/create.php' : 'auth/login.php';

header('Location: ' . $destination);
exit;
