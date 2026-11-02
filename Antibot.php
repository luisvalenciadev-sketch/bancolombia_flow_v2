<?php
/**
 * ================================================================
 * Antibot - Protección contra bots (Compatible con Vercel / Render)
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
        // Se mantiene por compatibilidad si algún archivo lo llama
    }

    public static function campos(): string
    {
        // Renderiza únicamente el campo oculto "website" (Honeypot) para atrapar bots
        return '
            <input type="text" name="website" id="website" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;">
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

        // 1. Detección por Honeypot: Si un bot llena el campo trampa "website", se rechaza
        if (!empty($data['website']) || !empty($_GET['website'])) {
            error_log("ANTIBOT: campo honeypot lleno (posible bot detectado)");
            return false;
        }

        // 2. Se confía en la validación del flujo y el sessionId manejados en send.php
        return true;
    }

    public static function rechazar(string $mensaje = 'Solicitud bloqueada por seguridad.'): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'antibot', 'message' => $mensaje]);
        exit;
    }
}