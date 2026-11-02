<?php
// api/poll.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Función auxiliar para comunicarse con Redis usando la variable REDIS_URL
function upstash_redis($command, array $args = []) {
    $redisUrl = getenv('REDIS_URL');
    if (!$redisUrl) return null;
    
    // Parsear la URL rediss://default:TOKEN@HOST:PORT
    $parsed = parse_url($redisUrl);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? 6379;
    $pass = $parsed['pass'] ?? '';
    
    if (empty($host) || empty($pass)) return null;

    // Construir la URL REST API
    $url = "https://{$host}:{$port}";
    
    $payload = array_merge([$command], $args);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " . $pass,
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    $data = json_decode($response, true);
    return $data['result'] ?? null;
}

$sessionId = $_GET['sessionId'] ?? ($_POST['sessionId'] ?? '');
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'sessionId inválido']);
    exit;
}

// Consultar el estado directamente en Redis
$rawSession = upstash_redis('GET', [$sessionId]);
if (!$rawSession) {
    echo json_encode(['ok' => true, 'status' => 'pending', 'action' => null]);
    exit;
}

$session = json_decode($rawSession, true) ?: [];

echo json_encode([
    'ok'        => true,
    'status'    => ($session['status'] ?? '') === 'completed' ? 'completed' : 'pending',
    'action'    => $session['action'] ?? null,
    'step'      => $session['currentStep'] ?? null,
    'sessionId' => $sessionId
]);
exit;