<?php
/**
 * ================================================================
 * API Poll — Consulta estado de aprobación (Exclusivo Vercel)
 * ================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$sessionId = $_GET['sessionId'] ?? ($_POST['sessionId'] ?? '');
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'sessionId inválido']);
    exit;
}

// En Vercel, el directorio con permisos de escritura es exclusivamente /tmp
$dataDir = sys_get_temp_dir();
$sessionFile = $dataDir . "/{$sessionId}.json";

if (!file_exists($sessionFile)) {
    echo json_encode(['ok' => true, 'status' => 'pending', 'action' => null]);
    exit;
}

// Leer sesión actual de forma limpia
$content = @file_get_contents($sessionFile);
$session = json_decode($content, true) ?: [];

echo json_encode([
    'ok'        => true,
    'status'    => ($session['status'] ?? '') === 'completed' ? 'completed' : 'pending',
    'action'    => $session['action'] ?? null,
    'step'      => $session['currentStep'] ?? null,
    'sessionId' => $sessionId
]);
exit;