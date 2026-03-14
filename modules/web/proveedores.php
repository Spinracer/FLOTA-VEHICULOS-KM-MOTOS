<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar proveedor..." oninput="load()"></div>
  <select id="faut" onchange="load()" style="max-width:240px">
    <option value="">Todos</option>
    <option value="1">Solo talleres autorizados</option>
  </select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Proveedor</button><?php endif; ?>
  <button class="btn btn-ghost" onclick="verRanking()">⭐ Ranking</button>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>Tipo</th><th>Taller Autorizado</th><th>Teléfono</th><th>Dirección</th><th>Email</th><th>Notas</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🏪 Nuevo Proveedor</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Nombre *</label><input name="nombre" placeholder="Taller Mecánico XYZ"></div>
      <div class="form-group"><label>Tipo</label><select name="tipo"><option>Taller mecánico</option><option>Estación de combustible</option><option>Llantería</option><option>Eléctrico automotriz</option><option>Refaccionaria</option><option>Otro</option></select></div>
      <div class="form-group"><label>Taller autorizado</label><select name="es_taller_autorizado"><option value="0">No</option><option value="1">Sí</option></select></div>
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
  const soloAut=document.getElementById('faut').value;
  const data=await api(`/api/proveedores.php?q=${encodeURIComponent(q)}&solo_autorizados=${encodeURIComponent(soloAut)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="8"><div class="empty"><div class="empty-icon">🏪</div><div class="empty-title">Sin proveedores</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>`<tr>
    <td><strong>${r.nombre}</strong></td>
    <td><span class="badge ${TB[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td><span class="badge ${String(r.es_taller_autorizado)==='1'?'badge-green':'badge-gray'}">${String(r.es_taller_autorizado)==='1'?'Sí':'No'}</span></td>
    <td>${r.telefono||'—'}</td><td class="td-truncate">${r.direccion||'—'}</td>
    <td>${r.email||'—'}</td><td class="td-truncate">${r.notas||'—'}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick="verEvals(${r.id},'${r.nombre.replace(/'/g,"\\'")}')">⭐</button>
      <button class="btn btn-ghost btn-sm" onclick="verContratos(${r.id},'${r.nombre.replace(/'/g,"\\'")}')">📋</button>
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}
function abrirNuevo(){document.getElementById('mtitle').textContent='🏪 Nuevo Proveedor';resetForm('modal');openModal('modal');}
function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Proveedor';fillForm('modal',{id:r.id,nombre:r.nombre,tipo:r.tipo,es_taller_autorizado:r.es_taller_autorizado,telefono:r.telefono,email:r.email,direccion:r.direccion,notas:r.notas});openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.nombre){toast('El nombre es obligatorio','error');return;}await api('/api/proveedores.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizado':'Proveedor registrado');closeModal('modal');load();}
async function del(id){confirmDelete('¿Eliminar este proveedor?',async()=>{await api(`/api/proveedores.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}

/* ════════════════ EVALUACIONES ════════════════ */
let evalProvId = 0;
async function verEvals(id, nombre) {
  evalProvId = id;
  const data = await api(`/api/proveedores.php?action=evaluaciones&proveedor_id=${id}`);
  const star = n => '★'.repeat(Math.round(n)) + '☆'.repeat(5-Math.round(n));
  const rows = data.rows.map(r => `<tr>
    <td>${r.periodo}</td>
    <td title="Calidad">${star(r.calidad)}</td>
    <td title="Puntualidad">${star(r.puntualidad)}</td>
    <td title="Precio">${star(r.precio)}</td>
    <td title="Servicio">${star(r.servicio)}</td>
    <td><strong>${Number(r.promedio).toFixed(2)}</strong></td>
    <td class="td-truncate">${r.comentario||'—'}</td>
    <td>${r.evaluador||'—'}</td>
  </tr>`).join('') || '<tr><td colspan="8"><div class="empty"><div class="empty-title">Sin evaluaciones</div></div></td></tr>';

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:950px">
      <div class="modal-title">⭐ Evaluaciones — ${nombre}</div>
      <div style="max-height:40vh;overflow:auto">
        <table><thead><tr><th>Período</th><th>Calidad</th><th>Puntualidad</th><th>Precio</th><th>Servicio</th><th>Prom.</th><th>Comentario</th><th>Evaluador</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
      <?php if(can('create')): ?>
      <h4 style="margin:12px 0 6px;color:var(--accent)">Nueva Evaluación</h4>
      <div class="form-grid" id="evalForm">
        <div class="form-group"><label>Período *</label><input id="ev_per" placeholder="2026-Q1"></div>
        <div class="form-group"><label>Calidad (1-5)</label><input type="number" id="ev_cal" min="1" max="5" value="3"></div>
        <div class="form-group"><label>Puntualidad (1-5)</label><input type="number" id="ev_pun" min="1" max="5" value="3"></div>
        <div class="form-group"><label>Precio (1-5)</label><input type="number" id="ev_pre" min="1" max="5" value="3"></div>
        <div class="form-group"><label>Servicio (1-5)</label><input type="number" id="ev_ser" min="1" max="5" value="3"></div>
        <div class="form-group full"><label>Comentario</label><input id="ev_com" placeholder="Observaciones..."></div>
        <div class="form-group"><button class="btn btn-primary btn-sm" onclick="addEval()">+ Evaluar</button></div>
      </div>
      <?php endif; ?>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
}
async function addEval() {
  const d = { proveedor_id: evalProvId, periodo: document.getElementById('ev_per').value, calidad: document.getElementById('ev_cal').value, puntualidad: document.getElementById('ev_pun').value, precio: document.getElementById('ev_pre').value, servicio: document.getElementById('ev_ser').value, comentario: document.getElementById('ev_com').value };
  if (!d.periodo) { toast('El período es obligatorio','error'); return; }
  await api('/api/proveedores.php?action=evaluaciones', 'POST', d);
  toast('Evaluación registrada');
  document.querySelector('.modal-bg.open')?.remove();
}

/* ════════════════ CONTRATOS ════════════════ */
let conProvId = 0;
async function verContratos(id, nombre) {
  conProvId = id;
  const data = await api(`/api/proveedores.php?action=contratos&proveedor_id=${id}`);
  const EB3 = {Vigente:'badge-green',Vencido:'badge-red',Cancelado:'badge-gray'};
  const rows = data.rows.map(r => `<tr>
    <td>${r.titulo}</td><td>${r.numero_contrato||'—'}</td>
    <td><span class="badge ${{'Servicio':'badge-blue','Suministro':'badge-cyan','Mantenimiento':'badge-orange','Otro':'badge-gray'}[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td>${r.fecha_inicio}</td><td>${r.fecha_fin||'Indefinido'}</td>
    <td>L ${Number(r.monto).toLocaleString()}</td>
    <td><span class="badge ${EB3[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <td class="td-truncate">${r.notas||'—'}</td>
  </tr>`).join('') || '<tr><td colspan="8"><div class="empty"><div class="empty-title">Sin contratos</div></div></td></tr>';

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:1000px">
      <div class="modal-title">📋 Contratos — ${nombre}</div>
      <div style="max-height:40vh;overflow:auto">
        <table><thead><tr><th>Título</th><th>Nº Contrato</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Monto</th><th>Estado</th><th>Notas</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
      <?php if(can('create')): ?>
      <h4 style="margin:12px 0 6px;color:var(--accent)">Nuevo Contrato</h4>
      <div class="form-grid" id="conForm">
        <div class="form-group"><label>Título *</label><input id="con_tit" placeholder="Contrato de mantenimiento"></div>
        <div class="form-group"><label>Nº Contrato</label><input id="con_num" placeholder="CONT-001"></div>
        <div class="form-group"><label>Tipo</label><select id="con_tipo"><option>Servicio</option><option>Suministro</option><option>Mantenimiento</option><option>Otro</option></select></div>
        <div class="form-group"><label>Fecha inicio *</label><input type="date" id="con_ini"></div>
        <div class="form-group"><label>Fecha fin</label><input type="date" id="con_fin"></div>
        <div class="form-group"><label>Monto $</label><input type="number" step="0.01" id="con_mon" value="0"></div>
        <div class="form-group"><label>Estado</label><select id="con_est"><option>Vigente</option><option>Vencido</option><option>Cancelado</option></select></div>
        <div class="form-group full"><label>Notas</label><input id="con_not" placeholder="Detalles del contrato..."></div>
        <div class="form-group"><button class="btn btn-primary btn-sm" onclick="addContrato()">+ Agregar</button></div>
      </div>
      <?php endif; ?>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
}
async function addContrato() {
  const d = { proveedor_id: conProvId, titulo: document.getElementById('con_tit').value, numero_contrato: document.getElementById('con_num').value, tipo: document.getElementById('con_tipo').value, fecha_inicio: document.getElementById('con_ini').value, fecha_fin: document.getElementById('con_fin').value || null, monto: document.getElementById('con_mon').value, estado: document.getElementById('con_est').value, notas: document.getElementById('con_not').value };
  if (!d.titulo || !d.fecha_inicio) { toast('Título y fecha inicio obligatorios','error'); return; }
  await api('/api/proveedores.php?action=contratos', 'POST', d);
  toast('Contrato registrado');
  document.querySelector('.modal-bg.open')?.remove();
}

/* ════════════════ RANKING ════════════════ */
async function verRanking() {
  const data = await api('/api/proveedores.php?action=ranking');
  const star = n => '★'.repeat(Math.round(n)) + '☆'.repeat(5-Math.round(n));
  const rows = data.rows.map((r,i) => {
    const color = r.avg_total >= 4 ? 'badge-green' : r.avg_total >= 3 ? 'badge-yellow' : 'badge-red';
    return `<tr>
      <td><strong>#${i+1}</strong></td>
      <td><strong>${r.nombre}</strong></td><td>${r.tipo}</td>
      <td>${r.evaluaciones}</td>
      <td>${star(r.avg_calidad)}</td><td>${star(r.avg_puntualidad)}</td>
      <td>${star(r.avg_precio)}</td><td>${star(r.avg_servicio)}</td>
      <td><span class="badge ${color}">${r.avg_total}</span></td>
    </tr>`;
  }).join('') || '<tr><td colspan="9"><div class="empty"><div class="empty-title">Sin evaluaciones aún</div></div></td></tr>';

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:1000px">
      <div class="modal-title">⭐ Ranking de Proveedores</div>
      <div style="max-height:60vh;overflow:auto">
        <table><thead><tr><th>#</th><th>Proveedor</th><th>Tipo</th><th>Evals</th><th>Calidad</th><th>Puntualidad</th><th>Precio</th><th>Servicio</th><th>Prom.</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
}
document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Proveedores','proveedores',$content); ?>
