<?php
/**
 * ================================================================
 * Antibot - Protección contra bots (Compatible con Vercel Serverless)
 * ================================================================
 */

header('X-Robots-Tag: noindex, nofollow, noarchive');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Antibot
{
    public static function init(): void
    {
        // Si no existe un token en la sesión actual, se crea uno nuevo
        if (empty($_SESSION['antibot_token'])) {
            $_SESSION['antibot_token'] = bin2hex(random_bytes(16));
            $_SESSION['antibot_time']  = time();
        }
    }

    public static function campos(): string
    {
        self::init();
        $token = $_SESSION['antibot_token'] ?? '';
        return '
            <input type="text" name="website" id="website" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;">
            <input type="hidden" name="ab_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">
        ';
    }

    public static function esHumano(array $data = null): bool
    {
        if ($data === null) {
            $data = $_POST;
            if (empty($data)) {
                $raw = file_get_contents('php://input');
                if (!empty($raw)) {
                    $json = json_decode($raw, true);
                    if (is_array($json)) $data = $json;
                }
            }
        }

        // 1. Honeypot (si los bots llenan el campo trampa "website")
        if (!empty($data['website']) || !empty($_GET['website'])) {
            error_log("ANTIBOT: campo honeypot lleno");
            return false;
        }

        $token = $data['ab_token'] ?? $_GET['ab_token'] ?? '';
        if (empty($token)) {
            error_log("ANTIBOT: token vacio en la peticion");
            return false;
        }

        $sessionToken = $_SESSION['antibot_token'] ?? '';
        
        // 2. Validación directa contra la sesión PHP activa
        if (!empty($sessionToken) && hash_equals($sessionToken, $token)) {
            $tiempo = time() - ($_SESSION['antibot_time'] ?? 0);
            
            if ($tiempo < 1) {
                error_log("ANTIBOT: envío demasiado rápido ($tiempo segundos)");
                return false;
            }
            if ($tiempo > 1800) {
                error_log("ANTIBOT: sesión expirada ($tiempo segundos)");
                return false;
            }
            return true;
        }

        error_log("ANTIBOT: token inválido. Recibido: $token | Esperado (session): " . ($sessionToken ?: 'NINGUNO'));
        return false;
    }

    public static function rechazar(string $mensaje = 'Solicitud bloqueada por seguridad.'): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'antibot', 'message' => $mensaje]);
        exit;
    }
}