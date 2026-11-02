<?php

header('Content-Type: application/json; charset=utf-8');

function getClientIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    return filter_var($ip, FILTER_VALIDATE_IP)
        ? $ip
        : 'IP no disponible';
}

echo json_encode([
    'success' => true,
    'ip' => getClientIP()
]);