<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
$operadores = $db->query("SELECT id,nombre,estado FROM operadores ORDER BY nombre")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por placa, operador..." oninput="load()"></div>
  <select id="fv" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca']) ?></option><?php endforeach; ?>
  </select>
  <select id="fe" onchange="load()" style="max-width:160px"><option value="">Todos</option><option>Activa</option><option>Cerrada</option></select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="openNew()">+ Nueva Asignación</button><?php endif; ?>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Vehículo</th><th>Operador</th><th>Inicio</th><th>KM Inicio</th><th>Fin</th><th>KM Fin</th><th>Estado</th>
        <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  <div id="pgr"></div>
</div>

<div class="modal-bg" id="modal-new">
  <div class="modal">
    <div class="modal-title">📝 Nueva Asignación</div>
    <div class="form-grid">
      <div class="form-group"><label>Vehículo *</label>
        <select name="vehiculo_id">
          <option value="">— Seleccionar —</option>
          <?php foreach($vehiculos as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Operador *</label>
        <select name="operador_id">
          <option value="">— Seleccionar —</option>
          <?php foreach($operadores as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre'].' ('.$o['estado'].')') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Inicio *</label><input name="start_at" type="datetime-local"></div>
      <div class="form-group"><label>KM Inicio</label><input name="start_km" type="number" step="0.1" placeholder="45000"></div>
      <div class="form-group full"><label>Notas</label><textarea name="start_notes" placeholder="Observaciones de entrega..."></textarea></div>
      <div class="form-group full"><label>Justificación override (solo admin)</label><textarea name="override_reason" placeholder="Solo si necesitas saltar un bloqueo."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-new')">Cancelar</button><button class="btn btn-primary" onclick="saveNew()">Guardar</button></div>
  </div>
</div>

<div class="modal-bg" id="modal-close">
  <div class="modal">
    <div class="modal-title">✅ Cerrar Asignación</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fin *</label><input name="end_at" type="datetime-local"></div>
      <div class="form-group"><label>KM Fin *</label><input name="end_km" type="number" step="0.1" placeholder="45500"></div>
      <div class="form-group full"><label>Notas de cierre</label><textarea name="end_notes" placeholder="Observaciones de retorno..."></textarea></div>
      <div class="form-group full"><label>Justificación override (solo admin)</label><textarea name="override_reason" placeholder="Solo si necesitas saltar validación de odómetro."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-close')">Cancelar</button><button class="btn btn-primary" onclick="saveClose()">Cerrar asignación</button></div>
  </div>
</div>

<script>
const pager = new Paginator('pgr', load, 25);

async function load(){
  const q = document.getElementById('s').value;
  const vid = document.getElementById('fv').value;
  const est = document.getElementById('fe').value;
  const data = await api(`/api/asignaciones.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody = document.getElementById('tbody');
  const EB = {'Activa':'badge-green','Cerrada':'badge-gray'};

  if(!data.rows.length){
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty"><div class="empty-icon">📝</div><div class="empty-title">Sin asignaciones</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td>${r.id}</td>
      <td><strong style="color:var(--accent2)">${r.placa || ''} ${r.marca || ''}</strong></td>
      <td>${r.operador_nombre || '—'}</td>
      <td>${(r.start_at || '').replace('T',' ').slice(0,16) || '—'}</td>
      <td>${r.start_km ? Number(r.start_km).toLocaleString()+' km' : '—'}</td>
      <td>${r.end_at ? String(r.end_at).slice(0,16) : '—'}</td>
      <td>${r.end_km ? Number(r.end_km).toLocaleString()+' km' : '—'}</td>
      <td><span class="badge ${EB[r.estado] || 'badge-gray'}">${r.estado}</span></td>
      <?php if(can('edit')): ?>
      <td>
        <div class="action-btns">
          <button class="btn btn-ghost btn-sm" onclick="window.open('/print.php?type=asignacion&id=${r.id}','_blank')" title="Imprimir PDF">🖨️</button>
          ${r.estado==='Activa' ? `<button class="btn btn-primary btn-sm" onclick='openClose(${JSON.stringify(r)})'>Cerrar</button>` : ''}
          <?php if(can('delete')): ?>
          <button class="btn btn-danger btn-sm" onclick="delItem(${r.id})">🗑️</button>
          <?php endif; ?>
        </div>
      </td>
      <?php endif; ?>
    </tr>`).join('');
}

function openNew(){
  resetForm('modal-new');
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset()*60000).toISOString().slice(0,16);
  document.querySelector('#modal-new [name=start_at]').value = local;
  openModal('modal-new');
}

async function saveNew(){
  const d = getForm('modal-new');
  if(!d.vehiculo_id || !d.operador_id || !d.start_at){ toast('Vehículo, operador e inicio son obligatorios','error'); return; }
  d.start_at = d.start_at.replace('T',' ')+':00';
  await api('/api/asignaciones.php', 'POST', d);
  toast('Asignación creada');
  closeModal('modal-new');
  load();
}

function openClose(r){
  resetForm('modal-close');
  fillForm('modal-close', { id: r.id });
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset()*60000).toISOString().slice(0,16);
  document.querySelector('#modal-close [name=end_at]').value = local;
  openModal('modal-close');
}

async function saveClose(){
  const d = getForm('modal-close');
  if(!d.id || !d.end_at || !d.end_km){ toast('Fin y km fin son obligatorios','error'); return; }
  d.end_at = d.end_at.replace('T',' ')+':00';
  await api('/api/asignaciones.php', 'PUT', { ...d, action: 'close' });
  toast('Asignación cerrada');
  closeModal('modal-close');
  load();
}

async function delItem(id){
  confirmDelete('¿Eliminar esta asignación?', async () => {
    await api(`/api/asignaciones.php?id=${id}`, 'DELETE');
    toast('Asignación eliminada', 'warning');
    load();
  });
}

document.addEventListener('DOMContentLoaded', load);
</script>

<?php $content = ob_get_clean(); echo render_layout('Asignaciones', 'asignaciones', $content); ?>
