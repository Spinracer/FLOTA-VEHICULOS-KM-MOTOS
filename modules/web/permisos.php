<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
require_admin();
$db = getDB();
ob_start();
?>
<style>
.perms-grid { overflow-x:auto; margin-top:16px; }
.perms-grid table { width:100%; border-collapse:collapse; font-size:13px; }
.perms-grid th, .perms-grid td { padding:8px 10px; border:1px solid #222730; text-align:center; }
.perms-grid th { background:#181c24; color:#e8ff47; font-weight:600; position:sticky; top:0; z-index:2; }
.perms-grid th:first-child { text-align:left; min-width:140px; }
.perms-grid td:first-child { text-align:left; font-weight:500; background:#13151b; }
.perm-check { width:16px; height:16px; accent-color:#e8ff47; cursor:pointer; }
.perm-check:disabled { accent-color:#555; cursor:not-allowed; opacity:.5; }
.save-bar { margin-top:14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.user-select-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.user-select-wrap select { min-width:260px; font-size:14px; }
.user-info { font-size:12px; color:var(--text2); display:flex; gap:12px; align-items:center; }
.user-info .badge { font-size:11px; }
</style>

<div class="toolbar">
  <h3 style="margin:0;font-size:16px;">🔐 Permisos por Usuario</h3>
</div>

<div class="toolbar" style="margin-top:6px;">
  <div class="user-select-wrap">
    <label style="font-weight:600;color:var(--accent);font-size:13px;">👤 Usuario:</label>
    <select id="user-select" onchange="switchUser()" style="max-width:320px"></select>
    <div class="user-info" id="user-info"></div>
  </div>
</div>

<div class="perms-grid">
  <table id="perms-table">
    <thead><tr id="perms-head"></tr></thead>
    <tbody id="perms-body"></tbody>
  </table>
</div>
<div class="save-bar" id="save-bar">
  <button class="btn btn-primary" onclick="guardarPermisos()">💾 Guardar cambios</button>
  <button class="btn btn-ghost" onclick="initFromRole()" title="Copiar permisos del rol base al usuario seleccionado">🔄 Resetear desde rol</button>
  <span id="save-status" style="font-size:12px;color:#8892a4;"></span>
</div>

<script>
const PERM_LABELS = { view:'👁 Ver', create:'➕ Crear', edit:'✏️ Editar', 'delete':'🗑 Eliminar' };
const MOD_LABELS = {
  vehiculos:'Vehículos', asignaciones:'Asignaciones', mantenimientos:'Mantenimientos',
  combustible:'Combustible', incidentes:'Incidentes', recordatorios:'Recordatorios',
  operadores:'Operadores', proveedores:'Proveedores',
  preventivos:'Preventivos', reportes:'Reportes',
  usuarios:'Usuarios', auditoria:'Auditoría', sucursales:'Sucursales', notificaciones:'Notificaciones'
};
const ROLE_LABELS = <?= json_encode(ROLES) ?>;
const ADMIN_ROLES = ['coordinador_it','admin'];
let matrix={}, roleMatrix={}, modulos=[], permisos=[], users=[], activeUserId=0;

async function loadMatrix() {
  try {
    const data = await api('/api/permisos.php');
    matrix     = data.matrix || {};
    roleMatrix = data.roleMatrix || {};
    modulos    = data.modulos || [];
    permisos   = data.permisos || [];
    users      = data.users || [];
    renderSelect();
    renderTable();
  } catch(e) { console.error('Error loading permisos:', e); }
}

function renderSelect() {
  const sel = document.getElementById('user-select');
  const prev = activeUserId;
  sel.innerHTML = '<option value="">— Seleccione un usuario —</option>' + users.map(u =>
    `<option value="${u.id}" ${u.id===prev?'selected':''}>${u.nombre} (${ROLE_LABELS[u.rol]||u.rol}) — ${u.email}</option>`
  ).join('');
  if (!prev && users.length) { activeUserId = users[0].id; sel.value = activeUserId; }
  updateUserInfo();
}

function switchUser() {
  activeUserId = parseInt(document.getElementById('user-select').value) || 0;
  updateUserInfo();
  renderTable();
}

function updateUserInfo() {
  const info = document.getElementById('user-info');
  const bar = document.getElementById('save-bar');
  const user = users.find(u => u.id === activeUserId);
  if (!user) { info.innerHTML = ''; bar.style.display = 'none'; return; }
  const isAdmin = ADMIN_ROLES.includes(user.rol);
  const customized = isCustomized(activeUserId);
  info.innerHTML = `<span class="badge badge-gray">${ROLE_LABELS[user.rol]||user.rol}</span>` +
    (isAdmin ? '<span style="color:#e8ff47">⚡ Acceso total (admin)</span>' :
     customized ? '<span style="color:#2ed573">✅ Permisos personalizados</span>' :
     '<span style="color:#8892a4">📋 Usando permisos de rol</span>');
  bar.style.display = isAdmin ? 'none' : 'flex';
}

function getEffectivePerms(userId, mod) {
  const user = users.find(u => u.id === userId);
  if (user && ADMIN_ROLES.includes(user.rol)) return [...permisos]; // admin = todo
  if (matrix[userId] && Object.keys(matrix[userId]).length > 0) {
    return matrix[userId][mod] || [];
  }
  if (user && roleMatrix[user.rol]) {
    return roleMatrix[user.rol][mod] || [];
  }
  return [];
}

function isCustomized(userId) {
  return matrix[userId] && Object.keys(matrix[userId]).length > 0;
}

function renderTable() {
  const head = document.getElementById('perms-head');
  const body = document.getElementById('perms-body');
  if (!activeUserId) {
    head.innerHTML = '';
    body.innerHTML = '<tr><td style="text-align:center;padding:40px;color:#8892a4;font-size:14px" colspan="5">👆 Seleccione un usuario para ver y editar sus permisos</td></tr>';
    return;
  }
  const user = users.find(u => u.id === activeUserId);
  const isAdmin = user && ADMIN_ROLES.includes(user.rol);
  const customized = isCustomized(activeUserId);
  head.innerHTML = '<th>Módulo</th>' + permisos.map(p => `<th>${PERM_LABELS[p]||p}</th>`).join('');

  let info = '';
  if (isAdmin) {
    info = `<tr><td colspan="${permisos.length+1}" style="background:#181c24;font-size:12px;color:#e8ff47;text-align:center;padding:10px">
      ⚡ Los usuarios con rol Coordinador IT / Admin tienen acceso total. No es necesario configurar permisos.
    </td></tr>`;
  } else if (!customized) {
    info = `<tr><td colspan="${permisos.length+1}" style="background:#181c24;font-size:12px;color:var(--accent);text-align:center;padding:10px">
      📋 Este usuario usa permisos heredados del rol <strong>${ROLE_LABELS[user?.rol]||''}</strong>. Edite los checkboxes y guarde para personalizar, o use "Resetear desde rol" para copiar los del rol como base.
    </td></tr>`;
  }

  body.innerHTML = info + modulos.map(mod => {
    const effPerms = getEffectivePerms(activeUserId, mod);
    const cells = permisos.map(p => {
      const checked = effPerms.includes(p) ? 'checked' : '';
      const disabled = isAdmin ? 'disabled' : '';
      return `<td><input type="checkbox" class="perm-check" data-mod="${mod}" data-perm="${p}" ${checked} ${disabled}></td>`;
    }).join('');
    return `<tr><td>${MOD_LABELS[mod]||mod}</td>${cells}</tr>`;
  }).join('');
}

async function guardarPermisos() {
  if (!activeUserId) return;
  const user = users.find(u => u.id === activeUserId);
  if (user && ADMIN_ROLES.includes(user.rol)) return;
  const status = document.getElementById('save-status');
  status.textContent = 'Guardando...';
  const checks = document.querySelectorAll('#perms-body .perm-check');
  const byModule = {};
  checks.forEach(c => {
    const mod = c.dataset.mod;
    if (!byModule[mod]) byModule[mod] = [];
    if (c.checked) byModule[mod].push(c.dataset.perm);
  });
  let ok = 0, fail = 0;
  for (const [mod, prms] of Object.entries(byModule)) {
    try {
      await api('/api/permisos.php', 'PUT', { user_id: activeUserId, modulo: mod, permisos: prms });
      ok++;
    } catch(e) { fail++; }
  }
  if (!matrix[activeUserId]) matrix[activeUserId] = {};
  for (const [mod, prms] of Object.entries(byModule)) {
    matrix[activeUserId][mod] = prms;
  }
  status.textContent = `✅ ${ok} módulos actualizados` + (fail ? `, ${fail} errores` : '');
  toast(`Permisos de ${user?.nombre||'usuario'} actualizados (${ok} módulos).`,'success');
  updateUserInfo();
  renderTable();
  setTimeout(() => status.textContent = '', 3000);
}

async function initFromRole() {
  const user = users.find(u => u.id === activeUserId);
  if (!user) return;
  if (!confirm(`¿Resetear permisos de "${user.nombre}" desde su rol "${ROLE_LABELS[user.rol]||user.rol}"?\nEsto sobreescribirá cualquier personalización.`)) return;
  try {
    await api('/api/permisos.php?action=init_user', 'POST', { user_id: activeUserId });
    toast('Permisos restablecidos desde rol', 'success');
    await loadMatrix();
  } catch(e) { toast('Error al resetear', 'error'); }
}

loadMatrix();
</script>
<?php
$content = ob_get_clean();
echo render_layout('Permisos por Usuario', 'permisos', $content);
