<?php
// api/poll.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
$sessionId = $_GET['sessionId'] ?? ($_POST['sessionId'] ?? '');
if (empty($sessionId) || !preg_match('/^sess_[a-zA-Z0-9_]+$/', $sessionId)) {
    echo json_encode(['ok' => false, 'error' => 'sessionId inválido']);
    exit;
}

// Consultar el estado directamente en Redis
$rawSession = upstash_redis('GET', [$sessionId]);
if (!$rawSession) {
    echo json_encode(['ok' => true, 'status' => 'pending', 'action' => null]);
    exit;
}

$session = json_decode($rawSession, true) ?: [];

echo json_encode([
    'ok'        => true,
    'status'    => ($session['status'] ?? '') === 'completed' ? 'completed' : 'pending',
    'action'    => $session['action'] ?? null,
    'step'      => $session['currentStep'] ?? null,
    'sessionId' => $sessionId
]);
exit;