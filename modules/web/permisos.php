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
.perms-grid .module-header { background:#0f1117; color:#47ffe8; font-weight:600; text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
.perm-check { width:16px; height:16px; accent-color:#e8ff47; cursor:pointer; }
.perm-check:disabled { cursor:not-allowed; opacity:0.4; }
.role-tab { display:inline-block; padding:8px 18px; cursor:pointer; border-radius:8px 8px 0 0; background:#181c24; color:#8892a4; border:1px solid #222730; border-bottom:none; font-size:13px; margin-right:2px; transition:.2s; }
.role-tab.active { background:#1a1e27; color:#e8ff47; font-weight:600; }
.save-bar { margin-top:14px; display:flex; gap:10px; align-items:center; }
</style>

<div class="toolbar">
  <h3 style="margin:0;font-size:16px;">🔐 Matriz de Permisos por Módulo</h3>
</div>

<div id="role-tabs"></div>
<div class="perms-grid">
  <table id="perms-table">
    <thead><tr id="perms-head"></tr></thead>
    <tbody id="perms-body"></tbody>
  </table>
</div>
<div class="save-bar">
  <button class="btn btn-primary" onclick="guardarPermisos()">💾 Guardar cambios</button>
  <span id="save-status" style="font-size:12px;color:#8892a4;"></span>
</div>

<script>
const PERM_LABELS = { view:'👁 Ver', create:'➕ Crear', edit:'✏️ Editar', 'delete':'🗑 Eliminar' };
const MOD_LABELS = {
  vehiculos:'Vehículos', asignaciones:'Asignaciones', mantenimientos:'Mantenimientos',
  combustible:'Combustible', incidentes:'Incidentes', recordatorios:'Recordatorios',
  operadores:'Operadores', proveedores:'Proveedores', componentes:'Componentes',
  preventivos:'Preventivos', reportes:'Reportes', catalogos:'Catálogos',
  usuarios:'Usuarios', auditoria:'Auditoría'
};
let matrix={}, modulos=[], permisos=[], roles=[], activeRole='';

async function loadMatrix() {
  const res = await fetch('/api/permisos.php');
  const data = await res.json();
  matrix  = data.matrix || {};
  modulos = data.modulos || [];
  permisos= data.permisos || [];
  roles   = data.roles || [];
  if (!activeRole && roles.length) activeRole = roles[0];
  renderTabs();
  renderTable();
}

function renderTabs() {
  const ROLE_LABELS = <?= json_encode(ROLES) ?>;
  document.getElementById('role-tabs').innerHTML = roles.map(r =>
    `<div class="role-tab ${r===activeRole?'active':''}" onclick="switchRole('${r}')">${ROLE_LABELS[r]||r}</div>`
  ).join('');
}

function switchRole(r) {
  activeRole = r;
  renderTabs();
  renderTable();
}

function renderTable() {
  const head = document.getElementById('perms-head');
  const body = document.getElementById('perms-body');
  const isAdmin = ['coordinador_it','admin'].includes(activeRole);
  head.innerHTML = '<th>Módulo</th>' + permisos.map(p => `<th>${PERM_LABELS[p]||p}</th>`).join('');
  body.innerHTML = modulos.map(mod => {
    const rolPerms = (matrix[activeRole] && matrix[activeRole][mod]) || [];
    const cells = permisos.map(p => {
      const checked = rolPerms.includes(p) ? 'checked' : '';
      const disabled = isAdmin ? 'disabled' : '';
      return `<td><input type="checkbox" class="perm-check" data-mod="${mod}" data-perm="${p}" ${checked} ${disabled}></td>`;
    }).join('');
    return `<tr><td>${MOD_LABELS[mod]||mod}</td>${cells}</tr>`;
  }).join('');
}

async function guardarPermisos() {
  if (['coordinador_it','admin'].includes(activeRole)) {
    showToast('Los permisos de administrador no se pueden modificar.','error');
    return;
  }
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
  for (const [mod, perms] of Object.entries(byModule)) {
    const res = await fetch('/api/permisos.php', {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ rol: activeRole, modulo: mod, permisos: perms })
    });
    if (res.ok) ok++; else fail++;
  }
  if (!matrix[activeRole]) matrix[activeRole] = {};
  for (const [mod, perms] of Object.entries(byModule)) {
    matrix[activeRole][mod] = perms;
  }
  status.textContent = `✅ ${ok} módulos actualizados` + (fail ? `, ${fail} errores` : '');
  showToast(`Permisos de ${activeRole} actualizados (${ok} módulos).`,'ok');
  setTimeout(() => status.textContent = '', 3000);
}

loadMatrix();
</script>
<?php
$content = ob_get_clean();
echo render_layout('Permisos por Módulo', 'permisos', $content);
