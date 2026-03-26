<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$isAdmin = in_array(current_user()['rol'], ['coordinador_it','admin']);
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por descripción, solicitante, placa, proveedor..." oninput="load()"></div>
  <select id="fest" onchange="load()" style="max-width:160px">
    <option value="">Todos los estados</option>
    <option>Pendiente</option><option>Aprobada</option><option>Rechazada</option><option>Completada</option><option>Cancelada</option>
  </select>
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Nueva Orden</button>
  <?php endif; ?>
</div>

<div class="stats-row" id="stats-bar" style="margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;"></div>

<div class="table-wrap">
  <table><thead><tr>
    <th>Folio</th><th>Fecha</th><th>Solicitante</th><th>Descripción</th><th>Vehículo</th><th>Proveedor</th><th>Monto est.</th><th>Items</th><th>Urgencia</th><th>Estado</th>
    <th>Acciones</th>
  </tr></thead>
  <tbody id="tbody"></tbody></table>
  <div id="pgr"></div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal-bg" id="modal">
  <div class="modal" style="max-width:700px">
    <div class="modal-title" id="mtitle">🛒 Nueva Orden de Compra</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group full"><label>Descripción *</label><textarea name="descripcion" placeholder="Describa los artículos o servicios que necesita..." style="min-height:80px"></textarea></div>
      <div class="form-group"><label>Vehículo *</label>
        <select name="vehiculo_id">
          <option value="">— Seleccionar vehículo —</option>
          <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Proveedor (opcional)</label>
        <select name="proveedor_id"><option value="">— Ninguno —</option>
          <?php foreach($proveedores as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Monto estimado (L)</label><input name="monto_estimado" type="number" step="0.01" placeholder="0.00"></div>
      <div class="form-group"><label>Urgencia</label>
        <select name="urgencia"><option>Normal</option><option>Baja</option><option>Alta</option><option>Urgente</option></select>
      </div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Notas adicionales..." style="min-height:50px"></textarea></div>
      <!-- Adjuntos: Cotización -->
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
        <label style="font-size:14px;font-weight:600;color:var(--accent2)">📄 Foto de Cotización</label>
        <div id="att-cotizacion-wrap"></div>
      </div>
      <!-- Adjuntos: Factura -->
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
        <label style="font-size:14px;font-weight:600;color:var(--accent2)">🧾 Foto de Factura</label>
        <div id="att-factura-wrap"></div>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL DETALLE -->
<div class="modal-bg" id="modal-detail">
  <div class="modal" style="max-width:750px">
    <div class="modal-title" id="detail-title">📋 Detalle de Orden</div>
    <div id="detail-content" style="max-height:70vh;overflow-y:auto;font-size:14px">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <!-- Adjuntos visibles -->
    <div id="att-detail-section" style="margin-top:12px">
      <h4 style="font-size:13px;font-weight:600;margin-bottom:6px">📎 Archivos adjuntos</h4>
      <div id="att-preview-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px"></div>
      <div id="att-detail-cot-wrap"></div>
      <div id="att-detail-fac-wrap" style="margin-top:8px"></div>
    </div>
    <?php if($isAdmin): ?>
    <!-- Cambio rápido de estado -->
    <div id="quick-status" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
      <h4 style="font-size:13px;font-weight:600;color:var(--accent2);margin-bottom:8px">Cambiar estado</h4>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-sm" style="background:#ffa502;color:#000" onclick="cambiarEstado('Pendiente')">Pendiente</button>
        <button class="btn btn-primary btn-sm" onclick="cambiarEstado('Aprobada')">Aprobada</button>
        <button class="btn btn-danger btn-sm" onclick="cambiarEstado('Rechazada')">Rechazada</button>
        <button class="btn btn-sm" style="background:#3498db;color:#fff" onclick="cambiarEstado('Completada')">Completada</button>
        <button class="btn btn-ghost btn-sm" onclick="cambiarEstado('Cancelada')">Cancelada</button>
      </div>
    </div>
    <div id="approval-section" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:none">
      <h4 style="font-size:13px;font-weight:600;color:var(--accent2);margin-bottom:8px">Notas de aprobación/rechazo</h4>
      <textarea id="notas-aprobacion" placeholder="Notas de aprobación/rechazo (opcional)..." style="width:100%;min-height:50px;font-size:12px;margin-bottom:8px"></textarea>
    </div>
    <?php endif; ?>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-detail')">Cerrar</button>
      <button class="btn btn-primary btn-sm" onclick="window.open('/print.php?type=orden_compra&id='+detailId,'_blank')">🖨️ Imprimir</button>
    </div>
  </div>
</div>

<!-- MODAL VISTA PREVIA DE ARCHIVO -->
<div class="modal-bg" id="modal-preview">
  <div class="modal" style="max-width:900px;max-height:90vh">
    <div class="modal-title" id="preview-title">Vista previa</div>
    <div id="preview-content" style="text-align:center;overflow:auto;max-height:70vh"></div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-preview')">Cerrar</button>
      <a id="preview-download" href="#" download class="btn btn-primary btn-sm">Descargar</a>
    </div>
  </div>
</div>

<!-- MODAL PARTIDAS OC -->
<div class="modal-bg" id="modal-oc-items">
  <div class="modal" style="max-width:800px">
    <div class="modal-title" id="oc-items-title">📋 Partidas de Orden</div>
    <div id="oc-items-content" style="max-height:60vh;overflow-y:auto">
      <table class="data-table"><thead><tr><th>Descripción</th><th>Cant.</th><th>Unidad</th><th>P.Unit.</th><th>Subtotal</th><th>Notas</th><th>Acciones</th></tr></thead>
      <tbody id="oc-items-body"></tbody>
      <tfoot><tr><td colspan="4"><strong>TOTAL</strong></td><td id="oc-items-total"><strong>L 0.00</strong></td><td colspan="2"></td></tr></tfoot>
      </table>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-oc-items')">Cerrar</button>
      <button class="btn btn-primary btn-sm" id="btn-add-oc-item" onclick="abrirNuevoItemOC()">+ Agregar Partida</button>
    </div>
  </div>
</div>

<!-- MODAL NUEVA/EDITAR PARTIDA OC -->
<div class="modal-bg" id="modal-oc-item">
  <div class="modal" style="max-width:550px">
    <div class="modal-title" id="oc-item-title">➕ Nueva Partida</div>
    <div class="form-grid">
      <input type="hidden" name="oc_item_id">
      <div class="form-group"><label>Componente (catálogo)</label>
        <select name="oc_component_id" id="selOCComponent"><option value="">— Sin componente —</option></select>
      </div>
      <div class="form-group full"><label>Descripción *</label><input name="oc_item_desc" placeholder="Descripción del item..."></div>
      <div class="form-group"><label>Cantidad</label><input name="oc_item_qty" type="number" step="0.01" value="1"></div>
      <div class="form-group"><label>Unidad</label>
        <select name="oc_item_unidad"><option>PZA</option><option>LT</option><option>KG</option><option>SVC</option><option>HR</option><option>JUEGO</option></select>
      </div>
      <div class="form-group"><label>Precio Unitario (L)</label><input name="oc_item_precio" type="number" step="0.01" value="0"></div>
      <div class="form-group"><label>Subtotal</label><input name="oc_item_subtotal" readonly style="font-weight:700;color:var(--accent2)"></div>
      <div class="form-group full"><label>Notas</label><input name="oc_item_notas" placeholder="Observaciones opcionales..."></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-oc-item')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarItemOC()">Guardar</button>
    </div>
  </div>
</div>

<script>
const pager = new Paginator('pgr', load, 25);
const EB = {'Pendiente':'badge-yellow','Aprobada':'badge-green','Rechazada':'badge-red','Completada':'badge-blue','Cancelada':'badge-gray'};
const UB = {'Baja':'badge-gray','Normal':'badge-blue','Alta':'badge-orange','Urgente':'badge-red'};

const attCot = new AttachmentWidget('att-cotizacion-wrap', 'oc_cotizacion');
const attFac = new AttachmentWidget('att-factura-wrap', 'oc_factura');
let detailId = null;

async function load() {
  const q = document.getElementById('s').value;
  const est = document.getElementById('fest').value;
  const data = await api(`/api/ordenes_compra.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);

  const rows = data.rows;
  const pendientes = rows.filter(r => r.estado === 'Pendiente').length;
  const aprobadas = rows.filter(r => r.estado === 'Aprobada').length;
  const montoTotal = rows.reduce((s, r) => s + Number(r.monto_estimado || 0), 0);

  document.getElementById('stats-bar').innerHTML = `
    <div class="stat-card"><div class="stat-value">${data.total}</div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#ffa502">${pendientes}</div><div class="stat-label">Pendientes</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#2ed573">${aprobadas}</div><div class="stat-label">Aprobadas</div></div>
    <div class="stat-card"><div class="stat-value">L ${montoTotal.toFixed(0)}</div><div class="stat-label">Monto est.</div></div>
  `;

  const tbody = document.getElementById('tbody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="11"><div class="empty"><div class="empty-icon">🛒</div><div class="empty-title">Sin órdenes de compra</div></div></td></tr>'; return; }

  tbody.innerHTML = rows.map(r => {
    const folio = 'OC-' + String(r.id).padStart(6, '0');
    return `<tr>
    <td><strong style="font-family:monospace;color:var(--accent2)">${folio}</strong></td>
    <td>${r.created_at?.substring(0,10) || '—'}</td>
    <td>${r.solicitante_nombre || '—'}</td>
    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(r.descripcion||'').replace(/"/g,'&quot;')}">${r.descripcion || '—'}</td>
    <td>${r.placa ? '<strong style="color:var(--accent2)">'+r.placa+'</strong> '+r.marca : '—'}</td>
    <td>${r.proveedor_nombre || '—'}</td>
    <td>${Number(r.monto_estimado) > 0 ? 'L '+Number(r.monto_estimado).toFixed(2) : '—'}</td>
    <td><button class="btn btn-ghost btn-sm" onclick="verItemsOC(${r.id},'${r.estado}')" title="Ver partidas">📋 ${r.items_count||0}</button></td>
    <td><span class="badge ${UB[r.urgencia]||'badge-gray'}">${r.urgencia || 'Normal'}</span></td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick="verDetalle(${r.id})" title="Ver detalle">📋</button>
      <button class="btn btn-ghost btn-sm" onclick="window.open('/print.php?type=orden_compra&id=${r.id}','_blank')" title="Imprimir">🖨️</button>
      <?php if(can('edit')): ?><button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})' title="Editar">✏️</button><?php endif; ?>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})" title="Eliminar">🗑️</button><?php endif; ?>
    </div></td>
  </tr>`;}).join('');
}

function abrirNuevo() {
  document.getElementById('mtitle').textContent = '🛒 Nueva Orden de Compra';
  resetForm('modal');
  attCot.reset(); attFac.reset();
  openModal('modal');
}

function editar(r) {
  document.getElementById('mtitle').textContent = '✏️ Editar Orden';
  fillForm('modal', {
    id: r.id, descripcion: r.descripcion, vehiculo_id: r.vehiculo_id || '',
    proveedor_id: r.proveedor_id || '', monto_estimado: r.monto_estimado || '',
    urgencia: r.urgencia || 'Normal',
    notas: r.notas || ''
  });
  attCot.setEntityId(r.id); attCot.load();
  attFac.setEntityId(r.id); attFac.load();
  openModal('modal');
}

async function guardar() {
  const d = getForm('modal');
  if (!d.descripcion) { toast('La descripción es obligatoria', 'error'); return; }
  if (!d.vehiculo_id) { toast('Debes seleccionar un vehículo', 'error'); return; }
  const res = await api('/api/ordenes_compra.php', d.id ? 'PUT' : 'POST', d);
  const savedId = d.id || res.id;
  if (savedId) {
    if (attCot.hasPending()) await attCot.uploadPending(savedId);
    if (attFac.hasPending()) await attFac.uploadPending(savedId);
  }
  toast(d.id ? 'Orden actualizada' : 'Orden creada');
  closeModal('modal'); load();
}

async function del(id) {
  confirmDelete('¿Eliminar esta orden de compra?', async () => {
    await api(`/api/ordenes_compra.php?id=${id}`, 'DELETE');
    toast('Orden eliminada', 'warning'); load();
  });
}

async function verDetalle(id) {
  detailId = id;
  openModal('modal-detail');
  const r = await api(`/api/ordenes_compra.php?detail=${id}`);
  if (!r || !r.id) { document.getElementById('detail-content').innerHTML = '<div class="empty"><div class="empty-icon">❌</div><div class="empty-title">No encontrada</div></div>'; return; }

  const folio = 'OC-' + String(r.id).padStart(6, '0');
  document.getElementById('detail-title').innerHTML = `📋 ${folio} — Detalle de Orden`;

  document.getElementById('detail-content').innerHTML = `
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:#8892a4;width:150px;padding:6px 0">Folio</td><td style="padding:6px 0"><strong style="font-family:monospace">${folio}</strong></td></tr>
      <tr><td style="color:#8892a4">Solicitante</td><td><strong>${r.solicitante_nombre||'—'}</strong></td></tr>
      <tr><td style="color:#8892a4">Fecha</td><td>${r.created_at?.substring(0,10)||'—'}</td></tr>
      <tr><td style="color:#8892a4">Descripción</td><td>${r.descripcion||'—'}</td></tr>
      <tr><td style="color:#8892a4">Vehículo</td><td>${r.placa ? r.placa+' '+r.marca+' '+r.modelo : '—'}</td></tr>
      <tr><td style="color:#8892a4">Proveedor</td><td>${r.proveedor_nombre||'—'}</td></tr>
      <tr><td style="color:#8892a4">Monto estimado</td><td>${Number(r.monto_estimado)>0?'L '+Number(r.monto_estimado).toFixed(2):'—'}</td></tr>
      <tr><td style="color:#8892a4">Urgencia</td><td><span class="badge ${UB[r.urgencia]||'badge-gray'}">${r.urgencia||'Normal'}</span></td></tr>
      <tr><td style="color:#8892a4">Estado</td><td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td></tr>
      ${r.aprobador_nombre ? `<tr><td style="color:#8892a4">Aprobado por</td><td>${r.aprobador_nombre} — ${r.fecha_aprobacion||''}</td></tr>` : ''}
      ${r.notas_aprobacion ? `<tr><td style="color:#8892a4">Notas aprobación</td><td>${r.notas_aprobacion}</td></tr>` : ''}
      ${r.notas ? `<tr><td style="color:#8892a4">Notas</td><td>${r.notas}</td></tr>` : ''}
    </table>`;

  // Load and render attachments with preview
  loadAttachmentsPreview(id);

  const attDetCot = new AttachmentWidget('att-detail-cot-wrap', 'oc_cotizacion', id);
  attDetCot.load();
  const attDetFac = new AttachmentWidget('att-detail-fac-wrap', 'oc_factura', id);
  attDetFac.load();

  <?php if($isAdmin): ?>
  document.getElementById('approval-section').style.display = (r.estado === 'Pendiente' || r.estado === 'Rechazada') ? '' : 'none';
  <?php endif; ?>
}

// Cargar adjuntos con vista previa visual
async function loadAttachmentsPreview(id) {
  const container = document.getElementById('att-preview-list');
  container.innerHTML = '<span style="font-size:12px;color:#8892a4">Cargando archivos...</span>';
  try {
    const files = [];
    for (const entidad of ['oc_cotizacion', 'oc_factura', 'ordenes_compra']) {
      try {
        const res = await fetch(`/api/attachments.php?entidad=${entidad}&entidad_id=${id}`, { credentials: 'include' });
        if (res.ok) {
          const data = await res.json();
          if (data.attachments) files.push(...data.attachments.map(f => ({...f, _entidad: entidad})));
        }
      } catch(e) {}
    }
    if (!files.length) {
      container.innerHTML = '<span style="font-size:12px;color:#8892a4">Sin archivos adjuntos</span>';
      return;
    }
    container.innerHTML = files.map(f => {
      const isImg = f.mime_type && f.mime_type.startsWith('image/');
      const icon = isImg ? '🖼️' : f.mime_type === 'application/pdf' ? '📕' : '📄';
      const label = f._entidad === 'oc_cotizacion' ? 'Cotización' : f._entidad === 'oc_factura' ? 'Factura' : 'Adjunto';
      return `<div style="border:1px solid var(--border);border-radius:8px;padding:6px 10px;cursor:pointer;font-size:12px;display:flex;align-items:center;gap:6px;background:var(--surface2)" onclick="previewFile(${f.id},'${f.mime_type}','${(f.original_name||'').replace(/'/g,"\\'")}')">
        <span>${icon}</span>
        <div>
          <div style="font-weight:500">${label}</div>
          <div style="font-size:10px;color:#8892a4;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${f.original_name||'archivo'}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    container.innerHTML = '';
  }
}

function previewFile(id, mime, name) {
  document.getElementById('preview-title').textContent = name || 'Vista previa';
  const content = document.getElementById('preview-content');
  const downloadLink = document.getElementById('preview-download');
  const url = `/api/attachments.php?id=${id}&download=1`;
  downloadLink.href = url;

  if (mime && mime.startsWith('image/')) {
    content.innerHTML = `<img src="${url}" style="max-width:100%;max-height:65vh;border-radius:8px" alt="${name}">`;
  } else if (mime === 'application/pdf') {
    content.innerHTML = `<iframe src="${url}" style="width:100%;height:65vh;border:none;border-radius:8px"></iframe>`;
  } else {
    content.innerHTML = `<div style="padding:40px;text-align:center"><span style="font-size:48px">📄</span><p style="margin-top:12px;color:#8892a4">No hay vista previa disponible. Usa el botón Descargar.</p></div>`;
  }
  openModal('modal-preview');
}

<?php if($isAdmin): ?>
async function cambiarEstado(nuevoEstado) {
  if (!detailId) return;
  const notas = document.getElementById('notas-aprobacion')?.value?.trim() || '';
  if (nuevoEstado === 'Rechazada' && !notas) {
    toast('Indica el motivo del rechazo', 'error');
    document.getElementById('approval-section').style.display = '';
    return;
  }
  await api('/api/ordenes_compra.php', 'PUT', { id: detailId, _accion: 'cambiar_estado', estado: nuevoEstado, notas_aprobacion: notas || undefined });
  toast(`Estado cambiado a ${nuevoEstado}`);
  closeModal('modal-detail'); load();
}

async function aprobar() { cambiarEstado('Aprobada'); }
async function rechazar() { cambiarEstado('Rechazada'); }
<?php endif; ?>

// ── OC Items ──
let currentOCId = null;
let currentOCEstado = null;
let ocComponents = [];

async function loadOCComponents() {
  if (ocComponents.length) return;
  try {
    const data = await api('/api/componentes.php?section=catalog&per=500&activo=1');
    ocComponents = data.rows || [];
    const sel = document.getElementById('selOCComponent');
    sel.innerHTML = '<option value="">— Sin componente —</option>' +
      ocComponents.map(c => `<option value="${c.id}">${c.nombre} (${c.tipo})</option>`).join('');
  } catch(e) {}
}

async function verItemsOC(ocId, estado) {
  currentOCId = ocId;
  currentOCEstado = estado;
  const folio = 'OC-' + String(ocId).padStart(6, '0');
  document.getElementById('oc-items-title').textContent = '📋 Partidas — ' + folio;
  document.getElementById('btn-add-oc-item').style.display = (estado === 'Completada' || estado === 'Cancelada') ? 'none' : '';
  openModal('modal-oc-items');
  await loadOCItems();
}

async function loadOCItems() {
  const data = await api(`/api/ordenes_compra.php?action=items&orden_compra_id=${currentOCId}`);
  const tbody = document.getElementById('oc-items-body');
  const canEdit = (currentOCEstado !== 'Completada' && currentOCEstado !== 'Cancelada');
  if (!data.items || !data.items.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:#8892a4">Sin partidas. Agrega la primera.</td></tr>';
  } else {
    tbody.innerHTML = data.items.map(i => `<tr>
      <td>${i.descripcion}</td>
      <td>${Number(i.cantidad).toFixed(2)}</td>
      <td>${i.unidad}</td>
      <td>L ${Number(i.precio_unitario).toFixed(2)}</td>
      <td><strong>L ${Number(i.subtotal).toFixed(2)}</strong></td>
      <td style="font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis">${i.notas||''}</td>
      <td>${canEdit ? `<div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick='editarItemOC(${JSON.stringify(i)})' title="Editar">✏️</button>
        <button class="btn btn-danger btn-sm" onclick="delItemOC(${i.id})" title="Eliminar">🗑️</button>
      </div>` : ''}</td>
    </tr>`).join('');
  }
  document.getElementById('oc-items-total').innerHTML = `<strong>L ${Number(data.total||0).toFixed(2)}</strong>`;
}

function abrirNuevoItemOC() {
  loadOCComponents();
  document.getElementById('oc-item-title').textContent = '➕ Nueva Partida';
  const modal = document.getElementById('modal-oc-item');
  modal.querySelector('[name=oc_item_id]').value = '';
  modal.querySelector('[name=oc_item_desc]').value = '';
  modal.querySelector('[name=oc_item_qty]').value = '1';
  modal.querySelector('[name=oc_item_unidad]').value = 'PZA';
  modal.querySelector('[name=oc_item_precio]').value = '0';
  modal.querySelector('[name=oc_item_subtotal]').value = '';
  modal.querySelector('[name=oc_item_notas]').value = '';
  modal.querySelector('[name=oc_component_id]').value = '';
  openModal('modal-oc-item');
}

function editarItemOC(item) {
  loadOCComponents();
  document.getElementById('oc-item-title').textContent = '✏️ Editar Partida';
  const modal = document.getElementById('modal-oc-item');
  modal.querySelector('[name=oc_item_id]').value = item.id;
  modal.querySelector('[name=oc_item_desc]').value = item.descripcion;
  modal.querySelector('[name=oc_item_qty]').value = item.cantidad;
  modal.querySelector('[name=oc_item_unidad]').value = item.unidad;
  modal.querySelector('[name=oc_item_precio]').value = item.precio_unitario;
  modal.querySelector('[name=oc_item_subtotal]').value = 'L ' + Number(item.subtotal).toFixed(2);
  modal.querySelector('[name=oc_item_notas]').value = item.notas || '';
  modal.querySelector('[name=oc_component_id]').value = item.component_id || '';
  openModal('modal-oc-item');
}

// Auto-calc subtotal preview
document.querySelector('[name=oc_item_qty]')?.addEventListener('input', calcOCSubtotal);
document.querySelector('[name=oc_item_precio]')?.addEventListener('input', calcOCSubtotal);
function calcOCSubtotal() {
  const q = parseFloat(document.querySelector('[name=oc_item_qty]').value) || 0;
  const p = parseFloat(document.querySelector('[name=oc_item_precio]').value) || 0;
  document.querySelector('[name=oc_item_subtotal]').value = 'L ' + (q * p).toFixed(2);
}

// Auto-fill desc from component
document.getElementById('selOCComponent')?.addEventListener('change', function() {
  const comp = ocComponents.find(c => c.id == this.value);
  if (comp) {
    const descField = document.querySelector('[name=oc_item_desc]');
    if (!descField.value) descField.value = comp.nombre;
  }
});

async function guardarItemOC() {
  const modal = document.getElementById('modal-oc-item');
  const id = modal.querySelector('[name=oc_item_id]').value;
  const desc = modal.querySelector('[name=oc_item_desc]').value.trim();
  if (!desc) { toast('La descripción es obligatoria', 'error'); return; }
  const payload = {
    descripcion: desc,
    cantidad: parseFloat(modal.querySelector('[name=oc_item_qty]').value) || 1,
    unidad: modal.querySelector('[name=oc_item_unidad]').value,
    precio_unitario: parseFloat(modal.querySelector('[name=oc_item_precio]').value) || 0,
    notas: modal.querySelector('[name=oc_item_notas]').value || null,
    component_id: modal.querySelector('[name=oc_component_id]').value || null,
  };
  if (id) payload.id = parseInt(id);
  await api(`/api/ordenes_compra.php?action=items&orden_compra_id=${currentOCId}`, id ? 'PUT' : 'POST', payload);
  toast(id ? 'Partida actualizada' : 'Partida agregada');
  closeModal('modal-oc-item');
  await loadOCItems();
  load(); // refresh main table for updated monto
}

async function delItemOC(itemId) {
  if (!confirm('¿Eliminar esta partida?')) return;
  await api(`/api/ordenes_compra.php?action=items&orden_compra_id=${currentOCId}&item_id=${itemId}`, 'DELETE');
  toast('Partida eliminada', 'warning');
  await loadOCItems();
  load();
}

document.addEventListener('DOMContentLoaded', load);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Órdenes de Compra', 'ordenes_compra', $content);
?>
