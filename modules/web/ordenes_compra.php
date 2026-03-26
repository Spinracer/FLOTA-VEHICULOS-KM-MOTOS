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
    <th>Folio</th><th>Fecha</th><th>Solicitante</th><th>Descripción</th><th>Vehículo</th><th>Proveedor</th><th>Monto est.</th><th>Urgencia</th><th>Estado</th>
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
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="10"><div class="empty"><div class="empty-icon">🛒</div><div class="empty-title">Sin órdenes de compra</div></div></td></tr>'; return; }

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

document.addEventListener('DOMContentLoaded', load);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Órdenes de Compra', 'ordenes_compra', $content);
?>
