<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por placa, tipo, aseguradora, póliza..." oninput="load()"></div>
  <select id="fv" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <select id="fest" onchange="load()" style="max-width:140px"><option value="">Todos los estados</option><option>Abierto</option><option>En proceso</option><option>Cerrado</option></select>
  <select id="freclamo" onchange="load()" style="max-width:150px"><option value="">Todos</option><option value="1">Con reclamo</option><option value="0">Sin reclamo</option></select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Reportar Incidente</button><?php endif; ?>
</div>

<!-- Stats cards -->
<div class="stats-row" id="stats-bar" style="margin-bottom:18px;display:flex;gap:12px;flex-wrap:wrap;"></div>

<div class="table-wrap">
  <table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Tipo</th><th>Severidad</th><th>Costo est.</th><th>Seguro</th><th>Reclamo</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>

<!-- MODAL PRINCIPAL -->
<div class="modal-bg" id="modal">
  <div class="modal" style="max-width:700px">
    <div class="modal-title" id="mtitle">⚠️ Reportar Incidente</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fecha *</label><input name="fecha" type="date"></div>
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id"><option value="">— Seleccionar —</option><?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Tipo</label><select name="tipo"><option>Accidente</option><option>Falla mecánica</option><option>Robo</option><option>Infracción</option><option>Daño menor</option><option>Otro</option></select></div>
      <div class="form-group"><label>Severidad</label><select name="severidad"><option>Baja</option><option>Media</option><option>Alta</option><option>Crítica</option></select></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Abierto</option><option>En proceso</option><option>Cerrado</option></select></div>
      <div class="form-group"><label>Costo estimado ($)</label><input name="costo_est" type="number" step="0.01" placeholder="0.00"></div>
      <div class="form-group full"><label>Descripción *</label><textarea name="descripcion" placeholder="Descripción del incidente..." style="min-height:80px"></textarea></div>

      <!-- Sección de Seguros -->
      <div class="form-group full" style="margin-top:8px;padding-top:12px;border-top:1px solid var(--border)">
        <label style="font-size:15px;font-weight:600;color:var(--accent2,#5effc1)">🛡️ Información de Seguro</label>
      </div>
      <div class="form-group"><label>Aseguradora</label><input name="aseguradora" placeholder="Ej: Qualitas, GNP, AXA..."></div>
      <div class="form-group"><label>No. Póliza</label><input name="poliza_numero" placeholder="POL-00000"></div>
      <div class="form-group"><label>¿Reclamo al seguro?</label><select name="tiene_reclamo" onchange="toggleReclamo(this.value)"><option value="0">No</option><option value="1">Sí</option></select></div>
      <div class="form-group reclamo-field" style="display:none"><label>Estado reclamo</label><select name="estado_reclamo"><option>N/A</option><option>En proceso</option><option>Aprobado</option><option>Rechazado</option><option>Pagado</option></select></div>
      <div class="form-group reclamo-field" style="display:none"><label>Monto reclamo ($)</label><input name="monto_reclamo" type="number" step="0.01" placeholder="0.00"></div>
      <div class="form-group reclamo-field" style="display:none"><label>Fecha reclamo</label><input name="fecha_reclamo" type="date"></div>
      <div class="form-group reclamo-field" style="display:none"><label>Ref. reclamo</label><input name="referencia_reclamo" placeholder="No. siniestro"></div>
      <div class="form-group full reclamo-field" style="display:none"><label>Notas seguro</label><textarea name="notas_seguro" placeholder="Observaciones del reclamo..." style="min-height:60px"></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>

<!-- MODAL DETALLE -->
<div class="modal-bg" id="modal-detail">
  <div class="modal" style="max-width:650px">
    <div class="modal-title">📋 Detalle del Incidente</div>
    <div id="detail-content" style="max-height:70vh;overflow-y:auto;font-size:14px;">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-detail')">Cerrar</button></div>
  </div>
</div>

<script>
const pager=new Paginator('pgr',load,25);
const SB={'Baja':'badge-green','Media':'badge-yellow','Alta':'badge-orange','Crítica':'badge-red'};
const EB={'Abierto':'badge-red','En proceso':'badge-orange','Cerrado':'badge-green'};
const RB={'N/A':'badge-gray','En proceso':'badge-orange','Aprobado':'badge-green','Rechazado':'badge-red','Pagado':'badge-green'};

function toggleReclamo(v){
  document.querySelectorAll('.reclamo-field').forEach(el=>el.style.display=v==='1'?'':'none');
}

async function load(){
  const q=document.getElementById('s').value, vid=document.getElementById('fv').value,
        est=document.getElementById('fest').value, rec=document.getElementById('freclamo').value;
  const data=await api(`/api/incidentes.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(est)}&vehiculo_id=${vid}&tiene_reclamo=${rec}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);

  // Stats
  const rows = data.rows;
  const abiertos = rows.filter(r=>r.estado==='Abierto').length;
  const conReclamo = rows.filter(r=>Number(r.tiene_reclamo)===1).length;
  const costoTotal = rows.reduce((s,r)=>s+Number(r.costo_est||0),0);
  const reclamoTotal = rows.reduce((s,r)=>s+Number(r.monto_reclamo||0),0);
  document.getElementById('stats-bar').innerHTML=`
    <div class="stat-card"><div class="stat-value">${data.total}</div><div class="stat-label">Total</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#ff4757">${abiertos}</div><div class="stat-label">Abiertos</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#ffa502">${conReclamo}</div><div class="stat-label">Con reclamo</div></div>
    <div class="stat-card"><div class="stat-value">$${costoTotal.toFixed(0)}</div><div class="stat-label">Costo est.</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#5effc1">$${reclamoTotal.toFixed(0)}</div><div class="stat-label">Reclamos</div></div>
  `;

  const tbody=document.getElementById('tbody');
  if(!rows.length){tbody.innerHTML=`<tr><td colspan="9"><div class="empty"><div class="empty-icon">✅</div><div class="empty-title">Sin incidentes</div></div></td></tr>`;return;}
  tbody.innerHTML=rows.map(r=>`<tr>
    <td>${r.fecha}</td>
    <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
    <td>${r.tipo}</td>
    <td><span class="badge ${SB[r.severidad]||'badge-gray'}">${r.severidad}</span></td>
    <td>${Number(r.costo_est)>0?'$'+Number(r.costo_est).toFixed(2):'—'}</td>
    <td>${r.aseguradora||'—'}</td>
    <td>${Number(r.tiene_reclamo)?'<span class="badge '+RB[r.estado_reclamo]+'">'+r.estado_reclamo+'</span>':'—'}</td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick="verDetalle(${r.id})" title="Ver detalle">📋</button>
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}

function abrirNuevo(){
  document.getElementById('mtitle').textContent='⚠️ Reportar Incidente';
  resetForm('modal');toggleReclamo('0');openModal('modal');
}
function editar(r){
  document.getElementById('mtitle').textContent='✏️ Editar Incidente';
  fillForm('modal',{id:r.id,fecha:r.fecha,vehiculo_id:r.vehiculo_id,tipo:r.tipo,severidad:r.severidad,
    estado:r.estado,costo_est:r.costo_est,descripcion:r.descripcion,
    aseguradora:r.aseguradora||'',poliza_numero:r.poliza_numero||'',
    tiene_reclamo:r.tiene_reclamo||'0',estado_reclamo:r.estado_reclamo||'N/A',
    monto_reclamo:r.monto_reclamo||'',fecha_reclamo:r.fecha_reclamo||'',
    referencia_reclamo:r.referencia_reclamo||'',notas_seguro:r.notas_seguro||''});
  toggleReclamo(String(r.tiene_reclamo||0));
  openModal('modal');
}
async function guardar(){
  const d=getForm('modal');
  if(!d.vehiculo_id||!d.descripcion){toast('Vehículo y descripción son obligatorios','error');return;}
  await api('/api/incidentes.php',d.id?'PUT':'POST',d);
  toast(d.id?'Actualizado':'Incidente reportado');closeModal('modal');load();
}
async function del(id){confirmDelete('¿Eliminar este incidente?',async()=>{await api(`/api/incidentes.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}

async function verDetalle(id){
  openModal('modal-detail');
  const r = await api(`/api/incidentes.php?detail=${id}`);
  if(!r || !r.id){document.getElementById('detail-content').innerHTML='<div class="empty"><div class="empty-icon">❌</div><div class="empty-title">No encontrado</div></div>';return;}
  const seguroHtml = r.aseguradora ? `
    <tr><td style="color:#8892a4">Aseguradora</td><td><strong>${r.aseguradora}</strong></td></tr>
    <tr><td style="color:#8892a4">Póliza</td><td>${r.poliza_numero||'—'}</td></tr>
    ${Number(r.tiene_reclamo)?`
    <tr><td style="color:#8892a4">Estado reclamo</td><td><span class="badge ${RB[r.estado_reclamo]||'badge-gray'}">${r.estado_reclamo}</span></td></tr>
    <tr><td style="color:#8892a4">Monto reclamo</td><td>$${Number(r.monto_reclamo).toFixed(2)}</td></tr>
    <tr><td style="color:#8892a4">Fecha reclamo</td><td>${r.fecha_reclamo||'—'}</td></tr>
    <tr><td style="color:#8892a4">Ref. siniestro</td><td>${r.referencia_reclamo||'—'}</td></tr>
    <tr><td style="color:#8892a4">Notas seguro</td><td>${r.notas_seguro||'—'}</td></tr>
    `:''}
  ` : '<tr><td colspan="2" style="color:#8892a4">Sin información de seguro</td></tr>';

  document.getElementById('detail-content').innerHTML=`
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="color:#8892a4;width:140px;padding:6px 0">Fecha</td><td style="padding:6px 0">${r.fecha}</td></tr>
      <tr><td style="color:#8892a4">Vehículo</td><td><strong style="color:var(--accent)">${r.placa} ${r.marca} ${r.modelo||''}</strong></td></tr>
      <tr><td style="color:#8892a4">Tipo</td><td>${r.tipo}</td></tr>
      <tr><td style="color:#8892a4">Severidad</td><td><span class="badge ${SB[r.severidad]}">${r.severidad}</span></td></tr>
      <tr><td style="color:#8892a4">Estado</td><td><span class="badge ${EB[r.estado]}">${r.estado}</span></td></tr>
      <tr><td style="color:#8892a4">Costo estimado</td><td>$${Number(r.costo_est).toFixed(2)}</td></tr>
      <tr><td style="color:#8892a4">Descripción</td><td>${r.descripcion}</td></tr>
      <tr><td colspan="2" style="padding:12px 0 6px;border-top:1px solid var(--border);font-weight:600;color:var(--accent2)">🛡️ Seguro</td></tr>
      ${seguroHtml}
      ${r.vehiculo_venc_seguro?`<tr><td style="color:#8892a4">Venc. seguro vehículo</td><td>${r.vehiculo_venc_seguro}</td></tr>`:''}
    </table>`;
}

document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Gestión de Incidentes','incidentes',$content); ?>
