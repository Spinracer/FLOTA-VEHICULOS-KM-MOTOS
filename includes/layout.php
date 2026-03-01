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
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>

<aside id="sidebar">
  <div class="logo">
    <div class="logo-text">FlotaCtrl</div>
    <div class="logo-sub">Sistema de Flotas</div>
  </div>
  <nav>
    <div class="nav-section">Principal</div>
    <a href="/dashboard.php" class="nav-item <?= $active_page==='dashboard'?'active':'' ?>">
      <span class="nav-icon">📊</span><span>Dashboard</span>
    </a>
    <a href="/vehiculos.php" class="nav-item <?= $active_page==='vehiculos'?'active':'' ?>">
      <span class="nav-icon">🚗</span><span>Vehículos</span>
    </a>
    <a href="/asignaciones.php" class="nav-item <?= $active_page==='asignaciones'?'active':'' ?>">
      <span class="nav-icon">📝</span><span>Asignaciones</span>
    </a>
    <a href="/combustible.php" class="nav-item <?= $active_page==='combustible'?'active':'' ?>">
      <span class="nav-icon">⛽</span><span>Combustible</span>
    </a>

    <div class="nav-section">Gestión</div>
    <a href="/mantenimientos.php" class="nav-item <?= $active_page==='mantenimientos'?'active':'' ?>">
      <span class="nav-icon">🔧</span><span>Mantenimientos</span>
    </a>
    <a href="/incidentes.php" class="nav-item <?= $active_page==='incidentes'?'active':'' ?>">
      <span class="nav-icon">⚠️</span><span>Incidentes</span>
    </a>
    <a href="/recordatorios.php" class="nav-item <?= $active_page==='recordatorios'?'active':'' ?>">
      <span class="nav-icon">🔔</span><span>Recordatorios</span>
    </a>
    <a href="/reportes.php" class="nav-item <?= $active_page==='reportes'?'active':'' ?>">
      <span class="nav-icon">📈</span><span>Reportes</span>
    </a>
    <a href="/componentes.php" class="nav-item <?= $active_page==='componentes'?'active':'' ?>">
      <span class="nav-icon">🧰</span><span>Componentes</span>
    </a>
    <a href="/preventivos.php" class="nav-item <?= $active_page==='preventivos'?'active':'' ?>">
      <span class="nav-icon">📅</span><span>Preventivos</span>
    </a>

    <?php if ($can_edit || $is_admin): ?>
    <div class="nav-section">Administración</div>
    <a href="/operadores.php" class="nav-item <?= $active_page==='operadores'?'active':'' ?>">
      <span class="nav-icon">👤</span><span>Operadores</span>
    </a>
    <a href="/proveedores.php" class="nav-item <?= $active_page==='proveedores'?'active':'' ?>">
      <span class="nav-icon">🏪</span><span>Proveedores</span>
    </a>
    <a href="/sucursales.php" class="nav-item <?= $active_page==='sucursales'?'active':'' ?>">
      <span class="nav-icon">🏢</span><span>Sucursales</span>
    </a>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="nav-section">Sistema</div>
    <a href="/catalogos.php" class="nav-item <?= $active_page==='catalogos'?'active':'' ?>">
      <span class="nav-icon">🗂️</span><span>Catálogos</span>
    </a>
    <a href="/usuarios.php" class="nav-item <?= $active_page==='usuarios'?'active':'' ?>">
      <span class="nav-icon">🔑</span><span>Usuarios</span>
    </a>
    <a href="/auditoria.php" class="nav-item <?= $active_page==='auditoria'?'active':'' ?>">
      <span class="nav-icon">📜</span><span>Auditoría</span>
    </a>
    <a href="/permisos.php" class="nav-item <?= $active_page==='permisos'?'active':'' ?>">
      <span class="nav-icon">🔐</span><span>Permisos</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
        <div class="user-role badge <?= role_badge($rol) ?>"><?= role_label($rol) ?></div>
      </div>
    </div>
    <a href="/logout.php" class="btn-logout">Cerrar sesión</a>
  </div>
</aside>

<div id="main">
  <header class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
    <div class="topbar-actions" id="topbar-actions">
      <div id="notif-bell" style="position:relative;cursor:pointer;margin-right:12px" onclick="toggleNotifPanel()">
        <span style="font-size:20px">🔔</span>
        <span id="notif-badge" style="display:none;position:absolute;top:-4px;right:-6px;background:#ff4757;color:#fff;font-size:10px;border-radius:50%;width:18px;height:18px;text-align:center;line-height:18px;font-weight:700"></span>
      </div>
      <div id="notif-panel" style="display:none;position:absolute;right:12px;top:52px;width:360px;max-height:420px;background:var(--card-bg,#111318);border:1px solid var(--border,#222730);border-radius:12px;z-index:999;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,0.5)">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border,#222730);display:flex;justify-content:space-between;align-items:center">
          <strong style="font-size:14px">Notificaciones</strong>
          <button class="btn btn-ghost btn-sm" onclick="markAllRead()" style="font-size:11px">Marcar todas leídas</button>
        </div>
        <div id="notif-list" style="padding:8px"></div>
      </div>
      <?php if ($rol === 'monitoreo'): ?>
        <span class="badge badge-cyan" style="padding:6px 12px;font-size:11px">👁 Modo solo lectura</span>
      <?php endif; ?>
    </div>
  </header>
  <script src="/assets/app.js"></script>
  <div class="page-content">
    <?= $content ?>
  </div>
</div>

<div id="toast-container"></div>

<script>
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
      if (d.unread > 0) { badge.textContent = d.unread > 9 ? '9+' : d.unread; badge.style.display = ''; }
      else { badge.style.display = 'none'; }
    }
  } catch(e) {}
}
function toggleNotifPanel() {
  notifOpen = !notifOpen;
  document.getElementById('notif-panel').style.display = notifOpen ? '' : 'none';
  if (notifOpen) loadNotifs();
}
async function loadNotifs() {
  try {
    const r = await fetch('/api/notificaciones.php?all=1&limit=20');
    if (!r.ok) return;
    const d = await r.json();
    const list = document.getElementById('notif-list');
    if (!d.rows || !d.rows.length) { list.innerHTML = '<div style="text-align:center;padding:24px;color:#8892a4;font-size:13px">Sin notificaciones</div>'; return; }
    const icons = {alerta:'🚨',info:'ℹ️',exito:'✅',warning:'⚠️'};
    list.innerHTML = d.rows.map(n => `
      <div style="padding:10px 12px;border-radius:8px;margin-bottom:4px;background:${Number(n.leida)?'transparent':'rgba(232,255,71,0.05)'};cursor:pointer;font-size:13px" onclick="readNotif(${n.id},this)">
        <div style="display:flex;justify-content:space-between;align-items:start">
          <span>${icons[n.tipo]||'📌'} <strong>${n.titulo}</strong></span>
          <small style="color:#555;white-space:nowrap;margin-left:8px">${n.created_at?.slice(5,16)||''}</small>
        </div>
        <div style="color:#8892a4;margin-top:4px">${n.mensaje}</div>
      </div>`).join('');
  } catch(e) {}
}
async function readNotif(id, el) {
  await fetch(`/api/notificaciones.php?id=${id}`, {method:'PUT'});
  el.style.background = 'transparent';
  pollNotifs();
}
async function markAllRead() {
  await fetch('/api/notificaciones.php?all=1', {method:'PUT'});
  pollNotifs(); loadNotifs();
}
// Close panel on outside click
document.addEventListener('click', e => {
  if (notifOpen && !e.target.closest('#notif-bell') && !e.target.closest('#notif-panel')) {
    notifOpen = false; document.getElementById('notif-panel').style.display = 'none';
  }
});
pollNotifs();
setInterval(pollNotifs, 30000);
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
