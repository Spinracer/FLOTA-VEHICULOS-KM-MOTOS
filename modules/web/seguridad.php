<?php
// ─────────────────────────────────────────────────────────
// FlotaControl — Web: Seguridad (2FA + Dashboard)
// ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/layout.php';
require_login();

$user = current_user();
$isAdmin = in_array($user['rol'], ['coordinador_it', 'admin']);

ob_start();
?>

<!-- KPI Cards (admin only) -->
<?php if ($isAdmin): ?>
<div id="security-kpis" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-surface border border-border rounded-xl p-5">
    <div class="text-xs text-muted uppercase tracking-widest mb-1">Usuarios con 2FA</div>
    <div class="text-2xl font-heading font-bold text-accent" id="kpi-2fa">—</div>
    <div class="text-xs text-muted mt-1" id="kpi-2fa-pct"></div>
  </div>
  <div class="bg-surface border border-border rounded-xl p-5">
    <div class="text-xs text-muted uppercase tracking-widest mb-1">Usuarios activos</div>
    <div class="text-2xl font-heading font-bold text-accent2" id="kpi-users">—</div>
  </div>
  <div class="bg-surface border border-border rounded-xl p-5">
    <div class="text-xs text-muted uppercase tracking-widest mb-1">Logins fallidos (24h)</div>
    <div class="text-2xl font-heading font-bold text-danger" id="kpi-failed">—</div>
  </div>
  <div class="bg-surface border border-border rounded-xl p-5">
    <div class="text-xs text-muted uppercase tracking-widest mb-1">Rate limits activos</div>
    <div class="text-2xl font-heading font-bold text-warning" id="kpi-rate">—</div>
  </div>
</div>
<?php endif; ?>

<!-- 2FA Configuration Section -->
<div class="bg-surface border border-border rounded-xl p-6 mb-6">
  <h2 class="font-heading font-bold text-xl text-accent mb-4">🔐 Autenticación de Dos Factores (2FA)</h2>

  <div id="2fa-status" class="mb-4">
    <div class="flex items-center gap-3 mb-4">
      <div id="2fa-badge-off" class="hidden">
        <span class="badge badge-gray px-3 py-1.5 text-sm">⚪ Desactivado</span>
      </div>
      <div id="2fa-badge-on" class="hidden">
        <span class="badge badge-green px-3 py-1.5 text-sm">🟢 Activado</span>
      </div>
    </div>

    <p class="text-sm text-muted mb-4">
      2FA agrega una capa extra de seguridad a tu cuenta. Al activarlo, necesitarás un código temporal
      de tu app de autenticación (Google Authenticator, Authy, etc.) cada vez que inicies sesión.
    </p>
  </div>

  <!-- Actions when 2FA is OFF -->
  <div id="2fa-actions-off" class="hidden">
    <button onclick="setup2FA()" class="btn btn-primary">🔐 Activar 2FA</button>
  </div>

  <!-- Actions when 2FA is ON -->
  <div id="2fa-actions-on" class="hidden">
    <button onclick="showDisable2FA()" class="btn btn-ghost border border-danger text-danger hover:bg-danger/10">⚠️ Desactivar 2FA</button>
  </div>

  <!-- Setup flow -->
  <div id="2fa-setup" class="hidden mt-6 border-t border-border pt-6">
    <h3 class="font-heading font-bold text-lg mb-3">Paso 1: Escanea el código QR</h3>
    <p class="text-sm text-muted mb-4">Abre tu aplicación de autenticación y escanea este código QR:</p>
    <div id="2fa-qr-container" class="mb-6"></div>

    <h3 class="font-heading font-bold text-lg mb-3">Paso 2: Verifica el código</h3>
    <p class="text-sm text-muted mb-3">Ingresa el código de 6 dígitos que muestra tu app:</p>
    <div class="flex items-center gap-3">
      <input type="text" id="2fa-verify-code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
             placeholder="000000"
             class="bg-dark border border-border rounded-lg px-4 py-2.5 text-slate-100 text-center text-xl tracking-[0.3em] font-mono w-44 focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none">
      <button onclick="verify2FA()" class="btn btn-primary">Verificar y activar</button>
      <button onclick="cancel2FASetup()" class="btn btn-ghost">Cancelar</button>
    </div>
  </div>

  <!-- Disable flow -->
  <div id="2fa-disable" class="hidden mt-6 border-t border-border pt-6">
    <h3 class="font-heading font-bold text-lg text-danger mb-3">⚠️ Desactivar 2FA</h3>
    <p class="text-sm text-muted mb-3">Confirma tu contraseña para desactivar 2FA:</p>
    <div class="flex items-center gap-3">
      <input type="password" id="2fa-disable-password" placeholder="Tu contraseña"
             class="bg-dark border border-border rounded-lg px-4 py-2.5 text-slate-100 text-sm w-64 focus:border-accent focus:ring-2 focus:ring-accent/20 outline-none">
      <button onclick="disable2FA()" class="btn btn-ghost border border-danger text-danger">Confirmar desactivación</button>
      <button onclick="cancelDisable2FA()" class="btn btn-ghost">Cancelar</button>
    </div>
  </div>
</div>

<!-- Security Features Info -->
<div class="bg-surface border border-border rounded-xl p-6 mb-6">
  <h2 class="font-heading font-bold text-xl text-accent mb-4">🛡️ Protecciones activas del sistema</h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="p-4 rounded-lg bg-surface2 border border-border">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-accent text-lg">🔑</span>
        <span class="font-bold text-sm">Protección CSRF</span>
      </div>
      <p class="text-xs text-muted">Todas las solicitudes de escritura requieren un token CSRF válido que previene ataques de falsificación de solicitudes.</p>
    </div>
    <div class="p-4 rounded-lg bg-surface2 border border-border">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-accent text-lg">⏱️</span>
        <span class="font-bold text-sm">Rate Limiting</span>
      </div>
      <p class="text-xs text-muted">Límite de 5 intentos de login por minuto, 60 escrituras/min y 120 lecturas/min por usuario para prevenir abuso.</p>
    </div>
    <div class="p-4 rounded-lg bg-surface2 border border-border">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-accent text-lg">🔐</span>
        <span class="font-bold text-sm">2FA Opcional</span>
      </div>
      <p class="text-xs text-muted">Autenticación de dos factores con TOTP compatible con Google Authenticator, Authy y similares.</p>
    </div>
  </div>
</div>

<!-- Security Events (admin only) -->
<?php if ($isAdmin): ?>
<div class="bg-surface border border-border rounded-xl p-6">
  <h2 class="font-heading font-bold text-xl text-accent mb-4">📋 Eventos de Seguridad Recientes</h2>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-muted border-b border-border">
          <th class="pb-2 pr-4">Fecha</th>
          <th class="pb-2 pr-4">Usuario</th>
          <th class="pb-2 pr-4">Acción</th>
          <th class="pb-2">Detalles</th>
        </tr>
      </thead>
      <tbody id="security-events"></tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

async function loadStatus() {
  try {
    const res = await api('/api/seguridad.php?action=2fa_status');
    if (res.totp_enabled) {
      document.getElementById('2fa-badge-on').classList.remove('hidden');
      document.getElementById('2fa-badge-off').classList.add('hidden');
      document.getElementById('2fa-actions-on').classList.remove('hidden');
      document.getElementById('2fa-actions-off').classList.add('hidden');
    } else {
      document.getElementById('2fa-badge-off').classList.remove('hidden');
      document.getElementById('2fa-badge-on').classList.add('hidden');
      document.getElementById('2fa-actions-off').classList.remove('hidden');
      document.getElementById('2fa-actions-on').classList.add('hidden');
    }
  } catch(e) { console.error(e); }

  if (isAdmin) loadStats();
}

async function loadStats() {
  try {
    const res = await api('/api/seguridad.php?action=stats');
    document.getElementById('kpi-2fa').textContent = res.users_with_2fa;
    document.getElementById('kpi-2fa-pct').textContent = res.total_users > 0
      ? `${Math.round(res.users_with_2fa / res.total_users * 100)}% de ${res.total_users} usuarios`
      : '';
    document.getElementById('kpi-users').textContent = res.total_users;
    document.getElementById('kpi-failed').textContent = res.failed_logins_24h;
    document.getElementById('kpi-rate').textContent = res.rate_limit_entries;

    // Events table
    const tbody = document.getElementById('security-events');
    tbody.innerHTML = (res.events || []).map(e => {
      const accionBadge = {
        'login': '<span class="badge badge-green">Login</span>',
        'login_failed': '<span class="badge badge-red">Login fallido</span>',
        'logout': '<span class="badge badge-gray">Logout</span>',
        '2fa_enabled': '<span class="badge badge-blue">2FA activado</span>',
        '2fa_disabled': '<span class="badge badge-orange">2FA desactivado</span>',
        '2fa_verified': '<span class="badge badge-green">2FA verificado</span>',
        '2fa_admin_reset': '<span class="badge badge-red">2FA reset (admin)</span>',
      }[e.accion] || `<span class="badge badge-gray">${e.accion}</span>`;
      const details = e.despues_json ? (() => { try { const d = JSON.parse(e.despues_json); return d.email || ''; } catch { return ''; } })() : '';
      return `<tr class="border-b border-border/50 hover:bg-surface2/50">
        <td class="py-2 pr-4 text-xs text-muted">${new Date(e.created_at).toLocaleString('es')}</td>
        <td class="py-2 pr-4">${e.user_nombre || '—'}</td>
        <td class="py-2 pr-4">${accionBadge}</td>
        <td class="py-2 text-xs text-muted">${details}</td>
      </tr>`;
    }).join('');
  } catch(e) { console.error(e); }
}

async function setup2FA() {
  try {
    const res = await api('/api/seguridad.php?action=2fa_setup', 'POST', {});
    // Show QR code
    const container = document.getElementById('2fa-qr-container');
    const qr = qrcode(0, 'M');
    qr.addData(res.uri);
    qr.make();
    container.innerHTML = `
      <div class="text-center">
        <div class="inline-block bg-white p-4 rounded-xl mb-3">${qr.createSvgTag(5, 0)}</div>
        <div class="mt-2">
          <p class="text-xs text-muted mb-1">O ingresa este código manualmente:</p>
          <code class="text-accent font-mono text-lg tracking-widest select-all">${res.secret}</code>
        </div>
      </div>`;
    document.getElementById('2fa-setup').classList.remove('hidden');
    document.getElementById('2fa-actions-off').classList.add('hidden');
    document.getElementById('2fa-verify-code').focus();
  } catch(e) {
    toast(e.message, 'error');
  }
}

async function verify2FA() {
  const code = document.getElementById('2fa-verify-code').value.trim();
  if (code.length !== 6) { toast('Ingresa un código de 6 dígitos', 'error'); return; }
  try {
    await api('/api/seguridad.php?action=2fa_enable', 'POST', { code });
    toast('¡2FA activado correctamente!');
    document.getElementById('2fa-setup').classList.add('hidden');
    loadStatus();
  } catch(e) {
    toast(e.message, 'error');
  }
}

function cancel2FASetup() {
  document.getElementById('2fa-setup').classList.add('hidden');
  document.getElementById('2fa-actions-off').classList.remove('hidden');
}

function showDisable2FA() {
  document.getElementById('2fa-disable').classList.remove('hidden');
  document.getElementById('2fa-actions-on').classList.add('hidden');
  document.getElementById('2fa-disable-password').focus();
}

async function disable2FA() {
  const password = document.getElementById('2fa-disable-password').value;
  if (!password) { toast('Ingresa tu contraseña', 'error'); return; }
  try {
    await api('/api/seguridad.php?action=2fa_disable', 'POST', { password });
    toast('2FA desactivado', 'warning');
    document.getElementById('2fa-disable').classList.add('hidden');
    document.getElementById('2fa-disable-password').value = '';
    loadStatus();
  } catch(e) {
    toast(e.message, 'error');
  }
}

function cancelDisable2FA() {
  document.getElementById('2fa-disable').classList.add('hidden');
  document.getElementById('2fa-actions-on').classList.remove('hidden');
  document.getElementById('2fa-disable-password').value = '';
}

document.addEventListener('DOMContentLoaded', loadStatus);
</script>

<?php $content = ob_get_clean(); echo render_layout('Seguridad', 'seguridad', $content); ?>
