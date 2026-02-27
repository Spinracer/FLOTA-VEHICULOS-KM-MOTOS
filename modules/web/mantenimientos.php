<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/catalogos.php';
require_login();
$db = getDB();
$vehiculos   = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$tiposMantenimiento = catalogo_items('tipos_mantenimiento');
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span>
    <input type="text" id="s" placeholder="Buscar por placa, tipo..." oninput="load()"></div>
  <select id="fv" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <select id="fest" onchange="load()" style="max-width:140px">
    <option value="">Todos los estados</option>
    <option>Completado</option><option>En proceso</option><option>Pendiente</option>
  </select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Mantenimiento</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Tipo</th><th>Descripción</th><th>Costo</th><th>KM</th><th>Proveedor</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🔧 Nuevo Mantenimiento</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fecha *</label><input name="fecha" type="date"></div>
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id"><option value="">— Seleccionar —</option><?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Tipo</label><select name="tipo">
        <?php foreach($tiposMantenimiento as $tm): ?><option value="<?=htmlspecialchars($tm['nombre'])?>"><?=htmlspecialchars($tm['nombre'])?></option><?php endforeach; ?>
        <?php if(empty($tiposMantenimiento)): ?><option>Preventivo</option><option>Correctivo</option><?php endif; ?>
      </select></div>
      <div class="form-group"><label>Costo ($)</label><input name="costo" type="number" step="0.01" placeholder="0.00"></div>
      <div class="form-group"><label>KM al momento</label><input name="km" type="number" step="0.1" placeholder="46500"></div>
      <div class="form-group"><label>Próximo servicio (km)</label><input name="proximo_km" type="number" placeholder="56500"></div>
      <div class="form-group"><label>Proveedor / Taller</label><select name="proveedor_id"><option value="">— Ninguno —</option><?php foreach($proveedores as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Completado</option><option>En proceso</option><option>Pendiente</option></select></div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Detalles del servicio..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
const pager=new Paginator('pgr',load,25);
async function load(){
  const q=document.getElementById('s').value,vid=document.getElementById('fv').value,est=document.getElementById('fest').value;
  const data=await api(`/api/mantenimientos.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const EB={'Completado':'badge-green','En proceso':'badge-orange','Pendiente':'badge-blue'};
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="9"><div class="empty"><div class="empty-icon">🔧</div><div class="empty-title">Sin mantenimientos</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>`<tr>
    <td>${r.fecha}</td>
    <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
    <td><span class="badge badge-yellow">${r.tipo}</span></td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td><strong style="color:var(--green)">$${Number(r.costo).toFixed(2)}</strong></td>
    <td>${r.km?Number(r.km).toLocaleString()+' km':'—'}</td>
    <td>${r.proveedor_nombre||'—'}</td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='🔧 Nuevo Mantenimiento';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Mantenimiento';fillForm('modal',{id:r.id,fecha:r.fecha,vehiculo_id:r.vehiculo_id,tipo:r.tipo,costo:r.costo,km:r.km,proximo_km:r.proximo_km,proveedor_id:r.proveedor_id,estado:r.estado,descripcion:r.descripcion});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.vehiculo_id){toast('Selecciona un vehículo','error');return;}await api('/api/mantenimientos.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Registrado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este mantenimiento?',async()=>{await api(`/api/mantenimientos.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Bitácora de Mantenimientos','mantenimientos',$content); ?>
