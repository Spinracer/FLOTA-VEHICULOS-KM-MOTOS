<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar recordatorio..." oninput="load()"></div>
  <select id="fest" onchange="load()" style="max-width:140px"><option value="">Todos</option><option>Pendiente</option><option>Completado</option><option>Cancelado</option></select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Recordatorio</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Vehículo</th><th>Tipo</th><th>Descripción</th><th>Fecha límite</th><th>Estado</th><th>Días restantes</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🔔 Nuevo Recordatorio</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id"><option value="">— Seleccionar —</option><?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Tipo</label><select name="tipo"><option>Vencimiento de licencia</option><option>Vencimiento de seguro</option><option>Mantenimiento preventivo</option><option>Revisión técnica</option><option>Cambio de aceite</option><option>Circulación / Placa</option><option>Otro</option></select></div>
      <div class="form-group"><label>Fecha límite *</label><input name="fecha_limite" type="date"></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Pendiente</option><option>Completado</option><option>Cancelado</option></select></div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Detalles del recordatorio..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
const pager=new Paginator('pgr',load,25);
async function load(){
  const q=document.getElementById('s').value,est=document.getElementById('fest').value;
  const data=await api(`/api/recordatorios.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="7"><div class="empty"><div class="empty-icon">🔔</div><div class="empty-title">Sin recordatorios</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>{
    const dias=parseInt(r.dias);
    let db2='badge-gray',dt='—';
    if(r.estado==='Completado'){db2='badge-green';dt='Completado';}
    else if(r.estado==='Cancelado'){db2='badge-gray';dt='Cancelado';}
    else if(dias<0){db2='badge-red';dt='Vencido '+Math.abs(dias)+'d';}
    else if(dias<=7){db2='badge-orange';dt=dias===0?'Hoy':dias+'d';}
    else if(dias<=30){db2='badge-yellow';dt=dias+'d';}
    else{db2='badge-gray';dt=dias+'d';}
    const EB={'Pendiente':'badge-orange','Completado':'badge-green','Cancelado':'badge-gray'};
    return `<tr>
      <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
      <td>${r.tipo}</td><td class="td-truncate">${r.descripcion||'—'}</td>
      <td>${r.fecha_limite}</td>
      <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
      <td><span class="badge ${db2}">${dt}</span></td>
      <?php if(can('edit')): ?><td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
        <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
      </div></td><?php endif; ?>
    </tr>`;
  }).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='🔔 Nuevo Recordatorio';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Recordatorio';fillForm('modal',{id:r.id,vehiculo_id:r.vehiculo_id,tipo:r.tipo,fecha_limite:r.fecha_limite,estado:r.estado,descripcion:r.descripcion});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.vehiculo_id||!d.fecha_limite){toast('Vehículo y fecha son obligatorios','error');return;}await api('/api/recordatorios.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Recordatorio creado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este recordatorio?',async()=>{await api(`/api/recordatorios.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Recordatorios y Alertas','recordatorios',$content); ?>
