<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar proveedor..." oninput="load()"></div>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Proveedor</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>Tipo</th><th>Teléfono</th><th>Dirección</th><th>Email</th><th>Notas</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🏪 Nuevo Proveedor</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Nombre *</label><input name="nombre" placeholder="Taller Mecánico XYZ"></div>
      <div class="form-group"><label>Tipo</label><select name="tipo"><option>Taller mecánico</option><option>Estación de combustible</option><option>Llantería</option><option>Eléctrico automotriz</option><option>Refaccionaria</option><option>Otro</option></select></div>
      <div class="form-group"><label>Teléfono</label><input name="telefono" placeholder="+504 2222-2222"></div>
      <div class="form-group"><label>Email</label><input name="email" type="email"></div>
      <div class="form-group full"><label>Dirección</label><input name="direccion" placeholder="Dirección del proveedor"></div>
      <div class="form-group full"><label>Notas / Especialidades</label><textarea name="notas" placeholder="Servicios que ofrece, notas importantes..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
const pager=new Paginator('pgr',load,25);
const TB={'Taller mecánico':'badge-blue','Estación de combustible':'badge-orange','Llantería':'badge-green'};
async function load(){
  const q=document.getElementById('s').value;
  const data=await api(`/api/proveedores.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="7"><div class="empty"><div class="empty-icon">🏪</div><div class="empty-title">Sin proveedores</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>`<tr>
    <td><strong>${r.nombre}</strong></td>
    <td><span class="badge ${TB[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td>${r.telefono||'—'}</td><td class="td-truncate">${r.direccion||'—'}</td>
    <td>${r.email||'—'}</td><td class="td-truncate">${r.notas||'—'}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='🏪 Nuevo Proveedor';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Proveedor';fillForm('modal',{id:r.id,nombre:r.nombre,tipo:r.tipo,telefono:r.telefono,email:r.email,direccion:r.direccion,notas:r.notas});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.nombre){toast('El nombre es obligatorio','error');return;}await api('/api/proveedores.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Proveedor registrado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este proveedor?',async()=>{await api(`/api/proveedores.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Proveedores','proveedores',$content); ?>
