<?php
/**
 * ================================================================
 * Antibot - Proteccion contra bots automatizados
 * ================================================================
 * Usa archivos JSON en data/ en lugar de sesiones PHP
 * para mayor compatibilidad con Docker/contenedores.
 */

header('X-Robots-Tag: noindex, nofollow, noarchive');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Antibot
{
    private static function dataDir(): string
    {
        return __DIR__ . '/data';
    }

    private static function tokenFile(string $token): string
    {
        return self::dataDir() . '/antibot_' . $token . '.json';
    }

    public static function init(): void
    {
        $token = bin2hex(random_bytes(16));
        $time  = time();

        // Guardar en archivo (mecanismo principal)
        $file = self::tokenFile($token);
        @file_put_contents($file, json_encode([
            'time'   => $time,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'used'   => false
        ]));

        // Tambien guardar en sesion PHP (fallback)
        $_SESSION['antibot_token'] = $token;
        $_SESSION['antibot_time']  = $time;
    }

    public static function campos(): string
    {
        if (empty($_SESSION['antibot_token'])) {
            self::init();
        }
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

        // Honeypot
        if (!empty($data['website']) || !empty($_GET['website'])) {
            error_log("ANTIBOT: campo honeypot lleno");
            return false;
        }

        $token = $data['ab_token'] ?? $_GET['ab_token'] ?? '';
        if (empty($token)) {
            error_log("ANTIBOT: token vacio");
            return false;
        }

        // --- Metodo 1: Verificar archivo ---
        $file = self::tokenFile($token);
        if (file_exists($file)) {
            $info = json_decode(@file_get_contents($file), true);
            if (!empty($info)) {
                $tiempo = time() - ($info['time'] ?? 0);
                if ($tiempo < 2) {
                    error_log("ANTIBOT: muy rapido ($tiempo segundos)");
                    return false;
                }
                if ($tiempo > 1800) {
                    error_log("ANTIBOT: sesion expirada ($tiempo segundos)");
                    @unlink($file);
                    return false;
                }
                // One-time use: eliminar archivo
                @unlink($file);
                self::init();
                return true;
            }
        }

        // --- Metodo 2: Fallback a sesion PHP ---
        if ($token === ($_SESSION['antibot_token'] ?? '')) {
            $tiempo = time() - ($_SESSION['antibot_time'] ?? 0);
            if ($tiempo < 2) {
                error_log("ANTIBOT: muy rapido ($tiempo segundos) [session]");
                return false;
            }
            if ($tiempo > 1800) {
                error_log("ANTIBOT: sesion expirada ($tiempo segundos) [session]");
                return false;
            }
            self::init();
            return true;
        }

        error_log("ANTIBOT: token invalido. Recibido: $token | Esperado (session): " . ($_SESSION['antibot_token'] ?? 'NINGUNO'));
        return false;
    }

    public static function debug(): array
    {
        $token = $_SESSION['antibot_token'] ?? 'NO_SESSION_TOKEN';
        $file  = self::tokenFile($token);
        return [
            'session_token' => $token,
            'session_time'  => $_SESSION['antibot_time'] ?? 0,
            'file_exists'   => file_exists($file),
            'file_path'     => $file,
            'data_dir'      => self::dataDir(),
            'data_writable' => is_writable(self::dataDir()),
            'ip'            => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
    }

    public static function rechazar(string $mensaje = 'Solicitud bloqueada por seguridad.'): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'antibot', 'message' => $mensaje]);
        exit;
    }
}
