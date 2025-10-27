<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Auth.php';
require_once __DIR__ . '/../../app/Redirect.php';

Auth::logout();

$redirectTarget = resolve_redirect_target($_GET['redirect'] ?? '', 'login.php');

header('Location: ' . $redirectTarget);
exit;
