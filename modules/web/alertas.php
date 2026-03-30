<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<!-- ═══════════════ KPI PILLS ═══════════════ -->
<div class="kpi-row" id="kpiRow">
  <div class="kpi-card"><div class="kpi-value" id="kActivas">—</div><div class="kpi-label">Activas</div></div>
  <div class="kpi-card"><div class="kpi-value" id="kUrgentes" style="color:#ef4444">—</div><div class="kpi-label">Urgentes</div></div>
  <div class="kpi-card"><div class="kpi-value" id="kAltas" style="color:#f97316">—</div><div class="kpi-label">Altas</div></div>
  <div class="kpi-card"><div class="kpi-value" id="kSinAsignar">—</div><div class="kpi-label">Sin Asignar</div></div>
</div>

<!-- ═══════════════ TOOLBAR ═══════════════ -->
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar alerta..." oninput="debouncedLoad()"></div>
  <select id="fTipo" onchange="load()" style="max-width:160px">
    <option value="">Todos los tipos</option>
    <option value="vencimiento">📅 Vencimiento</option>
    <option value="mantenimiento">🔧 Mantenimiento</option>
    <option value="incidente">⚠️ Incidente</option>
    <option value="combustible">⛽ Combustible</option>
    <option value="recordatorio">🔔 Recordatorio</option>
    <option value="componente">🧰 Componente</option>
    <option value="licencia">🪪 Licencia</option>
    <option value="contrato">📋 Contrato</option>
    <option value="seguro">🛡️ Seguro</option>
    <option value="inventario">📦 Inventario</option>
  </select>
  <select id="fPri" onchange="load()" style="max-width:130px">
    <option value="">Prioridad</option>
    <option value="Urgente">🔴 Urgente</option>
    <option value="Alta">🟠 Alta</option>
    <option value="Normal">🔵 Normal</option>
    <option value="Baja">⚪ Baja</option>
  </select>
  <select id="fEstado" onchange="load()" style="max-width:130px">
    <option value="">Solo Activas</option>
    <option value="Activa">Activas</option>
    <option value="Atendida">Atendidas</option>
    <option value="Resuelta">Resueltas</option>
    <option value="Descartada">Descartadas</option>
    <option value="all">Todas</option>
  </select>
  <button class="btn btn-ghost" onclick="escanear()">🔄 Escanear</button>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNueva()">+ Nueva Alerta</button><?php endif; ?>
</div>

<!-- ═══════════════ TABLA ═══════════════ -->
<div class="table-wrap">
  <table>
    <thead><tr>
      <th>Prioridad</th><th>Tipo</th><th>Título</th><th>Vehículo</th>
      <th>Responsable</th><th>Ref.</th><th>Creada</th>
      <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
    </tr></thead>
    <tbody id="tbody"></tbody>
  </table>
  <div id="pgr"></div>
</div>

<!-- ═══════════════ MODAL CREAR/EDITAR ═══════════════ -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🚨 Nueva Alerta</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Tipo *</label>
        <select name="tipo">
          <option value="recordatorio">🔔 Recordatorio</option>
          <option value="mantenimiento">🔧 Mantenimiento</option>
          <option value="incidente">⚠️ Incidente</option>
          <option value="vencimiento">📅 Vencimiento</option>
          <option value="combustible">⛽ Combustible</option>
          <option value="componente">🧰 Componente</option>
          <option value="seguro">🛡️ Seguro</option>
          <option value="inventario">📦 Inventario</option>
        </select>
      </div>
      <div class="form-group"><label>Prioridad</label>
        <select name="prioridad">
          <option value="Normal">🔵 Normal</option>
          <option value="Baja">⚪ Baja</option>
          <option value="Alta">🟠 Alta</option>
          <option value="Urgente">🔴 Urgente</option>
        </select>
      </div>
      <div class="form-group full"><label>Título *</label><input name="titulo" placeholder="Descripción breve de la alerta"></div>
      <div class="form-group full"><label>Mensaje</label><textarea name="mensaje" placeholder="Detalle..."></textarea></div>
      <div class="form-group"><label>Vehículo</label><select name="vehiculo_id" id="selVeh"><option value="">— Ninguno —</option></select></div>
      <div class="form-group"><label>Responsable</label><select name="responsable_id" id="selResp"><option value="">— Sin asignar —</option></select></div>
      <div class="form-group"><label>Fecha referencia</label><input name="fecha_referencia" type="date"></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Notas internas..."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- ═══════════════ MODAL DETALLE ═══════════════ -->
<div class="modal-bg" id="modalDetalle">
  <div class="modal" style="max-width:750px">
    <div class="modal-title" id="detTitle">🚨 Detalle de Alerta</div>
    <div id="detContent"></div>
    <div id="detHistorial" style="margin-top:12px"></div>
    <?php if(can('edit')): ?>
    <div style="margin-top:12px;display:flex;gap:8px;align-items:center" id="detActions">
      <input id="detComent" placeholder="Agregar nota o comentario..." style="flex:1;padding:6px 10px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text)">
      <button class="btn btn-ghost btn-sm" onclick="addNota()">💬</button>
      <select id="detEstado" style="padding:4px 8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text)">
        <option value="Activa">Activa</option><option value="Atendida">Atendida</option>
        <option value="Resuelta">Resuelta</option><option value="Descartada">Descartada</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="cambiarEstado()">Cambiar</button>
    </div>
    <?php endif; ?>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalDetalle')">Cerrar</button></div>
  </div>
</div>

<script>
const pager = new Paginator('pgr', load, 25);
const TIPO_ICON = {vencimiento:'📅',mantenimiento:'🔧',incidente:'⚠️',combustible:'⛽',recordatorio:'🔔',componente:'🧰',licencia:'🪪',contrato:'📋',seguro:'🛡️',inventario:'📦'};
const TIPO_BADGE = {vencimiento:'badge-yellow',mantenimiento:'badge-blue',incidente:'badge-red',combustible:'badge-orange',recordatorio:'badge-cyan',componente:'badge-gray',licencia:'badge-yellow',contrato:'badge-blue',seguro:'badge-green',inventario:'badge-orange'};
const PRI_BADGE = {Urgente:'badge-red',Alta:'badge-orange',Normal:'badge-blue',Baja:'badge-gray'};
const EST_BADGE = {Activa:'badge-yellow',Atendida:'badge-blue',Resuelta:'badge-green',Descartada:'badge-gray'};
let currentAlertId = 0;

async function loadStats() {
  const s = await api('/api/alertas.php?action=stats');
  document.getElementById('kActivas').textContent = s.activas;
  document.getElementById('kUrgentes').textContent = s.urgentes;
  document.getElementById('kAltas').textContent = s.altas;
  document.getElementById('kSinAsignar').textContent = s.sin_asignar;
}

async function load() {
  const q = document.getElementById('s').value;
  const tipo = document.getElementById('fTipo').value;
  const pri = document.getElementById('fPri').value;
  const est = document.getElementById('fEstado').value;
  let url = `/api/alertas.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`;
  if (tipo) url += `&tipo=${tipo}`;
  if (pri) url += `&prioridad=${pri}`;
  if (est && est !== 'all') url += `&estado=${est}`;
  else if (est === 'all') url += `&estado=all`;

  const data = await api(url);
  pager.setTotal(data.total);
  const tbody = document.getElementById('tbody');
  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><div class="empty-icon">🚨</div><div class="empty-title">Sin alertas</div><div class="empty-sub">El sistema está limpio o usa "Escanear" para detectar nuevas</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => `<tr style="cursor:pointer" onclick="verDetalle(${r.id})">
    <td><span class="badge ${PRI_BADGE[r.prioridad]||'badge-gray'}">${r.prioridad}</span></td>
    <td><span class="badge ${TIPO_BADGE[r.tipo]||'badge-gray'}">${TIPO_ICON[r.tipo]||'📌'} ${r.tipo}</span></td>
    <td><strong>${r.titulo}</strong>${r.mensaje?'<br><small style="color:var(--muted)">'+r.mensaje.substring(0,80)+'</small>':''}</td>
    <td>${r.placa ? r.placa + ' ' + (r.marca||'') : '—'}</td>
    <td>${r.responsable_nombre||'<span style="color:var(--muted)">Sin asignar</span>'}</td>
    <td>${r.fecha_referencia||'—'}</td>
    <td><small>${r.created_at}</small></td>
    <?php if(can('edit')): ?><td><div class="action-btns" onclick="event.stopPropagation()">
      <button class="btn btn-ghost btn-sm" onclick="editarAlerta(${r.id})">✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delAlerta(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
  loadStats();
}
const debouncedLoad = debounce(load, 300);

async function escanear() {
  toast('Escaneando alertas...','info');
  const r = await api('/api/alertas.php?action=scan');
  toast(`Escaneo completado: ${r.created} nuevas alertas`, r.created > 0 ? 'warning' : 'success');
  load();
}

function abrirNueva() {
  document.getElementById('mtitle').textContent = '🚨 Nueva Alerta';
  resetForm('modal');
  openModal('modal');
}

async function editarAlerta(id) {
  const data = await api(`/api/alertas.php?estado=all&q=&per=1&page=1`);
  // fetch single
  const resp = await fetch(`/api/alertas.php?estado=all&q=&per=100`);
  const allData = await resp.json();
  const r = allData.rows.find(x => x.id == id);
  if (!r) { toast('Alerta no encontrada','error'); return; }
  document.getElementById('mtitle').textContent = '✏️ Editar Alerta';
  fillForm('modal', { id:r.id, tipo:r.tipo, prioridad:r.prioridad, titulo:r.titulo, mensaje:r.mensaje, vehiculo_id:r.vehiculo_id||'', responsable_id:r.responsable_id||'', fecha_referencia:r.fecha_referencia||'', notas:r.notas||'' });
  openModal('modal');
}

async function guardar() {
  const d = getForm('modal');
  if (!d.titulo) { toast('El título es obligatorio','error'); return; }
  await api('/api/alertas.php', d.id ? 'PUT' : 'POST', d);
  toast(d.id ? 'Alerta actualizada' : 'Alerta creada');
  closeModal('modal');
  load();
}

async function delAlerta(id) {
  confirmDelete('¿Eliminar esta alerta?', async () => {
    await api(`/api/alertas.php?id=${id}`, 'DELETE');
    toast('Eliminada','warning');
    load();
  });
}

async function verDetalle(id) {
  currentAlertId = id;
  // fetch from all estados
  const allData = await api(`/api/alertas.php?estado=all&q=&per=500`);
  const r = allData.rows.find(x => x.id == id);
  if (!r) { toast('No encontrada','error'); return; }

  document.getElementById('detTitle').textContent = `${TIPO_ICON[r.tipo]||'📌'} ${r.titulo}`;
  document.getElementById('detContent').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
      <div><strong>Tipo:</strong> <span class="badge ${TIPO_BADGE[r.tipo]||''}">${r.tipo}</span></div>
      <div><strong>Prioridad:</strong> <span class="badge ${PRI_BADGE[r.prioridad]||''}">${r.prioridad}</span></div>
      <div><strong>Estado:</strong> <span class="badge ${EST_BADGE[r.estado]||''}">${r.estado}</span></div>
      <div><strong>Responsable:</strong> ${r.responsable_nombre||'Sin asignar'}</div>
      <div><strong>Vehículo:</strong> ${r.placa ? r.placa+' '+(r.marca||'') : '—'}</div>
      <div><strong>Fecha ref.:</strong> ${r.fecha_referencia||'—'}</div>
      <div class="full" style="grid-column:1/-1"><strong>Mensaje:</strong> ${r.mensaje||'—'}</div>
      ${r.notas ? '<div style="grid-column:1/-1"><strong>Notas:</strong> '+r.notas+'</div>' : ''}
      <div><strong>Creada:</strong> ${r.created_at}</div>
      ${r.resuelto_at ? '<div><strong>Resuelta:</strong> '+r.resuelto_at+'</div>' : ''}
    </div>`;

  const detEst = document.getElementById('detEstado');
  if (detEst) detEst.value = r.estado;

  // Historial
  const hist = await api(`/api/alertas.php?action=historial&id=${id}`);
  const hDiv = document.getElementById('detHistorial');
  hDiv.innerHTML = `<h4 style="color:var(--accent);margin-bottom:8px">Historial</h4>` +
    (hist.rows.length ? hist.rows.map(h => `<div style="display:flex;gap:8px;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px">
      <span style="min-width:130px;color:var(--muted)">${h.created_at}</span>
      <span class="badge badge-gray" style="font-size:10px">${h.accion}</span>
      <span>${h.comentario||''}</span>
      <span style="color:var(--muted);margin-left:auto">${h.usuario_nombre||'Sistema'}</span>
    </div>`).join('') : '<div style="color:var(--muted);font-size:13px">Sin historial</div>');

  openModal('modalDetalle');
}

async function cambiarEstado() {
  const estado = document.getElementById('detEstado').value;
  const comentario = document.getElementById('detComent').value;
  await api('/api/alertas.php', 'PUT', { id: currentAlertId, estado, comentario });
  toast('Estado actualizado');
  closeModal('modalDetalle');
  load();
}

async function addNota() {
  const comentario = document.getElementById('detComent').value;
  if (!comentario) { toast('Escribe un comentario','error'); return; }
  await api('/api/alertas.php', 'PUT', { id: currentAlertId, comentario });
  toast('Nota agregada');
  document.getElementById('detComent').value = '';
  verDetalle(currentAlertId);
}

/* ═══ Cargar selects ═══ */
async function loadSelects() {
  try {
    const vData = await api('/api/vehiculos.php?per=500');
    const selV = document.getElementById('selVeh');
    vData.rows.forEach(v => { const o = document.createElement('option'); o.value = v.id; o.textContent = `${v.placa} — ${v.marca}`; selV.appendChild(o); });
  } catch(e) { console.error(e); }
  try {
    const uData = await api('/api/usuarios.php?per=500');
    const selU = document.getElementById('selResp');
    (uData.rows||[]).forEach(u => { const o = document.createElement('option'); o.value = u.id; o.textContent = u.nombre; selU.appendChild(o); });
  } catch(e) { console.error(e); }
}

document.addEventListener('DOMContentLoaded', () => { loadSelects(); loadStats(); load(); });
</script>
<?php $content = ob_get_clean(); echo render_layout('Centro de Alertas', 'alertas', $content); ?>
