<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/importacion_vehiculos.php';
require_login();

if (!can('create')) {
    header('Location: /vehiculos.php');
    exit;
}

$campos = importacion_campos_destino();
$extensiones = importacion_extensiones_permitidas();

ob_start();
?>

<!-- ── Wizard de Importación ── -->
<div class="px-4 sm:px-6 py-6 max-w-5xl mx-auto">

  <!-- Pasos -->
  <div class="flex items-center gap-2 mb-8" id="steps-bar">
    <div class="step-indicator active" data-step="1">
      <span class="step-num">1</span>
      <span class="step-label">Subir archivo</span>
    </div>
    <div class="step-line"></div>
    <div class="step-indicator" data-step="2">
      <span class="step-num">2</span>
      <span class="step-label">Mapear columnas</span>
    </div>
    <div class="step-line"></div>
    <div class="step-indicator" data-step="3">
      <span class="step-num">3</span>
      <span class="step-label">Resultado</span>
    </div>
  </div>

  <!-- ═══ PASO 1: Subir archivo ═══ -->
  <div id="step-1" class="step-panel">
    <div class="card p-6">
      <h2 class="text-lg font-heading font-bold mb-1">Subir archivo</h2>
      <p class="text-muted text-sm mb-4">Formatos permitidos: <?= strtoupper(implode(', ', $extensiones)) ?>. Máximo 10 MB.</p>

      <div class="mb-6 p-4 bg-surface2 rounded-lg border border-border/50">
        <div class="flex items-center justify-between flex-wrap gap-3">
          <div>
            <p class="text-sm font-medium">Plantilla de ejemplo</p>
            <p class="text-xs text-muted mt-0.5">Descarga el CSV con el formato correcto y 5 vehículos de ejemplo</p>
          </div>
          <a href="/assets/plantilla_importacion_vehiculos.csv" download class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-accent text-dark text-sm font-semibold hover:opacity-90 transition-opacity">
            <span>⬇</span> Descargar plantilla CSV
          </a>
        </div>
      </div>

      <div id="drop-zone" class="border-2 border-dashed border-border rounded-xl p-10 text-center cursor-pointer hover:border-accent transition-colors">
        <div class="text-4xl mb-3">📁</div>
        <p class="text-sm text-muted mb-2">Arrastra tu archivo aquí o haz clic para seleccionar</p>
        <p class="text-xs text-muted">CSV, XLSX</p>
        <input type="file" id="file-input" accept=".csv,.xlsx" class="hidden">
      </div>

      <div id="file-info" class="hidden mt-4 p-4 bg-surface2 rounded-lg">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">📄</span>
            <div>
              <div class="font-medium text-sm" id="file-name"></div>
              <div class="text-xs text-muted" id="file-size"></div>
            </div>
          </div>
          <button onclick="resetUpload()" class="text-danger text-sm hover:underline">Quitar</button>
        </div>
      </div>

      <!-- Selector de hoja (XLSX) -->
      <div id="sheet-selector" class="hidden mt-4">
        <label class="block text-sm font-medium mb-1">Hoja del archivo:</label>
        <select id="sheet-select" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm" onchange="changeSheet()">
        </select>
      </div>

      <div id="upload-error" class="hidden mt-4 p-3 bg-danger/10 border border-danger/30 rounded-lg text-danger text-sm"></div>

      <div id="upload-progress" class="hidden mt-4">
        <div class="flex items-center gap-3">
          <div class="animate-spin text-accent text-xl">⟳</div>
          <span class="text-sm text-muted">Leyendo archivo...</span>
        </div>
      </div>

      <div class="flex justify-between mt-6">
        <a href="/vehiculos.php" class="btn-secondary">← Volver a Vehículos</a>
        <button id="btn-step2" class="btn-primary hidden" onclick="goToStep2()">Continuar al mapeo →</button>
      </div>
    </div>
  </div>

  <!-- ═══ PASO 2: Mapeo de columnas ═══ -->
  <div id="step-2" class="step-panel hidden">
    <div class="card p-6">
      <div class="flex items-center justify-between mb-1">
        <h2 class="text-lg font-heading font-bold">Mapear columnas</h2>
        <span class="text-sm text-muted" id="rows-count"></span>
      </div>
      <p class="text-muted text-sm mb-6">Asigna cada columna de tu archivo al campo correspondiente del sistema. Los campos con * son obligatorios.</p>

      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="mapping-table">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-2 px-3 text-muted font-medium">Columna del archivo</th>
              <th class="text-left py-2 px-3 text-muted font-medium">Vista previa</th>
              <th class="text-left py-2 px-3 text-muted font-medium">Campo destino</th>
            </tr>
          </thead>
          <tbody id="mapping-body"></tbody>
        </table>
      </div>

      <div id="mapping-errors" class="hidden mt-4 p-3 bg-danger/10 border border-danger/30 rounded-lg text-danger text-sm"></div>

      <div class="flex justify-between mt-6">
        <button onclick="goToStep1()" class="btn-secondary">← Archivo</button>
        <div class="flex gap-3">
          <button class="btn-secondary" onclick="resetMappings()" title="Limpiar todos los mapeos">Limpiar mapeo</button>
          <button id="btn-import" class="btn-primary" onclick="ejecutarImportacion()">Importar vehículos</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ PASO 3: Resultado ═══ -->
  <div id="step-3" class="step-panel hidden">
    <div class="card p-6">
      <h2 class="text-lg font-heading font-bold mb-4" id="result-title">Resultado de importación</h2>

      <!-- KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" id="result-kpis">
        <div class="bg-surface2 rounded-xl p-4 text-center">
          <div class="text-3xl font-heading font-bold text-accent" id="kpi-total">0</div>
          <div class="text-xs text-muted mt-1">Total filas</div>
        </div>
        <div class="bg-surface2 rounded-xl p-4 text-center">
          <div class="text-3xl font-heading font-bold text-success" id="kpi-creados">0</div>
          <div class="text-xs text-muted mt-1">Creados</div>
        </div>
        <div class="bg-surface2 rounded-xl p-4 text-center">
          <div class="text-3xl font-heading font-bold text-danger" id="kpi-errores">0</div>
          <div class="text-xs text-muted mt-1">Errores</div>
        </div>
      </div>

      <!-- Progreso girando -->
      <div id="import-progress" class="text-center py-8">
        <div class="animate-spin text-accent text-4xl mb-4">⟳</div>
        <p class="text-muted">Importando vehículos... Por favor espera.</p>
        <p class="text-xs text-muted mt-1">No cierres esta ventana</p>
      </div>

      <!-- Detalle de errores -->
      <div id="errors-detail" class="hidden">
        <h3 class="font-medium text-sm mb-2 text-danger">Detalle de errores:</h3>
        <div class="max-h-64 overflow-y-auto bg-surface2 rounded-lg">
          <table class="w-full text-xs">
            <thead class="sticky top-0 bg-surface2">
              <tr class="border-b border-border">
                <th class="text-left py-2 px-3">Fila</th>
                <th class="text-left py-2 px-3">Placa</th>
                <th class="text-left py-2 px-3">Tipo</th>
                <th class="text-left py-2 px-3">Error</th>
              </tr>
            </thead>
            <tbody id="errors-body"></tbody>
          </table>
        </div>
      </div>

      <div class="flex justify-between mt-6">
        <a href="/vehiculos.php" class="btn-primary">Ver inventario de vehículos</a>
        <div class="flex gap-3">
          <button onclick="goBackToMapping()" class="btn-secondary" title="Volver a mapear columnas con el mismo archivo">Reasignar columnas</button>
          <button onclick="resetAll()" class="btn-secondary">Nueva importación</button>
        </div>
      </div>
    </div>

    <!-- Historial de importaciones -->
    <div class="card p-6 mt-6">
      <h3 class="font-heading font-bold text-sm mb-3">Historial de importaciones</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-border">
              <th class="text-left py-2 px-3 text-muted">Fecha</th>
              <th class="text-left py-2 px-3 text-muted">Archivo</th>
              <th class="text-left py-2 px-3 text-muted">Usuario</th>
              <th class="text-right py-2 px-3 text-muted">Total</th>
              <th class="text-right py-2 px-3 text-muted">Creados</th>
              <th class="text-right py-2 px-3 text-muted">Errores</th>
              <th class="text-left py-2 px-3 text-muted">Estado</th>
              <th class="text-center py-2 px-3 text-muted">Acciones</th>
            </tr>
          </thead>
          <tbody id="history-body">
            <tr><td colspan="7" class="py-4 text-center text-muted">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
.step-indicator {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 12px; border-radius: 8px;
  background: var(--tw-surface2, #181c24); font-size: 13px; color: #8892a4;
  transition: all 0.3s;
}
.step-indicator.active { background: #e8ff47; color: #0a0c10; font-weight: 600; }
.step-indicator.done { background: #2ed573; color: #0a0c10; font-weight: 600; }
.step-num { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; background: rgba(255,255,255,0.15); }
.step-indicator.active .step-num, .step-indicator.done .step-num { background: rgba(0,0,0,0.2); }
.step-line { flex: 1; height: 2px; background: #222730; }
.step-label { display: none; }
@media(min-width:640px) { .step-label { display: inline; } }

.btn-primary {
  padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600;
  background: #e8ff47; color: #0a0c10; transition: all 0.2s;
}
.btn-primary:hover { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-secondary {
  padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 500;
  background: #181c24; color: #8892a4; border: 1px solid #222730; transition: all 0.2s;
}
.btn-secondary:hover { border-color: #e8ff47; color: #e8ff47; }
.card { background: #111318; border: 1px solid #222730; border-radius: 12px; }

/* Light mode */
.light .card { background: #fff; border-color: #e5e7eb; }
.light .step-indicator { background: #f3f4f6; color: #6b7280; }
.light .step-line { background: #e5e7eb; }
.light .btn-secondary { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
.light .btn-secondary:hover { border-color: #0a0c10; color: #0a0c10; }

#drop-zone.drag-over { border-color: #e8ff47; background: rgba(232,255,71,0.05); }

.tipo-validacion { color: #ffa502; }
.tipo-duplicado_bd, .tipo-duplicado_archivo { color: #1e90ff; }
.tipo-error_bd { color: #ff4757; }
</style>

<script>
// ── Estado ──
let uploadedData = null; // { headers, preview, total_rows, sheets, campos }
let currentSheet = 0;

// ── Drag & Drop ──
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  if (e.dataTransfer.files.length) {
    fileInput.files = e.dataTransfer.files;
    handleFile(e.dataTransfer.files[0]);
  }
});
fileInput.addEventListener('change', () => {
  if (fileInput.files.length) handleFile(fileInput.files[0]);
});

// ── Subir archivo ──
async function handleFile(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  const allowed = <?= json_encode($extensiones) ?>;
  if (!allowed.includes(ext)) {
    showUploadError('Formato no permitido. Usa: ' + allowed.join(', ').toUpperCase());
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    showUploadError('Archivo demasiado grande. Máximo: 10 MB');
    return;
  }

  // Mostrar info
  document.getElementById('file-name').textContent = file.name;
  document.getElementById('file-size').textContent = (file.size / 1024).toFixed(1) + ' KB';
  document.getElementById('file-info').classList.remove('hidden');
  document.getElementById('drop-zone').classList.add('hidden');
  document.getElementById('upload-error').classList.add('hidden');
  document.getElementById('upload-progress').classList.remove('hidden');
  document.getElementById('btn-step2').classList.add('hidden');

  try {
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('sheet_index', currentSheet);

    const res = await fetch('/api/importacion_vehiculos.php?action=upload', {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    });

    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error al procesar archivo');

    uploadedData = data;
    document.getElementById('upload-progress').classList.add('hidden');
    document.getElementById('btn-step2').classList.remove('hidden');

    // Mostrar selector de hojas si XLSX con múltiples
    if (data.sheets && data.sheets.length > 1) {
      const sel = document.getElementById('sheet-select');
      sel.innerHTML = data.sheets.map((s, i) => `<option value="${i}">${s}</option>`).join('');
      document.getElementById('sheet-selector').classList.remove('hidden');
    }

    toast(`Archivo leído: ${data.total_rows} filas detectadas`, 'success');

  } catch (err) {
    document.getElementById('upload-progress').classList.add('hidden');
    showUploadError(err.message);
  }
}

function showUploadError(msg) {
  const el = document.getElementById('upload-error');
  el.textContent = msg;
  el.classList.remove('hidden');
}

function resetUpload() {
  fileInput.value = '';
  uploadedData = null;
  currentSheet = 0;
  document.getElementById('file-info').classList.add('hidden');
  document.getElementById('drop-zone').classList.remove('hidden');
  document.getElementById('btn-step2').classList.add('hidden');
  document.getElementById('sheet-selector').classList.add('hidden');
  document.getElementById('upload-error').classList.add('hidden');
  document.getElementById('upload-progress').classList.add('hidden');
}

async function changeSheet() {
  const sel = document.getElementById('sheet-select');
  currentSheet = parseInt(sel.value);
  document.getElementById('upload-progress').classList.remove('hidden');
  document.getElementById('btn-step2').classList.add('hidden');

  try {
    const res = await fetch('/api/importacion_vehiculos.php?action=sheets', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ sheet_index: currentSheet })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error al leer hoja');

    uploadedData.headers = data.headers;
    uploadedData.preview = data.preview;
    uploadedData.total_rows = data.total_rows;
    document.getElementById('upload-progress').classList.add('hidden');
    document.getElementById('btn-step2').classList.remove('hidden');
    toast(`Hoja cambiada: ${data.total_rows} filas`, 'success');
  } catch (err) {
    document.getElementById('upload-progress').classList.add('hidden');
    showUploadError(err.message);
  }
}

// ── Navegación entre pasos ──
function setStep(n) {
  document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
  document.getElementById('step-' + n).classList.remove('hidden');
  document.querySelectorAll('.step-indicator').forEach(s => {
    const sn = parseInt(s.dataset.step);
    s.classList.remove('active', 'done');
    if (sn < n) s.classList.add('done');
    if (sn === n) s.classList.add('active');
  });
}

function goToStep1() { setStep(1); }

function goToStep2() {
  if (!uploadedData) return;
  buildMappingTable();
  setStep(2);
}

// ── Tabla de mapeo ──
function buildMappingTable() {
  const body = document.getElementById('mapping-body');
  const campos = uploadedData.campos;
  body.innerHTML = '';

  document.getElementById('rows-count').textContent = `${uploadedData.total_rows} filas a procesar`;

  uploadedData.headers.forEach((header, idx) => {
    // Preview: primeras 3 filas para esta columna
    const previewVals = uploadedData.preview
      .slice(0, 3)
      .map(row => row[idx] ?? '')
      .filter(v => v !== '')
      .join(', ');

    // Auto-mapeo inteligente
    const autoMap = autoDetectMapping(header, campos);

    const tr = document.createElement('tr');
    tr.className = 'border-b border-border/50 hover:bg-surface2/50';
    tr.innerHTML = `
      <td class="py-2.5 px-3 font-medium">${escHtml(header)}</td>
      <td class="py-2.5 px-3 text-muted text-xs max-w-[200px] truncate" title="${escHtml(previewVals)}">${escHtml(previewVals) || '<span class="italic">vacío</span>'}</td>
      <td class="py-2.5 px-3">
        <select name="map_${idx}" class="w-full bg-surface border border-border rounded-lg px-2 py-1.5 text-sm mapping-select" data-idx="${idx}">
          <option value="__ignorar__">— Ignorar columna —</option>
          ${Object.entries(campos).map(([key, info]) =>
            `<option value="${key}" ${autoMap === key ? 'selected' : ''}>${info.label}${info.required ? ' *' : ''}</option>`
          ).join('')}
        </select>
      </td>
    `;
    body.appendChild(tr);
  });
}

function autoDetectMapping(header, campos) {
  const h = header.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const map = {
    'placa': 'placa', 'patente': 'placa', 'plate': 'placa', 'matricula': 'placa',
    'marca': 'marca', 'brand': 'marca', 'fabricante': 'marca',
    'modelo': 'modelo', 'model': 'modelo',
    'ano': 'anio', 'anio': 'anio', 'year': 'anio',
    'tipo': 'tipo', 'type': 'tipo', 'categoria': 'tipo',
    'combustible': 'combustible', 'fuel': 'combustible',
    'km': 'km_actual', 'kilometros': 'km_actual', 'kilometraje': 'km_actual', 'km_actual': 'km_actual', 'odometro': 'km_actual',
    'color': 'color',
    'vin': 'vin', 'chasis': 'vin', 'nro_chasis': 'vin', 'numero_chasis': 'vin',
    'estado': 'estado', 'status': 'estado',
    'vencimiento_seguro': 'venc_seguro', 'venc_seguro': 'venc_seguro', 'seguro': 'venc_seguro',
    'notas': 'notas', 'observaciones': 'notas', 'notes': 'notas', 'comentarios': 'notas',
    'sucursal': 'sucursal_id', 'sucursal_id': 'sucursal_id',
    'costo': 'costo_adquisicion', 'costo_adquisicion': 'costo_adquisicion', 'precio': 'costo_adquisicion', 'valor': 'costo_adquisicion',
    'aseguradora': 'aseguradora', 'compania_seguro': 'aseguradora',
    'poliza': 'poliza_numero', 'poliza_numero': 'poliza_numero', 'nro_poliza': 'poliza_numero',
  };
  return map[h] || '';
}

function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

// ── Ejecutar importación ──
async function ejecutarImportacion() {
  // Recoger mapeo
  const selects = document.querySelectorAll('.mapping-select');
  const mapping = {};
  selects.forEach(sel => {
    mapping[sel.dataset.idx] = sel.value;
  });

  // Validar obligatorios mapeados
  const campos = uploadedData.campos;
  const mappedFields = Object.values(mapping).filter(v => v !== '__ignorar__');

  for (const [key, info] of Object.entries(campos)) {
    if (info.required && !mappedFields.includes(key)) {
      document.getElementById('mapping-errors').textContent = `Debes mapear el campo obligatorio: ${info.label}`;
      document.getElementById('mapping-errors').classList.remove('hidden');
      return;
    }
  }

  // Verificar duplicados en mapeo
  const nonEmpty = mappedFields;
  const seen = new Set();
  for (const f of nonEmpty) {
    if (seen.has(f)) {
      const label = campos[f]?.label || f;
      document.getElementById('mapping-errors').textContent = `El campo "${label}" está mapeado más de una vez`;
      document.getElementById('mapping-errors').classList.remove('hidden');
      return;
    }
    seen.add(f);
  }

  document.getElementById('mapping-errors').classList.add('hidden');

  // Ir al paso 3 con progreso
  setStep(3);
  document.getElementById('import-progress').classList.remove('hidden');
  document.getElementById('errors-detail').classList.add('hidden');
  document.getElementById('kpi-total').textContent = '...';
  document.getElementById('kpi-creados').textContent = '...';
  document.getElementById('kpi-errores').textContent = '...';

  try {
    const res = await fetch('/api/importacion_vehiculos.php?action=import', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ mapping, sheet_index: currentSheet })
    });

    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error en importación');

    document.getElementById('import-progress').classList.add('hidden');

    // KPIs
    document.getElementById('kpi-total').textContent = data.total;
    document.getElementById('kpi-creados').textContent = data.creados;
    document.getElementById('kpi-errores').textContent = data.errores;

    // Título
    if (data.errores === 0) {
      document.getElementById('result-title').textContent = 'Importación exitosa';
      toast(`${data.creados} vehículos importados correctamente`, 'success');
    } else if (data.creados > 0) {
      document.getElementById('result-title').textContent = 'Importación parcial';
      toast(`${data.creados} creados, ${data.errores} con errores`, 'warning');
    } else {
      document.getElementById('result-title').textContent = 'Importación fallida';
      toast('No se pudo importar ningún vehículo', 'error');
    }

    // Detalle errores
    if (data.detalle && data.detalle.length > 0) {
      const tbody = document.getElementById('errors-body');
      tbody.innerHTML = data.detalle.map(d => `
        <tr class="border-b border-border/30">
          <td class="py-1.5 px-3">${d.fila}</td>
          <td class="py-1.5 px-3 font-mono">${escHtml(d.placa || '—')}</td>
          <td class="py-1.5 px-3 tipo-${d.tipo}">${formatTipo(d.tipo)}</td>
          <td class="py-1.5 px-3">${d.errores.map(e => escHtml(e)).join('<br>')}</td>
        </tr>
      `).join('');
      document.getElementById('errors-detail').classList.remove('hidden');
    }

    // Cargar historial
    loadHistory();

  } catch (err) {
    document.getElementById('import-progress').classList.add('hidden');
    document.getElementById('result-title').textContent = 'Error';
    document.getElementById('kpi-total').textContent = '—';
    document.getElementById('kpi-creados').textContent = '—';
    document.getElementById('kpi-errores').textContent = '—';
    toast(err.message, 'error');
  }
}

function formatTipo(tipo) {
  const labels = {
    'validacion': 'Validación',
    'duplicado_bd': 'Duplicado BD',
    'duplicado_archivo': 'Duplicado archivo',
    'error_bd': 'Error BD'
  };
  return labels[tipo] || tipo;
}

// ── Historial ──
async function loadHistory() {
  try {
    const res = await fetch('/api/importacion_vehiculos.php?action=history', {
      credentials: 'include',
      headers: { 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (!data.ok) return;

    const tbody = document.getElementById('history-body');
    if (!data.runs || data.runs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="py-4 text-center text-muted">Sin importaciones previas</td></tr>';
      return;
    }

    tbody.innerHTML = data.runs.map(r => {
      const estadoClass = r.estado === 'completado' ? 'text-success' : r.estado === 'fallido' ? 'text-danger' : 'text-warning';
      const fecha = new Date(r.created_at).toLocaleString('es-CR', { dateStyle: 'short', timeStyle: 'short' });
      return `
        <tr class="border-b border-border/30">
          <td class="py-1.5 px-3">${fecha}</td>
          <td class="py-1.5 px-3 max-w-[150px] truncate" title="${escHtml(r.nombre_archivo)}">${escHtml(r.nombre_archivo)}</td>
          <td class="py-1.5 px-3">${escHtml(r.usuario_nombre || '—')}</td>
          <td class="py-1.5 px-3 text-right">${r.total_filas}</td>
          <td class="py-1.5 px-3 text-right text-success">${r.creados}</td>
          <td class="py-1.5 px-3 text-right text-danger">${r.errores}</td>
          <td class="py-1.5 px-3 ${estadoClass} capitalize">${r.estado}</td>
          <td class="py-1.5 px-3 text-center"><button onclick="deleteRun(${r.id})" class="text-danger text-xs hover:underline" title="Eliminar registro">🗑️</button></td>
        </tr>`;
    }).join('');
  } catch (e) {
    console.error('Error cargando historial:', e);
  }
}

// ── Reset total ──
function resetAll() {
  uploadedData = null;
  currentSheet = 0;
  resetUpload();
  setStep(1);
}

// ── Reasignar columnas (volver al paso 2 con el mismo archivo) ──
function goBackToMapping() {
  if (!uploadedData) {
    toast('No hay archivo cargado. Sube uno nuevo.', 'error');
    setStep(1);
    return;
  }
  buildMappingTable();
  setStep(2);
}

// ── Limpiar todos los mapeos ──
function resetMappings() {
  document.querySelectorAll('.mapping-select').forEach(sel => {
    sel.value = '__ignorar__';
  });
  toast('Mapeo limpiado. Reasigna las columnas.');
}

// ── Eliminar registro de historial ──
async function deleteRun(runId) {
  if (!confirm('¿Eliminar este registro de importación del historial?')) return;
  try {
    const res = await fetch('/api/importacion_vehiculos.php?action=delete_run', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ run_id: runId })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error al eliminar');
    toast('Registro eliminado');
    loadHistory();
  } catch(e) {
    toast(e.message, 'error');
  }
}

// Cargar historial al inicio si vamos directamente al paso 3 (no aplica, pero por si acaso)
document.addEventListener('DOMContentLoaded', () => {
  // Nada por ahora
});
</script>

<?php
$content = ob_get_clean();
echo render_layout('Importar Vehículos', 'importacion_vehiculos', $content);
