<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar operador..." oninput="debouncedLoad()"></div>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Operador</button>
  <button class="btn btn-ghost" onclick="openDeptModal()" title="Administrar departamentos">🏢 Departamentos</button><?php endif; ?>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>DNI</th><th>Departamento</th><th>Licencia</th><th>Cat.</th><th>Teléfono</th><th>Vehículo asignado</th><th>Venc. licencia</th><th>Estado</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">👤 Nuevo Operador</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group full"><label>Nombre completo *</label><input name="nombre" placeholder="Juan Pérez García"></div>
      <div class="form-group"><label>DNI / Identidad</label><input name="dni" placeholder="0801-1990-12345"></div>
      <div class="form-group"><label>Departamento *</label><select name="departamento_id" id="sel-depto" required><option value="">— Seleccionar —</option></select></div>
      <div class="form-group"><label>No. Licencia</label><input name="licencia" placeholder="L12345678"></div>
      <div class="form-group"><label>Categoría</label><select name="categoria_lic"><option>A</option><option>B</option><option>C</option><option>D</option><option>E</option></select></div>
      <div class="form-group"><label>Venc. licencia</label><input name="venc_licencia" type="date"></div>
      <div class="form-group"><label>Teléfono</label><input name="telefono" placeholder="+504 9999-9999"></div>
      <div class="form-group"><label>Email</label><input name="email" type="email" placeholder="op@empresa.com"></div>
      <div class="form-group"><label>Estado</label><select name="estado"><option>Activo</option><option>Inactivo</option><option>Suspendido</option></select></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
      <div class="form-group full" id="att-op-wrap"></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>

<!-- ═══════════ MODAL CAPACITACIONES ═══════════ -->
<div class="modal-bg" id="modalCap">
  <div class="modal" style="max-width:900px">
    <div class="modal-title" id="capTitle">📜 Capacitaciones</div>
    <div style="max-height:60vh;overflow:auto">
      <table><thead><tr><th>Fecha</th><th>Título</th><th>Tipo</th><th>Horas</th><th>Vencimiento</th><th>Descripción</th><th></th></tr></thead>
      <tbody id="capBody"></tbody></table>
    </div>
    <?php if(can('create')): ?>
    <form id="capForm" onsubmit="event.preventDefault();addCap()" style="margin-top:12px">
      <div class="form-grid">
        <div class="form-group"><label>Título *</label><input name="cap_titulo" id="cap_titulo" placeholder="Curso de manejo defensivo" required></div>
        <div class="form-group"><label>Tipo</label><select name="cap_tipo" id="cap_tipo"><option>Interna</option><option>Externa</option><option>Online</option></select></div>
        <div class="form-group"><label>Fecha *</label><input type="date" name="cap_fecha" id="cap_fecha" required></div>
        <div class="form-group"><label>Horas</label><input type="number" step="0.5" name="cap_horas" id="cap_horas" value="0"></div>
        <div class="form-group"><label>Vencimiento</label><input type="date" name="cap_venc" id="cap_venc"></div>
        <div class="form-group"><label>Descripción</label><input name="cap_desc" id="cap_desc" placeholder="Detalles..."></div>
        <div class="form-group"><button type="submit" class="btn btn-primary btn-sm">+ Agregar</button></div>
      </div>
    </form>
    <?php endif; ?>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalCap')">Cerrar</button></div>
  </div>
</div>

<!-- ═══════════ MODAL INFRACCIONES ═══════════ -->
<div class="modal-bg" id="modalInf">
  <div class="modal" style="max-width:900px">
    <div class="modal-title" id="infTitle">⚠️ Infracciones</div>
    <div style="max-height:60vh;overflow:auto">
      <table><thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th><th>Estado</th><th>Referencia</th><th></th></tr></thead>
      <tbody id="infBody"></tbody></table>
    </div>
    <?php if(can('create')): ?>
    <form id="infForm" onsubmit="event.preventDefault();addInf()" style="margin-top:12px">
      <div class="form-grid">
        <div class="form-group"><label>Fecha *</label><input type="date" name="inf_fecha" id="inf_fecha" required></div>
        <div class="form-group"><label>Tipo</label><select name="inf_tipo" id="inf_tipo"><option>Multa</option><option>Accidente</option><option>Violación</option><option>Otro</option></select></div>
        <div class="form-group"><label>Monto $</label><input type="number" step="0.01" name="inf_monto" id="inf_monto" value="0"></div>
        <div class="form-group"><label>Referencia</label><input name="inf_ref" id="inf_ref" placeholder="Folio / boleta"></div>
        <div class="form-group full"><label>Descripción</label><input name="inf_desc" id="inf_desc" placeholder="Detalle de la infracción..."></div>
        <div class="form-group"><button type="submit" class="btn btn-primary btn-sm">+ Agregar</button></div>
      </div>
    </form>
    <?php endif; ?>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalInf')">Cerrar</button></div>
  </div>
</div>

<!-- ═══════════ MODAL DEPARTAMENTOS ═══════════ -->
<div class="modal-bg" id="modalDept">
  <div class="modal" style="max-width:500px">
    <div class="modal-title">🏢 Departamentos</div>
    <div style="max-height:40vh;overflow:auto">
      <table><thead><tr><th>Nombre</th><th></th></tr></thead>
      <tbody id="deptBody"></tbody></table>
    </div>
    <form id="deptForm" onsubmit="event.preventDefault();addDept()" style="margin-top:12px">
      <div style="display:flex;gap:8px;align-items:center">
        <input name="dept_nombre" id="dept_nombre" placeholder="Nombre del departamento..." style="flex:1" required>
        <button type="submit" class="btn btn-primary btn-sm">+ Agregar</button>
      </div>
    </form>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalDept')">Cerrar</button></div>
  </div>
</div>

<script>
const pager=new Paginator('pgr',load,25);
const EB={'Activo':'badge-green','Inactivo':'badge-gray','Suspendido':'badge-red'};
const attOp = new AttachmentWidget('att-op-wrap', 'operadores');
async function load(){
  const q=document.getElementById('s').value;
  const data=await api(`/api/operadores.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="10"><div class="empty"><div class="empty-icon">👤</div><div class="empty-title">Sin operadores</div></div></td></tr>`;return;}
  tbody.innerHTML=data.rows.map(r=>{
    const dias=parseInt(r.dias_licencia);
    let lb='badge-green',lt='Vigente';
    if(!r.venc_licencia){lb='badge-gray';lt='—';}
    else if(dias<0){lb='badge-red';lt='Vencida';}
    else if(dias<=30){lb='badge-orange';lt=dias+'d restantes';}
    return `<tr>
      <td><strong>${r.nombre}</strong></td>
      <td>${r.dni||'—'}</td><td>${r.departamento_nombre||'—'}</td>
      <td>${r.licencia||'—'}</td><td>${r.categoria_lic||'—'}</td><td>${r.telefono||'—'}</td>
      <td>${r.vehiculo_placa?r.vehiculo_placa+' '+r.vehiculo_marca:'—'}</td>
      <td><span class="badge ${lb}">${r.venc_licencia||lt}</span></td>
      <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
      <?php if(can('edit')): ?><td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick="verKPIs(${r.id},'${r.nombre.replace(/'/g,"\\'")}')">📊</button>
        <button class="btn btn-ghost btn-sm" onclick="verCapacitaciones(${r.id},'${r.nombre.replace(/'/g,"\\'")}')">📜</button>
        <button class="btn btn-ghost btn-sm" onclick="verInfracciones(${r.id},'${r.nombre.replace(/'/g,"\\'")}')">⚠️</button>
        <button class="btn btn-ghost btn-sm" onclick="verHistorial(${r.id})">📚</button>
        <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
        <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
      </div></td><?php endif; ?>
    </tr>`;
  }).join('');
}
const debouncedLoad = debounce(load, 300);
async function abrirNuevo(){document.getElementById('mtitle').textContent='👤 Nuevo Operador';resetForm('modal');attOp.reset();await loadDepartamentos();openModal('modal');}
async function editar(r){document.getElementById('mtitle').textContent='✏️ Editar Operador';await loadDepartamentos(r.departamento_id);fillForm('modal',{id:r.id,nombre:r.nombre,licencia:r.licencia,categoria_lic:r.categoria_lic,venc_licencia:r.venc_licencia,telefono:r.telefono,email:r.email,estado:r.estado,notas:r.notas,dni:r.dni,departamento_id:r.departamento_id});attOp.setEntityId(r.id);attOp.load();openModal('modal');}
async function guardar(){const d=getForm('modal');if(!d.nombre){toast('El nombre es obligatorio','error');return;}if(!d.departamento_id){toast('El departamento es obligatorio','error');return;}const res=await api('/api/operadores.php',d.id?'PUT':'POST',d);const savedId=d.id||res.id;if(attOp.hasPending()&&savedId){await attOp.uploadPending(savedId);}toast(d.id?'Actualizado':'Operador registrado');closeModal('modal');load();}

/* ════════════════ DEPARTAMENTOS ════════════════ */
async function loadDepartamentos(selId){
  const data=await api('/api/operadores.php?action=departamentos');
  const sel=document.getElementById('sel-depto');
  sel.innerHTML='<option value="">— Seleccione —</option>'+data.rows.map(d=>`<option value="${d.id}" ${d.id==selId?'selected':''}>${d.nombre}</option>`).join('');
}
async function openDeptModal(){
  await loadDeptList();
  openModal('modalDept');
}
async function loadDeptList(){
  const data=await api('/api/operadores.php?action=departamentos');
  document.getElementById('deptBody').innerHTML=data.rows.map(d=>`<tr><td>${d.nombre}</td><td style="width:40px"><?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delDept(${d.id})">🗑️</button><?php endif; ?></td></tr>`).join('')||'<tr><td colspan="2">Sin departamentos</td></tr>';
}
async function addDept(){
  const inp=document.getElementById('dept_nombre');
  if(!inp.value.trim()){toast('Nombre requerido','error');return;}
  await api('/api/operadores.php?action=departamentos','POST',{nombre:inp.value.trim()});
  inp.value='';toast('Departamento creado');loadDeptList();
}
async function delDept(id){confirmDelete('¿Eliminar departamento?',async()=>{await api(`/api/operadores.php?action=departamentos&id=${id}`,'DELETE');toast('Eliminado','warning');loadDeptList();});}
async function del(id){confirmDelete('¿Eliminar este operador?',async()=>{await api(`/api/operadores.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}

async function verHistorial(id){
  const h = await api(`/api/operadores.php?action=history&id=${id}`);
  const op = h.operador || {};
  const asg = h.asignaciones || [];
  const fuel = h.combustible || [];
  const inc = h.incidentes || [];

  const htmlAsg = asg.length
    ? `<table><thead><tr><th>ID</th><th>Vehículo</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead><tbody>${asg.map(a=>`<tr><td>${a.id}</td><td>${a.placa||''}</td><td>${a.start_at||'—'}</td><td>${a.end_at||'—'}</td><td>${a.estado||'—'}</td></tr>`).join('')}</tbody></table>`
    : `<div class="empty" style="margin-top:8px"><div class="empty-title">Sin asignaciones</div></div>`;

  const htmlFuel = fuel.length
    ? `<table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Litros</th><th>Total</th><th>Asignación</th></tr></thead><tbody>${fuel.map(c=>`<tr><td>${c.fecha}</td><td>${c.placa||'—'}</td><td>${Number(c.litros||0).toFixed(2)}</td><td>L ${Number(c.total||0).toFixed(2)}</td><td>#${c.asignacion_id||'—'}</td></tr>`).join('')}</tbody></table>`
    : `<div class="empty" style="margin-top:8px"><div class="empty-title">Sin combustible asociado</div></div>`;

  const htmlInc = inc.length
    ? `<table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Tipo</th><th>Severidad</th><th>Estado</th></tr></thead><tbody>${inc.map(i=>`<tr><td>${i.fecha}</td><td>${i.placa||'—'}</td><td>${i.tipo||'—'}</td><td>${i.severidad||'—'}</td><td>${i.estado||'—'}</td></tr>`).join('')}</tbody></table>`
    : `<div class="empty" style="margin-top:8px"><div class="empty-title">Sin incidentes asociados</div></div>`;

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:980px">
      <div class="modal-title">📚 Historial de ${op.nombre || 'Operador'}</div>
      <div style="display:grid;gap:14px;max-height:70vh;overflow:auto;padding-right:4px">
        <div><h4 style="margin-bottom:8px">Asignaciones (${asg.length})</h4>${htmlAsg}</div>
        <div><h4 style="margin-bottom:8px">Combustible asociado (${fuel.length})</h4>${htmlFuel}</div>
        <div><h4 style="margin-bottom:8px">Incidentes asociados (${inc.length})</h4>${htmlInc}</div>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click', (e)=>{ if(e.target===wrap) wrap.remove(); });
  document.body.appendChild(wrap);
}
document.addEventListener('DOMContentLoaded',load);

/* ════════════════ CAPACITACIONES ════════════════ */
let capOpId = 0, capOpName = '';
async function verCapacitaciones(id, nombre) {
  capOpId = id; capOpName = nombre;
  document.getElementById('capTitle').textContent = `📜 Capacitaciones — ${nombre}`;
  await loadCapacitaciones();
  openModal('modalCap');
}
async function loadCapacitaciones() {
  const data = await api(`/api/operadores.php?action=capacitaciones&operador_id=${capOpId}`);
  const tbody = document.getElementById('capBody');
  if (!data.rows.length) { tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-title">Sin capacitaciones registradas</div></div></td></tr>'; return; }
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td>${r.fecha}</td><td>${r.titulo}</td>
    <td><span class="badge ${{'Interna':'badge-blue','Externa':'badge-green','Online':'badge-cyan'}[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td>${Number(r.horas).toFixed(1)}h</td>
    <td>${r.vencimiento||'—'}</td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td><?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delCap(${r.id})">🗑️</button><?php endif; ?></td>
  </tr>`).join('');
}
async function addCap() {
  const f = document.getElementById('capForm');
  const d = { operador_id: capOpId, titulo: f.cap_titulo.value, tipo: f.cap_tipo.value, horas: f.cap_horas.value, fecha: f.cap_fecha.value, descripcion: f.cap_desc.value, vencimiento: f.cap_venc.value || null };
  if (!d.titulo || !d.fecha) { toast('Título y fecha son obligatorios','error'); return; }
  await api('/api/operadores.php?action=capacitaciones', 'POST', d);
  toast('Capacitación registrada'); f.reset(); loadCapacitaciones();
}
async function delCap(id) { confirmDelete('¿Eliminar?',async()=>{ await api(`/api/operadores.php?action=capacitaciones&id=${id}`,'DELETE'); toast('Eliminada','warning'); loadCapacitaciones(); }); }

/* ════════════════ INFRACCIONES ════════════════ */
let infOpId = 0, infOpName = '';
async function verInfracciones(id, nombre) {
  infOpId = id; infOpName = nombre;
  document.getElementById('infTitle').textContent = `⚠️ Infracciones — ${nombre}`;
  await loadInfracciones();
  openModal('modalInf');
}
async function loadInfracciones() {
  const data = await api(`/api/operadores.php?action=infracciones&operador_id=${infOpId}`);
  const tbody = document.getElementById('infBody');
  if (!data.rows.length) { tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-title">Sin infracciones registradas</div></div></td></tr>'; return; }
  const EB2={'Pendiente':'badge-orange','Pagada':'badge-green','Contestada':'badge-blue'};
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td>${r.fecha}</td>
    <td><span class="badge ${{Multa:'badge-orange',Accidente:'badge-red','Violación':'badge-red',Otro:'badge-gray'}[r.tipo]||'badge-gray'}">${r.tipo}</span></td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td>L ${Number(r.monto).toFixed(2)}</td>
    <td><span class="badge ${EB2[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <td>${r.referencia||'—'}</td>
    <td><div class="action-btns">
      <?php if(can('edit')): ?><select onchange="cambiarEstInf(${r.id},this.value)" style="font-size:11px;padding:2px 4px">
        <option ${r.estado==='Pendiente'?'selected':''}>Pendiente</option>
        <option ${r.estado==='Pagada'?'selected':''}>Pagada</option>
        <option ${r.estado==='Contestada'?'selected':''}>Contestada</option>
      </select><?php endif; ?>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delInf(${r.id})">🗑️</button><?php endif; ?>
    </div></td>
  </tr>`).join('');
}
async function addInf() {
  const f = document.getElementById('infForm');
  const d = { operador_id: infOpId, fecha: f.inf_fecha.value, tipo: f.inf_tipo.value, descripcion: f.inf_desc.value, monto: f.inf_monto.value || 0, referencia: f.inf_ref.value || null };
  if (!d.fecha) { toast('La fecha es obligatoria','error'); return; }
  await api('/api/operadores.php?action=infracciones', 'POST', d);
  toast('Infracción registrada'); f.reset(); loadInfracciones();
}
async function cambiarEstInf(id, estado) { await api('/api/operadores.php?action=infracciones', 'PUT', {id, estado}); toast('Estado actualizado'); loadInfracciones(); }
async function delInf(id) { confirmDelete('¿Eliminar?',async()=>{ await api(`/api/operadores.php?action=infracciones&id=${id}`,'DELETE'); toast('Eliminada','warning'); loadInfracciones(); }); }

/* ════════════════ KPIs ════════════════ */
async function verKPIs(id, nombre) {
  const k = await api(`/api/operadores.php?action=kpis&id=${id}`);
  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:680px">
      <div class="modal-title">📊 KPIs de Desempeño — ${nombre}</div>
      <div class="kpi-row" style="flex-wrap:wrap;gap:12px">
        <div class="kpi-card"><div class="kpi-value">${k.total_asignaciones}</div><div class="kpi-label">Asignaciones</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.km_recorridos.toLocaleString()}</div><div class="kpi-label">km Recorridos</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.dias_activo}</div><div class="kpi-label">Días Activo</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.km_por_dia}</div><div class="kpi-label">km/día</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.eficiencia_kml!==null?k.eficiencia_kml:'—'}</div><div class="kpi-label">km/L Prom.</div></div>
        <div class="kpi-card"><div class="kpi-value" style="color:${k.incidentes>3?'#ff4757':'inherit'}">${k.incidentes}</div><div class="kpi-label">Incidentes</div></div>
        <div class="kpi-card"><div class="kpi-value" style="color:${k.infracciones>2?'#ff4757':'inherit'}">${k.infracciones}</div><div class="kpi-label">Infracciones</div></div>
        <div class="kpi-card"><div class="kpi-value">L ${k.infracciones_monto.toLocaleString()}</div><div class="kpi-label">Monto Infracciones</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.capacitaciones}</div><div class="kpi-label">Capacitaciones</div></div>
        <div class="kpi-card"><div class="kpi-value">${k.horas_capacitacion}h</div><div class="kpi-label">Horas Formación</div></div>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap)wrap.remove();});
  document.body.appendChild(wrap);
}
</script>
<?php $content=ob_get_clean(); echo render_layout('Operadores','operadores',$content); ?>
