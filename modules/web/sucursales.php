<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="sq" placeholder="Buscar sucursal..." oninput="debouncedLoadSuc()"></div>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNueva()">+ Nueva Sucursal</button><?php endif; ?>
  <button class="btn btn-ghost" onclick="verDashboard()">📊 Dashboard Comparativo</button>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Nombre</th><th>Ciudad</th><th>Dirección</th><th>Teléfono</th><th>Responsable</th><th>Estado</th><th>Vehículos</th><th>Operadores</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="stbody"></tbody></table><div id="spgr"></div>
</div>
<div class="modal-bg" id="msuc">
  <div class="modal">
    <div class="modal-title" id="msuc-title">🏢 Nueva Sucursal</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Nombre *</label><input name="nombre" placeholder="Sucursal Centro"></div>
      <div class="form-group"><label>Ciudad</label><input name="ciudad" placeholder="Ciudad"></div>
      <div class="form-group full"><label>Dirección</label><input name="direccion" placeholder="Calle y número"></div>
      <div class="form-group"><label>Teléfono</label><input name="telefono" placeholder="55 1234 5678"></div>
      <div class="form-group"><label>Responsable</label><input name="responsable" placeholder="Nombre del encargado"></div>
      <div class="form-group"><label>Activo</label><select name="activo"><option value="1">Sí</option><option value="0">No</option></select></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('msuc')">Cancelar</button><button class="btn btn-primary" onclick="guardarSuc()">Guardar</button></div>
  </div>
</div>
<script>
const pager = new Paginator('spgr', loadSuc, 25);
async function loadSuc() {
  const q = document.getElementById('sq').value;
  const data = await api(`/api/sucursales.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody = document.getElementById('stbody');
  if (!data.rows.length) {
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty"><div class="empty-icon">🏢</div><div class="empty-title">Sin sucursales</div></div></td></tr>`;
    return;
  }
  // Load counts
  let vehCounts = {}, opCounts = {};
  try {
    const vData = await api('/api/vehiculos.php?per=1000&page=1&q=');
    vData.rows.forEach(v => { const s = v.sucursal_id || 0; vehCounts[s] = (vehCounts[s] || 0) + 1; });
  } catch(e) { console.error(e); }
  try {
    const oData = await api('/api/operadores.php?per=1000&page=1&q=');
    oData.rows.forEach(o => { const s = o.sucursal_id || 0; opCounts[s] = (opCounts[s] || 0) + 1; });
  } catch(e) { console.error(e); }

  tbody.innerHTML = data.rows.map(r => `<tr>
    <td><strong style="color:var(--accent)">${r.nombre}</strong></td>
    <td>${r.ciudad || '—'}</td>
    <td class="td-truncate">${r.direccion || '—'}</td>
    <td>${r.telefono || '—'}</td>
    <td>${r.responsable || '—'}</td>
    <td><span class="badge ${Number(r.activo) ? 'badge-green' : 'badge-red'}">${Number(r.activo) ? 'Activa' : 'Inactiva'}</span></td>
    <td>${vehCounts[r.id] || 0}</td>
    <td>${opCounts[r.id] || 0}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      <button class="btn btn-ghost btn-sm" onclick='editarSuc(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="delSuc(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}
const debouncedLoadSuc = debounce(loadSuc, 300);
function abrirNueva(){document.getElementById('msuc-title').textContent='🏢 Nueva Sucursal';resetForm('msuc');openModal('msuc');}
function editarSuc(r){document.getElementById('msuc-title').textContent='✏️ Editar Sucursal';fillForm('msuc',{id:r.id,nombre:r.nombre,ciudad:r.ciudad||'',direccion:r.direccion||'',telefono:r.telefono||'',responsable:r.responsable||'',activo:r.activo});openModal('msuc');}
async function guardarSuc(){const d=getForm('msuc');if(!d.nombre){toast('Nombre es obligatorio','error');return;}await api('/api/sucursales.php',d.id?'PUT':'POST',d);toast(d.id?'Actualizada':'Sucursal creada');closeModal('msuc');loadSuc();}
async function delSuc(id){confirmDelete('¿Eliminar esta sucursal?',async()=>{await api(`/api/sucursales.php?id=${id}`,'DELETE');toast('Eliminada','warning');loadSuc();});}

/* ════════════════ DASHBOARD COMPARATIVO ════════════════ */
let dashCharts = [];
async function verDashboard() {
  const d = await api('/api/sucursales.php?action=dashboard');
  // destroy previous charts
  dashCharts.forEach(c => c.destroy()); dashCharts = [];

  const wrap = document.createElement('div');
  wrap.className = 'modal-bg open';
  wrap.innerHTML = `
    <div class="modal" style="max-width:1100px">
      <div class="modal-title">📊 Dashboard Comparativo de Sucursales (últimos 12 meses)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-height:70vh;overflow:auto">
        <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:12px"><canvas id="chVeh"></canvas></div>
        <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:12px"><canvas id="chMant"></canvas></div>
        <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:12px"><canvas id="chFuel"></canvas></div>
        <div style="background:rgba(0,0,0,.2);border-radius:8px;padding:12px"><canvas id="chInc"></canvas></div>
      </div>
      <div class="modal-actions"><button class="btn btn-ghost" onclick="dashCharts.forEach(c=>c.destroy());dashCharts=[];this.closest('.modal-bg').remove()">Cerrar</button></div>
    </div>`;
  wrap.addEventListener('click',(e)=>{if(e.target===wrap){dashCharts.forEach(c=>c.destroy());dashCharts=[];wrap.remove();}});
  document.body.appendChild(wrap);

  const colors = ['#e8ff47','#4f46e5','#22d3ee','#f97316','#ef4444','#22c55e','#a855f7','#ec4899'];
  const chartOpts = (title) => ({ responsive:true, plugins:{legend:{display:false},title:{display:true,text:title,color:'#fff',font:{size:14}}} });

  // Vehículos + Operadores
  const labels1 = d.vehiculos.map(r=>r.nombre);
  dashCharts.push(new Chart(wrap.querySelector('#chVeh'), {
    type:'bar', data:{
      labels: labels1,
      datasets:[
        {label:'Vehículos',data:d.vehiculos.map(r=>r.vehiculos),backgroundColor:'#e8ff47'},
        {label:'Operadores',data:d.operadores.map(r=>r.operadores),backgroundColor:'#4f46e5'}
      ]
    }, options:{...chartOpts('Vehículos y Operadores'),plugins:{...chartOpts('').plugins,legend:{display:true,labels:{color:'#ccc'}}}}
  }));

  // Gasto Mantenimiento
  dashCharts.push(new Chart(wrap.querySelector('#chMant'), {
    type:'bar', data:{
      labels: d.mantenimiento.map(r=>r.nombre),
      datasets:[{label:'Gasto Mant. $',data:d.mantenimiento.map(r=>Number(r.gasto_mantenimiento)),backgroundColor:'#f97316'}]
    }, options:chartOpts('Gasto Mantenimiento')
  }));

  // Gasto Combustible
  dashCharts.push(new Chart(wrap.querySelector('#chFuel'), {
    type:'bar', data:{
      labels: d.combustible.map(r=>r.nombre),
      datasets:[{label:'Gasto Comb. $',data:d.combustible.map(r=>Number(r.gasto_combustible)),backgroundColor:'#22d3ee'}]
    }, options:chartOpts('Gasto Combustible')
  }));

  // Incidentes
  dashCharts.push(new Chart(wrap.querySelector('#chInc'), {
    type:'bar', data:{
      labels: d.incidentes.map(r=>r.nombre),
      datasets:[
        {label:'Total',data:d.incidentes.map(r=>r.incidentes),backgroundColor:'#ef4444'},
        {label:'Abiertos',data:d.incidentes.map(r=>r.abiertos),backgroundColor:'#f97316'}
      ]
    }, options:{...chartOpts('Incidentes'),plugins:{...chartOpts('').plugins,legend:{display:true,labels:{color:'#ccc'}}}}
  }));
}
document.addEventListener('DOMContentLoaded', loadSuc);
</script>
<?php $content = ob_get_clean(); echo render_layout('Sucursales', 'sucursales', $content); ?>
