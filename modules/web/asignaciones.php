<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$db = getDB();
$vehiculos = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
$operadores = $db->query("SELECT id,nombre,estado FROM operadores ORDER BY nombre")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar por placa, operador..." oninput="load()"></div>
  <select id="fv" onchange="load();loadCalendar()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca']) ?></option><?php endforeach; ?>
  </select>
  <select id="fe" onchange="load()" style="max-width:160px"><option value="">Todos</option><option>Activa</option><option>Cerrada</option></select>
  <button class="btn btn-ghost" id="btn-view-toggle" onclick="toggleView()">📅 Calendario</button>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="openNew()">+ Nueva Asignación</button><?php endif; ?>
</div>

<!-- Vista Calendario -->
<div id="calendar-view" style="display:none;background:var(--bg2);border-radius:12px;padding:16px;margin-bottom:16px">
  <div id="fc-calendar" style="min-height:500px"></div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Vehículo</th><th>Operador</th><th>Inicio</th><th>KM Inicio</th><th>Fin</th><th>KM Fin</th><th>Firma</th><th>Estado</th>
        <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  <div id="pgr"></div>
</div>

<div class="modal-bg" id="modal-new">
  <div class="modal" style="max-width:700px">
    <div class="modal-title">📝 Nueva Asignación</div>
    <div class="form-grid">
      <div class="form-group"><label>Vehículo *</label>
        <select name="vehiculo_id" onchange="autoFillChecklist()">
          <option value="">— Seleccionar —</option>
          <?php foreach($vehiculos as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Operador *</label>
        <select name="operador_id">
          <option value="">— Seleccionar —</option>
          <?php foreach($operadores as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre'].' ('.$o['estado'].')') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Inicio *</label><input name="start_at" type="datetime-local"></div>
      <div class="form-group"><label>KM Inicio</label><input name="start_km" type="number" step="0.1" placeholder="45000"></div>
      <div class="form-group full"><label>Notas</label><textarea name="start_notes" placeholder="Observaciones de entrega..."></textarea></div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:10px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label style="font-weight:700;font-size:13px;display:block;margin:0">✅ Checklist de Entrega</label>
          <div style="display:flex;gap:6px;align-items:center">
            <select id="plantilla-select" onchange="loadPlantillaItems()" style="font-size:11px;padding:4px 8px;border-radius:6px;background:var(--bg2);border:1px solid var(--border);color:var(--text)">
              <option value="">Checklist estándar</option>
            </select>
            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAddItem()" title="Agregar item personalizado" style="font-size:12px;padding:4px 8px">+ Item</button>
          </div>
        </div>
        <div id="add-item-row" style="display:none;margin-bottom:8px">
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" id="new-item-label" placeholder="Nombre del nuevo item..." style="flex:1;font-size:12px;padding:6px 10px">
            <label class="ck-item" style="border:none;background:none;padding:0;white-space:nowrap;font-size:11px"><input type="checkbox" id="new-item-required"> Requerido</label>
            <button type="button" class="btn btn-primary btn-sm" onclick="addCustomItem()" style="font-size:11px;padding:4px 10px">Agregar</button>
          </div>
          <label class="ck-item" style="border:none;background:none;padding:2px 0;margin-top:4px;font-size:10px;color:var(--text2)">
            <input type="checkbox" id="new-item-save-vehicle"> Guardar para futuras asignaciones de este vehículo
          </label>
        </div>
        <div id="checklist-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px">
          <label class="ck-item"><input type="checkbox" name="checklist_gata" value="1"> Gata</label>
          <label class="ck-item"><input type="checkbox" name="checklist_herramientas" value="1"> Herramientas</label>
          <label class="ck-item"><input type="checkbox" name="checklist_llanta" value="1"> Llanta de repuesto</label>
          <label class="ck-item"><input type="checkbox" name="checklist_bac" value="1"> BAC Flota</label>
          <label class="ck-item"><input type="checkbox" name="checklist_revision" value="1"> Revisión general</label>
          <label class="ck-item"><input type="checkbox" name="checklist_luces" value="1"> Luces</label>
          <label class="ck-item"><input type="checkbox" name="checklist_liquidos" value="1"> Nivel de líquidos</label>
          <label class="ck-item"><input type="checkbox" name="checklist_motor" value="1"> Motor</label>
          <label class="ck-item"><input type="checkbox" name="checklist_parabrisas" value="1"> Parabrisas</label>
          <label class="ck-item"><input type="checkbox" name="checklist_documentacion" value="1"> Documentación</label>
          <label class="ck-item"><input type="checkbox" name="checklist_frenos" value="1"> Frenos</label>
          <label class="ck-item"><input type="checkbox" name="checklist_espejos" value="1"> Espejos</label>
        </div>
        <div id="custom-items-area"></div>
        <div style="margin-top:6px"><textarea name="checklist_detalles" placeholder="Detalles adicionales del checklist de entrega..." style="font-size:12px"></textarea></div>
      </div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:10px">
        <label style="font-weight:700;font-size:13px;margin-bottom:8px;display:block">✍️ Firma de Entrega</label>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_entrega_tipo" value="ninguna" checked> Sin firma</label>
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_entrega_tipo" value="digital"> Firma digital</label>
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_entrega_tipo" value="fisica"> Firma física</label>
        </div>
        <div id="firma-entrega-digital-area" style="display:none">
          <p style="font-size:11px;color:var(--text2);margin-bottom:6px">Dibuje la firma del operador en el recuadro o envíe un link:</p>
          <canvas id="firma-entrega-canvas" width="400" height="150" style="border:1px solid var(--border);border-radius:6px;background:#ffffff;cursor:crosshair;display:block;margin-bottom:6px"></canvas>
          <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" onclick="clearFirmaEntrega()">Limpiar</button>
            <button class="btn btn-ghost btn-sm" id="btn-link-entrega" onclick="enviarLinkFirmaEntrega()" title="Guarda primero, luego genera el link">📲 Enviar link al operador</button>
          </div>
        </div>
        <div id="firma-entrega-fisica-area" style="display:none">
          <p style="font-size:11px;color:var(--text2)">Imprima el acta, obtenga la firma física y luego suba una foto del documento firmado como adjunto.</p>
        </div>
      </div>
      <?php if(can('manage_permissions')): ?>
      <div class="form-group full"><label>Justificación override (solo admin)</label><textarea name="override_reason" placeholder="Solo si necesitas saltar un bloqueo."></textarea></div>
      <?php endif; ?>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-new')">Cancelar</button><button class="btn btn-primary" onclick="saveNew()">Guardar</button></div>
  </div>
</div>

<div class="modal-bg" id="modal-close">
  <div class="modal" style="max-width:700px">
    <div class="modal-title">✅ Cerrar Asignación</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fin *</label><input name="end_at" type="datetime-local"></div>
      <div class="form-group"><label>KM Fin *</label><input name="end_km" type="number" step="0.1" placeholder="45500"></div>
      <div class="form-group full"><label>Notas de cierre</label><textarea name="end_notes" placeholder="Observaciones de retorno..."></textarea></div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:10px">
        <label style="font-weight:700;font-size:13px;margin-bottom:8px;display:block">✅ Checklist de Retorno</label>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px">
          <label class="ck-item"><input type="checkbox" name="end_checklist_gata" value="1"> Gata</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_herramientas" value="1"> Herramientas</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_llanta" value="1"> Llanta de repuesto</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_bac" value="1"> BAC Flota</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_revision" value="1"> Revisión general</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_luces" value="1"> Luces</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_liquidos" value="1"> Nivel de líquidos</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_motor" value="1"> Motor</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_parabrisas" value="1"> Parabrisas</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_documentacion" value="1"> Documentación</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_frenos" value="1"> Frenos</label>
          <label class="ck-item"><input type="checkbox" name="end_checklist_espejos" value="1"> Espejos</label>
        </div>
        <div style="margin-top:6px"><textarea name="end_checklist_detalles" placeholder="Detalles del checklist de retorno..." style="font-size:12px"></textarea></div>
      </div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:10px">
        <label style="font-weight:700;font-size:13px;margin-bottom:8px;display:block">✍️ Firma del Operador</label>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_tipo" value="ninguna" checked> Sin firma</label>
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_tipo" value="digital"> Firma digital</label>
          <label class="ck-item" style="border:none;background:none;padding:2px 0"><input type="radio" name="firma_tipo" value="fisica"> Firma física (imprimir)</label>
        </div>
        <div id="firma-digital-area" style="display:none">
          <p style="font-size:11px;color:var(--text2);margin-bottom:6px">Dibuje la firma en el recuadro o envíe un link al operador:</p>
          <canvas id="firma-canvas" width="400" height="150" style="border:1px solid var(--border);border-radius:6px;background:#ffffff;cursor:crosshair;display:block;margin-bottom:6px"></canvas>
          <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" onclick="clearFirma()">Limpiar</button>
            <button class="btn btn-ghost btn-sm" onclick="enviarLinkFirma()">📲 Enviar link al operador</button>
          </div>
        </div>
        <div id="firma-fisica-area" style="display:none">
          <p style="font-size:11px;color:var(--text2)">Imprima el acta, obtenga la firma física y luego suba una foto del documento firmado como adjunto.</p>
        </div>
      </div>
      <?php if(can('manage_permissions')): ?>
      <div class="form-group full"><label>Justificación override (solo admin)</label><textarea name="override_reason" placeholder="Solo si necesitas saltar validación de odómetro."></textarea></div>
      <?php endif; ?>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-close')">Cancelar</button><button class="btn btn-primary" onclick="saveClose()">Cerrar asignación</button></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
const pager = new Paginator('pgr', load, 25);
let calendarView = false;
let fcInstance = null;

// ── FullCalendar ──
function toggleView() {
  calendarView = !calendarView;
  document.getElementById('calendar-view').style.display = calendarView ? '' : 'none';
  document.querySelector('.table-wrap').style.display = calendarView ? 'none' : '';
  document.getElementById('btn-view-toggle').textContent = calendarView ? '📋 Tabla' : '📅 Calendario';
  if (calendarView && !fcInstance) initCalendar();
  if (calendarView) loadCalendar();
}
function initCalendar() {
  const el = document.getElementById('fc-calendar');
  fcInstance = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    locale: 'es',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
    eventClick: info => { if (info.event.extendedProps.estado === 'Activa') openClose(info.event.extendedProps._raw); },
    height: 'auto',
    themeSystem: 'standard',
  });
  fcInstance.render();
}
async function loadCalendar() {
  if (!fcInstance) return;
  const vid = document.getElementById('fv').value;
  const info = fcInstance.view;
  const from = info.activeStart.toISOString().slice(0,10);
  const to = info.activeEnd.toISOString().slice(0,10);
  try {
    const data = await api(`/api/asignaciones.php?action=calendar&from=${from}&to=${to}&vehiculo_id=${vid}`);
    fcInstance.removeAllEvents();
    (data.events || []).forEach(ev => {
      ev._raw = ev.extendedProps;
      fcInstance.addEvent(ev);
    });
  } catch(e) {}
}

// ── Dynamic checklist plantillas ──
const FIXED_CHECKLIST_HTML = document.getElementById('checklist-grid').innerHTML;
async function loadPlantillas() {
  try {
    const data = await api('/api/asignaciones.php?action=checklist_plantillas');
    const sel = document.getElementById('plantilla-select');
    if (!sel) return;
    sel.innerHTML = '<option value="">Checklist estándar</option>';
    (data.plantillas || []).forEach(p => {
      sel.innerHTML += `<option value="${p.id}">${p.nombre} (${p.tipo})</option>`;
    });
  } catch(e) {}
}
async function loadPlantillaItems() {
  const sel = document.getElementById('plantilla-select');
  const grid = document.getElementById('checklist-grid');
  if (!sel || !grid) return;
  const pid = sel.value;
  if (!pid) { grid.innerHTML = FIXED_CHECKLIST_HTML; return; }
  try {
    const data = await api(`/api/asignaciones.php?action=checklist_items&plantilla_id=${pid}`);
    const items = data.items || [];
    if (!items.length) { grid.innerHTML = FIXED_CHECKLIST_HTML; return; }
    grid.innerHTML = items.map(it => `<label class="ck-item">
        <input type="checkbox" class="dyn-check" data-label="${it.label}" ${it.requerido?'required':''}> ${it.label}${it.requerido?' *':''}
      </label>`).join('');
  } catch(e) { grid.innerHTML = FIXED_CHECKLIST_HTML; }
}

// ── Custom items inline ──
function toggleAddItem() {
  const row = document.getElementById('add-item-row');
  row.style.display = row.style.display === 'none' ? '' : 'none';
  if (row.style.display !== 'none') document.getElementById('new-item-label').focus();
}
async function addCustomItem() {
  const label = document.getElementById('new-item-label').value.trim();
  if (!label) { toast('Escribe el nombre del item', 'error'); return; }
  const req = document.getElementById('new-item-required').checked;
  const saveForVehicle = document.getElementById('new-item-save-vehicle').checked;
  const area = document.getElementById('custom-items-area');
  const grid = area.querySelector('.custom-grid') || (() => {
    const g = document.createElement('div');
    g.className = 'custom-grid';
    g.style.cssText = 'display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px;margin-top:8px';
    area.appendChild(g);
    return g;
  })();
  grid.innerHTML += `<label class="ck-item" style="border-color:var(--accent2)">
    <input type="checkbox" class="dyn-check custom-check" data-label="${label}" ${req?'required':''} checked> ${label}${req?' *':''}
  </label>`;
  // Save per vehicle if checkbox is on
  if (saveForVehicle) {
    const vid = document.querySelector('#modal-new [name="vehiculo_id"]').value;
    if (vid) {
      try {
        await api('/api/asignaciones.php?action=save_vehicle_item', 'POST', { vehiculo_id: parseInt(vid), label, requerido: req ? 1 : 0 });
      } catch(e) {}
    }
  }
  document.getElementById('new-item-label').value = '';
  document.getElementById('new-item-required').checked = false;
}

// ── Firma canvas ──
let firmaDrawing = false;
const firmaCanvas = document.getElementById('firma-canvas');
const firmaCtx = firmaCanvas ? firmaCanvas.getContext('2d') : null;
if (firmaCanvas) {
  firmaCanvas.addEventListener('mousedown', e => { firmaDrawing = true; firmaCtx.beginPath(); firmaCtx.moveTo(e.offsetX, e.offsetY); });
  firmaCanvas.addEventListener('mousemove', e => { if (!firmaDrawing) return; firmaCtx.lineTo(e.offsetX, e.offsetY); firmaCtx.strokeStyle = '#1a1a1a'; firmaCtx.lineWidth = 2.5; firmaCtx.lineCap = 'round'; firmaCtx.stroke(); });
  firmaCanvas.addEventListener('mouseup', () => firmaDrawing = false);
  firmaCanvas.addEventListener('mouseleave', () => firmaDrawing = false);
  // Touch support
  firmaCanvas.addEventListener('touchstart', e => { e.preventDefault(); firmaDrawing = true; const t = e.touches[0]; const r = firmaCanvas.getBoundingClientRect(); firmaCtx.beginPath(); firmaCtx.moveTo(t.clientX - r.left, t.clientY - r.top); });
  firmaCanvas.addEventListener('touchmove', e => { e.preventDefault(); if (!firmaDrawing) return; const t = e.touches[0]; const r = firmaCanvas.getBoundingClientRect(); firmaCtx.lineTo(t.clientX - r.left, t.clientY - r.top); firmaCtx.strokeStyle = '#1a1a1a'; firmaCtx.lineWidth = 2.5; firmaCtx.lineCap = 'round'; firmaCtx.stroke(); });
  firmaCanvas.addEventListener('touchend', () => firmaDrawing = false);
}
function clearFirma() { if (firmaCtx) { firmaCtx.clearRect(0, 0, firmaCanvas.width, firmaCanvas.height); } }

// Export canvas with white background (CSS bg not included in toDataURL)
function canvasToDataURL(canvas) {
  const tmp = document.createElement('canvas');
  tmp.width = canvas.width; tmp.height = canvas.height;
  const tctx = tmp.getContext('2d');
  tctx.fillStyle = '#ffffff';
  tctx.fillRect(0, 0, tmp.width, tmp.height);
  tctx.drawImage(canvas, 0, 0);
  return tmp.toDataURL('image/png');
}

// ── Firma entrega canvas (modal-new) ──
let firmaEntregaDrawing = false;
const firmaEntregaCanvas = document.getElementById('firma-entrega-canvas');
const firmaEntregaCtx = firmaEntregaCanvas ? firmaEntregaCanvas.getContext('2d') : null;
if (firmaEntregaCanvas) {
  firmaEntregaCanvas.addEventListener('mousedown', e => { firmaEntregaDrawing = true; firmaEntregaCtx.beginPath(); firmaEntregaCtx.moveTo(e.offsetX, e.offsetY); });
  firmaEntregaCanvas.addEventListener('mousemove', e => { if (!firmaEntregaDrawing) return; firmaEntregaCtx.lineTo(e.offsetX, e.offsetY); firmaEntregaCtx.strokeStyle = '#1a1a1a'; firmaEntregaCtx.lineWidth = 2.5; firmaEntregaCtx.lineCap = 'round'; firmaEntregaCtx.stroke(); });
  firmaEntregaCanvas.addEventListener('mouseup', () => firmaEntregaDrawing = false);
  firmaEntregaCanvas.addEventListener('mouseleave', () => firmaEntregaDrawing = false);
  firmaEntregaCanvas.addEventListener('touchstart', e => { e.preventDefault(); firmaEntregaDrawing = true; const t = e.touches[0]; const r = firmaEntregaCanvas.getBoundingClientRect(); firmaEntregaCtx.beginPath(); firmaEntregaCtx.moveTo(t.clientX - r.left, t.clientY - r.top); });
  firmaEntregaCanvas.addEventListener('touchmove', e => { e.preventDefault(); if (!firmaEntregaDrawing) return; const t = e.touches[0]; const r = firmaEntregaCanvas.getBoundingClientRect(); firmaEntregaCtx.lineTo(t.clientX - r.left, t.clientY - r.top); firmaEntregaCtx.strokeStyle = '#1a1a1a'; firmaEntregaCtx.lineWidth = 2.5; firmaEntregaCtx.lineCap = 'round'; firmaEntregaCtx.stroke(); });
  firmaEntregaCanvas.addEventListener('touchend', () => firmaEntregaDrawing = false);
}
function clearFirmaEntrega() { if (firmaEntregaCtx) { firmaEntregaCtx.clearRect(0, 0, firmaEntregaCanvas.width, firmaEntregaCanvas.height); } }

// Firma type toggles
document.querySelectorAll('[name="firma_entrega_tipo"]').forEach(r => r.addEventListener('change', () => {
  document.getElementById('firma-entrega-digital-area').style.display = r.value === 'digital' && r.checked ? '' : 'none';
  document.getElementById('firma-entrega-fisica-area').style.display = r.value === 'fisica' && r.checked ? '' : 'none';
}));

// Firma type toggle (close modal)
document.querySelectorAll('[name="firma_tipo"]').forEach(r => r.addEventListener('change', () => {
  document.getElementById('firma-digital-area').style.display = r.value === 'digital' && r.checked ? '' : 'none';
  document.getElementById('firma-fisica-area').style.display = r.value === 'fisica' && r.checked ? '' : 'none';
}));

// ── Auto-fill checklist + km from vehicle ──
async function autoFillChecklist() {
  const vid = document.querySelector('#modal-new [name="vehiculo_id"]').value;
  if (!vid) return;
  // Reset custom items
  document.getElementById('custom-items-area').innerHTML = '';
  try {
    // Fetch vehicle profile, last km, and vehicle custom items in parallel
    const [profileData, kmData, customData] = await Promise.all([
      api(`/api/vehiculos.php?action=profile&id=${vid}`),
      api(`/api/asignaciones.php?action=last_km&vehiculo_id=${vid}`),
      api(`/api/asignaciones.php?action=vehicle_items&vehiculo_id=${vid}`).catch(() => ({ items: [] }))
    ]);
    const v = profileData.vehiculo;
    if (v) {
      document.querySelector('#modal-new [name="checklist_gata"]').checked = !!parseInt(v.tiene_gata);
      document.querySelector('#modal-new [name="checklist_herramientas"]').checked = !!parseInt(v.tiene_herramientas);
      document.querySelector('#modal-new [name="checklist_llanta"]').checked = !!parseInt(v.tiene_llanta_repuesto);
      document.querySelector('#modal-new [name="checklist_bac"]').checked = !!parseInt(v.tiene_bac_flota);
      document.querySelector('#modal-new [name="checklist_revision"]').checked = !!parseInt(v.revision_ok);
      if (v.detalles_checklist) document.querySelector('#modal-new [name="checklist_detalles"]').value = v.detalles_checklist;
    }
    // Auto-fill km: prefer last assignment end_km, fallback to vehicle km_actual
    const lastKm = kmData.km || (v ? v.km_actual : null);
    if (lastKm) document.querySelector('#modal-new [name="start_km"]').value = lastKm;
    // Load vehicle custom items
    const cItems = customData.items || [];
    if (cItems.length) {
      const area = document.getElementById('custom-items-area');
      const g = document.createElement('div');
      g.className = 'custom-grid';
      g.style.cssText = 'display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px;margin-top:8px';
      g.innerHTML = cItems.map(it => `<label class="ck-item" style="border-color:var(--accent2)">
        <input type="checkbox" class="dyn-check custom-check" data-label="${it.label}" ${it.requerido?'required':''} checked> ${it.label}${it.requerido?' *':''}
      </label>`).join('');
      area.appendChild(g);
    }
  } catch(e) {}
}

async function enviarLinkFirmaEntrega() {
  toast('Creando asignación y generando link de firma...', 'info');
  try {
    const d = getForm('modal-new');
    if(!d.vehiculo_id || !d.operador_id || !d.start_at){ toast('Vehículo, operador e inicio son obligatorios','error'); return; }
    d.start_at = d.start_at.replace('T',' ')+':00';
    ['checklist_gata','checklist_herramientas','checklist_llanta','checklist_bac','checklist_revision','checklist_luces','checklist_liquidos','checklist_motor','checklist_parabrisas','checklist_documentacion','checklist_frenos','checklist_espejos'].forEach(f => {
      const cb = document.querySelector(`#modal-new [name="${f}"]`);
      d[f] = cb && cb.checked ? 1 : 0;
    });
    const pSel = document.getElementById('plantilla-select');
    if (pSel && pSel.value) d.plantilla_id = parseInt(pSel.value);
    // Don't set firma_entrega_tipo yet — let the link signing set it
    d.firma_entrega_tipo = 'ninguna';
    const res = await api('/api/asignaciones.php', 'POST', d);
    if (!res.id) { toast('Error al crear asignación', 'error'); return; }
    // Save dynamic checklist
    const dynChecks = document.querySelectorAll('#checklist-grid .dyn-check, #custom-items-area .dyn-check');
    if (dynChecks.length) {
      const items = [];
      dynChecks.forEach(cb => items.push({ label: cb.dataset.label, checked: cb.checked ? 1 : 0, observacion: null }));
      try { await api('/api/asignaciones.php?action=checklist_respuestas', 'POST', { asignacion_id: res.id, momento: 'entrega', items }); } catch(e) {}
    }
    // Generate firma link
    const lr = await api('/api/asignaciones.php?action=firma_link', 'POST', { id: res.id, momento: 'entrega' });
    if (lr.token) {
      const link = window.location.origin + '/firma.php?token=' + lr.token;
      await navigator.clipboard.writeText(link);
      toast('Asignación creada. Link de firma copiado al portapapeles ✅');
    }
    closeModal('modal-new');
    load();
  } catch(e) { toast('Error: ' + e.message, 'error'); }
}

// Helper: save and return ID, then generate firma entrega link
let _lastNewId = null;
async function saveNewAndGetId() {
  const d = getForm('modal-new');
  if(!d.vehiculo_id || !d.operador_id || !d.start_at){ toast('Vehículo, operador e inicio son obligatorios','error'); return; }
  d.start_at = d.start_at.replace('T',' ')+':00';
  ['checklist_gata','checklist_herramientas','checklist_llanta','checklist_bac','checklist_revision','checklist_luces','checklist_liquidos','checklist_motor','checklist_parabrisas','checklist_documentacion','checklist_frenos','checklist_espejos'].forEach(f => {
    const cb = document.querySelector(`#modal-new [name="${f}"]`);
    d[f] = cb && cb.checked ? 1 : 0;
  });
  const pSel = document.getElementById('plantilla-select');
  if (pSel && pSel.value) d.plantilla_id = parseInt(pSel.value);
  const feRadio = document.querySelector('#modal-new [name="firma_entrega_tipo"]:checked');
  d.firma_entrega_tipo = feRadio ? feRadio.value : 'ninguna';
  if (d.firma_entrega_tipo === 'digital' && firmaEntregaCanvas) {
    d.firma_entrega_data = canvasToDataURL(firmaEntregaCanvas);
  }
  const res = await api('/api/asignaciones.php', 'POST', d);
  _lastNewId = res.id;
  // Save dynamic checklist responses
  if (res.id) {
    const dynChecks = document.querySelectorAll('#checklist-grid .dyn-check, #custom-items-area .dyn-check');
    if (dynChecks.length) {
      const items = [];
      dynChecks.forEach(cb => items.push({ label: cb.dataset.label, checked: cb.checked ? 1 : 0, observacion: null }));
      try { await api('/api/asignaciones.php?action=checklist_respuestas', 'POST', { asignacion_id: res.id, momento: 'entrega', items }); } catch(e) {}
    }
  }
  // Generate firma link if digital
  if (res.id && d.firma_entrega_tipo === 'digital') {
    try {
      const lr = await api('/api/asignaciones.php?action=firma_link', 'POST', { id: res.id, momento: 'entrega' });
      if (lr.token) {
        const link = window.location.origin + '/firma.php?token=' + lr.token;
        await navigator.clipboard.writeText(link);
        toast('Link de firma de entrega copiado al portapapeles ✅');
      }
    } catch(e) {}
  }
  toast('Asignación creada');
  closeModal('modal-new');
  load();
}

async function enviarLinkFirma() {
  const asigId = document.querySelector('#modal-close [name="id"]').value;
  if (!asigId) { toast('Primero guarda la asignación','error'); return; }
  try {
    const res = await api('/api/asignaciones.php?action=firma_link', 'POST', { id: parseInt(asigId), momento: 'retorno' });
    if (res.token) {
      const link = window.location.origin + '/firma.php?token=' + res.token;
      await navigator.clipboard.writeText(link);
      toast('Link de firma copiado al portapapeles');
    }
  } catch(e) { toast('Error al generar link de firma','error'); }
}

async function load(){
  const q = document.getElementById('s').value;
  const vid = document.getElementById('fv').value;
  const est = document.getElementById('fe').value;
  const data = await api(`/api/asignaciones.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  const tbody = document.getElementById('tbody');
  const EB = {'Activa':'badge-green','Cerrada':'badge-gray'};

  if(!data.rows.length){
    tbody.innerHTML = `<tr><td colspan="10"><div class="empty"><div class="empty-icon">📝</div><div class="empty-title">Sin asignaciones</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.rows.map(r => {
    const fe = r.firma_entrega_tipo && r.firma_entrega_tipo !== 'ninguna';
    const fr = r.firma_tipo && r.firma_tipo !== 'ninguna';
    const fePend = !fe && r.firma_entrega_token;
    let firmaHtml = '—';
    if (fe && fr) firmaHtml = '<span title="Entrega + Retorno firmados">✍️✍️</span>';
    else if (fe && !fr) firmaHtml = '<span title="Firma de entrega">✍️ Ent</span>';
    else if (!fe && fr) firmaHtml = '<span title="Firma de retorno">✍️ Ret</span>';
    else if (fePend) firmaHtml = '<span title="Link de firma enviado, pendiente" style="opacity:.5">⏳ Pend</span>';
    return `
    <tr>
      <td>${r.id}</td>
      <td><strong style="color:var(--accent2)">${r.placa || ''} ${r.marca || ''}</strong></td>
      <td>${r.operador_nombre || '—'}</td>
      <td>${(r.start_at || '').replace('T',' ').slice(0,16) || '—'}</td>
      <td>${r.start_km ? Number(r.start_km).toLocaleString()+' km' : '—'}</td>
      <td>${r.end_at ? String(r.end_at).slice(0,16) : '—'}</td>
      <td>${r.end_km ? Number(r.end_km).toLocaleString()+' km' : '—'}</td>
      <td style="font-size:12px">${firmaHtml}</td>
      <td><span class="badge ${EB[r.estado] || 'badge-gray'}">${r.estado}</span></td>
      <?php if(can('edit')): ?>
      <td>
        <div class="action-btns">
          <button class="btn btn-ghost btn-sm" onclick="window.open('/print.php?type=asignacion&id=${r.id}','_blank')" title="Imprimir PDF">🖨️</button>
          ${r.estado==='Activa' ? `<button class="btn btn-primary btn-sm" onclick='openClose(${JSON.stringify(r)})'>Cerrar</button>` : ''}
          <?php if(can('delete')): ?>
          <button class="btn btn-danger btn-sm" onclick="delItem(${r.id})">🗑️</button>
          <?php endif; ?>
        </div>
      </td>
      <?php endif; ?>
    </tr>`}).join('');
}

function openNew(){
  resetForm('modal-new');
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset()*60000).toISOString().slice(0,16);
  document.querySelector('#modal-new [name=start_at]').value = local;
  // Reset checklist to standard
  document.getElementById('checklist-grid').innerHTML = FIXED_CHECKLIST_HTML;
  document.getElementById('custom-items-area').innerHTML = '';
  document.getElementById('add-item-row').style.display = 'none';
  const pSel = document.getElementById('plantilla-select');
  if (pSel) pSel.value = '';
  openModal('modal-new');
}

async function saveNew(){
  await saveNewAndGetId();
}

function openClose(r){
  resetForm('modal-close');
  fillForm('modal-close', { id: r.id });
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset()*60000).toISOString().slice(0,16);
  document.querySelector('#modal-close [name=end_at]').value = local;
  openModal('modal-close');
}

async function saveClose(){
  const d = getForm('modal-close');
  if(!d.id || !d.end_at || !d.end_km){ toast('Fin y km fin son obligatorios','error'); return; }
  d.end_at = d.end_at.replace('T',' ')+':00';
  // End checklist checkboxes
  ['end_checklist_gata','end_checklist_herramientas','end_checklist_llanta','end_checklist_bac','end_checklist_revision','end_checklist_luces','end_checklist_liquidos','end_checklist_motor','end_checklist_parabrisas','end_checklist_documentacion','end_checklist_frenos','end_checklist_espejos'].forEach(f => {
    const cb = document.querySelector(`#modal-close [name="${f}"]`);
    d[f] = cb && cb.checked ? 1 : 0;
  });
  // Firma
  const firmaRadio = document.querySelector('#modal-close [name="firma_tipo"]:checked');
  d.firma_tipo = firmaRadio ? firmaRadio.value : 'ninguna';
  if (d.firma_tipo === 'digital' && firmaCanvas) {
    d.firma_data = canvasToDataURL(firmaCanvas);
  }
  await api('/api/asignaciones.php', 'PUT', { ...d, action: 'close' });
  toast('Asignación cerrada');
  closeModal('modal-close');
  load();
}

async function delItem(id){
  confirmDelete('¿Eliminar esta asignación?', async () => {
    await api(`/api/asignaciones.php?id=${id}`, 'DELETE');
    toast('Asignación eliminada', 'warning');
    load();
  });
}

document.addEventListener('DOMContentLoaded', () => { load(); loadPlantillas(); });
</script>

<?php $content = ob_get_clean(); echo render_layout('Asignaciones', 'asignaciones', $content); ?>
