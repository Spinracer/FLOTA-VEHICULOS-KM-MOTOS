<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
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
    <th>Fecha</th><th>Solicitante</th><th>Descripción</th><th>Vehículo</th><th>Proveedor</th><th>Monto est.</th><th>Urgencia</th><th>Estado</th>
    <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
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
      <div class="form-group"><label>Vehículo (opcional)</label>
        <select name="vehiculo_id"><option value="">— Ninguno —</option>
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
      <?php if($isAdmin): ?>
      <div class="form-group"><label>Estado</label>
        <select name="estado"><option>Pendiente</option><option>Aprobada</option><option>Rechazada</option><option>Completada</option><option>Cancelada</option></select>
      </div>
      <?php endif; ?>
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
  <div class="modal" style="max-width:700px">
    <div class="modal-title">📋 Detalle de Orden</div>
    <div id="detail-content" style="max-height:70vh;overflow-y:auto;font-size:14px">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <div id="att-detail-cot-wrap" style="margin-top:12px"></div>
    <div id="att-detail-fac-wrap" style="margin-top:12px"></div>
    <?php if($isAdmin): ?>
    <div id="approval-section" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:none">
      <h4 style="font-size:13px;font-weight:600;color:var(--accent2);margin-bottom:8px">✅ Aprobación</h4>
      <textarea id="notas-aprobacion" placeholder="Notas de aprobación/rechazo..." style="width:100%;min-height:50px;font-size:12px;margin-bottom:8px"></textarea>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary btn-sm" onclick="aprobar()">✅ Aprobar</button>
        <button class="btn btn-danger btn-sm" onclick="rechazar()">❌ Rechazar</button>
      </div>
    </div>
    <?php endif; ?>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-detail')">Cerrar</button></div>
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
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="9"><div class="empty"><div class="empty-icon">🛒</div><div class="empty-title">Sin órdenes de compra</div></div></td></tr>'; return; }

  tbody.innerHTML = rows.map(r => `<tr>
    <td>${r.created_at?.substring(0,10) || '—'}</td>
    <td>${r.solicitante_nombre || '—'}</td>
    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(r.descripcion||'').replace(/"/g,'&quot;')}">${r.descripcion || '—'}</td>
    <td>${r.placa ? '<strong style="color:var(--accent2)">'+r.placa+'</strong> '+r.marca : '—'}</td>
    <td>${r.proveedor_nombre || '—'}</td>
    <td>${Number(r.monto_estimado) > 0 ? 'L '+Number(r.monto_estimado).toFixed(2) : '—'}</td>
    <td><span class="badge ${UB[r.urgencia]||'badge-gray'}">${r.urgencia || 'Normal'}</span></td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick="verDetalle(${r.id})" title="Ver detalle">📋</button>
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})' title="Editar">✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})" title="Eliminar">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
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
    urgencia: r.urgencia || 'Normal', estado: r.estado || 'Pendiente',
    notas: r.notas || ''
  });
  attCot.setEntityId(r.id); attCot.load();
  attFac.setEntityId(r.id); attFac.load();
  openModal('modal');
}

async function guardar() {
  const d = getForm('modal');
  if (!d.descripcion) { toast('La descripción es obligatoria', 'error'); return; }
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

  document.getElementById('detail-content').innerHTML = `
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:#8892a4;width:150px;padding:6px 0">Solicitante</td><td style="padding:6px 0"><strong>${r.solicitante_nombre||'—'}</strong></td></tr>
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

  // Load attachments
  const attDetCot = new AttachmentWidget('att-detail-cot-wrap', 'oc_cotizacion', id);
  attDetCot.load();
  const attDetFac = new AttachmentWidget('att-detail-fac-wrap', 'oc_factura', id);
  attDetFac.load();

  <?php if($isAdmin): ?>
  const approvalSec = document.getElementById('approval-section');
  approvalSec.style.display = r.estado === 'Pendiente' ? '' : 'none';
  <?php endif; ?>
}

<?php if($isAdmin): ?>
async function aprobar() {
  if (!detailId) return;
  const notas = document.getElementById('notas-aprobacion').value.trim();
  await api('/api/ordenes_compra.php', 'PUT', { id: detailId, _accion: 'aprobar', notas_aprobacion: notas });
  toast('Orden aprobada ✅'); closeModal('modal-detail'); load();
}
async function rechazar() {
  if (!detailId) return;
  const notas = document.getElementById('notas-aprobacion').value.trim();
  if (!notas) { toast('Indica el motivo del rechazo', 'error'); return; }
  await api('/api/ordenes_compra.php', 'PUT', { id: detailId, _accion: 'rechazar', notas_aprobacion: notas });
  toast('Orden rechazada', 'warning'); closeModal('modal-detail'); load();
}
<?php endif; ?>

document.addEventListener('DOMContentLoaded', load);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Órdenes de Compra', 'ordenes_compra', $content);
?>
