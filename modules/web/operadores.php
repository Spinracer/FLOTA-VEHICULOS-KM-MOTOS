<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar operador..." oninput="load()"></div>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Operador</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>Licencia</th><th>Cat.</th><th>Teléfono</th><th>Vehículo asignado</th><th>Venc. licencia</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">👤 Nuevo Operador</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group full"><label>Nombre completo *</label><input name="nombre" placeholder="Juan Pérez García"></div>
      <div class="form-group"><label>No. Licencia</label><input name="licencia" placeholder="L12345678"></div>
      <div class="form-group"><label>Categoría</label><select name="categoria_lic"><option>A</option><option>B</option><option>C</option><option>D</option><option>E</option></select></div>
      <div class="form-group"><label>Venc. licencia</label><input name="venc_licencia" type="date"></div>
      <div class="form-group"><label>Teléfono</label><input name="telefono" placeholder="+504 9999-9999"></div>
      <div class="form-group"><label>Email</label><input name="email" type="email" placeholder="op@empresa.com"></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Activo</option><option>Inactivo</option><option>Suspendido</option></select></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
const pager=new Paginator('pgr',load,25);
const EB={'Activo':'badge-green','Inactivo':'badge-gray','Suspendido':'badge-red'};
async function load(){
  const q=document.getElementById('s').value;
  const data=await api(`/api/operadores.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="8"><div class="empty"><div class="empty-icon">👤</div><div class="empty-title">Sin operadores</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>{
    const dias=parseInt(r.dias_licencia);
    let lb='badge-green',lt='Vigente';
    if(!r.venc_licencia){lb='badge-gray';lt='—';}
    else if(dias<0){lb='badge-red';lt='Vencida';}
    else if(dias<=30){lb='badge-orange';lt=dias+'d restantes';}
    return `<tr>
      <td><strong>${r.nombre}</strong></td>
      <td>${r.licencia||'—'}</td><td>${r.categoria_lic||'—'}</td><td>${r.telefono||'—'}</td>
      <td>${r.vehiculo_placa?r.vehiculo_placa+' '+r.vehiculo_marca:'—'}</td>
      <td><span class="badge ${lb}">${r.venc_licencia||lt}</span></td>
      <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
      <?php if(can('edit')): ?><td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
        <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
      </div></td><?php endif; ?>
    </tr>`;
  }).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='👤 Nuevo Operador';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Operador';fillForm('modal',{id:r.id,nombre:r.nombre,licencia:r.licencia,categoria_lic:r.categoria_lic,venc_licencia:r.venc_licencia,telefono:r.telefono,email:r.email,estado:r.estado,notas:r.notas});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.nombre){toast('El nombre es obligatorio','error');return;}await api('/api/operadores.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Operador registrado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este operador?',async()=>{await api(`/api/operadores.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Operadores','operadores',$content); ?>
