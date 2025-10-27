<?php

function sanitize_redirect_target(mixed $target): string
{
    if (!is_string($target)) {
        return '';
    }

    $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $target);

    if ($sanitized === null) {
        return '';
    }

    $sanitized = trim($sanitized);

    if ($sanitized === '' || preg_match('/^https?:/i', $sanitized) || str_starts_with($sanitized, '//')) {
        return '';
    }

    return $sanitized;
}

function resolve_redirect_target(mixed $target, string $default): string
{
    $safeTarget = sanitize_redirect_target($target);

    return $safeTarget !== '' ? $safeTarget : $default;
}
