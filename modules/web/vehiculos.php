<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/catalogos.php';
require_login();

$db = getDB();
$operadores = $db->query("SELECT id, nombre FROM operadores WHERE estado='Activo' ORDER BY nombre")->fetchAll();
$estadosVehiculo = catalogo_items('estados_vehiculo');
$sucursales = $db->query("SELECT id, nombre FROM sucursales WHERE activo=1 ORDER BY nombre")->fetchAll();

ob_start();
?>
<div class="toolbar">
  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="search-veh" placeholder="Buscar por placa, marca, modelo..." oninput="loadVehiculos()">
  </div>
  <select id="fsuc" onchange="loadVehiculos()" style="max-width:180px">
    <option value="">Todas las sucursales</option>
    <?php foreach($sucursales as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['nombre'])?></option><?php endforeach; ?>
  </select>
  <select id="ftag" onchange="loadVehiculos()" style="max-width:180px">
    <option value="">Todas las etiquetas</option>
    <?php
      try { $allTags = $db->query("SELECT DISTINCT etiqueta FROM vehiculo_etiquetas ORDER BY etiqueta")->fetchAll(); } catch(Throwable $e) { $allTags = []; }
      foreach($allTags as $tg): ?>
      <option value="<?=htmlspecialchars($tg['etiqueta'])?>"><?=htmlspecialchars($tg['etiqueta'])?></option>
    <?php endforeach; ?>
  </select>
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Vehículo</button>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Placa</th><th>Marca / Modelo</th><th>Año</th><th>Tipo</th>
        <th>Combustible</th><th>Operador</th><th>Estado</th><th>KM</th><th>Etiquetas</th>
        <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="tbody-veh">
      <tr><td colspan="10"><div class="empty"><div class="empty-icon">🚗</div><div class="empty-title">Cargando...</div></div></td></tr>
    </tbody>
  </table>
  <div id="pager-veh"></div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="modal-veh">
  <div class="modal">
    <div class="modal-title" id="modal-veh-title">🚗 Nuevo Vehículo</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Placa *</label><input name="placa" placeholder="ABC-1234"></div>
      <div class="form-group"><label>Marca *</label><input name="marca" placeholder="Toyota"></div>
      <div class="form-group"><label>Modelo *</label><input name="modelo" placeholder="Hilux"></div>
      <div class="form-group"><label>Año</label><input name="anio" type="number" min="1990" max="2030" placeholder="2022"></div>
      <div class="form-group"><label>Tipo</label>
        <select name="tipo">
          <option>Automóvil</option><option>Camioneta</option><option>Camión</option>
          <option>Motocicleta</option><option>Furgoneta</option><option>Maquinaria</option><option>Otro</option>
        </select></div>
      <div class="form-group"><label>Combustible</label>
        <select name="combustible">
          <option>Gasolina</option><option>Diésel</option><option>Gas LP</option><option>Eléctrico</option><option>Híbrido</option>
        </select></div>
      <div class="form-group"><label>KM Actual</label><input name="km_actual" type="number" placeholder="45000"></div>
      <div class="form-group"><label>Color</label><input name="color" placeholder="Blanco"></div>
      <div class="form-group"><label>No. Serie / VIN</label><input name="vin" placeholder="1HGCM82633A004352"></div>
      <div class="form-group"><label>Estado</label>
        <select name="estado">
          <?php foreach($estadosVehiculo as $ev): ?><option value="<?=htmlspecialchars($ev['nombre'])?>"><?=htmlspecialchars($ev['nombre'])?></option><?php endforeach; ?>
          <?php if(empty($estadosVehiculo)): ?><option>Activo</option><option>En mantenimiento</option><option>Fuera de servicio</option><?php endif; ?>
        </select></div>
      <div class="form-group"><label>Operador asignado</label>
        <select name="operador_id">
          <option value="">— Ninguno —</option>
          <?php foreach($operadores as $o): ?>
          <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Venc. Seguro</label><input name="venc_seguro" type="date"></div>
      <div class="form-group"><label>Sucursal</label>
        <select name="sucursal_id">
          <option value="">— Sin asignar —</option>
          <?php foreach($sucursales as $s): ?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['nombre'])?></option><?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Costo adquisición</label><input name="costo_adquisicion" type="number" step="0.01" min="0" placeholder="150000.00"></div>
      <div class="form-group"><label>Aseguradora</label><input name="aseguradora" placeholder="Qualitas, HDI..."></div>
      <div class="form-group"><label>No. Póliza</label><input name="poliza_numero" placeholder="POL-2024-001"></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
      <!-- Etiquetas -->
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
        <label style="font-weight:700;font-size:13px;margin-bottom:8px;display:block">🏷️ Etiquetas</label>
        <div id="modal-veh-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px"></div>
        <div style="display:flex;gap:6px">
          <input type="text" id="new-tag-input" placeholder="Nueva etiqueta..." style="flex:1;max-width:200px" onkeydown="if(event.key==='Enter'){event.preventDefault();agregarEtiqueta()}">
          <button type="button" class="btn btn-ghost btn-sm" onclick="agregarEtiqueta()">+ Agregar</button>
        </div>
      </div>
      <div class="form-group full" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
        <label style="font-weight:700;font-size:13px;margin-bottom:8px;display:block">✅ Checklist del Vehículo</label>
        <div id="veh-checklist-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px">
          <label class="ck-item"><input type="checkbox" name="tiene_gata" value="1"> Gata</label>
          <label class="ck-item"><input type="checkbox" name="tiene_herramientas" value="1"> Herramientas</label>
          <label class="ck-item"><input type="checkbox" name="tiene_llanta_repuesto" value="1"> Llanta de repuesto</label>
          <label class="ck-item"><input type="checkbox" name="tiene_bac_flota" value="1"> BAC Flota</label>
          <label class="ck-item"><input type="checkbox" name="revision_ok" value="1"> Revisión general</label>
          <label class="ck-item"><input type="checkbox" name="tiene_luces" value="1"> Luces</label>
          <label class="ck-item"><input type="checkbox" name="tiene_liquidos" value="1"> Nivel de líquidos</label>
          <label class="ck-item"><input type="checkbox" name="tiene_motor_ok" value="1"> Motor</label>
          <label class="ck-item"><input type="checkbox" name="tiene_parabrisas" value="1"> Parabrisas</label>
          <label class="ck-item"><input type="checkbox" name="tiene_documentacion" value="1"> Documentación</label>
          <label class="ck-item"><input type="checkbox" name="tiene_frenos" value="1"> Frenos</label>
          <label class="ck-item"><input type="checkbox" name="tiene_espejos" value="1"> Espejos</label>
        </div>
        <div style="margin-top:8px"><label style="font-size:12px;color:var(--text2)">Detalles adicionales</label>
          <textarea name="detalles_checklist" placeholder="Detalle libre: estado de llantas, nivel de aceite, observaciones..." style="margin-top:4px"></textarea>
        </div>
      </div>
      <div class="form-group full" id="att-veh-wrap"></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-veh')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL PERFIL 360 -->
<div class="modal-bg" id="modal-profile">
  <div class="modal" style="max-width:820px;">
    <div class="modal-title" id="profile-title">📋 Perfil del Vehículo</div>
    <div id="profile-content" style="max-height:75vh;overflow-y:auto;">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-profile')">Cerrar</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
let pager = new Paginator('pager-veh', loadVehiculos, 20);
const attVeh = new AttachmentWidget('att-veh-wrap', 'vehiculos');
let currentVehIdForTags = 0; // ID del vehículo actual en el modal para gestionar etiquetas

// ─── Tag colors ───
const tagColors = ['#e8ff47','#47ffe8','#ff6b6b','#a29bfe','#ffa502','#2ed573','#1e90ff','#fd79a8'];
function tagColor(str) { let h=0; for(let i=0;i<str.length;i++) h=str.charCodeAt(i)+((h<<5)-h); return tagColors[Math.abs(h)%tagColors.length]; }

async function loadVehiculos() {
  const q = document.getElementById('search-veh').value;
  const sucId = document.getElementById('fsuc').value;
  const tag = document.getElementById('ftag').value;
  try {
    const data = await api(`/api/vehiculos.php?q=${encodeURIComponent(q)}&sucursal_id=${sucId}&tag=${encodeURIComponent(tag)}&page=${pager.page}&per=${pager.perPage}`);
    pager.setTotal(data.total);
    const tbody = document.getElementById('tbody-veh');
    if (!data.rows.length) {
      tbody.innerHTML = `<tr><td colspan="10"><div class="empty"><div class="empty-icon">🚗</div><div class="empty-title">Sin vehículos</div></div></td></tr>`;
      return;
    }
    const badges = { 'Activo':'badge-green','En mantenimiento':'badge-orange','Fuera de servicio':'badge-red' };
    tbody.innerHTML = data.rows.map(v => {
      const tagsHtml = (v.etiquetas||[]).map(t => `<span class="badge" style="background:${tagColor(t)};color:#000;font-size:10px;padding:1px 6px;border-radius:8px">${t}</span>`).join(' ');
      return `
      <tr>
        <td><strong style="color:var(--accent)">${v.placa}</strong></td>
        <td>${v.marca} ${v.modelo}</td>
        <td>${v.anio || '—'}</td>
        <td><span class="badge badge-gray">${v.tipo}</span></td>
        <td>${v.combustible}</td>
        <td>${v.operador_nombre || '—'}</td>
        <td><span class="badge ${badges[v.estado]||'badge-gray'}">${v.estado}</span></td>
        <td>${Number(v.km_actual).toLocaleString()} km</td>
        <td>${tagsHtml || '<span style="color:var(--text2);font-size:11px">—</span>'}</td>
        <?php if(can('edit')): ?>
        <td><div class="action-btns">
          <button class="btn btn-ghost btn-sm" onclick="verPerfil(${v.id})" title="Perfil 360">📋</button>
          <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(v)})'>✏️</button>
          <?php if(can('delete')): ?>
          <button class="btn btn-danger btn-sm" onclick="eliminar(${v.id})">🗑️</button>
          <?php endif; ?>
        </div></td>
        <?php endif; ?>
      </tr>`;
    }).join('');
  } catch(e) {}
}

function abrirNuevo() {
  document.getElementById('modal-veh-title').textContent = '🚗 Nuevo Vehículo';
  resetForm('modal-veh');
  document.querySelectorAll('#modal-veh input[type=checkbox]').forEach(cb => cb.checked = false);
  currentVehIdForTags = 0;
  document.getElementById('modal-veh-tags').innerHTML = '';
  document.getElementById('new-tag-input').value = '';
  attVeh.reset();
  openModal('modal-veh');
}

function editar(v) {
  document.getElementById('modal-veh-title').textContent = '✏️ Editar Vehículo';
  fillForm('modal-veh', {
    id: v.id, placa: v.placa, marca: v.marca, modelo: v.modelo,
    anio: v.anio, tipo: v.tipo, combustible: v.combustible,
    km_actual: v.km_actual, color: v.color, vin: v.vin,
    estado: v.estado, operador_id: v.operador_id,
    venc_seguro: v.venc_seguro, sucursal_id: v.sucursal_id || '', notas: v.notas,
    detalles_checklist: v.detalles_checklist || '',
    costo_adquisicion: v.costo_adquisicion || '',
    aseguradora: v.aseguradora || '',
    poliza_numero: v.poliza_numero || ''
  });
  ['tiene_gata','tiene_herramientas','tiene_llanta_repuesto','tiene_bac_flota','revision_ok','tiene_luces','tiene_liquidos','tiene_motor_ok','tiene_parabrisas','tiene_documentacion','tiene_frenos','tiene_espejos'].forEach(f => {
    const cb = document.querySelector(`#modal-veh [name="${f}"]`);
    if (cb) cb.checked = !!parseInt(v[f]);
  });
  currentVehIdForTags = v.id;
  loadTagsModal(v.id);
  attVeh.setEntityId(v.id);
  attVeh.load();
  openModal('modal-veh');
}

async function loadTagsModal(vehId) {
  const wrap = document.getElementById('modal-veh-tags');
  wrap.innerHTML = '<span style="color:var(--text2);font-size:11px">Cargando...</span>';
  try {
    const res = await api(`/api/vehiculos.php?action=tags&id=${vehId}`);
    renderTagPills(wrap, res.tags, true);
  } catch(e) { wrap.innerHTML = ''; }
}

function renderTagPills(container, tags, removable) {
  container.innerHTML = tags.map(t => {
    const bg = tagColor(t.etiqueta || t);
    const label = t.etiqueta || t;
    const remove = removable ? ` <span onclick="eliminarEtiqueta(${t.id})" style="cursor:pointer;margin-left:4px;font-weight:700">&times;</span>` : '';
    return `<span class="badge" style="background:${bg};color:#000;font-size:11px;padding:2px 8px;border-radius:10px;display:inline-flex;align-items:center">${label}${remove}</span>`;
  }).join('');
}

async function agregarEtiqueta() {
  const input = document.getElementById('new-tag-input');
  const tag = input.value.trim();
  if (!tag) return;
  if (!currentVehIdForTags) { toast('Guarda el vehículo primero para agregar etiquetas', 'warning'); return; }
  try {
    await api('/api/vehiculos.php?action=add_tag', 'POST', { vehiculo_id: currentVehIdForTags, etiqueta: tag });
    input.value = '';
    loadTagsModal(currentVehIdForTags);
    toast('Etiqueta agregada');
  } catch(e) {}
}

async function eliminarEtiqueta(tagId) {
  try {
    await api(`/api/vehiculos.php?action=remove_tag&id=${tagId}`, 'DELETE');
    loadTagsModal(currentVehIdForTags);
    toast('Etiqueta eliminada', 'warning');
  } catch(e) {}
}

async function guardar() {
  const data = getForm('modal-veh');
  const checkFields = ['tiene_gata','tiene_herramientas','tiene_llanta_repuesto','tiene_bac_flota','revision_ok','tiene_luces','tiene_liquidos','tiene_motor_ok','tiene_parabrisas','tiene_documentacion','tiene_frenos','tiene_espejos'];
  checkFields.forEach(f => {
    const cb = document.querySelector(`#modal-veh [name="${f}"]`);
    data[f] = cb && cb.checked ? 1 : 0;
  });
  if (!data.placa || !data.marca || !data.modelo) { toast('Placa, Marca y Modelo son obligatorios', 'error'); return; }
  try {
    const method = data.id ? 'PUT' : 'POST';
    const res = await api('/api/vehiculos.php', method, data);
    const savedId = data.id || res.id;
    if (attVeh.hasPending() && savedId) {
      await attVeh.uploadPending(savedId);
    }
    toast(data.id ? 'Vehículo actualizado' : 'Vehículo registrado');
    closeModal('modal-veh');
    loadVehiculos();
  } catch(e) {}
}

async function eliminar(id) {
  confirmDelete('¿Eliminar este vehículo y todos sus registros?', async () => {
    try {
      await api(`/api/vehiculos.php?id=${id}`, 'DELETE');
      toast('Vehículo eliminado', 'warning');
      loadVehiculos();
    } catch(e) {}
  });
}

let kmChart = null;

function scrollToSection(secId) {
  const el = document.getElementById(secId);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function verPerfil(id) {
  const cnt = document.getElementById('profile-content');
  cnt.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>';
  openModal('modal-profile');
  try {
    const d = await api(`/api/vehiculos.php?action=profile&id=${id}`);
    const v = d.vehiculo;
    const t = d.totales;
    const badges = {'Activo':'badge-green','En mantenimiento':'badge-orange','Fuera de servicio':'badge-red'};

    // ── Etiquetas ──
    const tagsHtml = (d.etiquetas||[]).map(tg =>
      `<span class="badge" style="background:${tagColor(tg.etiqueta)};color:#000;font-size:11px;padding:2px 8px;border-radius:10px">${tg.etiqueta}</span>`
    ).join(' ');

    let html = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><strong style="color:var(--accent)">${v.placa}</strong> — ${v.marca} ${v.modelo} ${v.anio||''}</div>
        <div style="text-align:right"><span class="badge ${badges[v.estado]||'badge-gray'}">${v.estado}</span> — ${Number(v.km_actual).toLocaleString()} km</div>
      </div>
      ${tagsHtml ? `<div style="margin-bottom:12px">${tagsHtml}</div>` : ''}

      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:8px;">
        <div class="kpi-card" style="padding:10px;text-align:center;cursor:pointer;transition:border-color .2s" onclick="scrollToSection('sec-asignaciones')" title="Ver asignaciones">
          <div class="kpi-value" style="font-size:18px">${t.total_asignaciones}</div><div class="kpi-sub">Asignaciones</div>
        </div>
        <div class="kpi-card" style="padding:10px;text-align:center;cursor:pointer;transition:border-color .2s" onclick="scrollToSection('sec-mantenimientos')" title="Ver mantenimientos">
          <div class="kpi-value" style="font-size:18px">${t.total_mantenimientos}</div><div class="kpi-sub">Mantenimientos</div>
        </div>
        <div class="kpi-card" style="padding:10px;text-align:center;cursor:pointer;transition:border-color .2s" onclick="scrollToSection('sec-combustible')" title="Ver cargas de combustible">
          <div class="kpi-value" style="font-size:18px">${Number(t.total_litros).toFixed(0)} L</div><div class="kpi-sub">Litros total</div>
        </div>
        <div class="kpi-card" style="padding:10px;text-align:center;cursor:pointer;transition:border-color .2s" onclick="scrollToSection('sec-gastos')" title="Ver desglose de gastos">
          <div class="kpi-value" style="font-size:18px">L ${Number(t.gasto_total).toFixed(0)}</div><div class="kpi-sub">Gasto total</div>
        </div>
        <div class="kpi-card" style="padding:10px;text-align:center;border:1px solid var(--accent)">
          <div class="kpi-value" style="font-size:18px;color:var(--accent)">L ${Number(t.costo_por_km).toFixed(2)}</div>
          <div class="kpi-sub">Costo / km</div>
        </div>
      </div>

      <!-- Desglose de Gastos -->
      <div id="sec-gastos" style="background:var(--surface2);border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px">💰 Desglose de Gastos</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
          <div style="text-align:center;padding:8px;background:var(--bg);border-radius:8px">
            <div style="font-size:15px;font-weight:700;color:#f59e0b">L ${Number(t.gasto_mantenimiento).toFixed(2)}</div>
            <div style="font-size:11px;color:var(--text2)">Mantenimiento</div>
          </div>
          <div style="text-align:center;padding:8px;background:var(--bg);border-radius:8px">
            <div style="font-size:15px;font-weight:700;color:#3b82f6">L ${Number(t.gasto_combustible).toFixed(2)}</div>
            <div style="font-size:11px;color:var(--text2)">Combustible</div>
          </div>
          <div style="text-align:center;padding:8px;background:var(--bg);border-radius:8px">
            <div style="font-size:15px;font-weight:700;color:#ef4444">L ${Number(t.gasto_incidentes||0).toFixed(2)}</div>
            <div style="font-size:11px;color:var(--text2)">Incidentes</div>
          </div>
          <div style="text-align:center;padding:8px;background:var(--bg);border-radius:8px;border:1px solid var(--accent)">
            <div style="font-size:15px;font-weight:700;color:var(--accent)">L ${Number(t.gasto_total).toFixed(2)}</div>
            <div style="font-size:11px;color:var(--text2)">Total General</div>
          </div>
        </div>
        ${Number(t.gasto_total) > 0 ? `<div style="display:flex;height:8px;border-radius:4px;overflow:hidden;gap:2px">
          ${Number(t.gasto_mantenimiento) > 0 ? `<div style="flex:${t.gasto_mantenimiento};background:#f59e0b" title="Mantenimiento: L ${Number(t.gasto_mantenimiento).toFixed(2)}"></div>` : ''}
          ${Number(t.gasto_combustible) > 0 ? `<div style="flex:${t.gasto_combustible};background:#3b82f6" title="Combustible: L ${Number(t.gasto_combustible).toFixed(2)}"></div>` : ''}
          ${Number(t.gasto_incidentes||0) > 0 ? `<div style="flex:${t.gasto_incidentes};background:#ef4444" title="Incidentes: L ${Number(t.gasto_incidentes||0).toFixed(2)}"></div>` : ''}
        </div>` : ''}
      </div>`;


    // ── Datos adicionales del vehículo ──
    if (v.costo_adquisicion || v.aseguradora || v.poliza_numero) {
      html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;font-size:12px">';
      if (v.costo_adquisicion) html += `<div><strong>Costo adquisición:</strong> L ${Number(v.costo_adquisicion).toLocaleString()}</div>`;
      if (v.aseguradora) html += `<div><strong>Aseguradora:</strong> ${v.aseguradora}</div>`;
      if (v.poliza_numero) html += `<div><strong>Póliza:</strong> ${v.poliza_numero}</div>`;
      html += '</div>';
    }

    if (d.asignacion_activa) {
      const a = d.asignacion_activa;
      html += `<div class="alert-item" style="margin-bottom:8px"><div class="alert-dot"></div><div class="alert-text"><strong>Asignación activa:</strong> ${a.operador_nombre} — desde ${a.start_at}</div></div>`;
    }
    if (d.mantenimiento_activo) {
      const m = d.mantenimiento_activo;
      html += `<div class="alert-item critical" style="margin-bottom:8px"><div class="alert-dot"></div><div class="alert-text"><strong>Mantenimiento activo:</strong> ${m.tipo} — ${m.proveedor_nombre||'Sin taller'} (${m.estado})</div></div>`;
    }

    // ── Gráfica de Kilometraje ──
    if (d.historial_odometro && d.historial_odometro.length > 1) {
      html += `<div class="section-title" style="margin:16px 0 8px">📈 Historial de Kilometraje</div>
        <div style="background:var(--surface2);border-radius:8px;padding:12px;margin-bottom:16px">
          <canvas id="chart-km" height="180"></canvas>
        </div>`;
    }

    if (d.historial_asignaciones && d.historial_asignaciones.length) {
      html += '<div id="sec-asignaciones" class="section-title" style="margin:12px 0 6px">🚗 Últimas asignaciones</div><table><thead><tr><th>Operador</th><th>Inicio</th><th>Fin</th><th>KM Inicio</th><th>KM Fin</th><th>Estado</th></tr></thead><tbody>';
      d.historial_asignaciones.forEach(a => {
        const eb = {'Activa':'badge-green','Cerrada':'badge-gray'};
        html += `<tr><td>${a.operador_nombre||'—'}</td><td>${a.start_at}</td><td>${a.end_at||'—'}</td><td>${a.start_km?Number(a.start_km).toLocaleString()+' km':'—'}</td><td>${a.end_km?Number(a.end_km).toLocaleString()+' km':'—'}</td><td><span class="badge ${eb[a.estado]||'badge-gray'}">${a.estado}</span></td></tr>`;
      });
      html += '</tbody></table>';
    }

    if (d.historial_mantenimientos.length) {
      html += '<div id="sec-mantenimientos" class="section-title" style="margin:12px 0 6px">🔧 Últimos mantenimientos</div><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Costo</th><th>Estado</th><th>Proveedor</th></tr></thead><tbody>';
      d.historial_mantenimientos.forEach(m => {
        html += `<tr><td>${m.fecha}</td><td>${m.tipo}</td><td>L ${Number(m.costo).toFixed(2)}</td><td><span class="badge">${m.estado}</span></td><td>${m.proveedor_nombre||'—'}</td></tr>`;
      });
      html += '</tbody></table>';
    }

    if (d.historial_combustible.length) {
      html += '<div id="sec-combustible" class="section-title" style="margin:12px 0 6px">⛽ Últimas cargas</div><table><thead><tr><th>Fecha</th><th>Litros</th><th>Total</th><th>KM</th></tr></thead><tbody>';
      d.historial_combustible.forEach(f => {
        html += `<tr><td>${f.fecha}</td><td>${Number(f.litros).toFixed(1)} L</td><td>L ${Number(f.total).toFixed(2)}</td><td>${f.km?Number(f.km).toLocaleString()+' km':'—'}</td></tr>`;
      });
      html += '</tbody></table>';
    }

    // ── Telemetría placeholder ──
    html += `<div class="section-title" style="margin:16px 0 6px">📡 Telemetría</div>`;
    if (d.telemetria && d.telemetria.length) {
      html += '<table><thead><tr><th>Tipo</th><th>Valor</th><th>Unidad</th><th>Fecha</th></tr></thead><tbody>';
      d.telemetria.forEach(tel => {
        html += `<tr><td>${tel.tipo}</td><td>${tel.valor}</td><td>${tel.unidad||'—'}</td><td>${tel.recorded_at}</td></tr>`;
      });
      html += '</tbody></table>';
    } else {
      html += '<div style="text-align:center;padding:16px;color:var(--text2);font-size:12px;background:var(--surface2);border-radius:8px"><div style="font-size:24px;margin-bottom:4px">📡</div>Sin datos de telemetría aún.<br>Este módulo estará disponible para integración con proveedores GPS/OBD.</div>';
    }

    // Adjuntos
    html += `<div class="section-title" style="margin:12px 0 6px">📎 Adjuntos</div><div id="att-profile-wrap"></div>`;

    document.getElementById('profile-title').textContent = `📋 Perfil: ${v.placa} — ${v.marca} ${v.modelo}`;
    cnt.innerHTML = html;

    // ── Renderizar Chart.js ──
    if (d.historial_odometro && d.historial_odometro.length > 1) {
      const ctx = document.getElementById('chart-km');
      if (ctx) {
        if (kmChart) { kmChart.destroy(); kmChart = null; }
        const labels = d.historial_odometro.map(o => {
          const dt = new Date(o.recorded_at);
          return dt.toLocaleDateString('es-MX', {day:'2-digit', month:'short'});
        });
        const values = d.historial_odometro.map(o => Number(o.reading_km));
        kmChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels,
            datasets: [{
              label: 'Kilometraje',
              data: values,
              borderColor: '#e8ff47',
              backgroundColor: 'rgba(232,255,71,0.1)',
              fill: true,
              tension: 0.3,
              pointRadius: 3,
              pointBackgroundColor: '#e8ff47'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { ticks: { color: '#8892a4', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
              y: { ticks: { color: '#8892a4', callback: v => v.toLocaleString() + ' km' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
          }
        });
      }
    }

    // Attachment widget
    const attProfile = new AttachmentWidget('att-profile-wrap', 'vehiculos', id);
    attProfile.load();
  } catch(e) {
    cnt.innerHTML = '<div class="empty"><div class="empty-icon">❌</div><div class="empty-title">Error al cargar perfil</div></div>';
  }
}

document.addEventListener('DOMContentLoaded', loadVehiculos);
</script>
<?php
$content = ob_get_clean();
echo render_layout('Inventario de Vehículos', 'vehiculos', $content);
?>
