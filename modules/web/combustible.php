<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos   = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$operadores  = $db->query("SELECT id,nombre,estado FROM operadores ORDER BY nombre")->fetchAll();
ob_start();
?>
<div id="stat-pills" class="stat-pills"></div>
<div class="toolbar">
  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="search-comb" placeholder="Buscar por placa, proveedor..." oninput="load()">
  </div>
  <select id="filter-veh" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?>
    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca']) ?></option>
    <?php endforeach; ?>
  </select>
  <input id="from-date" type="date" onchange="load()" style="max-width:170px" title="Desde">
  <input id="to-date" type="date" onchange="load()" style="max-width:170px" title="Hasta">
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Registrar Carga</button>
  <?php endif; ?>
  <button class="btn btn-ghost" onclick="verAnomalias()" title="Detectar anomalías">⚠️ Anomalías</button>
  <button class="btn btn-ghost" onclick="toggleCharts()" id="btnCharts">📊 Gráficos</button>
  <button class="btn btn-ghost" onclick="verEficiencia()">🏆 Eficiencia</button>
</div>

<!-- Gráficos comparativos -->
<div id="charts-section" style="display:none;margin-bottom:18px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div style="background:var(--bg2);border-radius:12px;padding:16px">
      <h4 style="font-size:14px;margin-bottom:8px;color:var(--accent2)">💰 Gasto por período</h4>
      <canvas id="chart-gasto" height="220"></canvas>
    </div>
    <div style="background:var(--bg2);border-radius:12px;padding:16px">
      <h4 style="font-size:14px;margin-bottom:8px;color:var(--accent2)">⛽ Litros por período</h4>
      <canvas id="chart-litros" height="220"></canvas>
    </div>
  </div>
  <div style="display:flex;gap:12px;margin-top:12px">
    <div id="prev-compare" style="font-size:12px;color:var(--text2);background:var(--bg2);border-radius:8px;padding:10px;flex:1"></div>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Fecha</th><th>Vehículo</th><th>Litros</th><th>Costo/L</th><th>Total</th><th>KM</th><th>Rendimiento</th><th>Tipo</th><th>Proveedor</th>
      <th>Conductor</th><th>Pago</th><th>Recibo</th>
      <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr>
    </thead>
    <tbody id="tbody-comb"></tbody>
  </table>
  <div id="pager-comb"></div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="modal-comb">
  <div class="modal">
    <div class="modal-title" id="modal-comb-title">⛽ Registrar Carga de Combustible</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fecha *</label><input name="fecha" type="date"></div>
      <div class="form-group"><label>Vehículo *</label>
        <select name="vehiculo_id">
          <option value="">— Seleccionar —</option>
          <?php foreach($vehiculos as $v): ?>
          <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Conductor responsable *</label>
        <select name="operador_id">
          <option value="">— Seleccionar —</option>
          <?php foreach($operadores as $o): ?>
          <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre'].' ('.$o['estado'].')') ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Litros *</label><input name="litros" type="number" step="0.01" placeholder="50.00" oninput="calcTotal()"></div>
      <div class="form-group"><label>Costo por litro *</label><input name="costo_litro" type="number" step="0.01" placeholder="22.50" oninput="calcTotal()"></div>
      <div class="form-group"><label>Total ($)</label><input name="total" type="number" step="0.01" readonly></div>
      <div class="form-group"><label>KM al cargar</label><input name="km" type="number" step="0.1" placeholder="45800"></div>
      <div class="form-group"><label>Tipo de carga</label>
        <select name="tipo_carga"><option>Lleno</option><option>Parcial</option></select></div>
      <div class="form-group"><label>Proveedor</label>
        <select name="proveedor_id">
          <option value="">— Ninguno —</option>
          <?php foreach($proveedores as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Método de pago</label>
        <select name="metodo_pago"><option>Efectivo</option><option>Tarjeta</option><option>Transferencia</option><option>Crédito</option><option>Otro</option></select></div>
      <div class="form-group"><label>No. de recibo</label><input name="numero_recibo" placeholder="REC-2026-0001"></div>
      <div class="form-group full"><label>Justificación override (solo admin)</label><textarea name="override_reason" placeholder="Solo si necesitas saltar bloqueo por mantenimiento u odómetro."></textarea></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
      <div class="form-group full" id="att-comb-wrap"></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-comb')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL ANOMALÍAS -->
<div class="modal-bg" id="modal-anomalias">
  <div class="modal" style="max-width:900px">
    <div class="modal-title">⚠️ Detección de Anomalías</div>
    <div id="anomalias-info" style="margin-bottom:12px;font-size:13px;color:#8892a4"></div>
    <div class="table-wrap" style="max-height:500px;overflow-y:auto">
      <table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Litros</th><th>KM</th><th>Rend.</th><th>Alertas</th></tr></thead>
      <tbody id="tbody-anomalias"></tbody></table>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-anomalias')">Cerrar</button></div>
  </div>
</div>

<!-- MODAL EFICIENCIA -->
<div class="modal-bg" id="modal-eficiencia">
  <div class="modal" style="max-width:950px">
    <div class="modal-title">🏆 Eficiencia por Vehículo</div>
    <div id="eficiencia-filters" style="display:flex;gap:8px;margin-bottom:12px;font-size:12px">
      <label style="color:#8892a4;display:flex;align-items:center;gap:4px">Desde <input type="date" id="ef-from" style="max-width:140px"></label>
      <label style="color:#8892a4;display:flex;align-items:center;gap:4px">Hasta <input type="date" id="ef-to" style="max-width:140px"></label>
      <button class="btn btn-ghost btn-sm" onclick="loadEficiencia()">Filtrar</button>
    </div>
    <div class="table-wrap" style="max-height:450px;overflow-y:auto">
      <table><thead><tr><th>#</th><th>Vehículo</th><th>Cargas</th><th>Litros</th><th>Gasto</th><th>KM recorridos</th><th>km/L</th><th>L/km</th></tr></thead>
      <tbody id="tbody-eficiencia"></tbody></table>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-eficiencia')">Cerrar</button></div>
  </div>
</div>

<script>
const pager = new Paginator('pager-comb', load, 25);
const attComb = new AttachmentWidget('att-comb-wrap', 'combustible');
function calcTotal() {
  const l = parseFloat(document.querySelector('#modal-comb [name=litros]')?.value)||0;
  const c = parseFloat(document.querySelector('#modal-comb [name=costo_litro]')?.value)||0;
  document.querySelector('#modal-comb [name=total]').value = (l*c).toFixed(2);
}
async function load() {
  const q   = document.getElementById('search-comb').value;
  const vid = document.getElementById('filter-veh').value;
  const from = document.getElementById('from-date').value;
  const to = document.getElementById('to-date').value;
  const data = await api(`/api/combustible.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  // Pills
  document.getElementById('stat-pills').innerHTML = `
    <div class="stat-pill">⛽ Total litros: <strong>${Number(data.stats.litros).toFixed(1)}</strong></div>
    <div class="stat-pill">💰 Gasto total: <strong>L ${Number(data.stats.gasto).toFixed(2)}</strong></div>
    <div class="stat-pill">📋 Registros: <strong>${data.total}</strong></div>`;
  const badges = {'Lleno':'badge-green','Parcial':'badge-yellow'};
  const tbody = document.getElementById('tbody-comb');
  if (!data.rows.length) { tbody.innerHTML=`<tr><td colspan="13"><div class="empty"><div class="empty-icon">⛽</div><div class="empty-title">Sin registros</div></div></td></tr>`; return; }
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td>${r.fecha}</td>
      <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
      <td>${Number(r.litros).toFixed(1)} L</td>
      <td>L ${Number(r.costo_litro).toFixed(2)}</td>
      <td><strong>L ${Number(r.total).toFixed(2)}</strong></td>
      <td>${r.km ? Number(r.km).toLocaleString()+' km' : '—'}</td>
      <td><span class="badge ${r.rendimiento ? 'badge-cyan' : 'badge-gray'}">${r.rendimiento ? Number(r.rendimiento).toFixed(1)+' km/L' : '—'}</span></td>
      <td><span class="badge ${badges[r.tipo_carga]||'badge-gray'}">${r.tipo_carga}</span></td>
      <td>${r.proveedor_nombre||'—'}</td>
      <td>${r.operador_nombre||'—'}</td>
      <td>${r.metodo_pago||'—'}</td>
      <td>${r.numero_recibo||'—'}</td>
      <?php if(can('edit')): ?>
      <td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick="window.open('/print.php?type=combustible&id=${r.id}','_blank')" title="Imprimir PDF">🖨️</button>
        <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
        <?php if(can('delete')): ?>
        <button class="btn btn-danger btn-sm" onclick="eliminar(${r.id})">🗑️</button>
        <?php endif; ?>
      </div></td>
      <?php endif; ?>
    </tr>`).join('');
}
function abrirNuevo() {
  document.getElementById('modal-comb-title').textContent = '⛽ Registrar Carga';
  resetForm('modal-comb');
  attComb.reset();
  openModal('modal-comb');
}
function editar(r) {
  document.getElementById('modal-comb-title').textContent = '✏️ Editar Carga';
  fillForm('modal-comb', { id:r.id, fecha:r.fecha, vehiculo_id:r.vehiculo_id, operador_id:r.operador_id, litros:r.litros, costo_litro:r.costo_litro, total:r.total, km:r.km, tipo_carga:r.tipo_carga, proveedor_id:r.proveedor_id, metodo_pago:r.metodo_pago, numero_recibo:r.numero_recibo, notas:r.notas });
  attComb.setEntityId(r.id);
  attComb.load();
  openModal('modal-comb');
}
async function guardar() {
  const d = getForm('modal-comb');
  if (!d.vehiculo_id || !d.operador_id || !d.litros) { toast('Vehículo, conductor y litros son obligatorios','error'); return; }
  const res = await api('/api/combustible.php', d.id?'PUT':'POST', d);
  const savedId = d.id || res.id;
  if (attComb.hasPending() && savedId) {
    await attComb.uploadPending(savedId);
  }
  toast(d.id?'Carga actualizada':'Carga registrada');
  closeModal('modal-comb'); load();
}
async function eliminar(id) {
  confirmDelete('¿Eliminar este registro de combustible?', async () => {
    await api(`/api/combustible.php?id=${id}`, 'DELETE');
    toast('Registro eliminado','warning'); load();
  });
}
document.addEventListener('DOMContentLoaded', load);

// ═══ Gráficos comparativos ═══
let chartsVisible = false;
let chartGasto = null, chartLitros = null;
function toggleCharts() {
  chartsVisible = !chartsVisible;
  document.getElementById('charts-section').style.display = chartsVisible ? '' : 'none';
  document.getElementById('btnCharts').textContent = chartsVisible ? '📊 Ocultar' : '📊 Gráficos';
  if (chartsVisible) loadCharts();
}
async function loadCharts() {
  const vid = document.getElementById('filter-veh').value;
  const from = document.getElementById('from-date').value || '';
  const to = document.getElementById('to-date').value || '';
  try {
    const data = await api(`/api/combustible.php?action=chart_data&vehiculo_id=${vid}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
    const labels = (data.series || []).map(s => s.periodo);
    const gastos = (data.series || []).map(s => Number(s.gasto));
    const litros = (data.series || []).map(s => Number(s.litros));
    const costos = (data.series || []).map(s => Number(s.avg_costo_litro));

    if (chartGasto) chartGasto.destroy();
    if (chartLitros) chartLitros.destroy();

    const darkMode = document.documentElement.classList.contains('dark') || !document.documentElement.classList.contains('light');
    const gridColor = darkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
    const textColor = darkMode ? '#8892a4' : '#666';

    chartGasto = new Chart(document.getElementById('chart-gasto'), {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Gasto (L)', data: gastos, backgroundColor: 'rgba(232,255,71,0.7)', borderRadius: 4 },
          { label: 'L/L promedio', data: costos, type: 'line', borderColor: '#ff6b6b', pointRadius: 3, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true, interaction: { mode: 'index' },
        scales: {
          y: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => 'L ' + v.toLocaleString() } },
          y1: { position: 'right', grid: { display: false }, ticks: { color: '#ff6b6b', callback: v => 'L ' + v.toFixed(2) } },
          x: { ticks: { color: textColor } }
        },
        plugins: { legend: { labels: { color: textColor } } }
      }
    });

    chartLitros = new Chart(document.getElementById('chart-litros'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Litros', data: litros, backgroundColor: 'rgba(46,213,115,0.7)', borderRadius: 4 }]
      },
      options: {
        responsive: true,
        scales: {
          y: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => v.toLocaleString() + ' L' } },
          x: { ticks: { color: textColor } }
        },
        plugins: { legend: { labels: { color: textColor } } }
      }
    });

    // Comparativa con período anterior
    const prev = data.prev_period || {};
    const curGasto = gastos.reduce((a, b) => a + b, 0);
    const curLitros = litros.reduce((a, b) => a + b, 0);
    const diffGasto = prev.gasto > 0 ? (((curGasto - prev.gasto) / prev.gasto) * 100).toFixed(1) : '—';
    const diffLitros = prev.litros > 0 ? (((curLitros - prev.litros) / prev.litros) * 100).toFixed(1) : '—';
    document.getElementById('prev-compare').innerHTML = `
      <strong>vs período anterior:</strong>
      Gasto: L ${curGasto.toFixed(0)} ${typeof diffGasto === 'string' && diffGasto !== '—' ? (diffGasto > 0 ? `<span style="color:#ff4757">↑${diffGasto}%</span>` : `<span style="color:#2ed573">↓${Math.abs(diffGasto)}%</span>`) : '—'}
      &nbsp;|&nbsp; Litros: ${curLitros.toFixed(0)} ${typeof diffLitros === 'string' && diffLitros !== '—' ? (diffLitros > 0 ? `<span style="color:#ff4757">↑${diffLitros}%</span>` : `<span style="color:#2ed573">↓${Math.abs(diffLitros)}%</span>`) : '—'}
      &nbsp;|&nbsp; Cargas período anterior: ${prev.cargas || 0}
    `;
  } catch(e) { console.error('Chart error', e); }
}

// ═══ Eficiencia por vehículo ═══
function verEficiencia() {
  openModal('modal-eficiencia');
  loadEficiencia();
}
async function loadEficiencia() {
  const from = document.getElementById('ef-from').value;
  const to = document.getElementById('ef-to').value;
  try {
    const data = await api(`/api/combustible.php?action=eficiencia&from=${from}&to=${to}`);
    const tbody = document.getElementById('tbody-eficiencia');
    const rows = data.vehiculos || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><div class="empty-icon">📊</div><div class="empty-title">Sin datos suficientes (mín 2 cargas)</div></div></td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(r => {
      const kmlColor = r.rendimiento_kml >= 10 ? '#2ed573' : (r.rendimiento_kml >= 6 ? '#ffa502' : '#ff4757');
      return `<tr>
        <td><span class="badge badge-yellow">#${r.rank}</span></td>
        <td><strong style="color:var(--accent2)">${r.placa} ${r.marca||''}</strong></td>
        <td>${r.cargas}</td>
        <td>${Number(r.total_litros).toFixed(1)} L</td>
        <td>L ${Number(r.total_gasto).toFixed(2)}</td>
        <td>${Number(r.km_recorridos).toLocaleString()} km</td>
        <td><strong style="color:${kmlColor}">${r.rendimiento_kml ? r.rendimiento_kml + ' km/L' : '—'}</strong></td>
        <td>${r.costo_por_km ? 'L ' + r.costo_por_km + '/km' : '—'}</td>
      </tr>`;
    }).join('');
  } catch(e) { toast('Error al cargar eficiencia','error'); }
}

async function verAnomalias() {
  const vid = document.getElementById('filter-veh').value;
  const data = await api(`/api/combustible.php?action=anomalias&vehiculo_id=${vid}&limit=100`);
  document.getElementById('anomalias-info').innerHTML = `Umbral: <strong>${data.threshold}%</strong> bajo promedio | Alertas encontradas: <strong>${data.alertas.length}</strong>`;
  const tbody = document.getElementById('tbody-anomalias');
  if (!data.alertas.length) {
    tbody.innerHTML = '<tr><td colspan="6"><div class="empty"><div class="empty-icon">✅</div><div class="empty-title">Sin anomalías detectadas</div></div></td></tr>';
  } else {
    const sev = {'alta':'badge-red','media':'badge-orange','baja':'badge-yellow'};
    tbody.innerHTML = data.alertas.map(a => `<tr>
      <td>${a.fecha}</td>
      <td><strong style="color:var(--accent2)">${a.placa} ${a.marca}</strong></td>
      <td>${a.litros} L</td>
      <td>${Number(a.km).toLocaleString()} km</td>
      <td>${a.rendimiento !== null ? a.rendimiento + ' km/L' : '—'}</td>
      <td>${a.alertas.map(al => `<span class="badge ${sev[al.severidad]||'badge-gray'}" title="${al.tipo}">${al.msg}</span>`).join('<br>')}</td>
    </tr>`).join('');
  }
  openModal('modal-anomalias');
}
</script>
<?php $content = ob_get_clean(); echo render_layout('Control de Combustible','combustible',$content); ?>
