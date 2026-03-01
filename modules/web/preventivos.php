<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/catalogos.php';
require_login();
$db = getDB();
$vehiculos   = $db->query("SELECT id,placa,marca,modelo,km_actual FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$tiposMantenimiento = catalogo_items('tipos_mantenimiento');
ob_start();
?>
<!-- Alertas -->
<div id="alertas-panel" style="margin-bottom:18px"></div>

<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span>
    <input type="text" id="s" placeholder="Buscar..."></div>
  <select id="fv" onchange="loadIntervals()" style="max-width:200px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Intervalo</button><?php endif; ?>
  <button class="btn btn-ghost" onclick="checkAlertas()">🔔 Verificar vencimientos</button>
</div>

<div class="table-wrap">
  <table><thead><tr><th>Vehículo</th><th>Tipo</th><th>Cada KM</th><th>Cada Días</th><th>Último KM</th><th>Última Fecha</th><th>KM Actual</th><th>Taller</th><th>Notas</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table>
</div>

<!-- Modal intervalo -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">📅 Nuevo Intervalo Preventivo</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id">
        <option value="">— Seleccionar —</option>
        <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group"><label>Tipo *</label><select name="tipo">
        <?php foreach($tiposMantenimiento as $tm): ?><option value="<?=htmlspecialchars($tm['nombre'])?>"><?=htmlspecialchars($tm['nombre'])?></option><?php endforeach; ?>
        <?php if(empty($tiposMantenimiento)): ?><option>Preventivo</option><option>Aceite y Filtros</option><?php endif; ?>
      </select></div>
      <div class="form-group"><label>Cada KM</label><input name="cada_km" type="number" step="0.1" placeholder="5000"></div>
      <div class="form-group"><label>Cada Días</label><input name="cada_dias" type="number" placeholder="90"></div>
      <div class="form-group"><label>Último KM servicio</label><input name="ultimo_km" type="number" step="0.1" placeholder="45000"></div>
      <div class="form-group"><label>Última fecha servicio</label><input name="ultima_fecha" type="date"></div>
      <div class="form-group"><label>Taller preferido</label><select name="proveedor_id">
        <option value="">— Ninguno —</option>
        <?php foreach($proveedores as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option><?php endforeach; ?>
      </select></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>

<script>
async function loadIntervals() {
  const vid = document.getElementById('fv').value;
  const data = await api(`/api/preventivos.php?action=intervals&vehiculo_id=${vid}`);
  const tbody = document.getElementById('tbody');
  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="10"><div class="empty"><div class="empty-icon">📅</div><div class="empty-title">Sin intervalos configurados</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td><strong style="color:var(--accent2)">${r.placa} ${r.marca}</strong><br><small style="color:#8892a4">KM: ${Number(r.km_actual).toLocaleString()}</small></td>
    <td><span class="badge badge-yellow">${r.tipo}</span></td>
    <td>${r.cada_km ? Number(r.cada_km).toLocaleString()+' km' : '—'}</td>
    <td>${r.cada_dias ? r.cada_dias+' días' : '—'}</td>
    <td>${r.ultimo_km ? Number(r.ultimo_km).toLocaleString()+' km' : '—'}</td>
    <td>${r.ultima_fecha || '—'}</td>
    <td>${Number(r.km_actual).toLocaleString()} km</td>
    <td>${r.proveedor_nombre || '—'}</td>
    <td class="td-truncate">${r.notas || '—'}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}

function abrirNuevo() {
  document.getElementById('mtitle').textContent = '📅 Nuevo Intervalo Preventivo';
  resetForm('modal'); openModal('modal');
}
function editar(r) {
  document.getElementById('mtitle').textContent = '✏️ Editar Intervalo';
  fillForm('modal', {id:r.id, vehiculo_id:r.vehiculo_id, tipo:r.tipo, cada_km:r.cada_km, cada_dias:r.cada_dias, ultimo_km:r.ultimo_km, ultima_fecha:r.ultima_fecha, proveedor_id:r.proveedor_id, notas:r.notas});
  openModal('modal');
}
async function guardar() {
  const d = getForm('modal');
  if (!d.vehiculo_id || !d.tipo) { toast('Vehículo y tipo son obligatorios','error'); return; }
  if (!d.cada_km && !d.cada_dias) { toast('Configura al menos cada_km o cada_dias','error'); return; }
  await api('/api/preventivos.php?action=intervals', d.id?'PUT':'POST', d);
  toast(d.id?'Intervalo actualizado':'Intervalo creado'); closeModal('modal'); loadIntervals();
}
async function del(id) {
  confirmDelete('¿Desactivar este intervalo?', async () => {
    await api(`/api/preventivos.php?action=intervals&id=${id}`, 'DELETE');
    toast('Intervalo desactivado','warning'); loadIntervals();
  });
}

async function checkAlertas() {
  const vid = document.getElementById('fv').value;
  const data = await api(`/api/preventivos.php?action=check&vehiculo_id=${vid}`);
  const panel = document.getElementById('alertas-panel');
  if (!data.alertas.length) {
    panel.innerHTML = '<div class="stat-pill" style="background:#1a2e1a;border-color:#2ed573;color:#2ed573">✅ Sin vencimientos próximos</div>';
    return;
  }
  panel.innerHTML = data.alertas.map(a => {
    const isVencido = a.estado === 'vencido';
    const bg = isVencido ? '#2e1a1a' : '#2e2a1a';
    const border = isVencido ? '#ff4757' : '#ffa502';
    const color = isVencido ? '#ff4757' : '#ffa502';
    const icon = isVencido ? '🔴' : '🟡';
    let info = '';
    if (a.km_restante !== null) info += `KM: ${a.km_restante > 0 ? a.km_restante+' restantes' : Math.abs(a.km_restante)+' excedidos'}`;
    if (a.dias_restante !== null) info += (info ? ' | ' : '') + `Días: ${a.dias_restante > 0 ? a.dias_restante+' restantes' : Math.abs(a.dias_restante)+' excedidos'}`;
    return `<div style="background:${bg};border:1px solid ${border};color:${color};padding:10px 16px;border-radius:8px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;font-size:13px">
      <div>${icon} <strong>${a.placa} ${a.marca}</strong> — ${a.tipo} — ${info}</div>
      ${<?= can('create') ? 'true' : 'false' ?> ? `<button class="btn btn-primary btn-sm" onclick="crearOT(${a.interval_id})">+ Crear OT</button>` : ''}
    </div>`;
  }).join('');
}

async function crearOT(intervalId) {
  const resp = await api('/api/preventivos.php?action=create_ot', 'POST', {interval_id: intervalId});
  if (resp.ok) {
    toast('OT #' + resp.ot_id + ' creada exitosamente');
    checkAlertas();
    loadIntervals();
  }
}

document.addEventListener('DOMContentLoaded', () => { loadIntervals(); checkAlertas(); });
</script>
<?php $content = ob_get_clean(); echo render_layout('Mantenimiento Preventivo','preventivos',$content); ?>
