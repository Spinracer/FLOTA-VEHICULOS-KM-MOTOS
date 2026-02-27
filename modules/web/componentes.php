<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<!-- ═══════════════ TOOLBAR ═══════════════ -->
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar componente..." oninput="load()"></div>
  <select id="fTipo" onchange="load()" style="max-width:180px">
    <option value="">Todos los tipos</option>
    <option value="tool">🔧 Herramienta</option>
    <option value="safety">🦺 Seguridad</option>
    <option value="document">📄 Documento</option>
    <option value="card">💳 Tarjeta</option>
    <option value="accessory">🔩 Accesorio</option>
  </select>
  <!-- Tabs: Catálogo vs Por Vehículo -->
  <div style="display:flex;gap:8px;margin-left:auto">
    <button class="btn btn-ghost tab-btn active" id="tabCatalog" onclick="switchTab('catalog')">📦 Catálogo</button>
    <button class="btn btn-ghost tab-btn" id="tabVehicle" onclick="switchTab('vehicle')">🚗 Por Vehículo</button>
  </div>
</div>

<!-- ═══════════════ PANEL CATÁLOGO ═══════════════ -->
<div id="panelCatalog">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <h3 style="color:#e8ff47;margin:0">Catálogo maestro de componentes</h3>
    <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevoCatalogo()">+ Nuevo Componente</button><?php endif; ?>
  </div>
  <div class="table-wrap">
    <table><thead><tr><th>Nombre</th><th>Tipo</th><th>Descripción</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
    <tbody id="tbodyCatalog"></tbody></table>
    <div id="pgrCatalog"></div>
  </div>
</div>

<!-- ═══════════════ PANEL POR VEHÍCULO ═══════════════ -->
<div id="panelVehicle" style="display:none">
  <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
    <select id="fVehiculo" onchange="loadVehicle()" style="max-width:300px">
      <option value="">— Seleccionar vehículo —</option>
    </select>
    <select id="fEstadoVC" onchange="loadVehicle()" style="max-width:180px">
      <option value="">Todos los estados</option>
      <option value="Bueno">✅ Bueno</option>
      <option value="Regular">⚠️ Regular</option>
      <option value="Malo">❌ Malo</option>
      <option value="Faltante">🚫 Faltante</option>
    </select>
    <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirAsignar()">+ Asignar Componente</button><?php endif; ?>
  </div>
  <!-- KPIs de resumen -->
  <div class="kpi-row" id="kpiResumen" style="display:none">
    <div class="kpi-card"><div class="kpi-value" id="kpiBueno">0</div><div class="kpi-label">✅ Bueno</div></div>
    <div class="kpi-card"><div class="kpi-value" id="kpiRegular">0</div><div class="kpi-label">⚠️ Regular</div></div>
    <div class="kpi-card"><div class="kpi-value" id="kpiMalo">0</div><div class="kpi-label">❌ Malo</div></div>
    <div class="kpi-card"><div class="kpi-value" id="kpiFaltante">0</div><div class="kpi-label">🚫 Faltante</div></div>
  </div>
  <div class="table-wrap">
    <table><thead><tr><th>Componente</th><th>Tipo</th><th>Cant.</th><th>Estado</th><th>N° Serie</th><th>Proveedor</th><th>Instalación</th><th>Vencimiento</th><th>Notas</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
    <tbody id="tbodyVehicle"></tbody></table>
    <div id="pgrVehicle"></div>
  </div>
</div>

<!-- ═══════════════ MODAL CATÁLOGO ═══════════════ -->
<div class="modal-bg" id="modalCatalog">
  <div class="modal">
    <div class="modal-title" id="mtitleCat">📦 Nuevo Componente</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Nombre *</label><input name="nombre" placeholder="Gato hidráulico"></div>
      <div class="form-group"><label>Tipo</label>
        <select name="tipo">
          <option value="tool">🔧 Herramienta</option>
          <option value="safety">🦺 Seguridad</option>
          <option value="document">📄 Documento</option>
          <option value="card">💳 Tarjeta</option>
          <option value="accessory">🔩 Accesorio</option>
        </select>
      </div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Descripción del componente..."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalCatalog')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarCatalogo()">Guardar</button>
    </div>
  </div>
</div>

<!-- ═══════════════ MODAL ASIGNAR A VEHÍCULO ═══════════════ -->
<div class="modal-bg" id="modalVC">
  <div class="modal">
    <div class="modal-title" id="mtitleVC">🔗 Asignar Componente</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <input type="hidden" name="vehiculo_id">
      <div class="form-group"><label>Componente *</label>
        <select name="component_id" id="selComponent">
          <option value="">— Seleccionar —</option>
        </select>
      </div>
      <div class="form-group"><label>Cantidad</label><input name="cantidad" type="number" min="1" value="1"></div>
      <div class="form-group"><label>Estado</label>
        <select name="estado">
          <option value="Bueno">✅ Bueno</option>
          <option value="Regular">⚠️ Regular</option>
          <option value="Malo">❌ Malo</option>
          <option value="Faltante">🚫 Faltante</option>
        </select>
      </div>
      <div class="form-group"><label>N° Serie</label><input name="numero_serie" placeholder="Opcional"></div>
      <div class="form-group"><label>Proveedor</label><input name="proveedor" placeholder="Nombre del proveedor"></div>
      <div class="form-group"><label>Fecha instalación</label><input name="fecha_instalacion" type="date"></div>
      <div class="form-group"><label>Fecha vencimiento</label><input name="fecha_vencimiento" type="date"></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalVC')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarVC()">Guardar</button>
    </div>
  </div>
</div>

<script>
/* ═══ Estado global ═══ */
let currentTab = 'catalog';
const TIPO_LABELS = {tool:'🔧 Herramienta',safety:'🦺 Seguridad',document:'📄 Documento',card:'💳 Tarjeta',accessory:'🔩 Accesorio'};
const TIPO_BADGE  = {tool:'badge-blue',safety:'badge-orange',document:'badge-cyan',card:'badge-yellow',accessory:'badge-gray'};
const EST_BADGE   = {Bueno:'badge-green',Regular:'badge-yellow',Malo:'badge-red',Faltante:'badge-gray'};

const pagerCat = new Paginator('pgrCatalog', loadCatalog, 25);
const pagerVC  = new Paginator('pgrVehicle', loadVehicle, 50);

/* ═══ Tabs ═══ */
function switchTab(tab) {
  currentTab = tab;
  document.getElementById('panelCatalog').style.display = tab === 'catalog' ? '' : 'none';
  document.getElementById('panelVehicle').style.display = tab === 'vehicle' ? '' : 'none';
  document.getElementById('tabCatalog').classList.toggle('active', tab === 'catalog');
  document.getElementById('tabVehicle').classList.toggle('active', tab === 'vehicle');
  if (tab === 'catalog') loadCatalog();
  else loadVehicle();
}

/* ═══ CATÁLOGO ═══ */
async function loadCatalog() {
  const q    = document.getElementById('s').value;
  const tipo = document.getElementById('fTipo').value;
  const data = await api(`/api/componentes.php?section=catalog&q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(tipo)}&page=${pagerCat.page}&per=${pagerCat.perPage}`);
  pagerCat.setTotal(data.total);
  const tbody = document.getElementById('tbodyCatalog');
  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="5"><div class="empty"><div class="empty-icon">📦</div><div class="empty-title">Sin componentes</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td><strong>${r.nombre}</strong></td>
    <td><span class="badge ${TIPO_BADGE[r.tipo]||'badge-gray'}">${TIPO_LABELS[r.tipo]||r.tipo}</span></td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td><span class="badge ${r.activo==='1'?'badge-green':'badge-red'}">${r.activo==='1'?'Activo':'Inactivo'}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editarCatalogo(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delCatalogo(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}

function abrirNuevoCatalogo() {
  document.getElementById('mtitleCat').textContent = '📦 Nuevo Componente';
  resetForm('modalCatalog');
  openModal('modalCatalog');
}

function editarCatalogo(r) {
  document.getElementById('mtitleCat').textContent = '✏️ Editar Componente';
  fillForm('modalCatalog', {id: r.id, nombre: r.nombre, tipo: r.tipo, descripcion: r.descripcion});
  openModal('modalCatalog');
}

async function guardarCatalogo() {
  const d = getForm('modalCatalog');
  if (!d.nombre) { toast('El nombre es obligatorio', 'error'); return; }
  await api('/api/componentes.php?section=catalog', d.id ? 'PUT' : 'POST', d);
  toast(d.id ? 'Componente actualizado' : 'Componente creado');
  closeModal('modalCatalog');
  loadCatalog();
  loadComponentSelect(); // refrescar select de asignación
}

async function delCatalogo(id) {
  confirmDelete('¿Desactivar este componente del catálogo?', async () => {
    await api(`/api/componentes.php?section=catalog&id=${id}`, 'DELETE');
    toast('Componente desactivado', 'warning');
    loadCatalog();
  });
}

/* ═══ VEHÍCULO COMPONENTES ═══ */
async function loadVehicle() {
  const vid    = document.getElementById('fVehiculo').value;
  const tbody  = document.getElementById('tbodyVehicle');
  const kpiRow = document.getElementById('kpiResumen');
  if (!vid) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty"><div class="empty-icon">🚗</div><div class="empty-title">Selecciona un vehículo</div></div></td></tr>';
    kpiRow.style.display = 'none';
    return;
  }
  const q   = document.getElementById('s').value;
  const est = document.getElementById('fEstadoVC').value;
  const data = await api(`/api/componentes.php?section=vehicle&vehiculo_id=${vid}&q=${encodeURIComponent(q)}&estado=${encodeURIComponent(est)}&page=${pagerVC.page}&per=${pagerVC.perPage}`);
  pagerVC.setTotal(data.total);

  // KPIs
  kpiRow.style.display = 'flex';
  document.getElementById('kpiBueno').textContent   = data.resumen['Bueno'] || 0;
  document.getElementById('kpiRegular').textContent  = data.resumen['Regular'] || 0;
  document.getElementById('kpiMalo').textContent     = data.resumen['Malo'] || 0;
  document.getElementById('kpiFaltante').textContent = data.resumen['Faltante'] || 0;

  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty"><div class="empty-icon">🔧</div><div class="empty-title">Sin componentes asignados</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td><strong>${r.componente_nombre}</strong></td>
    <td><span class="badge ${TIPO_BADGE[r.componente_tipo]||'badge-gray'}">${TIPO_LABELS[r.componente_tipo]||r.componente_tipo}</span></td>
    <td>${r.cantidad}</td>
    <td><span class="badge ${EST_BADGE[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <td>${r.numero_serie||'—'}</td>
    <td>${r.proveedor||'—'}</td>
    <td>${r.fecha_instalacion||'—'}</td>
    <td>${r.fecha_vencimiento ? '<span class="'+(new Date(r.fecha_vencimiento)<new Date()?'badge badge-red':'')+'">'+r.fecha_vencimiento+'</span>' : '—'}</td>
    <td class="td-truncate">${r.notas||'—'}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editarVC(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delVC(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}

function abrirAsignar() {
  const vid = document.getElementById('fVehiculo').value;
  if (!vid) { toast('Selecciona un vehículo primero', 'error'); return; }
  document.getElementById('mtitleVC').textContent = '🔗 Asignar Componente';
  resetForm('modalVC');
  // Inyectar vehiculo_id
  document.querySelector('#modalVC input[name="vehiculo_id"]').value = vid;
  openModal('modalVC');
}

function editarVC(r) {
  document.getElementById('mtitleVC').textContent = '✏️ Editar Componente Asignado';
  fillForm('modalVC', {
    id: r.id, vehiculo_id: r.vehiculo_id, component_id: r.component_id,
    cantidad: r.cantidad, estado: r.estado, numero_serie: r.numero_serie,
    proveedor: r.proveedor, fecha_instalacion: r.fecha_instalacion,
    fecha_vencimiento: r.fecha_vencimiento, notas: r.notas
  });
  openModal('modalVC');
}

async function guardarVC() {
  const d = getForm('modalVC');
  if (!d.component_id) { toast('Selecciona un componente', 'error'); return; }
  if (!d.vehiculo_id) { toast('Error: vehículo no seleccionado', 'error'); return; }
  await api('/api/componentes.php?section=vehicle', d.id ? 'PUT' : 'POST', d);
  toast(d.id ? 'Componente actualizado' : 'Componente asignado');
  closeModal('modalVC');
  loadVehicle();
}

async function delVC(id) {
  confirmDelete('¿Quitar este componente del vehículo?', async () => {
    await api(`/api/componentes.php?section=vehicle&id=${id}`, 'DELETE');
    toast('Componente removido', 'warning');
    loadVehicle();
  });
}

/* ═══ Cargar selects auxiliares ═══ */
async function loadVehicleSelect() {
  const data = await api('/api/vehiculos.php?per=500');
  const sel = document.getElementById('fVehiculo');
  data.rows.forEach(v => {
    const opt = document.createElement('option');
    opt.value = v.id;
    opt.textContent = `${v.placa} — ${v.marca} ${v.modelo}`;
    sel.appendChild(opt);
  });
}

async function loadComponentSelect() {
  const data = await api('/api/componentes.php?section=catalog&per=500');
  const sel = document.getElementById('selComponent');
  sel.innerHTML = '<option value="">— Seleccionar —</option>';
  data.rows.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id;
    opt.textContent = `${c.nombre} (${TIPO_LABELS[c.tipo]||c.tipo})`;
    sel.appendChild(opt);
  });
}

function load() {
  if (currentTab === 'catalog') loadCatalog();
  else loadVehicle();
}

document.addEventListener('DOMContentLoaded', () => {
  loadVehicleSelect();
  loadComponentSelect();
  loadCatalog();
});
</script>
<?php $content = ob_get_clean(); echo render_layout('Componentes / Inventario', 'componentes', $content); ?>
