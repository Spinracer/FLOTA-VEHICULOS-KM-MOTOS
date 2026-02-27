<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <select id="rtype" onchange="switchReport()" style="max-width:220px">
    <option value="combustible">⛽ Combustible</option>
    <option value="mantenimiento">🔧 Mantenimiento</option>
    <option value="vehiculos">🚗 Utilización Vehículos</option>
    <option value="top_costosos">💸 Top Costosos</option>
    <option value="talleres">🏪 Desempeño Talleres</option>
  </select>
  <select id="fv" onchange="loadReport()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <input id="from-date" type="date" onchange="loadReport()" style="max-width:160px" title="Desde">
  <input id="to-date" type="date" onchange="loadReport()" style="max-width:160px" title="Hasta">
  <button class="btn btn-primary" onclick="exportCSV()">📥 Exportar CSV</button>
</div>

<div id="report-kpis" class="kpi-grid" style="margin-bottom:16px"></div>
<div id="report-chart" class="chart-card" style="margin-bottom:16px;display:none"><div class="chart-title" id="chart-title"></div><div id="chart-bars"></div></div>
<div id="report-table" class="table-wrap"><table><thead id="report-thead"></thead><tbody id="report-tbody"></tbody></table></div>

<script>
function getFilters() {
  return {
    from: document.getElementById('from-date').value,
    to: document.getElementById('to-date').value,
    vehiculo_id: document.getElementById('fv').value,
  };
}
function buildQS(extra={}) {
  const f = {...getFilters(), ...extra};
  return Object.entries(f).filter(([k,v])=>v).map(([k,v])=>`${k}=${encodeURIComponent(v)}`).join('&');
}

function switchReport() { loadReport(); }

async function loadReport() {
  const type = document.getElementById('rtype').value;
  const qs = buildQS({report: type});
  const data = await api(`/api/reportes.php?${qs}`);
  const kpis = document.getElementById('report-kpis');
  const chart = document.getElementById('report-chart');
  const thead = document.getElementById('report-thead');
  const tbody = document.getElementById('report-tbody');
  chart.style.display = 'none';

  if (type === 'combustible') {
    const t = data.totales;
    kpis.innerHTML = `
      <div class="kpi-card cyan"><div class="kpi-icon">⛽</div><div class="kpi-label">Total Litros</div><div class="kpi-value">${Number(t.total_litros).toFixed(0)}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">💰</div><div class="kpi-label">Gasto Total</div><div class="kpi-value">$${Number(t.total_gasto).toFixed(0)}</div></div>
      <div class="kpi-card yellow"><div class="kpi-icon">📋</div><div class="kpi-label">Registros</div><div class="kpi-value">${t.registros}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">📊</div><div class="kpi-label">Promedio/carga</div><div class="kpi-value">$${Number(t.avg_gasto).toFixed(0)}</div></div>`;
    thead.innerHTML = '<tr><th>Placa</th><th>Marca</th><th>Litros</th><th>Gasto</th><th>Cargas</th><th>KM aprox.</th></tr>';
    tbody.innerHTML = data.por_vehiculo.map(r => `<tr><td><strong style="color:var(--accent)">${r.placa}</strong></td><td>${r.marca}</td><td>${Number(r.litros).toFixed(1)} L</td><td><strong>$${Number(r.gasto).toFixed(2)}</strong></td><td>${r.cargas}</td><td>${r.km_recorridos?Number(r.km_recorridos).toLocaleString():'-'}</td></tr>`).join('');
    if (data.por_vehiculo.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='⛽ Gasto por vehículo';
      renderBarChart('chart-bars', data.por_vehiculo.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto)})), {unit:'$',color:'#47ffe8'});
    }
  } else if (type === 'mantenimiento') {
    const t = data.totales;
    kpis.innerHTML = `
      <div class="kpi-card orange"><div class="kpi-icon">🔧</div><div class="kpi-label">Total Servicios</div><div class="kpi-value">${t.registros}</div></div>
      <div class="kpi-card green"><div class="kpi-icon">💰</div><div class="kpi-label">Gasto Total</div><div class="kpi-value">$${Number(t.total_costo).toFixed(0)}</div></div>
      <div class="kpi-card blue"><div class="kpi-icon">📊</div><div class="kpi-label">Costo Promedio</div><div class="kpi-value">$${Number(t.avg_costo).toFixed(0)}</div></div>`;
    thead.innerHTML = '<tr><th>Placa</th><th>Marca</th><th>Gasto</th><th>Servicios</th></tr>';
    tbody.innerHTML = data.por_vehiculo.map(r => `<tr><td><strong style="color:var(--accent)">${r.placa}</strong></td><td>${r.marca}</td><td><strong>$${Number(r.gasto).toFixed(2)}</strong></td><td>${r.servicios}</td></tr>`).join('');
    if (data.por_vehiculo.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='🔧 Gasto mantenimiento por vehículo';
      renderBarChart('chart-bars', data.por_vehiculo.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto)})), {unit:'$',color:'#e8ff47'});
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
      <td>$${Number(r.gasto_combustible).toFixed(0)}</td>
      <td>$${Number(r.gasto_mantenimiento).toFixed(0)}</td>
      <td>${r.total_incidentes}</td>
    </tr>`).join('');
  } else if (type === 'top_costosos') {
    kpis.innerHTML = '';
    thead.innerHTML = '<tr><th>Placa</th><th>Marca/Modelo</th><th>Gasto Combustible</th><th>Gasto Mantenimiento</th><th>Gasto Total</th></tr>';
    tbody.innerHTML = data.rows.map(r => `<tr>
      <td><strong style="color:var(--accent)">${r.placa}</strong></td>
      <td>${r.marca} ${r.modelo}</td>
      <td>$${Number(r.gasto_combustible).toFixed(2)}</td>
      <td>$${Number(r.gasto_mantenimiento).toFixed(2)}</td>
      <td><strong style="color:var(--red)">$${Number(r.gasto_total).toFixed(2)}</strong></td>
    </tr>`).join('');
    if (data.rows.length) {
      chart.style.display='block';
      document.getElementById('chart-title').textContent='💸 Top vehículos más costosos';
      renderBarChart('chart-bars', data.rows.slice(0,8).map(r=>({label:r.placa,value:Number(r.gasto_total)})), {unit:'$',color:'#ff4757'});
    }
  } else if (type === 'talleres') {
    kpis.innerHTML = '';
    thead.innerHTML = '<tr><th>Taller</th><th>Servicios</th><th>Gasto Total</th><th>Costo Promedio</th><th>Completados</th><th>Activos</th></tr>';
    tbody.innerHTML = data.rows.map(r => `<tr>
      <td><strong>${r.nombre}</strong></td>
      <td>${r.total_servicios}</td>
      <td><strong>$${Number(r.gasto_total).toFixed(2)}</strong></td>
      <td>$${Number(r.avg_costo).toFixed(2)}</td>
      <td><span class="badge badge-green">${r.completados}</span></td>
      <td><span class="badge badge-orange">${r.activos}</span></td>
    </tr>`).join('');
  }
}

function exportCSV() {
  const type = document.getElementById('rtype').value;
  const exportType = (type === 'vehiculos' || type === 'top_costosos' || type === 'talleres') ? 'combustible' : type;
  const qs = buildQS({export: exportType, format: 'csv'});
  window.location.href = `/api/reportes.php?${qs}`;
}

document.addEventListener('DOMContentLoaded', loadReport);
</script>
<?php $content = ob_get_clean(); echo render_layout('Reportes y Exportaciones','reportes',$content); ?>
