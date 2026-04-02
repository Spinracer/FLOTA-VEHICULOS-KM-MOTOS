<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/importacion_vehiculos.php';
require_login();

$extensiones = importacion_extensiones_permitidas();
ob_start();
?>
<div class="page-heading">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
      <h1>📥 Importar Operadores</h1>
      <p class="text-muted">Sube un archivo CSV o XLSX con los datos de los operadores. La plantilla contiene ejemplos y formato válido.</p>
    </div>
    <a href="/assets/plantilla_importacion_operadores.csv" download class="btn btn-primary">⬇ Descargar plantilla de operadores</a>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;">
    <div>
      <div id="drop-zone" class="drop-zone">
        <div style="text-align:center;padding:36px 16px;">
          <div style="font-size:40px;">📄</div>
          <div style="margin-top:12px;font-weight:700">Arrastra o selecciona tu archivo</div>
          <div style="margin-top:6px;color:var(--text2);font-size:13px">CSV o XLSX máximo 10 MB</div>
        </div>
      </div>
      <input type="file" id="file-input" accept=".csv,.xlsx" class="hidden">
      <div id="file-info" class="mt-4 hidden">
        <div><strong>Archivo:</strong> <span id="file-name"></span></div>
        <div><strong>Tamaño:</strong> <span id="file-size"></span></div>
      </div>
      <div id="upload-error" class="mt-3 text-red hidden"></div>
      <div id="upload-progress" class="mt-4 hidden">Cargando archivo...</div>
      <div id="sheet-selector" class="mt-4 hidden">
        <label>Hoja:</label>
        <select id="sheet-select" onchange="changeSheet()"></select>
      </div>
      <button id="btn-step2" class="btn btn-secondary mt-4 hidden" onclick="goToStep2()">Siguiente: Mapear columnas</button>
    </div>
    <div style="border-left:1px solid var(--border);padding-left:20px">
      <h2 style="margin-bottom:12px">Instrucciones</h2>
      <ul style="margin:0;padding-left:18px;line-height:1.7;font-size:14px;color:var(--text2)">
        <li>Usa la plantilla de ejemplo para completar los operadores.</li>
        <li>El campo <strong>nombre</strong> es obligatorio.</li>
        <li>Si activas <strong>Actualizar operadores existentes</strong>, el sistema buscará por <strong>DNI</strong> o <strong>ID</strong>.</li>
        <li>Los estados válidos son <strong>Activo</strong>, <strong>Inactivo</strong> o <strong>Suspendido</strong>.</li>
      </ul>
    </div>
  </div>
</div>

<div class="card step-panel hidden" id="step-2" style="margin-top:20px;">
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <div>
      <h2>2. Mapear columnas</h2>
      <p class="text-muted">Asocia cada columna del archivo con el campo correcto del sistema.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <label><input type="checkbox" id="chk-update-existing" onchange="toggleUpdateMode()"> Actualizar operadores existentes</label>
    </div>
  </div>
  <div id="updateModeOptions" class="hidden" style="margin-top:12px;padding:12px;border:1px solid var(--border);border-radius:10px;background:rgba(232,255,71,0.05);">
    <div style="margin-bottom:6px;font-size:13px;color:var(--text2)">Usar como clave para búsqueda:</div>
    <label><input type="radio" name="update-key-field" value="dni" checked> DNI</label>
    <label style="margin-left:16px"><input type="radio" name="update-key-field" value="id"> ID</label>
  </div>
  <div style="margin-top:18px;overflow:auto;max-height:420px;">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr>
          <th>Encabezado</th>
          <th>Ejemplo</th>
          <th>Mapear a</th>
        </tr>
      </thead>
      <tbody id="mapping-body"></tbody>
    </table>
  </div>
  <div id="mapping-errors" class="text-red mt-3 hidden"></div>
  <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px;">
    <button class="btn btn-secondary" onclick="resetAll()">Reiniciar</button>
    <button class="btn btn-primary" onclick="ejecutarImportacion()">Importar operadores</button>
  </div>
</div>

<div class="card step-panel hidden" id="step-3" style="margin-top:20px;">
  <h2>3. Resultado de importación</h2>
  <div id="import-progress" class="mt-3">Procesando...</div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:20px;font-size:14px;">
    <div><strong>Total</strong><div id="kpi-total">—</div></div>
    <div><strong>Creados</strong><div id="kpi-creados">—</div></div>
    <div><strong>Actualizados</strong><div id="kpi-actualizados">—</div></div>
    <div><strong>Errores</strong><div id="kpi-errores">—</div></div>
  </div>
  <div id="errors-detail" class="mt-4 hidden">
    <h3>Errores</h3>
    <table style="width:100%;border-collapse:collapse">
      <thead><tr><th>Fila</th><th>Nombre</th><th>Tipo</th><th>Mensajes</th></tr></thead>
      <tbody id="errors-body"></tbody>
    </table>
  </div>
</div>

<div class="card step-panel hidden" id="step-4" style="margin-top:20px;">
  <h2>Historial de importaciones</h2>
  <div style="overflow:auto;max-height:360px;">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr><th>Archivo</th><th>Usuario</th><th>Fecha</th><th>Total</th><th>Creados</th><th>Actualizados</th><th>Errores</th></tr></thead>
      <tbody id="history-body"><tr><td colspan="7"><div class="empty"><div class="empty-icon">📦</div><div class="empty-title">No hay importaciones recientes</div></div></td></tr></tbody>
    </table>
  </div>
</div>

<style>
.hidden { display: none !important; }
.page-heading h1 { margin: 0 0 8px; }
.page-heading p { margin: 0; color: var(--text2); }
.drop-zone { border: 2px dashed var(--border); border-radius: 14px; cursor: pointer; transition: background .2s, border-color .2s; }
.drop-zone:hover { background: rgba(232,255,71,0.05); }
.drop-zone.drag-over { border-color: #e8ff47; background: rgba(232,255,71,0.12); }
.btn { display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
.btn-primary { background: #e8ff47; color: #0a0c10; padding: 10px 18px; border-radius: 10px; font-weight:700; }
.btn-secondary { background: #181c24; color: #8892a4; padding: 10px 18px; border-radius: 10px; }
.btn:hover { opacity: .95; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); }
th { background: rgba(232,255,71,0.08); }
.text-muted { color: var(--text2); }
.text-red { color: #ff6b6b; }
</style>

<script>
let uploadedData = null;
let currentSheet = 0;
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); }});
fileInput.addEventListener('change', () => { if (fileInput.files.length) handleFile(fileInput.files[0]); });

async function handleFile(file) {
  const ext = file.name.split('.').pop().toLowerCase();
  const allowed = <?= json_encode($extensiones) ?>;
  if (!allowed.includes(ext)) return showUploadError('Formato no permitido. Usa: ' + allowed.join(', ').toUpperCase());
  if (file.size > 10 * 1024 * 1024) return showUploadError('Archivo demasiado grande. Máximo: 10 MB');

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

    const res = await fetch('/api/importacion_operadores.php?action=upload', {
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

    if (data.sheets && data.sheets.length > 1) {
      const sel = document.getElementById('sheet-select');
      sel.innerHTML = data.sheets.map((s,i) => `<option value="${i}">${s}</option>`).join('');
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

function resetAll() {
  fileInput.value = '';
  uploadedData = null;
  currentSheet = 0;
  document.getElementById('file-info').classList.add('hidden');
  document.getElementById('drop-zone').classList.remove('hidden');
  document.getElementById('upload-error').classList.add('hidden');
  document.getElementById('upload-progress').classList.add('hidden');
  document.getElementById('btn-step2').classList.add('hidden');
  document.getElementById('sheet-selector').classList.add('hidden');
  document.getElementById('step-2').classList.add('hidden');
  document.getElementById('step-3').classList.add('hidden');
  document.getElementById('step-4').classList.add('hidden');
}

async function changeSheet() {
  const sel = document.getElementById('sheet-select');
  currentSheet = parseInt(sel.value);
  document.getElementById('upload-progress').classList.remove('hidden');
  document.getElementById('btn-step2').classList.add('hidden');
  try {
    const res = await fetch('/api/importacion_operadores.php?action=sheets', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
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

function toggleUpdateMode() {
  const isChecked = document.getElementById('chk-update-existing').checked;
  const optionsDiv = document.getElementById('updateModeOptions');
  optionsDiv.style.display = isChecked ? 'block' : 'none';
}

function goToStep2() {
  if (!uploadedData) return;
  buildMappingTable();
  document.getElementById('step-2').classList.remove('hidden');
  document.getElementById('step-3').classList.add('hidden');
  document.getElementById('step-4').classList.add('hidden');
}

function buildMappingTable() {
  const body = document.getElementById('mapping-body');
  const campos = uploadedData.campos;
  body.innerHTML = '';
  uploadedData.headers.forEach((header, idx) => {
    const previewVals = uploadedData.preview.slice(0,3).map(row => row[idx] ?? '').filter(v => v !== '').join(', ');
    const autoMap = autoDetectMapping(header, campos);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escHtml(header)}</td>
      <td>${escHtml(previewVals) || '<span style="color:var(--text2);font-style:italic">vacío</span>'}</td>
      <td><select name="map_${idx}" class="mapping-select" data-idx="${idx}">
        <option value="__ignorar__">— Ignorar columna —</option>
        ${Object.entries(campos).map(([key, info]) => `<option value="${key}" ${autoMap === key ? 'selected' : ''}>${info.label}${info.required ? ' *' : ''}</option>`).join('')}
      </select></td>
    `;
    body.appendChild(tr);
  });
}

function autoDetectMapping(header, campos) {
  const h = header.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const map = {
    'nombre': 'nombre', 'full name': 'nombre', 'operador': 'nombre',
    'dni': 'dni', 'identidad': 'dni', 'identificación': 'dni',
    'departamento': 'departamento_id', 'departamento_id': 'departamento_id',
    'licencia': 'licencia', 'no licencia': 'licencia', 'numero licencia': 'licencia',
    'categoria': 'categoria_lic', 'categoría': 'categoria_lic', 'cat': 'categoria_lic',
    'venc_licencia': 'venc_licencia', 'vencimiento': 'venc_licencia', 'fecha vencimiento': 'venc_licencia',
    'telefono': 'telefono', 'tel': 'telefono', 'phone': 'telefono',
    'email': 'email', 'correo': 'email',
    'estado': 'estado', 'status': 'estado',
    'notas': 'notas', 'observaciones': 'notas', 'comments': 'notas',
    'id': 'id', 'operador_id': 'id'
  };
  return map[h] || '';
}

function escHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

async function ejecutarImportacion() {
  const selects = document.querySelectorAll('.mapping-select');
  const mapping = {};
  selects.forEach(sel => { mapping[sel.dataset.idx] = sel.value; });

  const campos = uploadedData.campos;
  const mappedFields = Object.values(mapping).filter(v => v !== '__ignorar__');
  for (const [key, info] of Object.entries(campos)) {
    if (info.required && !mappedFields.includes(key)) {
      document.getElementById('mapping-errors').textContent = `Debes mapear el campo obligatorio: ${info.label}`;
      document.getElementById('mapping-errors').classList.remove('hidden');
      return;
    }
  }
  const seen = new Set();
  for (const f of mappedFields) {
    if (seen.has(f)) {
      const label = campos[f]?.label || f;
      document.getElementById('mapping-errors').textContent = `El campo "${label}" está mapeado más de una vez`;
      document.getElementById('mapping-errors').classList.remove('hidden');
      return;
    }
    seen.add(f);
  }
  document.getElementById('mapping-errors').classList.add('hidden');
  document.getElementById('import-progress').classList.remove('hidden');
  document.getElementById('step-3').classList.remove('hidden');
  document.getElementById('step-4').classList.add('hidden');
  document.getElementById('errors-detail').classList.add('hidden');

  try {
    const updateMode = document.getElementById('chk-update-existing').checked;
    const keyField = updateMode ? document.querySelector('input[name="update-key-field"]:checked').value : 'dni';
    const res = await fetch('/api/importacion_operadores.php?action=import', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ mapping, sheet_index: currentSheet, update_existing: updateMode, update_key_field: keyField })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || 'Error en importación');

    document.getElementById('import-progress').classList.add('hidden');
    document.getElementById('kpi-total').textContent = data.total;
    document.getElementById('kpi-creados').textContent = data.creados;
    document.getElementById('kpi-actualizados').textContent = data.actualizados || 0;
    document.getElementById('kpi-errores').textContent = data.errores;
    document.getElementById('step-4').classList.remove('hidden');
    loadHistory();
    if (data.errores === 0) {
      toast(`${data.creados} operadores importados correctamente`, 'success');
    } else if (data.creados > 0) {
      toast(`${data.creados} creados, ${data.errores} con errores`, 'warning');
    } else {
      toast('No se pudo importar ningún operador', 'error');
    }
    if (data.detalle && data.detalle.length > 0) {
      const tbody = document.getElementById('errors-body');
      tbody.innerHTML = data.detalle.map(d => `
        <tr>
          <td>${d.fila}</td>
          <td>${escHtml(d.nombre || '—')}</td>
          <td class="tipo-${d.tipo}">${escHtml(d.tipo)}</td>
          <td>${d.errores.map(e => escHtml(e)).join('<br>')}</td>
        </tr>
      `).join('');
      document.getElementById('errors-detail').classList.remove('hidden');
    }
  } catch (err) {
    document.getElementById('import-progress').classList.add('hidden');
    toast(err.message, 'error');
  }
}

async function loadHistory() {
  try {
    const res = await fetch('/api/importacion_operadores.php?action=history', { credentials: 'include' });
    const data = await res.json();
    if (!res.ok || !data.ok) return;
    const tbody = document.getElementById('history-body');
    if (!data.runs || !data.runs.length) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-icon">📦</div><div class="empty-title">No hay importaciones recientes</div></div></td></tr>';
      return;
    }
    tbody.innerHTML = data.runs.map(run => `
      <tr>
        <td>${escHtml(run.nombre_archivo)}</td>
        <td>${escHtml(run.usuario_nombre || 'Sistema')}</td>
        <td>${escHtml(run.created_at)}</td>
        <td>${run.total_filas}</td>
        <td>${run.creados}</td>
        <td>${run.actualizados || 0}</td>
        <td>${run.errores}</td>
      </tr>
    `).join('');
  } catch (err) {
    console.error(err);
  }
}

loadHistory();
</script>
<?php
$content = ob_get_clean();
echo render_layout('Importar Operadores', 'importacion_operadores', $content);
