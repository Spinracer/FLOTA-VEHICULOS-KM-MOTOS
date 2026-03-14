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
  <button class="btn btn-ghost" onclick="verDashboard()">📊 Dashboard Seguridad</button>
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
      <div class="form-group"><label>Prioridad</label><select name="prioridad"><option>Normal</option><option>Baja</option><option>Alta</option><option>Urgente</option></select></div>
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
      <div class="form-group full" id="att-inc-wrap"></div>
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
    <div id="seguimientos-section" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
      <h4 style="font-size:13px;font-weight:600;color:var(--accent2);margin-bottom:8px">📝 Seguimientos</h4>
      <div id="seguimientos-list" style="max-height:200px;overflow-y:auto;font-size:12px"></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <input type="text" id="nuevo-seg-comentario" placeholder="Agregar nota de seguimiento..." style="flex:1;font-size:12px">
        <button class="btn btn-primary btn-sm" onclick="addSeguimiento()">Agregar</button>
      </div>
    </div>
    <div id="att-detail-wrap" style="margin-top:12px"></div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-detail')">Cerrar</button></div>
  </div>
</div>

<!-- MODAL DASHBOARD SEGURIDAD -->
<div class="modal-bg" id="modal-dashboard">
  <div class="modal" style="max-width:1000px">
    <div class="modal-title">📊 Dashboard de Seguridad</div>
    <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
      <label style="font-size:12px;color:#8892a4">Año: <select id="dash-year" onchange="loadDashboard()" style="max-width:100px"></select></label>
    </div>
    <div id="dash-kpis" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div style="background:var(--bg2);border-radius:10px;padding:14px"><h4 style="font-size:13px;margin-bottom:8px;color:var(--accent2)">Incidentes por mes</h4><canvas id="chart-inc-month" height="200"></canvas></div>
      <div style="background:var(--bg2);border-radius:10px;padding:14px"><h4 style="font-size:13px;margin-bottom:8px;color:var(--accent2)">Por severidad</h4><canvas id="chart-inc-sev" height="200"></canvas></div>
    </div>
    <div style="background:var(--bg2);border-radius:10px;padding:14px">
      <h4 style="font-size:13px;margin-bottom:8px;color:var(--accent2)">🚗 Vehículos con más incidentes</h4>
      <div class="table-wrap" style="max-height:250px;overflow-y:auto">
        <table><thead><tr><th>Vehículo</th><th>Incidentes</th><th>Costo est. total</th></tr></thead>
        <tbody id="tbody-top-veh"></tbody></table>
      </div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-dashboard')">Cerrar</button></div>
  </div>
</div>

<script>
const pager=new Paginator('pgr',load,25);
const SB={'Baja':'badge-green','Media':'badge-yellow','Alta':'badge-orange','Crítica':'badge-red'};
const EB={'Abierto':'badge-red','En proceso':'badge-orange','Cerrado':'badge-green'};
const RB={'N/A':'badge-gray','En proceso':'badge-orange','Aprobado':'badge-green','Rechazado':'badge-red','Pagado':'badge-green'};
const PB={'Baja':'badge-gray','Normal':'badge-blue','Alta':'badge-orange','Urgente':'badge-red'};
const attInc = new AttachmentWidget('att-inc-wrap', 'incidentes');
let currentIncId = null;

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
    <div class="stat-card"><div class="stat-value">L ${costoTotal.toFixed(0)}</div><div class="stat-label">Costo est.</div></div>
    <div class="stat-card"><div class="stat-value" style="color:#5effc1">L ${reclamoTotal.toFixed(0)}</div><div class="stat-label">Reclamos</div></div>
  `;

  const tbody=document.getElementById('tbody');
  if(!rows.length){tbody.innerHTML=`<tr><td colspan="9"><div class="empty"><div class="empty-icon">✅</div><div class="empty-title">Sin incidentes</div></div></td></tr>`;return;}
  tbody.innerHTML=rows.map(r=>`<tr>
    <td>${r.fecha}</td>
    <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
    <td>${r.tipo}</td>
    <td><span class="badge ${SB[r.severidad]||'badge-gray'}">${r.severidad}</span></td>
    <td>${Number(r.costo_est)>0?'L '+Number(r.costo_est).toFixed(2):'—'}</td>
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
  resetForm('modal');toggleReclamo('0');
  attInc.reset();
  openModal('modal');
}
function editar(r){
  document.getElementById('mtitle').textContent='✏️ Editar Incidente';
  fillForm('modal',{id:r.id,fecha:r.fecha,vehiculo_id:r.vehiculo_id,tipo:r.tipo,severidad:r.severidad,
    estado:r.estado,costo_est:r.costo_est,descripcion:r.descripcion,prioridad:r.prioridad||'Normal',
    aseguradora:r.aseguradora||'',poliza_numero:r.poliza_numero||'',
    tiene_reclamo:r.tiene_reclamo||'0',estado_reclamo:r.estado_reclamo||'N/A',
    monto_reclamo:r.monto_reclamo||'',fecha_reclamo:r.fecha_reclamo||'',
    referencia_reclamo:r.referencia_reclamo||'',notas_seguro:r.notas_seguro||''});
  toggleReclamo(String(r.tiene_reclamo||0));
  attInc.setEntityId(r.id);
  attInc.load();
  openModal('modal');
}
async function guardar(){
  const d=getForm('modal');
  if(!d.vehiculo_id||!d.descripcion){toast('Vehículo y descripción son obligatorios','error');return;}
  const res = await api('/api/incidentes.php',d.id?'PUT':'POST',d);
  const savedId = d.id || res.id;
  if (attInc.hasPending() && savedId) {
    await attInc.uploadPending(savedId);
  }
  toast(d.id?'Actualizado':'Incidente reportado');closeModal('modal');load();
}
async function del(id){confirmDelete('¿Eliminar este incidente?',async()=>{await api(`/api/incidentes.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}

async function verDetalle(id){
  currentIncId = id;
  openModal('modal-detail');
  const r = await api(`/api/incidentes.php?detail=${id}`);
  if(!r || !r.id){document.getElementById('detail-content').innerHTML='<div class="empty"><div class="empty-icon">❌</div><div class="empty-title">No encontrado</div></div>';return;}
  const seguroHtml = r.aseguradora ? `
    <tr><td style="color:#8892a4">Aseguradora</td><td><strong>${r.aseguradora}</strong></td></tr>
    <tr><td style="color:#8892a4">Póliza</td><td>${r.poliza_numero||'—'}</td></tr>
    ${Number(r.tiene_reclamo)?`
    <tr><td style="color:#8892a4">Estado reclamo</td><td><span class="badge ${RB[r.estado_reclamo]||'badge-gray'}">${r.estado_reclamo}</span></td></tr>
    <tr><td style="color:#8892a4">Monto reclamo</td><td>L ${Number(r.monto_reclamo).toFixed(2)}</td></tr>
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
      <tr><td style="color:#8892a4">Costo estimado</td><td>L ${Number(r.costo_est).toFixed(2)}</td></tr>
      <tr><td style="color:#8892a4">Descripción</td><td>${r.descripcion}</td></tr>
      <tr><td colspan="2" style="padding:12px 0 6px;border-top:1px solid var(--border);font-weight:600;color:var(--accent2)">🛡️ Seguro</td></tr>
      ${seguroHtml}
      ${r.vehiculo_venc_seguro?`<tr><td style="color:#8892a4">Venc. seguro vehículo</td><td>${r.vehiculo_venc_seguro}</td></tr>`:''}
    </table>`;

  // Load seguimientos
  loadSeguimientos(id);
  // Load attachments
  const attDetail = new AttachmentWidget('att-detail-wrap', 'incidentes', id);
  attDetail.load();
}

async function loadSeguimientos(incId) {
  const list = document.getElementById('seguimientos-list');
  if (!list) return;
  try {
    const data = await api(`/api/incidentes.php?action=seguimientos&incidente_id=${incId}`);
    const segs = data.seguimientos || [];
    if (!segs.length) { list.innerHTML = '<p style="color:var(--text2);font-size:11px">Sin seguimientos aún.</p>'; return; }
    list.innerHTML = segs.map(s => {
      const icon = s.accion === 'estado_change' ? '🔄' : '💬';
      const stateInfo = s.estado_anterior ? `<span class="badge badge-gray">${s.estado_anterior}</span> → <span class="badge badge-green">${s.estado_nuevo}</span>` : '';
      return `<div style="padding:6px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <strong>${icon} ${s.usuario_nombre || 'Sistema'}</strong>
          <span style="color:var(--text2);font-size:10px">${s.created_at}</span>
        </div>
        ${stateInfo}
        ${s.comentario ? `<div style="margin-top:4px;color:var(--text1)">${s.comentario}</div>` : ''}
      </div>`;
    }).join('');
  } catch(e) { list.innerHTML = '<p style="color:var(--text2);font-size:11px">Error al cargar seguimientos.</p>'; }
}

async function addSeguimiento() {
  if (!currentIncId) return;
  const input = document.getElementById('nuevo-seg-comentario');
  const comentario = input.value.trim();
  if (!comentario) { toast('Escribe un comentario','error'); return; }
  try {
    await api('/api/incidentes.php?action=seguimientos', 'POST', { incidente_id: currentIncId, comentario });
    input.value = '';
    toast('Seguimiento agregado');
    loadSeguimientos(currentIncId);
  } catch(e) { toast('Error al agregar seguimiento','error'); }
}

// ═══ Dashboard de Seguridad ═══
let chartIncMonth = null, chartIncSev = null;
function verDashboard() {
  const sel = document.getElementById('dash-year');
  if (sel.options.length === 0) {
    const currentYear = new Date().getFullYear();
    for (let y = currentYear; y >= currentYear - 3; y--) {
      sel.innerHTML += `<option value="${y}">${y}</option>`;
    }
  }
  openModal('modal-dashboard');
  loadDashboard();
}
async function loadDashboard() {
  const year = document.getElementById('dash-year').value || new Date().getFullYear();
  try {
    const d = await api(`/api/incidentes.php?action=dashboard&year=${year}`);
    // KPIs
    const totalInc = (d.by_status || []).reduce((s, r) => s + Number(r.total), 0);
    const abiertos = (d.by_status || []).find(r => r.estado === 'Abierto');
    const criticos = (d.by_severity || []).find(r => r.severidad === 'Crítica');
    document.getElementById('dash-kpis').innerHTML = `
      <div class="stat-card"><div class="stat-value">${totalInc}</div><div class="stat-label">Total incidentes</div></div>
      <div class="stat-card"><div class="stat-value" style="color:#ff4757">${abiertos ? abiertos.total : 0}</div><div class="stat-label">Abiertos</div></div>
      <div class="stat-card"><div class="stat-value" style="color:#ff6348">${criticos ? criticos.total : 0}</div><div class="stat-label">Críticos</div></div>
      <div class="stat-card"><div class="stat-value" style="color:#2ed573">${d.avg_resolve_days ?? '—'}</div><div class="stat-label">Días prom. resolución</div></div>
    `;

    // Chart incidentes por mes
    const months = Array.from({length:12}, (_,i) => ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][i]);
    const monthData = new Array(12).fill(0);
    const monthCost = new Array(12).fill(0);
    (d.by_month || []).forEach(r => { monthData[r.mes - 1] = Number(r.total); monthCost[r.mes - 1] = Number(r.costo); });

    const darkMode = document.documentElement.classList.contains('dark') || !document.documentElement.classList.contains('light');
    const gridColor = darkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
    const textColor = darkMode ? '#8892a4' : '#666';

    if (chartIncMonth) chartIncMonth.destroy();
    chartIncMonth = new Chart(document.getElementById('chart-inc-month'), {
      type: 'bar', data: { labels: months, datasets: [
        { label: 'Incidentes', data: monthData, backgroundColor: 'rgba(255,71,87,0.7)', borderRadius: 4 },
        { label: 'Costo (L)', data: monthCost, type: 'line', borderColor: '#ffa502', pointRadius: 3, yAxisID: 'y1' }
      ]}, options: { responsive: true, scales: {
        y: { grid: { color: gridColor }, ticks: { color: textColor } },
        y1: { position: 'right', grid: { display: false }, ticks: { color: '#ffa502', callback: v => 'L ' + v.toLocaleString() } },
        x: { ticks: { color: textColor } }
      }, plugins: { legend: { labels: { color: textColor } } } }
    });

    // Chart por severidad (doughnut)
    const sevLabels = (d.by_severity || []).map(r => r.severidad);
    const sevData = (d.by_severity || []).map(r => Number(r.total));
    const sevColors = sevLabels.map(s => ({Crítica:'#ff4757',Alta:'#ff6348',Media:'#ffa502',Baja:'#2ed573'}[s] || '#8892a4'));
    if (chartIncSev) chartIncSev.destroy();
    chartIncSev = new Chart(document.getElementById('chart-inc-sev'), {
      type: 'doughnut', data: { labels: sevLabels, datasets: [{ data: sevData, backgroundColor: sevColors }] },
      options: { responsive: true, plugins: { legend: { labels: { color: textColor } } } }
    });

    // Top vehículos
    const topTbody = document.getElementById('tbody-top-veh');
    const topVeh = d.top_vehicles || [];
    topTbody.innerHTML = topVeh.length ? topVeh.map(v => `<tr>
      <td><strong style="color:var(--accent2)">${v.placa} ${v.marca||''}</strong></td>
      <td>${v.total}</td>
      <td>L ${Number(v.costo_total).toFixed(2)}</td>
    </tr>`).join('') : '<tr><td colspan="3">Sin datos</td></tr>';
  } catch(e) { toast('Error al cargar dashboard','error'); }
}

document.addEventListener('DOMContentLoaded',load);
</script>
<?php $content=ob_get_clean(); echo render_layout('Gestión de Incidentes','incidentes',$content); ?>
