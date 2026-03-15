<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por título, número, placa..." oninput="load()"></div>
  <select id="fveh" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <select id="ftipo" onchange="load()" style="max-width:160px">
    <option value="">Todos los tipos</option>
    <option value="revision">Revisión</option>
    <option value="factura">Factura</option>
    <option value="permiso">Permiso</option>
    <option value="placa_digital">Placa Digital</option>
    <option value="seguro">Seguro</option>
    <option value="otro">Otro</option>
  </select>
  <select id="fvenc" onchange="load()" style="max-width:160px">
    <option value="">Vencimiento</option>
    <option value="1">Vencidos</option>
    <option value="proximo">Por vencer (30d)</option>
  </select>
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Documento</button>
  <?php endif; ?>
</div>

<div class="stats-row" id="stats-bar" style="margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;"></div>

<div class="table-wrap">
  <table><thead><tr>
    <th>Vehículo</th><th>Tipo</th><th>Título</th><th>Nº Documento</th><th>Emisión</th><th>Vencimiento</th><th>Estado</th>
    <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
  </tr></thead>
  <tbody id="tbody"></tbody></table>
  <div id="pgr"></div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal-bg" id="modal">
  <div class="modal" style="max-width:650px">
    <div class="modal-title" id="mtitle">📄 Nuevo Documento Vehicular</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Vehículo *</label>
        <select name="vehiculo_id"><option value="">— Seleccionar —</option>
          <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Tipo *</label>
        <select name="tipo">
          <option value="">— Seleccionar —</option>
          <option value="revision">Revisión</option>
          <option value="factura">Factura</option>
          <option value="permiso">Permiso</option>
          <option value="placa_digital">Placa Digital</option>
          <option value="seguro">Seguro</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div class="form-group full"><label>Título *</label><input name="titulo" placeholder="Ej: Revisión técnica 2025, Factura compra repuestos..."></div>
      <div class="form-group"><label>Nº Documento</label><input name="numero_documento" placeholder="Ej: REV-2025-001"></div>
      <div class="form-group"><label>Fecha emisión</label><input name="fecha_emision" type="date"></div>
      <div class="form-group"><label>Fecha vencimiento</label><input name="fecha_vencimiento" type="date"></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..." style="min-height:50px"></textarea></div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
        <div id="att-doc-wrap"></div>
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
  <div class="modal" style="max-width:650px">
    <div class="modal-title">📋 Detalle del Documento</div>
    <div id="detail-content" style="max-height:60vh;overflow-y:auto;font-size:14px">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <div id="att-detail-wrap" style="margin-top:12px"></div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-detail')">Cerrar</button></div>
  </div>
</div>

<script>
const pager = new Paginator('pgr', load, 25);
const TIPO_LABELS = {revision:'📋 Revisión',factura:'🧾 Factura',permiso:'📝 Permiso',placa_digital:'🔢 Placa Digital',seguro:'🛡️ Seguro',otro:'📎 Otro'};
const TIPO_BADGE = {revision:'badge-blue',factura:'badge-green',permiso:'badge-yellow',placa_digital:'badge-purple',seguro:'badge-orange',otro:'badge-gray'};

const attDoc = new AttachmentWidget('att-doc-wrap', 'vehiculo_documentos');

function vencStatus(fecha) {
  if (!fecha) return {label:'Sin vencimiento',cls:'badge-gray'};
  const hoy = new Date(); hoy.setHours(0,0,0,0);
  const venc = new Date(fecha + 'T00:00:00');
  const diff = Math.ceil((venc - hoy) / 86400000);
  if (diff < 0) return {label:'Vencido',cls:'badge-red'};
  if (diff <= 30) return {label:`Vence en ${diff}d`,cls:'badge-orange'};
  return {label:'Vigente',cls:'badge-green'};
}

async function load() {
  const q = document.getElementById('s').value;
  const vid = document.getElementById('fveh').value;
  const tipo = document.getElementById('ftipo').value;
  const venc = document.getElementById('fvenc').value;
  const data = await api(`/api/vehiculo_documentos.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&tipo=${encodeURIComponent(tipo)}&vencidos=${venc}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);

  const st = data.stats || {};
  document.getElementById('stats-bar').innerHTML = `
    <div class="stat-card"><div class="stat-value">${st.total_docs||0}</div><div class="stat-label">Total docs</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#ff4757">${st.vencidos||0}</div><div class="stat-label">Vencidos</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#ffa502">${st.por_vencer||0}</div><div class="stat-label">Por vencer</div></div>
  `;

  const rows = data.rows;
  const tbody = document.getElementById('tbody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><div class="empty-icon">📄</div><div class="empty-title">Sin documentos vehiculares</div></div></td></tr>'; return; }

  tbody.innerHTML = rows.map(r => {
    const vs = vencStatus(r.fecha_vencimiento);
    return `<tr>
      <td><strong style="color:var(--accent2)">${r.placa||''}</strong> ${r.marca||''} ${r.modelo||''}</td>
      <td><span class="badge ${TIPO_BADGE[r.tipo]||'badge-gray'}">${TIPO_LABELS[r.tipo]||r.tipo}</span></td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.titulo||'—'}</td>
      <td>${r.numero_documento||'—'}</td>
      <td>${r.fecha_emision||'—'}</td>
      <td>${r.fecha_vencimiento||'—'}</td>
      <td><span class="badge ${vs.cls}">${vs.label}</span></td>
      <?php if(can('edit')): ?><td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick="verDetalle(${r.id})" title="Ver detalle">📋</button>
        <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})' title="Editar">✏️</button>
        <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})" title="Eliminar">🗑️</button><?php endif; ?>
      </div></td><?php endif; ?>
    </tr>`;
  }).join('');
}

function abrirNuevo() {
  document.getElementById('mtitle').textContent = '📄 Nuevo Documento Vehicular';
  resetForm('modal');
  attDoc.reset();
  openModal('modal');
}

function editar(r) {
  document.getElementById('mtitle').textContent = '✏️ Editar Documento';
  fillForm('modal', {
    id: r.id, vehiculo_id: r.vehiculo_id, tipo: r.tipo, titulo: r.titulo,
    numero_documento: r.numero_documento || '', fecha_emision: r.fecha_emision || '',
    fecha_vencimiento: r.fecha_vencimiento || '', notas: r.notas || ''
  });
  attDoc.setEntityId(r.id); attDoc.load();
  openModal('modal');
}

async function guardar() {
  const d = getForm('modal');
  if (!d.vehiculo_id || !d.tipo || !d.titulo) { toast('Vehículo, tipo y título son obligatorios', 'error'); return; }
  const res = await api('/api/vehiculo_documentos.php', d.id ? 'PUT' : 'POST', d);
  const savedId = d.id || res.id;
  if (attDoc.hasPending() && savedId) {
    await attDoc.uploadPending(savedId);
  }
  toast(d.id ? 'Documento actualizado' : 'Documento registrado');
  closeModal('modal'); load();
}

async function del(id) {
  confirmDelete('¿Eliminar este documento?', async () => {
    await api(`/api/vehiculo_documentos.php?id=${id}`, 'DELETE');
    toast('Documento eliminado', 'warning'); load();
  });
}

async function verDetalle(id) {
  openModal('modal-detail');
  const r = await api(`/api/vehiculo_documentos.php?detail=${id}`);
  if (!r || !r.id) { document.getElementById('detail-content').innerHTML = '<div class="empty"><div class="empty-icon">❌</div><div class="empty-title">No encontrado</div></div>'; return; }
  const vs = vencStatus(r.fecha_vencimiento);
  document.getElementById('detail-content').innerHTML = `
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:#8892a4;width:150px;padding:6px 0">Vehículo</td><td style="padding:6px 0"><strong style="color:var(--accent)">${r.placa} ${r.marca} ${r.modelo||''}</strong></td></tr>
      <tr><td style="color:#8892a4">Tipo</td><td><span class="badge ${TIPO_BADGE[r.tipo]||'badge-gray'}">${TIPO_LABELS[r.tipo]||r.tipo}</span></td></tr>
      <tr><td style="color:#8892a4">Título</td><td>${r.titulo||'—'}</td></tr>
      <tr><td style="color:#8892a4">Nº Documento</td><td>${r.numero_documento||'—'}</td></tr>
      <tr><td style="color:#8892a4">Emisión</td><td>${r.fecha_emision||'—'}</td></tr>
      <tr><td style="color:#8892a4">Vencimiento</td><td>${r.fecha_vencimiento||'—'} <span class="badge ${vs.cls}">${vs.label}</span></td></tr>
      <tr><td style="color:#8892a4">Registrado por</td><td>${r.creador_nombre||'—'} — ${r.created_at?.substring(0,10)||''}</td></tr>
      ${r.notas ? `<tr><td style="color:#8892a4">Notas</td><td>${r.notas}</td></tr>` : ''}
    </table>`;
  const attDetail = new AttachmentWidget('att-detail-wrap', 'vehiculo_documentos', id);
  attDetail.load();
}

document.addEventListener('DOMContentLoaded', load);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Documentos Vehiculares', 'vehiculo_documentos', $content);
?>
