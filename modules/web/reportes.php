<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$operadores = $db->query("SELECT id,nombre,estado FROM operadores ORDER BY nombre")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <select id="rtype" onchange="switchReport()" style="max-width:220px">
    <option value="combustible">⛽ Combustible</option>
    <option value="mantenimiento">🔧 Mantenimiento</option>
    <option value="vehiculos">🚗 Utilización Vehículos</option>
    <option value="top_costosos">💸 Top Costosos</option>
    <option value="talleres">🏪 Desempeño Talleres</option>
    <option value="overrides">🔓 Overrides Admin</option>
    <option value="operador_360">👤 Perfil Operador 360</option>
    <option value="historial_asignaciones">📋 Historial Asignaciones</option>
  </select>
  <select id="fv" onchange="loadReport()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <input id="from-date" type="date" onchange="loadReport()" style="max-width:160px" title="Desde">
  <input id="to-date" type="date" onchange="loadReport()" style="max-width:160px" title="Hasta">
  <select id="fop" onchange="loadReport()" style="max-width:180px;display:none">
    <option value="">Seleccione operador</option>
    <?php foreach($operadores as $op): ?><option value="<?=$op['id']?>"><?=htmlspecialchars($op['nombre'])?> (<?=$op['estado']?>)</option><?php endforeach; ?>
  </select>
  <div class="export-group" style="display:inline-flex;gap:4px;margin-left:auto;">
    <button class="btn btn-primary" onclick="exportReport('csv')" title="Exportar CSV">📥 CSV</button>
    <button class="btn btn-primary" onclick="exportReport('xlsx')" title="Exportar Excel" style="background:#217346;border-color:#217346;">📊 XLSX</button>
    <button class="btn btn-primary" onclick="exportReport('pdf')" title="Exportar PDF" style="background:#d32f2f;border-color:#d32f2f;">📄 PDF</button>
  </div>
</div>
<div class="toolbar" id="hist-toolbar" style="margin-top:4px;display:none;">
  <label style="font-size:12px;color:#8892a4;margin-right:6px;">Filtrar por:</label>
  <select id="hist-mode" onchange="histModeChange()" style="max-width:160px">
    <option value="">Todos (sin filtro)</option>
    <option value="vehiculo">🚗 Por Vehículo</option>
    <option value="operador">👤 Por Operador</option>
  </select>
  <select id="hist-vehiculo" onchange="loadReport()" style="max-width:200px;display:none">
    <option value="">— Todos los vehículos —</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?>
  </select>
  <select id="hist-operador" onchange="loadReport()" style="max-width:200px;display:none">
    <option value="">— Todos los operadores —</option>
    <?php foreach($operadores as $op): ?><option value="<?=$op['id']?>"><?=htmlspecialchars($op['nombre'])?> (<?=$op['estado']?>)</option><?php endforeach; ?>
  </select>
</div>
<div class="toolbar" id="group-toolbar" style="margin-top:4px;display:none;">
  <label style="font-size:12px;color:#8892a4;margin-right:6px;">Agrupar por:</label>
  <select id="group-by" onchange="loadReport()" style="max-width:160px">
    <option value="">Sin agrupación</option>
  </select>
  <label style="font-size:12px;color:#8892a4;margin-left:12px;margin-right:6px;">Ordenar:</label>
  <select id="order-by" onchange="loadReport()" style="max-width:120px">
    <option value="gasto">Gasto</option>
    <option value="cargas">Cargas</option>
    <option value="litros">Litros</option>
    <option value="servicios">Servicios</option>
  </select>
  <select id="order-dir" onchange="loadReport()" style="max-width:100px">
    <option value="DESC">Mayor→Menor</option>
    <option value="ASC">Menor→Mayor</option>
  </select>
</div>

<div id="report-kpis" class="kpi-grid" style="margin-bottom:16px"></div>
<div id="report-chart" class="chart-card" style="margin-bottom:16px;display:none"><div class="chart-title" id="chart-title"></div><div id="chart-bars"></div></div>
<div id="report-table" class="table-wrap"><table><thead id="report-thead"></thead><tbody id="report-tbody"></tbody></table></div>
<div id="group-table-wrap" class="table-wrap" style="margin-top:16px;display:none;">
  <div style="font-size:13px;font-weight:600;color:#e8ff47;margin-bottom:8px;" id="group-table-title">Agrupado</div>
  <table><thead id="group-thead"></thead><tbody id="group-tbody"></tbody></table>
</div>
<div id="extra-tables"></div>

<script>
function getFilters() {
  const type = document.getElementById('rtype').value;
  return {
    from: document.getElementById('from-date').value,
    to: document.getElementById('to-date').value,
    vehiculo_id: (type === 'historial_asignaciones' || type === 'operador_360') ? '' : document.getElementById('fv').value,
  };
}
function buildQS(extra={}) {
  const f = {...getFilters(), ...extra};
  const gb = document.getElementById('group-by').value;
  const ob = document.getElementById('order-by').value;
  const od = document.getElementById('order-dir').value;
  if (gb) { f.group_by = gb; f.order_by = ob; f.order_dir = od; }
  return Object.entries(f).filter(([k,v])=>v).map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
}

function switchReport() {
  const type = document.getElementById('rtype').value;
  const fop = document.getElementById('fop');
  const fv  = document.getElementById('fv');
  const grpToolbar = document.getElementById('group-toolbar');
  const grpSelect = document.getElementById('group-by');
  fop.style.display = type === 'operador_360' ? '' : 'none';
  fv.style.display  = (type === 'operador_360' || type === 'historial_asignaciones') ? 'none' : '';
  document.getElementById('hist-toolbar').style.display = type === 'historial_asignaciones' ? '' : 'none';
  if (type !== 'historial_asignaciones') { document.getElementById('hist-mode').value = ''; histModeChange(true); }

  // Populate grouping options based on report type
  const groupOptions = {
    combustible:   {vehiculo:'Vehículo',mes:'Mes',semana:'Semana',proveedor:'Proveedor',tipo_carga:'Tipo carga',metodo_pago:'Método pago'},
    mantenimiento: {vehiculo:'Vehículo',mes:'Mes',semana:'Semana',tipo:'Tipo',proveedor:'Proveedor',estado:'Estado'},
  };
  if (groupOptions[type]) {
    grpToolbar.style.display = '';
    grpSelect.innerHTML = '<option value="">Sin agrupación</option>' +
      Object.entries(groupOptions[type]).map(([k,v]) => `<option value="${k}">${v}</option>`).join('');
  } else {
    grpToolbar.style.display = 'none';
    grpSelect.innerHTML = '<option value="">Sin agrupación</option>';
  }
  loadReport();
}

async function loadReport() {
  const type = document.getElementById('rtype').value;
  let qs;
  if (type === 'operador_360') {
    const opId = document.getElementById('fop').value;
    if (!opId) {
      document.getElementById('report-kpis').innerHTML = '<div style="color:#8892a4;font-size:13px">Seleccione un operador para ver su perfil 360.</div>';
      document.getElementById('report-thead').innerHTML = '';
      document.getElementById('report-tbody').innerHTML = '';
      return;
    }
    qs = buildQS({report: type, operador_id: opId});
  } else if (type === 'historial_asignaciones') {
    const mode = document.getElementById('hist-mode').value;
    const extra = {report: type};
    if (mode === 'vehiculo') { const v = document.getElementById('hist-vehiculo').value; if (v) extra.vehiculo_id = v; }
    if (mode === 'operador') { const o = document.getElementById('hist-operador').value; if (o) extra.operador_id = o; }
    qs = buildQS(extra);
  } else {
    qs = buildQS({report: type});
  }
  const data = await api(`/api/reportes.php?${qs}`);
  const kpis = document.getElementById('report-kpis');
  const chart = document.getElementById('report-chart');
  const thead = document.getElementById('report-thead');
  const tbody = document.getElementById('report-tbody');
  const grpWrap = document.getElementById('group-table-wrap');
  chart.style.display = 'none';
  grpWrap.style.display = 'none';
  document.getElementById('extra-tables').innerHTML = '';

  // Render grouped table if present
  if (data.agrupado && data.agrupado.length) {
    grpWrap.style.display = '';
    const isComb = type === 'combustible';
    document.getElementById('group-table-title').textContent = '📊 Agrupado por: ' + (document.getElementById('group-by').selectedOptions[0]?.text || '');
    const gHead = document.getElementById('group-thead');
    const gBody = document.getElementById('group-tbody');
    if (isComb) {
      gHead.innerHTML = '<tr><th>Grupo</th><th>Litros</th><th>Gasto</th><th>Cargas</th></tr>';
      gBody.innerHTML = data.agrupado.map(r => `<tr><td><strong>${r.grupo}</strong></td><td>${Number(r.litros).toFixed(1)} L</td><td><strong>$${Number(r.gasto).toFixed(2)}</strong></td><td>${r.cargas}</td></tr>`).join('');
    } else {
      gHead.innerHTML = '<tr><th>Grupo</th><th>Gasto</th><th>Servicios</th></tr>';
      gBody.innerHTML = data.agrupado.map(r => `<tr><td><strong>${r.grupo}</strong></td><td><strong>$${Number(r.gasto).toFixed(2)}</strong></td><td>${r.servicios}</td></tr>`).join('');
    }
  }

  if (type === 'combustible') {
    const t = data.totales;
    kpis.innerHTML = `
      <div class="kpi-card cyan"><div class="kpi-icon">⛽</div><div class="kpi-label">Total Litros</div><div class="kpi-value">${Number(t.total_litros).toFixed(0)}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">💰</div><div class="kpi-label">Gasto Total</div><div class="kpi-value">L ${Number(t.total_gasto).toFixed(0)}</div></div>
      <div class="kpi-card yellow"><div class="kpi-icon">📋</div><div class="kpi-label">Registros</div><div class="kpi-value">${t.registros}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">📊</div><div class="kpi-label">Promedio/carga</div><div class="kpi-value">L ${Number(t.avg_gasto).toFixed(0)}</div></div>`;
    thead.innerHTML = '<tr><th>Placa</th><th>Marca</th><th>Litros</th><th>Gasto</th><th>Cargas</th><th>KM aprox.</th></tr>';
    tbody.innerHTML = data.por_vehiculo.map(r => `<tr><td><strong style="color:var(--accent)">${r.placa}</strong></td><td>${r.marca}</td><td>${Number(r.litros).toFixed(1)} L</td><td><strong>L ${Number(r.gasto).toFixed(2)}</strong></td><td>${r.cargas}</td><td>${r.km_recorridos?Number(r.km_recorridos).toLocaleString():'-'}</td></tr>`).join('');
    if (data.por_vehiculo.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='⛽ Gasto por vehículo';
      renderBarChart('chart-bars', data.por_vehiculo.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto)})), {unit:'L',color:'#47ffe8'});
    }
  } else if (type === 'mantenimiento') {
    const t = data.totales;
    kpis.innerHTML = `
      <div class="kpi-card orange"><div class="kpi-icon">🔧</div><div class="kpi-label">Total Servicios</div><div class="kpi-value">${t.registros}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">💰</div><div class="kpi-label">Gasto Total</div><div class="kpi-value">L ${Number(t.total_costo).toFixed(0)}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">📊</div><div class="kpi-label">Costo Promedio</div><div class="kpi-value">L ${Number(t.avg_costo).toFixed(0)}</div></div>`;
    thead.innerHTML = '<tr><th>Placa</th><th>Marca</th><th>Gasto</th><th>Servicios</th></tr>';
    tbody.innerHTML = data.por_vehiculo.map(r => `<tr><td><strong style="color:var(--accent)">${r.placa}</strong></td><td>${r.marca}</td><td><strong>L ${Number(r.gasto).toFixed(2)}</strong></td><td>${r.servicios}</td></tr>`).join('');
    if (data.por_vehiculo.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='🔧 Gasto mantenimiento por vehículo';
      renderBarChart('chart-bars', data.por_vehiculo.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto)})), {unit:'L',color:'#e8ff47'});
    }
  } else if (type === 'vehiculos') {
    kpis.innerHTML = `<div class="kpi-card yellow"><div class="kpi-icon">🚗</div><div class="kpi-label">Vehículos</div><div class="kpi-value">${data.rows.length}</div></div>`;
    thead.innerHTML = '<tr><th>Placa</th><th>Marca/Modelo</th><th>Estado</th><th>KM</th><th>Asignaciones</th><th>Mantenimientos</th><th>Litros</th><th>Gasto Comb.</th><th>Gasto Mant.</th><th>Incidentes</th></tr>';
    const EB={'Activo':'badge-green','En mantenimiento':'badge-orange','Fuera de servicio':'badge-red'};
    tbody.innerHTML = data.rows.map(r => `<tr>
      <td><strong style="color:var(--accent)">${r.placa}</strong></td>
      <td>${r.marca} ${r.modelo}</td>
      <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
      <td>${Number(r.km_actual).toLocaleString()}</td>
      <td>${r.total_asignaciones}</td>
      <td>${r.total_mantenimientos}</td>
      <td>${Number(r.total_litros).toFixed(0)} L</td>
      <td>L ${Number(r.gasto_combustible).toFixed(0)}</td>
      <td>L ${Number(r.gasto_mantenimiento).toFixed(0)}</td>
      <td>${r.total_incidentes}</td>
    </tr>`).join('');
  } else if (type === 'top_costosos') {
    kpis.innerHTML = '';
    thead.innerHTML = '<tr><th>Placa</th><th>Marca/Modelo</th><th>Gasto Combustible</th><th>Gasto Mantenimiento</th><th>Gasto Total</th></tr>';
    tbody.innerHTML = data.rows.map(r => `<tr>
      <td><strong style="color:var(--accent)">${r.placa}</strong></td>
      <td>${r.marca} ${r.modelo}</td>
      <td>L ${Number(r.gasto_combustible).toFixed(2)}</td>
      <td>L ${Number(r.gasto_mantenimiento).toFixed(2)}</td>
      <td><strong style="color:var(--red)">L ${Number(r.gasto_total).toFixed(2)}</strong></td>
    </tr>`).join('');
    if (data.rows.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='💸 Top vehículos más costosos';
      renderBarChart('chart-bars', data.rows.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto_total)})), {unit:'L',color:'#ff4757'});
    }
  } else if (type === 'talleres') {
    kpis.innerHTML = '';
    thead.innerHTML = '<tr><th>Taller</th><th>Servicios</th><th>Gasto Total</th><th>Costo Promedio</th><th>Completados</th><th>Activos</th></tr>';
    tbody.innerHTML = data.rows.map(r => `<tr>
      <td><strong>${r.nombre}</strong></td>
      <td>${r.total_servicios}</td>
      <td><strong>L ${Number(r.gasto_total).toFixed(2)}</strong></td>
      <td>L ${Number(r.avg_costo).toFixed(2)}</td>
      <td><span class="badge badge-green">${r.completados}</span></td>
      <td><span class="badge badge-orange">${r.activos}</span></td>
    </tr>`).join('');
  } else if (type === 'overrides') {
    kpis.innerHTML = `
      <div class="kpi-card red"><div class="kpi-icon">🔓</div><div class="kpi-label">Total Overrides</div><div class="kpi-value">${data.total}</div></div>
      <div class="kpi-card yellow"><div class="kpi-icon">👥</div><div class="kpi-label">Usuarios</div><div class="kpi-value">${Object.keys(data.por_usuario||{}).length}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">📦</div><div class="kpi-label">Entidades</div><div class="kpi-value">${Object.keys(data.por_entidad||{}).length}</div></div>`;
    thead.innerHTML = '<tr><th>Fecha</th><th>Usuario</th><th>Rol</th><th>Entidad</th><th>ID</th><th>Acción</th><th>Motivo Override</th><th>IP</th></tr>';
    tbody.innerHTML = (data.rows||[]).map(r => `<tr>
      <td>${r.created_at}</td>
      <td>${r.user_email||'—'}</td>
      <td><span class="badge badge-gray">${r.user_rol||'—'}</span></td>
      <td>${r.entidad}</td>
      <td>${r.entidad_id||'—'}</td>
      <td>${r.accion}</td>
      <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${(r.override_reason||'').replace(/"/g,'&quot;')}">${r.override_reason||'—'}</td>
      <td style="font-size:11px">${r.ip||'—'}</td>
    </tr>`).join('');
  } else if (type === 'operador_360') {
    if (data.error) { kpis.innerHTML = `<div style="color:#ff4757">${data.error}</div>`; return; }
    const op = data.operador;
    const t = data.totales;
    const EB = {'Activo':'badge-green','Inactivo':'badge-red','Suspendido':'badge-orange'};
    kpis.innerHTML = `
      <div class="kpi-card cyan"><div class="kpi-icon">👤</div><div class="kpi-label">${op.nombre}</div><div class="kpi-value"><span class="badge ${EB[op.estado]||'badge-gray'}">${op.estado}</span></div><div class="kpi-sub">Lic: ${op.licencia||'N/A'} · ${op.categoria_lic||''}</div></div>
      <div class="kpi-card yellow"><div class="kpi-icon">📝</div><div class="kpi-label">Asignaciones</div><div class="kpi-value">${t.asignaciones}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">⛽</div><div class="kpi-label">Litros Total</div><div class="kpi-value">${Number(t.litros_total).toFixed(0)}</div><div class="kpi-sub">L ${Number(t.gasto_combustible).toFixed(0)}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">🛣</div><div class="kpi-label">KM Total</div><div class="kpi-value">${Number(t.km_total).toLocaleString()}</div></div>
      <div class="kpi-card red"><div class="kpi-icon">⚠️</div><div class="kpi-label">Incidentes</div><div class="kpi-value">${t.incidentes}</div></div>`;
    thead.innerHTML = '<tr><th>Inicio</th><th>Fin</th><th>Placa</th><th>Marca</th><th>KM Inicio</th><th>KM Fin</th><th>Estado</th></tr>';
    tbody.innerHTML = (data.asignaciones||[]).map(r => `<tr>
      <td>${r.start_at}</td><td>${r.end_at||'—'}</td>
      <td><strong style="color:var(--accent)">${r.placa}</strong></td><td>${r.marca}</td>
      <td>${r.start_km||'—'}</td><td>${r.end_km||'—'}</td>
      <td><span class="badge ${r.estado==='Activa'?'badge-green':'badge-gray'}">${r.estado}</span></td>
    </tr>`).join('');
  } else if (type === 'historial_asignaciones') {
    const t = data.totales;
    const EB2 = {'Activa':'badge-green','Cerrada':'badge-gray'};
    kpis.innerHTML = `
      <div class="kpi-card yellow"><div class="kpi-icon">📋</div><div class="kpi-label">Asignaciones</div><div class="kpi-value">${t.asignaciones}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">🛣</div><div class="kpi-label">KM Recorridos</div><div class="kpi-value">${Number(t.km_total).toLocaleString()}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">✅</div><div class="kpi-label">Activas</div><div class="kpi-value">${t.activas}</div></div>
      <div class="kpi-card gray"><div class="kpi-icon">🔒</div><div class="kpi-label">Cerradas</div><div class="kpi-value">${t.cerradas}</div></div>
      <div class="kpi-card cyan"><div class="kpi-icon">🚗</div><div class="kpi-label">Vehículos</div><div class="kpi-value">${t.vehiculos}</div></div>
      <div class="kpi-card orange"><div class="kpi-icon">👤</div><div class="kpi-label">Operadores</div><div class="kpi-value">${t.operadores}</div></div>`;

    // Resumen por vehículo
    if (data.por_vehiculo && data.por_vehiculo.length) {
      grpWrap.style.display = '';
      document.getElementById('group-table-title').textContent = '🚗 Resumen por Vehículo';
      const gHead = document.getElementById('group-thead');
      const gBody = document.getElementById('group-tbody');
      gHead.innerHTML = '<tr><th>Placa</th><th>Vehículo</th><th>Asignaciones</th><th>KM Total</th><th>Activas</th><th>Cerradas</th><th>Operadores</th></tr>';
      gBody.innerHTML = data.por_vehiculo.map(r => `<tr>
        <td><strong style="color:var(--accent)">${r.placa}</strong></td>
        <td>${r.vehiculo}</td>
        <td>${r.asignaciones}</td>
        <td>${Number(r.km_total).toLocaleString()} km</td>
        <td><span class="badge badge-green">${r.activas}</span></td>
        <td><span class="badge badge-gray">${r.cerradas}</span></td>
        <td>${r.operadores.join(', ')}</td>
      </tr>`).join('');
    }

    // Resumen por operador (tabla extra)
    let extraHtml = '';
    if (data.por_operador && data.por_operador.length) {
      extraHtml = `<div class="table-wrap" style="margin-top:16px">
        <div style="font-size:13px;font-weight:600;color:#e8ff47;margin-bottom:8px">👤 Resumen por Operador</div>
        <table><thead><tr><th>Operador</th><th>DNI</th><th>Departamento</th><th>Asignaciones</th><th>KM Total</th><th>Activas</th><th>Cerradas</th><th>Vehículos</th></tr></thead>
        <tbody>${data.por_operador.map(r => `<tr>
          <td><strong>${r.operador}</strong></td>
          <td>${r.dni||'—'}</td>
          <td>${r.departamento||'—'}</td>
          <td>${r.asignaciones}</td>
          <td>${Number(r.km_total).toLocaleString()} km</td>
          <td><span class="badge badge-green">${r.activas}</span></td>
          <td><span class="badge badge-gray">${r.cerradas}</span></td>
          <td>${r.vehiculos.join(', ')}</td>
        </tr>`).join('')}</tbody></table></div>`;
    }
    document.getElementById('extra-tables').innerHTML = extraHtml;

    // Detalle
    thead.innerHTML = '<tr><th>Inicio</th><th>Fin</th><th>Placa</th><th>Vehículo</th><th>Operador</th><th>Depto</th><th>KM Ini</th><th>KM Fin</th><th>KM Rec.</th><th>Estado</th></tr>';
    tbody.innerHTML = (data.detalle||[]).map(r => `<tr>
      <td>${r.start_at}</td><td>${r.end_at||'—'}</td>
      <td><strong style="color:var(--accent)">${r.placa}</strong></td>
      <td>${r.vehiculo}</td>
      <td>${r.operador}</td>
      <td>${r.departamento||'—'}</td>
      <td>${r.start_km||'—'}</td><td>${r.end_km||'—'}</td>
      <td>${r.km_recorridos!=null?Number(r.km_recorridos).toLocaleString()+' km':'—'}</td>
      <td><span class="badge ${EB2[r.estado]||'badge-gray'}">${r.estado}</span></td>
    </tr>`).join('');
  }
}

function exportReport(format) {
  format = format || 'csv';
  const type = document.getElementById('rtype').value;
  // Mapear tipos de reporte a los tipos de exportación soportados por la API
  const exportMap = {
    combustible: 'combustible',
    mantenimiento: 'mantenimiento',
    vehiculos: 'vehiculos',
    top_costosos: 'vehiculos',
    talleres: 'mantenimiento',
    overrides: 'asignaciones',
    operador_360: 'asignaciones',
    asignaciones: 'asignaciones',
    historial_asignaciones: 'historial_asignaciones',
    incidentes: 'incidentes'
  };
  const exportType = exportMap[type] || type;
  const qs = buildQS({export: exportType, format: format});
  let exportQs = qs;
  if (type === 'operador_360') {
    const opId = document.getElementById('fop').value;
    if (opId) exportQs += '&operador_id=' + opId;
  } else if (type === 'historial_asignaciones') {
    const mode = document.getElementById('hist-mode').value;
    if (mode === 'vehiculo') { const v = document.getElementById('hist-vehiculo').value; if (v) exportQs += '&vehiculo_id=' + v; }
    if (mode === 'operador') { const o = document.getElementById('hist-operador').value; if (o) exportQs += '&operador_id=' + o; }
  }
  if (format === 'pdf') {
    window.open(`/api/reportes.php?${exportQs}`, '_blank');
  } else {
    window.location.href = `/api/reportes.php?${exportQs}`;
  }
}

// Mantener compatibilidad con llamadas anteriores
function exportCSV() { exportReport('csv'); }

function histModeChange(silent){
  const mode = document.getElementById('hist-mode').value;
  document.getElementById('hist-vehiculo').style.display = mode === 'vehiculo' ? '' : 'none';
  document.getElementById('hist-operador').style.display = mode === 'operador' ? '' : 'none';
  if (!silent) loadReport();
}
document.addEventListener('DOMContentLoaded', loadReport);
</script>
<?php $content = ob_get_clean(); echo render_layout('Reportes y Exportaciones','reportes',$content); ?>
