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
.user-tab { display:inline-block; padding:8px 18px; cursor:pointer; border-radius:8px 8px 0 0; background:#181c24; color:#8892a4; border:1px solid #222730; border-bottom:none; font-size:13px; margin-right:2px; transition:.2s; }
.user-tab.active { background:#1a1e27; color:#e8ff47; font-weight:600; }
.user-tab .tab-role { font-size:10px; color:var(--text2); display:block; }
.save-bar { margin-top:14px; display:flex; gap:10px; align-items:center; }
</style>

<div class="toolbar">
  <h3 style="margin:0;font-size:16px;">🔐 Permisos por Usuario</h3>
  <span style="font-size:12px;color:var(--text2);margin-left:12px">El admin personaliza los permisos de cada usuario individualmente</span>
</div>

<div id="user-tabs" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:2px;"></div>
<div class="perms-grid">
  <table id="perms-table">
    <thead><tr id="perms-head"></tr></thead>
    <tbody id="perms-body"></tbody>
  </table>
</div>
<div class="save-bar">
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
let matrix={}, roleMatrix={}, modulos=[], permisos=[], users=[], activeUserId=0;

async function loadMatrix() {
  try {
    const data = await api('/api/permisos.php');
    matrix     = data.matrix || {};
    roleMatrix = data.roleMatrix || {};
    modulos    = data.modulos || [];
    permisos   = data.permisos || [];
    users      = data.users || [];
    if (!activeUserId && users.length) activeUserId = users[0].id;
    renderTabs();
    renderTable();
  } catch(e) { console.error('Error loading permisos:', e); }
}

function renderTabs() {
  document.getElementById('user-tabs').innerHTML = users.map(u =>
    `<div class="user-tab ${u.id===activeUserId?'active':''}" onclick="switchUser(${u.id})">
      ${u.nombre}<span class="tab-role">${ROLE_LABELS[u.rol]||u.rol}</span>
    </div>`
  ).join('');
}

function switchUser(id) {
  activeUserId = id;
  renderTabs();
  renderTable();
}

function getEffectivePerms(userId, mod) {
  // Si el usuario tiene permisos personalizados, usarlos
  if (matrix[userId] && Object.keys(matrix[userId]).length > 0) {
    return matrix[userId][mod] || [];
  }
  // Si no, usar los del rol como default visual
  const user = users.find(u => u.id === userId);
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
  const customized = isCustomized(activeUserId);
  head.innerHTML = '<th>Módulo</th>' + permisos.map(p => `<th>${PERM_LABELS[p]||p}</th>`).join('');

  let info = '';
  if (!customized) {
    const user = users.find(u => u.id === activeUserId);
    info = `<tr><td colspan="${permisos.length+1}" style="background:#181c24;font-size:12px;color:var(--accent);text-align:center;padding:10px">
      ⚡ Este usuario usa permisos heredados de su rol (${ROLE_LABELS[user?.rol]||''}). Haz clic en "Resetear desde rol" para personalizar, o edita directamente y guarda.
    </td></tr>`;
  }

  body.innerHTML = info + modulos.map(mod => {
    const effPerms = getEffectivePerms(activeUserId, mod);
    const cells = permisos.map(p => {
      const checked = effPerms.includes(p) ? 'checked' : '';
      return `<td><input type="checkbox" class="perm-check" data-mod="${mod}" data-perm="${p}" ${checked}></td>`;
    }).join('');
    return `<tr><td>${MOD_LABELS[mod]||mod}</td>${cells}</tr>`;
  }).join('');
}

async function guardarPermisos() {
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
  // Actualizar matrix local
  if (!matrix[activeUserId]) matrix[activeUserId] = {};
  for (const [mod, prms] of Object.entries(byModule)) {
    matrix[activeUserId][mod] = prms;
  }
  const user = users.find(u => u.id === activeUserId);
  status.textContent = `✅ ${ok} módulos actualizados` + (fail ? `, ${fail} errores` : '');
  toast(`Permisos de ${user?.nombre||'usuario'} actualizados (${ok} módulos).`,'success');
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
