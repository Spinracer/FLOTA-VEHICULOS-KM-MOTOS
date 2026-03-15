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
    <option value="part">⚙️ Refacción</option>
    <option value="consumable">🛢️ Consumible</option>
    <option value="service">🔨 Servicio</option>
    <option value="tool">🔧 Herramienta</option>
    <option value="safety">🦺 Seguridad</option>
    <option value="accessory">🔩 Accesorio</option>
    <option value="document">📄 Documento</option>
    <option value="card">💳 Tarjeta</option>
  </select>
  <select id="fActivo" onchange="load()" style="max-width:160px">
    <option value="1">✅ Activos</option>
    <option value="0">❌ Inactivos</option>
    <option value="">Todos</option>
  </select>
  <!-- Tabs: Catálogo vs Por Vehículo -->
  <div style="display:flex;gap:8px;margin-left:auto">
    <button class="btn btn-ghost tab-btn active" id="tabCatalog" onclick="switchTab('catalog')">📦 Catálogo</button>
    <button class="btn btn-ghost tab-btn" id="tabVehicle" onclick="switchTab('vehicle')">🚗 Por Vehículo</button>
    <button class="btn btn-ghost" onclick="verMovimientos()">📦 Movimientos</button>
    <button class="btn btn-ghost" onclick="verAlertasVenc()">⏰ Vencimientos <span class="badge badge-red" id="badgeVenc" style="display:none">0</span></button>
  </div>
</div>

<!-- ═══════════════ PANEL CATÁLOGO ═══════════════ -->
<div id="panelCatalog">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <h3 style="color:#e8ff47;margin:0">Catálogo maestro de componentes</h3>
    <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevoCatalogo()">+ Nuevo Componente</button><?php endif; ?>
  </div>
  <div class="table-wrap">
    <table><thead><tr><th>Nombre</th><th>Tipo</th><th>Descripción</th><th>Stock</th><th>Mín.</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
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
          <option value="part">⚙️ Refacción</option>
          <option value="consumable">🛢️ Consumible</option>
          <option value="service">🔨 Servicio</option>
          <option value="tool">🔧 Herramienta</option>
          <option value="safety">🦺 Seguridad</option>
          <option value="accessory">🔩 Accesorio</option>
          <option value="document">📄 Documento</option>
          <option value="card">💳 Tarjeta</option>
        </select>
      </div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Descripción del componente..."></textarea></div>
      <div class="form-group"><label>Stock mínimo</label><input name="stock_minimo" type="number" min="0" value="0"></div>
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
const TIPO_LABELS = {part:'⚙️ Refacción',consumable:'🛢️ Consumible',service:'🔨 Servicio',tool:'🔧 Herramienta',safety:'🦺 Seguridad',document:'📄 Documento',card:'💳 Tarjeta',accessory:'🔩 Accesorio'};
const TIPO_BADGE  = {part:'badge-blue',consumable:'badge-cyan',service:'badge-green',tool:'badge-yellow',safety:'badge-orange',document:'badge-gray',card:'badge-gray',accessory:'badge-gray'};
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
  const activo = document.getElementById('fActivo').value;
  const data = await api(`/api/componentes.php?section=catalog&q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(tipo)}&activo=${encodeURIComponent(activo)}&page=${pagerCat.page}&per=${pagerCat.perPage}`);
  pagerCat.setTotal(data.total);
  const tbody = document.getElementById('tbodyCatalog');
  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-icon">📦</div><div class="empty-title">Sin componentes</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => {
    const stk = parseInt(r.stock||0), min = parseInt(r.stock_minimo||0);
    const stkClass = stk <= 0 ? 'badge-red' : stk <= min ? 'badge-orange' : 'badge-green';
    return `<tr>
    <td><strong>${r.nombre}</strong></td>
    <td><span class="badge ${TIPO_BADGE[r.tipo]||'badge-gray'}">${TIPO_LABELS[r.tipo]||r.tipo}</span></td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td><span class="badge ${stkClass}">${stk}</span></td>
    <td>${min}</td>
    <td><span class="badge ${Number(r.activo)?'badge-green':'badge-red'}">${Number(r.activo)?'Activo':'Inactivo'}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn ${Number(r.activo)?'btn-danger':'btn-primary'} btn-sm" onclick="toggleActivo(${r.id},${Number(r.activo)})" title="${Number(r.activo)?'Desactivar':'Activar'}">${Number(r.activo)?'❌':'✅'}</button>
      <button class="btn btn-ghost btn-sm" onclick='editarCatalogo(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delCatalogo(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`}).join('');
}

function abrirNuevoCatalogo() {
  document.getElementById('mtitleCat').textContent = '📦 Nuevo Componente';
  resetForm('modalCatalog');
  openModal('modalCatalog');
}

function editarCatalogo(r) {
  document.getElementById('mtitleCat').textContent = '✏️ Editar Componente';
  fillForm('modalCatalog', {id: r.id, nombre: r.nombre, tipo: r.tipo, descripcion: r.descripcion, stock_minimo: r.stock_minimo||0});
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

async function toggleActivo(id, currentState) {
  const newState = currentState ? 0 : 1;
  const label = newState ? 'activar' : 'desactivar';
  if (!confirm(`¿Deseas ${label} este componente?`)) return;
  await api('/api/componentes.php?section=catalog', 'PUT', {id, activo: newState, _toggle: true});
  toast(`Componente ${newState ? 'activado' : 'desactivado'}`);
  loadCatalog();
  loadComponentSelect();
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
  checkVencimientos();
});

/* ════════════════ MOVIMIENTOS ════════════════ */
async function verMovimientos() {
  const data = await api('/api/componentes.php?section=movimientos&per=100');
  const TB2 = {Entrada:'badge-green',Salida:'badge-red',Transferencia:'badge-blue',Ajuste:'badge-orange'};
  const rows = data.rows.map(r => `<tr>
    <td>${r.created_at}</td><td>${r.comp_nombre}</td>
    <td><span class="badge ${TB2[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td>${r.cantidad}</td><td>${r.placa||'—'}</td>
    <td>${r.referencia||'—'}</td><td>${r.usuario_nombre||'—'}</td>
    <td class="td-truncate">${r.notas||'—'}</td>
  </tr>`).join('') || '<tr><td colspan="8"><div class="empty"><div class="empty-title">Sin movimientos</div></div></td></tr>';

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:1000px">
      <div class="modal-title">📦 Movimientos de Inventario</div>
      <div style="max-height:40vh;overflow:auto">
        <table><thead><tr><th>Fecha</th><th>Componente</th><th>Tipo</th><th>Cant.</th><th>Vehículo</th><th>Referencia</th><th>Usuario</th><th>Notas</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
      <?php if(can('create')): ?>
      <h4 style="margin:12px 0 6px;color:var(--accent)">Registrar Movimiento</h4>
      <div class="form-grid" id="movForm">
        <div class="form-group"><label>Componente *</label><select id="mov_comp">${'<option value="">—</option>'}</select></div>
        <div class="form-group"><label>Tipo *</label><select id="mov_tipo"><option>Entrada</option><option>Salida</option><option>Transferencia</option><option>Ajuste</option></select></div>
        <div class="form-group"><label>Cantidad</label><input type="number" id="mov_cant" min="1" value="1"></div>
        <div class="form-group"><label>Vehículo</label><select id="mov_veh"><option value="">— Ninguno —</option></select></div>
        <div class="form-group"><label>Referencia</label><input id="mov_ref" placeholder="OT, factura..."></div>
        <div class="form-group"><label>Notas</label><input id="mov_notas" placeholder="Observaciones"></div>
        <div class="form-group"><button class="btn btn-primary btn-sm" onclick="addMovimiento()">+ Registrar</button></div>
      </div>
      <?php endif; ?>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
  // Fill selects
  const compSel = wrap.querySelector('#mov_comp');
  const vehSel = wrap.querySelector('#mov_veh');
  if (compSel) {
    const cdata = await api('/api/componentes.php?section=catalog&per=500');
    cdata.rows.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.nombre; compSel.appendChild(o); });
  }
  if (vehSel) {
    const vdata = await api('/api/vehiculos.php?per=500');
    vdata.rows.forEach(v => { const o = document.createElement('option'); o.value = v.id; o.textContent = `${v.placa} — ${v.marca}`; vehSel.appendChild(o); });
  }
}
async function addMovimiento() {
  const d = { component_id: document.getElementById('mov_comp').value, tipo: document.getElementById('mov_tipo').value, cantidad: document.getElementById('mov_cant').value, vehiculo_id: document.getElementById('mov_veh').value || null, referencia: document.getElementById('mov_ref').value || null, notas: document.getElementById('mov_notas').value || null };
  if (!d.component_id) { toast('Selecciona un componente','error'); return; }
  await api('/api/componentes.php?section=movimientos', 'POST', d);
  toast('Movimiento registrado');
  document.querySelector('.modal-bg.open')?.remove();
  loadCatalog();
}

/* ════════════════ ALERTAS VENCIMIENTO ════════════════ */
async function checkVencimientos() {
  try {
    const data = await api('/api/componentes.php?section=alertas_vencimiento&dias=30');
    const badge = document.getElementById('badgeVenc');
    if (data.rows.length > 0) { badge.textContent = data.rows.length; badge.style.display = ''; }
  } catch(e){}
}
async function verAlertasVenc() {
  const data = await api('/api/componentes.php?section=alertas_vencimiento&dias=60');
  const rows = data.rows.map(r => {
    const d = parseInt(r.dias_restantes);
    const cls = d < 0 ? 'badge-red' : d <= 15 ? 'badge-orange' : 'badge-yellow';
    const lbl = d < 0 ? 'Vencido' : d + 'd';
    return `<tr>
      <td>${r.placa} ${r.marca}</td><td>${r.comp_nombre}</td>
      <td><span class="badge ${TIPO_BADGE[r.comp_tipo]||'badge-gray'}">${TIPO_LABELS[r.comp_tipo]||r.comp_tipo}</span></td>
      <td>${r.fecha_vencimiento}</td>
      <td><span class="badge ${cls}">${lbl}</span></td>
      <td>${r.estado}</td>
    </tr>`;
  }).join('') || '<tr><td colspan="6"><div class="empty"><div class="empty-title">Sin vencimientos próximos</div></div></td></tr>';

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:850px">
      <div class="modal-title">⏰ Alertas de Vencimiento (próximos 60 días)</div>
      <div style="max-height:60vh;overflow:auto">
        <table><thead><tr><th>Vehículo</th><th>Componente</th><th>Tipo</th><th>Vencimiento</th><th>Días</th><th>Estado</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
}
</script>
<?php $content = ob_get_clean(); echo render_layout('Componentes / Inventario', 'componentes', $content); ?>
