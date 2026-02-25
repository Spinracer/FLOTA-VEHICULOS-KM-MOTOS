<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
require_admin();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar usuario..." oninput="load()"></div>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Usuario</button>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Creado</th><th>Acciones</th></tr></thead>
  <tbody id="tbody"></tbody></table>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🔑 Nuevo Usuario</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group full"><label>Nombre completo *</label><input name="nombre" placeholder="Juan González"></div>
      <div class="form-group"><label>Email *</label><input name="email" type="email" placeholder="usuario@empresa.com"></div>
      <div class="form-group"><label>Contraseña <span id="pass-note" style="color:var(--text2);font-size:10px">(requerida)</span></label>
        <input name="password" type="password" id="pass-input" placeholder="Mínimo 6 caracteres"></div>
      <div class="form-group"><label>Rol</label>
        <select name="rol">
          <option value="monitoreo">👁 Monitoreo (solo lectura)</option>
          <option value="soporte">🛠 Soporte (crear/editar)</option>
          <option value="coordinador_it">🔑 Coordinador IT (admin total)</option>
        </select></div>
      <div class="form-group"><label>Estado</label>
        <select name="activo"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
    </div>
    <div style="margin-top:14px;padding:12px 14px;background:var(--surface2);border-radius:8px;font-size:12px;color:var(--text2)">
      <strong style="color:var(--accent)">Roles del sistema:</strong><br>
      🔑 <strong>Coordinador IT</strong> — Acceso total, administra usuarios y permisos<br>
      🛠️ <strong>Soporte</strong> — Puede ver, crear y editar registros<br>
      👁️ <strong>Monitoreo</strong> — Solo puede visualizar información (lectura)
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>
<script>
async function load(){
  const q=(document.getElementById('s').value||'').toLowerCase();
  const data=await api('/api/usuarios.php');
  const rows=data.rows.filter(u=>!q||u.nombre.toLowerCase().includes(q)||u.email.toLowerCase().includes(q));
  const EB={'coordinador_it':'badge-yellow','soporte':'badge-blue','monitoreo':'badge-cyan','admin':'badge-yellow','operador':'badge-blue','lectura':'badge-gray'};
  const RL={'coordinador_it':'Coordinador IT','soporte':'Soporte','monitoreo':'Monitoreo','admin':'Admin','operador':'Operador','lectura':'Lectura'};
  const tbody=document.getElementById('tbody');
  if(!rows.length){tbody.innerHTML=`<tr><td colspan="7"><div class="empty"><div class="empty-icon">🔑</div><div class="empty-title">Sin usuarios</div></div></td></tr>`;return;}
  tbody.innerHTML=rows.map(u=>`<tr>
    <td><strong>${u.nombre}</strong></td>
    <td>${u.email}</td>
    <td><span class="badge ${EB[u.rol]||'badge-gray'}">${RL[u.rol]||u.rol}</span></td>
    <td><span class="badge ${u.activo=='1'?'badge-green':'badge-red'}">${u.activo=='1'?'Activo':'Inactivo'}</span></td>
    <td>${u.ultimo_acceso||'—'}</td>
    <td>${u.created_at?.split(' ')[0]||'—'}</td>
    <td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(u)})'>✏️</button>
      <button class="btn btn-danger btn-sm" onclick="del(${u.id})">🗑️</button>
    </div></td>
  </tr>`).join('');
}
function abrirNuevo(){
  document.getElementById('mtitle').textContent='🔑 Nuevo Usuario';
  document.getElementById('pass-note').textContent='(requerida)';
  document.getElementById('pass-input').required=true;
  resetForm('modal'); openModal('modal');
}
function editar(u){
  document.getElementById('mtitle').textContent='✏️ Editar Usuario';
  document.getElementById('pass-note').textContent='(dejar vacío para no cambiar)';
  document.getElementById('pass-input').required=false;
  fillForm('modal',{id:u.id,nombre:u.nombre,email:u.email,rol:u.rol,activo:u.activo});
  document.querySelector('#modal [name=password]').value='';
  openModal('modal');
}
async function guardar(){
  const d=getForm('modal');
  if(!d.nombre||!d.email){toast('Nombre y email son obligatorios','error');return;}
  if(!d.id&&!d.password){toast('La contraseña es obligatoria para nuevos usuarios','error');return;}
  try{await api('/api/usuarios.php',d.id?'PUT':'POST',d);toast(d.id?'Usuario actualizado':'Usuario creado');closeModal('modal');load();}catch(e){}
}
async function del(id){confirmDelete('¿Eliminar este usuario?',async()=>{try{await api(`/api/usuarios.php?id=${id}`,'DELETE');toast('Usuario eliminado','warning');load();}catch(e){}});}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Gestión de Usuarios','usuarios',$content); ?>
