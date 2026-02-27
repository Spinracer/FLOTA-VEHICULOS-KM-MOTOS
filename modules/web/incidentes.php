<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por placa, tipo, descripción..." oninput="load()"></div>
  <select id="fv" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <select id="fest" onchange="load()" style="max-width:140px"><option value="">Todos los estados</option><option>Abierto</option><option>En proceso</option><option>Cerrado</option></select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Reportar Incidente</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Tipo</th><th>Descripción</th><th>Severidad</th><th>Costo est.</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">⚠️ Reportar Incidente</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fecha *</label><input name="fecha" type="date"></div>
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id"><option value="">— Seleccionar —</option><?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Tipo</label><select name="tipo"><option>Accidente</option><option>Falla mecánica</option><option>Robo</option><option>Infracción</option><option>Daño menor</option><option>Otro</option></select></div>
      <div class="form-group"><label>Severidad</label><select name="severidad"><option>Baja</option><option>Media</option><option>Alta</option><option>Crítica</option></select></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Abierto</option><option>En proceso</option><option>Cerrado</option></select></div>
      <div class="form-group"><label>Costo estimado ($)</label><input name="costo_est" type="number" step="0.01" placeholder="0.00"></div>
      <div class="form-group full"><label>Descripción *</label><textarea name="descripcion" placeholder="Descripción del incidente..." style="min-height:100px"></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
const pager=new Paginator('pgr',load,25);
const SB={'Baja':'badge-green','Media':'badge-yellow','Alta':'badge-orange','Crítica':'badge-red'};
const EB={'Abierto':'badge-red','En proceso':'badge-orange','Cerrado':'badge-green'};
async function load(){
  const q=document.getElementById('s').value,vid=document.getElementById('fv').value,est=document.getElementById('fest').value;
  const data=await api(`/api/incidentes.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(est)}&vehiculo_id=${vid}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="8"><div class="empty"><div class="empty-icon">✅</div><div class="empty-title">Sin incidentes</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>`<tr>
    <td>${r.fecha}</td>
    <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
    <td>${r.tipo}</td>
    <td class="td-truncate">${r.descripcion}</td>
    <td><span class="badge ${SB[r.severidad]||'badge-gray'}">${r.severidad}</span></td>
    <td>${Number(r.costo_est)>0?'$'+Number(r.costo_est).toFixed(2):'—'}</td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='⚠️ Reportar Incidente';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Incidente';fillForm('modal',{id:r.id,fecha:r.fecha,vehiculo_id:r.vehiculo_id,tipo:r.tipo,severidad:r.severidad,estado:r.estado,costo_est:r.costo_est,descripcion:r.descripcion});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.vehiculo_id||!d.descripcion){toast('Vehículo y descripción son obligatorios','error');return;}await api('/api/incidentes.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Incidente reportado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este incidente?',async()=>{await api(`/api/incidentes.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Gestión de Incidentes','incidentes',$content); ?>
