<?php
require_once __DIR__ . '/auth.php';
$user = current_user();

function render_layout(string $page_title, string $active_page, string $content) {
    global $user;
    ob_start();
    $rol = $user['rol'];
    $is_admin   = in_array($rol, ['coordinador_it', 'admin']);
    $can_edit   = can('edit');
    $can_create = can('create');

    // Helper para nav items
    $nav = function($href, $page, $icon, $label) use ($active_page) {
        $active = $active_page === $page;
        $base = 'group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 nav-item';
        $cls = $active
            ? $base . ' bg-accent text-dark active'
            : $base . ' text-slate-400 hover:bg-surface2 hover:text-slate-100';
        return "<a href=\"{$href}\" class=\"{$cls}\"><span class=\"text-base w-5 text-center nav-icon\">{$icon}</span><span class=\"nav-label\">{$label}</span></a>";
    };
    ?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= csrf_meta() ?>
<title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<script>const _tw=console.warn;console.warn=function(...a){if(a[0]&&typeof a[0]==='string'&&a[0].includes('cdn.tailwindcss.com'))return;_tw.apply(console,a)};</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        dark:     '#0a0c10',
        surface:  '#111318',
        surface2: '#181c24',
        border:   '#222730',
        accent:   '#e8ff47',
        accent2:  '#47ffe8',
        danger:   '#ff4757',
        warning:  '#ffa502',
        success:  '#2ed573',
        info:     '#1e90ff',
        muted:    '#8892a4',
      },
      fontFamily: {
        heading: ['Syne', 'sans-serif'],
        body:    ['DM Sans', 'sans-serif'],
      },
      borderRadius: {
        xl: '12px',
        '2xl': '16px',
      },
    }
  }
}
</script>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="font-body bg-dark text-slate-100 min-h-screen flex dark:bg-dark light:bg-gray-50 light:text-gray-900">

<!-- Sidebar backdrop (mobile) -->
<div id="sidebar-backdrop" class="fixed inset-0 bg-black/50 z-[99] hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- Mobile sidebar toggle -->
<button id="sidebar-toggle" class="fixed top-4 left-4 z-[110] lg:hidden bg-surface border border-border rounded-lg p-2 text-accent" onclick="toggleSidebar()">
  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
</button>

<aside id="sidebar" class="w-60 min-h-screen bg-surface border-r border-border flex flex-col fixed top-0 left-0 bottom-0 z-[100] transition-transform duration-300 lg:translate-x-0 -translate-x-full sidebar-closed">
  <!-- Logo -->
  <div class="px-6 pt-7 pb-5 border-b border-border">
    <div class="font-heading font-extrabold text-[22px] text-accent tracking-tight">FlotaCtrl</div>
    <div class="text-[11px] text-muted tracking-[2px] uppercase mt-0.5">IT y Seguridad</div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-3 pb-1.5 nav-section-label">Principal</div>
    <?= $nav('/dashboard.php', 'dashboard', '📊', 'Dashboard') ?>
    <?= $nav('/vehiculos.php', 'vehiculos', '🚗', 'Vehículos') ?>
    <?php if ($can_create): ?>
    <?= $nav('/importacion_vehiculos.php', 'importacion_vehiculos', '📥', 'Importar Vehículos') ?>
    <?php endif; ?>
    <?= $nav('/asignaciones.php', 'asignaciones', '📝', 'Asignaciones') ?>
    <?= $nav('/combustible.php', 'combustible', '⛽', 'Combustible') ?>

    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-4 pb-1.5 nav-section-label">Gestión</div>
    <?= $nav('/mantenimientos.php', 'mantenimientos', '🔧', 'Mantenimientos') ?>
    <?= $nav('/incidentes.php', 'incidentes', '⚠️', 'Incidentes') ?>
    <?= $nav('/recordatorios.php', 'recordatorios', '🔔', 'Recordatorios') ?>
    <?= $nav('/reportes.php', 'reportes', '📈', 'Reportes') ?>
    <?= $nav('/componentes.php', 'componentes', '🧰', 'Catálogo') ?>
    <?= $nav('/preventivos.php', 'preventivos', '📅', 'Preventivos') ?>
    <?= $nav('/alertas.php', 'alertas', '🚨', 'Centro de Alertas') ?>

    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-4 pb-1.5 nav-section-label">Compras y Docs</div>
    <?= $nav('/ordenes_compra.php', 'ordenes_compra', '🛒', 'Órdenes de Compra') ?>
    <?= $nav('/vehiculo_documentos.php', 'vehiculo_documentos', '📄', 'Docs Vehiculares') ?>

    <?php if ($can_edit || $is_admin): ?>
    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-4 pb-1.5 nav-section-label">Administración</div>
    <?= $nav('/operadores.php', 'operadores', '👤', 'Operadores') ?>
    <?= $nav('/proveedores.php', 'proveedores', '🏪', 'Proveedores') ?>
    <?= $nav('/sucursales.php', 'sucursales', '🏢', 'Sucursales') ?>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-4 pb-1.5 nav-section-label">Sistema</div>
    <?= $nav('/catalogos.php', 'catalogos', '🗂️', 'Catálogos') ?>
    <?= $nav('/usuarios.php', 'usuarios', '🔑', 'Usuarios') ?>
    <?= $nav('/auditoria.php', 'auditoria', '📜', 'Auditoría') ?>
    <?= $nav('/permisos.php', 'permisos', '🔐', 'Permisos') ?>
    <?= $nav('/seguridad.php', 'seguridad', '🛡️', 'Seguridad') ?>
    <?php else: ?>
    <div class="text-[10px] text-muted tracking-[2px] uppercase px-3 pt-4 pb-1.5 nav-section-label">Mi Cuenta</div>
    <?= $nav('/seguridad.php', 'seguridad', '🛡️', 'Seguridad 2FA') ?>
    <?php endif; ?>
  </nav>

  <!-- Sidebar Footer -->
  <div class="px-4 py-4 border-t border-border">
    <div class="flex items-center gap-3 mb-3">
      <div class="w-9 h-9 rounded-full bg-accent text-dark flex items-center justify-center font-extrabold text-sm shrink-0">
        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-[13px] font-semibold truncate user-name"><?= htmlspecialchars($user['nombre']) ?></div>
        <div class="badge <?= role_badge($rol) ?> text-[10px] mt-0.5 user-role"><?= role_label($rol) ?></div>
      </div>
    </div>
    <a href="/logout.php" class="block text-center py-1.5 rounded-lg bg-surface2 border border-border text-muted text-xs hover:border-danger hover:text-danger transition-all duration-200 btn-logout-link">Cerrar sesión</a>
  </div>
</aside>

<div id="main" class="ml-0 lg:ml-60 flex-1 min-h-screen flex flex-col min-w-0 overflow-x-hidden">
  <!-- Topbar -->
  <header class="h-16 border-b border-border flex items-center justify-between px-4 sm:px-8 bg-surface sticky top-0 z-50">
    <h1 class="font-heading text-lg font-bold pl-10 lg:pl-0"><?= htmlspecialchars($page_title) ?></h1>
    <div class="flex gap-3 items-center" id="topbar-actions">
      <!-- Theme toggle -->
      <button id="theme-toggle" onclick="toggleTheme()" class="p-2 rounded-lg hover:bg-surface2 transition-colors" title="Cambiar tema">
        <span id="theme-icon-dark" class="text-lg">🌙</span>
        <span id="theme-icon-light" class="text-lg hidden">☀️</span>
      </button>
      <!-- Notifications -->
      <div id="notif-bell" class="relative cursor-pointer p-2 rounded-lg hover:bg-surface2 transition-colors" onclick="toggleNotifPanel()">
        <span class="text-xl">🔔</span>
        <span id="notif-badge" class="hidden absolute -top-1 -right-1 bg-danger text-white text-[10px] rounded-full w-[18px] h-[18px] text-center leading-[18px] font-bold"></span>
      </div>
      <!-- Notifications Panel -->
      <div id="notif-panel" class="hidden absolute right-3 top-14 w-[calc(100vw-24px)] sm:w-[360px] max-h-[420px] bg-surface border border-border rounded-xl z-[999] overflow-y-auto shadow-2xl">
        <div class="px-4 py-3.5 border-b border-border flex justify-between items-center">
          <strong class="text-sm">Notificaciones</strong>
          <button class="btn btn-ghost btn-sm text-[11px]" onclick="markAllRead()">Marcar todas leídas</button>
        </div>
        <div id="notif-list" class="p-2"></div>
      </div>
      <?php if ($rol === 'visitante'): ?>
        <span class="badge badge-gray px-3 py-1.5 text-[11px]">👁 Solo lectura</span>
      <?php endif; ?>
    </div>
  </header>

  <script src="/assets/app.js?v=<?= filemtime(__DIR__.'/../assets/app.js') ?>"></script>
  <div class="page-content flex-1 p-4 sm:p-8 min-w-0 overflow-x-hidden">
    <?= $content ?>
  </div>
</div>

<div id="toast-container" class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2"></div>

<script>
// ── Theme toggle ──
function getTheme() {
  return localStorage.getItem('fc-theme') || 'dark';
}
function applyTheme(theme) {
  const html = document.documentElement;
  if (theme === 'light') {
    html.classList.remove('dark');
    html.classList.add('light');
    document.body.classList.remove('bg-dark', 'text-slate-100');
    document.body.classList.add('bg-gray-50', 'text-gray-900');
    document.getElementById('theme-icon-dark').classList.add('hidden');
    document.getElementById('theme-icon-light').classList.remove('hidden');
  } else {
    html.classList.add('dark');
    html.classList.remove('light');
    document.body.classList.add('bg-dark', 'text-slate-100');
    document.body.classList.remove('bg-gray-50', 'text-gray-900');
    document.getElementById('theme-icon-dark').classList.remove('hidden');
    document.getElementById('theme-icon-light').classList.add('hidden');
  }
}
function toggleTheme() {
  const current = getTheme();
  const next = current === 'dark' ? 'light' : 'dark';
  localStorage.setItem('fc-theme', next);
  applyTheme(next);
}
applyTheme(getTheme());

// Pasar permisos del rol al JS
const USER_ROLE = <?= json_encode($rol) ?>;
const USER_CAN  = <?= json_encode(array_values(ROLE_PERMISSIONS[$rol] ?? [])) ?>;
function userCan(perm) { return USER_CAN.includes(perm); }

// Permisos granulares por módulo (cargado async)
let MODULE_PERMS = {};
async function loadModulePerms() {
  try {
    const r = await fetch('/api/permisos.php');
    if (r.ok) {
      const d = await r.json();
      MODULE_PERMS = (d.matrix && d.matrix[USER_ROLE]) || {};
    }
  } catch(e) {}
}
function userCanModule(mod, perm) {
  if (['coordinador_it','admin'].includes(USER_ROLE)) return true;
  return MODULE_PERMS[mod] && MODULE_PERMS[mod].includes(perm);
}
if (USER_ROLE !== 'coordinador_it') loadModulePerms();

// Notificaciones en tiempo real
let notifOpen = false;
async function pollNotifs() {
  try {
    const r = await fetch('/api/notificaciones.php?count=1');
    if (r.ok) {
      const d = await r.json();
      const badge = document.getElementById('notif-badge');
      if (d.unread > 0) { badge.textContent = d.unread > 9 ? '9+' : d.unread; badge.style.display = ''; badge.classList.remove('hidden'); }
      else { badge.classList.add('hidden'); }
    }
  } catch(e) {}
}
function toggleNotifPanel() {
  notifOpen = !notifOpen;
  const panel = document.getElementById('notif-panel');
  if (notifOpen) { panel.classList.remove('hidden'); loadNotifs(); }
  else { panel.classList.add('hidden'); }
}
async function loadNotifs() {
  try {
    const r = await fetch('/api/notificaciones.php?all=1&limit=20');
    if (!r.ok) return;
    const d = await r.json();
    const list = document.getElementById('notif-list');
    if (!d.rows || !d.rows.length) { list.innerHTML = '<div class="text-center py-6 text-muted text-sm">Sin notificaciones</div>'; return; }
    const icons = {alerta:'🚨',info:'ℹ️',exito:'✅',warning:'⚠️'};
    list.innerHTML = d.rows.map(n => `
      <div class="px-3 py-2.5 rounded-lg mb-1 cursor-pointer text-sm transition-colors hover:bg-surface2 ${Number(n.leida)?'':'bg-accent/5'}" onclick="readNotif(${n.id},this)">
        <div class="flex justify-between items-start">
          <span>${icons[n.tipo]||'📌'} <strong>${n.titulo}</strong></span>
          <small class="text-muted whitespace-nowrap ml-2 text-[11px]">${n.created_at?.slice(5,16)||''}</small>
        </div>
        <div class="text-muted mt-1 text-[13px]">${n.mensaje}</div>
      </div>`).join('');
  } catch(e) {}
}
async function readNotif(id, el) {
  await fetch(`/api/notificaciones.php?id=${id}`, {method:'PUT', headers:{'X-CSRF-Token': getCsrfToken()}});
  el.style.background = 'transparent';
  pollNotifs();
}
async function markAllRead() {
  await fetch('/api/notificaciones.php?all=1', {method:'PUT', headers:{'X-CSRF-Token': getCsrfToken()}});
  pollNotifs(); loadNotifs();
}
// Close panel on outside click
document.addEventListener('click', e => {
  if (notifOpen && !e.target.closest('#notif-bell') && !e.target.closest('#notif-panel')) {
    notifOpen = false; document.getElementById('notif-panel').classList.add('hidden');
  }
});
// Sidebar toggle helpers (mobile)
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('sidebar-backdrop');
  sb.classList.toggle('sidebar-open');
  bd.classList.toggle('hidden');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('sidebar-open');
  document.getElementById('sidebar-backdrop').classList.add('hidden');
}
// Close sidebar on nav click (mobile)
document.querySelectorAll('#sidebar a.nav-item').forEach(a => {
  a.addEventListener('click', () => { if (window.innerWidth < 1024) closeSidebar(); });
});
pollNotifs();
setInterval(pollNotifs, 30000);
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
