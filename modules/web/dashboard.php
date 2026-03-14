<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

<!-- ═══════════════ FILTROS ═══════════════ -->
<div class="toolbar" style="flex-wrap:wrap;gap:8px">
  <div style="font-size:18px;font-weight:700;font-family:var(--font-heading);color:var(--accent);margin-right:auto">📊 Dashboard Ejecutivo</div>
  <select id="fSuc" onchange="onSucChange();loadDash()" style="max-width:170px">
    <option value="">Todas las sucursales</option>
  </select>
  <select id="fVeh" onchange="loadDash()" style="max-width:160px">
    <option value="">Todos los vehículos</option>
  </select>
  <select id="fPeriodo" onchange="loadDash()" style="max-width:140px">
    <option value="mes">Este Mes</option>
    <option value="trimestre">Trimestre</option>
    <option value="semestre">Semestre</option>
    <option value="anio" selected>Este Año</option>
  </select>
  <button class="btn btn-ghost" onclick="loadDash()" title="Actualizar">🔄</button>
</div>

<!-- ═══════════════ KPIs ═══════════════ -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-6" id="kpiGrid">
  <!-- Rendered by JS -->
</div>

<!-- ═══════════════ CHARTS ROW 1 ═══════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
  <div class="chart-card">
    <div class="chart-title">📈 Gasto Mensual (12 meses)</div>
    <canvas id="chGastoMensual" height="220"></canvas>
  </div>
  <div class="chart-card">
    <div class="chart-title">🏆 Top 10 Vehículos por Costo</div>
    <canvas id="chTopVeh" height="220"></canvas>
  </div>
</div>

<!-- ═══════════════ CHARTS ROW 2 ═══════════════ -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
  <div class="chart-card">
    <div class="chart-title">🔧 Distribución Mantenimiento</div>
    <canvas id="chDistMant" height="200"></canvas>
  </div>
  <div class="chart-card">
    <div class="chart-title">⚠️ Incidentes Mensuales</div>
    <canvas id="chIncMensual" height="200"></canvas>
  </div>
  <div class="chart-card">
    <div class="chart-title">⛽ Eficiencia Operadores (km/L)</div>
    <canvas id="chEficiencia" height="200"></canvas>
  </div>
</div>

<!-- ═══════════════ LISTAS ═══════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div>
    <div class="section-title">🔔 Recordatorios Próximos</div>
    <div class="alert-list" id="listRec"></div>
  </div>
  <div>
    <div class="section-title">🔧 OTs Activas</div>
    <div class="alert-list" id="listOTs"></div>
  </div>
  <div>
    <div class="section-title">🚨 Alertas Activas</div>
    <div class="alert-list" id="listAlertas"></div>
  </div>
</div>

<script>
const CHART_COLORS = {
  accent: '#e8ff47', accent2: '#47ffe8', orange: '#f97316',
  red: '#ef4444', green: '#22c55e', blue: '#3b82f6', purple: '#a855f7',
  pink: '#ec4899', cyan: '#06b6d4', gray: '#6b7280'
};
let charts = {};

function isDark() {
  return document.documentElement.classList.contains('dark') || !document.documentElement.classList.contains('light');
}
function chartColors() {
  const d = isDark();
  return { grid: d ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)', text: d ? '#8892a4' : '#666' };
}

function trendBadge(val) {
  if (!val) return '';
  const cls = val > 0 ? 'color:#ef4444' : 'color:#22c55e';
  const arrow = val > 0 ? '▲' : '▼';
  return `<span style="font-size:11px;${cls}">${arrow} ${Math.abs(val)}%</span>`;
}

function fmtCurrency(val) {
  return '<span class="kpi-currency">L</span>' + Number(val).toLocaleString('es', {maximumFractionDigits:0});
}

function renderKPIs(k) {
  const items = [
    { icon: '🚗', label: 'Vehículos', value: k.vehiculos, sub: 'en flota', cls: 'yellow' },
    { icon: '👤', label: 'Operadores', value: k.operadores, sub: 'activos', cls: 'orange' },
    { icon: '⛽', label: 'Combustible', value: fmtCurrency(k.gasto_comb), sub: Number(k.litros).toLocaleString('es',{maximumFractionDigits:0}) + ' litros', cls: 'cyan', trend: k.trend_comb },
    { icon: '🔧', label: 'Mantenimiento', value: fmtCurrency(k.gasto_mant), sub: k.total_mant + ' OTs', cls: 'blue', trend: k.trend_mant },
    { icon: '⚠️', label: 'Incidentes', value: k.inc_abiertos, sub: 'abiertos', cls: 'red' },
    { icon: '🚨', label: 'Alertas', value: k.alertas_activas, sub: k.ots_pendientes + ' OTs pen.', cls: 'yellow' },
  ];
  document.getElementById('kpiGrid').innerHTML = items.map(i => `
    <div class="kpi-card ${i.cls}">
      <div class="kpi-icon">${i.icon}</div>
      <div class="kpi-label">${i.label}</div>
      <div class="kpi-value">${i.value}</div>
      <div class="kpi-sub">${i.sub} ${i.trend !== undefined ? trendBadge(i.trend) : ''}</div>
    </div>`).join('');
}

function destroyCharts() {
  Object.values(charts).forEach(c => { if (c) c.destroy(); });
  charts = {};
}

function renderCharts(data) {
  destroyCharts();
  const cc = chartColors();

  // 1. Gasto mensual — línea doble
  const gm = data.gasto_mensual;
  charts.gastoMensual = new Chart(document.getElementById('chGastoMensual'), {
    type: 'line',
    data: {
      labels: gm.map(r => r.mes),
      datasets: [
        { label: 'Combustible L', data: gm.map(r => r.combustible), borderColor: CHART_COLORS.accent2, backgroundColor: 'rgba(71,255,232,0.1)', fill: true, tension: 0.3, pointRadius: 3 },
        { label: 'Mantenimiento L', data: gm.map(r => r.mantenimiento), borderColor: CHART_COLORS.accent, backgroundColor: 'rgba(232,255,71,0.1)', fill: true, tension: 0.3, pointRadius: 3 }
      ]
    },
    options: {
      responsive: true, interaction: { mode: 'index', intersect: false },
      scales: {
        y: { grid: { color: cc.grid }, ticks: { color: cc.text, callback: v => 'L ' + v.toLocaleString() } },
        x: { ticks: { color: cc.text, maxRotation: 45 } }
      },
      plugins: { legend: { labels: { color: cc.text } } }
    }
  });

  // 2. Top vehículos — bar horizontal
  const tv = data.top_vehiculos;
  charts.topVeh = new Chart(document.getElementById('chTopVeh'), {
    type: 'bar',
    data: {
      labels: tv.map(r => r.placa),
      datasets: [
        { label: 'Combustible', data: tv.map(r => Number(r.gasto_comb)), backgroundColor: CHART_COLORS.accent2 },
        { label: 'Mantenimiento', data: tv.map(r => Number(r.gasto_mant)), backgroundColor: CHART_COLORS.orange }
      ]
    },
    options: {
      indexAxis: 'y', responsive: true,
      scales: {
        x: { stacked: true, grid: { color: cc.grid }, ticks: { color: cc.text, callback: v => 'L ' + v.toLocaleString() } },
        y: { stacked: true, ticks: { color: cc.text } }
      },
      plugins: { legend: { labels: { color: cc.text } } }
    }
  });

  // 3. Distribución mantenimiento — doughnut
  const dm = data.dist_mant;
  charts.distMant = new Chart(document.getElementById('chDistMant'), {
    type: 'doughnut',
    data: {
      labels: dm.map(r => r.tipo),
      datasets: [{
        data: dm.map(r => Number(r.gasto)),
        backgroundColor: [CHART_COLORS.accent, CHART_COLORS.blue, CHART_COLORS.orange, CHART_COLORS.purple, CHART_COLORS.green]
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { color: cc.text, padding: 12 } },
        tooltip: { callbacks: { label: ctx => ctx.label + ': L ' + Number(ctx.parsed).toLocaleString() } }
      }
    }
  });

  // 4. Incidentes mensual — bar
  const im = data.inc_mensual;
  charts.incMensual = new Chart(document.getElementById('chIncMensual'), {
    type: 'bar',
    data: {
      labels: im.map(r => r.mes),
      datasets: [{ label: 'Incidentes', data: im.map(r => r.total), backgroundColor: 'rgba(239,68,68,0.6)', borderRadius: 4 }]
    },
    options: {
      responsive: true,
      scales: {
        y: { grid: { color: cc.grid }, ticks: { color: cc.text, stepSize: 1 } },
        x: { ticks: { color: cc.text, maxRotation: 45 } }
      },
      plugins: { legend: { display: false } }
    }
  });

  // 5. Eficiencia operadores — bar horizontal
  const ef = data.top_eficiencia || [];
  if (ef.length) {
    charts.eficiencia = new Chart(document.getElementById('chEficiencia'), {
      type: 'bar',
      data: {
        labels: ef.map(r => r.nombre),
        datasets: [{ label: 'km/L', data: ef.map(r => Number(r.kml).toFixed(1)), backgroundColor: CHART_COLORS.green, borderRadius: 4 }]
      },
      options: {
        indexAxis: 'y', responsive: true,
        scales: {
          x: { grid: { color: cc.grid }, ticks: { color: cc.text } },
          y: { ticks: { color: cc.text, font: { size: 11 } } }
        },
        plugins: { legend: { display: false } }
      }
    });
  } else {
    document.getElementById('chEficiencia').parentElement.innerHTML += '<div class="empty" style="padding:12px"><div class="empty-icon" style="font-size:24px">📉</div><div style="font-size:12px">Sin datos de eficiencia</div></div>';
  }
}

function renderLists(lists) {
  // Recordatorios
  const recDiv = document.getElementById('listRec');
  if (!lists.recordatorios.length) {
    recDiv.innerHTML = '<div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div class="text-sm">Sin alertas próximas</div></div>';
  } else {
    recDiv.innerHTML = lists.recordatorios.map(a => {
      const d = parseInt(a.dias);
      const cls = d < 0 ? 'critical' : (d <= 7 ? '' : 'info');
      const txt = d < 0 ? `Vencido hace ${Math.abs(d)}d` : (d === 0 ? 'Hoy' : `En ${d}d`);
      return `<div class="alert-item ${cls}"><div class="alert-dot"></div>
        <div class="alert-text"><strong>${a.placa}</strong> — ${a.tipo}</div>
        <div class="alert-meta">${txt}</div></div>`;
    }).join('');
  }

  // OTs
  const otDiv = document.getElementById('listOTs');
  if (!lists.ots.length) {
    otDiv.innerHTML = '<div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div class="text-sm">Sin OTs pendientes</div></div>';
  } else {
    otDiv.innerHTML = lists.ots.map(o => {
      const cls = o.estado === 'En proceso' ? '' : 'info';
      return `<div class="alert-item ${cls}"><div class="alert-dot"></div>
        <div class="alert-text"><strong>${o.placa}</strong> — ${o.tipo} (${o.estado})</div>
        <div class="alert-meta">L ${Number(o.costo).toLocaleString()} · ${o.fecha}</div></div>`;
    }).join('');
  }

  // Alertas
  const alDiv = document.getElementById('listAlertas');
  if (!lists.alertas.length) {
    alDiv.innerHTML = '<div class="empty"><div class="empty-icon" style="font-size:28px">✅</div><div class="text-sm">Sin alertas activas</div></div>';
  } else {
    const PRI = {Urgente:'critical',Alta:'',Normal:'info',Baja:'info'};
    alDiv.innerHTML = lists.alertas.map(a => `
      <div class="alert-item ${PRI[a.prioridad]||'info'}"><div class="alert-dot"></div>
        <div class="alert-text"><strong>${a.placa||'—'}</strong> — ${a.titulo}</div>
        <div class="alert-meta">${a.prioridad}</div></div>`).join('');
    alDiv.innerHTML += '<a href="/alertas.php" class="text-xs text-accent2 no-underline block text-right mt-1.5 hover:underline">Ver todas →</a>';
  }
}

function fillFilters(filters) {
  const selSuc = document.getElementById('fSuc');
  const current = selSuc.value;
  if (selSuc.options.length <= 1) {
    filters.sucursales.forEach(s => {
      const o = document.createElement('option'); o.value = s.id; o.textContent = s.nombre; selSuc.appendChild(o);
    });
  }
  updateVehSelect(filters.vehiculos);
}

function updateVehSelect(vehs) {
  const sel = document.getElementById('fVeh');
  const cur = sel.value;
  sel.innerHTML = '<option value="">Todos los vehículos</option>';
  vehs.forEach(v => {
    const o = document.createElement('option'); o.value = v.id; o.textContent = v.placa + ' ' + v.marca; sel.appendChild(o);
  });
  sel.value = cur;
}

function onSucChange() {
  // Al cambiar sucursal, resetear vehículo y recargar lista de vehículos filtrada
  document.getElementById('fVeh').value = '';
}

async function loadDash() {
  const suc = document.getElementById('fSuc').value;
  const veh = document.getElementById('fVeh').value;
  const per = document.getElementById('fPeriodo').value;
  let url = `/api/dashboard.php?periodo=${per}`;
  if (suc) url += `&sucursal_id=${suc}`;
  if (veh) url += `&vehiculo_id=${veh}`;

  try {
    const resp = await fetch(url);
    const data = await resp.json();
    if (!data.ok) { toast('Error cargando dashboard','error'); return; }

    renderKPIs(data.kpis);
    renderCharts(data.charts);
    renderLists(data.lists);
    fillFilters(data.filters);
  } catch(e) {
    toast('Error de conexión','error');
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', loadDash);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Dashboard', 'dashboard', $content);
?>
