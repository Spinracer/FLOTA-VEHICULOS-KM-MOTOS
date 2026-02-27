<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/catalogos.php';
require_login();

$db = getDB();
$operadores = $db->query("SELECT id, nombre FROM operadores WHERE estado='Activo' ORDER BY nombre")->fetchAll();
$estadosVehiculo = catalogo_items('estados_vehiculo');

ob_start();
?>
<div class="toolbar">
  <div class="search-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="search-veh" placeholder="Buscar por placa, marca, modelo..." oninput="loadVehiculos()">
  </div>
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo Vehículo</button>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Placa</th><th>Marca / Modelo</th><th>Año</th><th>Tipo</th>
        <th>Combustible</th><th>Operador</th><th>Estado</th><th>KM</th>
        <?php if(can('edit')): ?><th>Acciones</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="tbody-veh">
      <tr><td colspan="9"><div class="empty"><div class="empty-icon">🚗</div><div class="empty-title">Cargando...</div></div></td></tr>
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
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-veh')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL PERFIL 360 -->
<div class="modal-bg" id="modal-profile">
  <div class="modal" style="max-width:720px;">
    <div class="modal-title" id="profile-title">📋 Perfil del Vehículo</div>
    <div id="profile-content" style="max-height:70vh;overflow-y:auto;">
      <div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-profile')">Cerrar</button>
    </div>
  </div>
</div>

<script>
let pager = new Paginator('pager-veh', loadVehiculos, 20);

async function loadVehiculos() {
  const q = document.getElementById('search-veh').value;
  try {
    const data = await api(`/api/vehiculos.php?q=${encodeURIComponent(q)}&page=${pager.page}&per=${pager.perPage}`);
    pager.setTotal(data.total);
    const tbody = document.getElementById('tbody-veh');
    if (!data.rows.length) {
      tbody.innerHTML = `<tr><td colspan="9"><div class="empty"><div class="empty-icon">🚗</div><div class="empty-title">Sin vehículos</div></div></td></tr>`;
      return;
    }
    const badges = { 'Activo':'badge-green','En mantenimiento':'badge-orange','Fuera de servicio':'badge-red' };
    tbody.innerHTML = data.rows.map(v => `
      <tr>
        <td><strong style="color:var(--accent)">${v.placa}</strong></td>
        <td>${v.marca} ${v.modelo}</td>
        <td>${v.anio || '—'}</td>
        <td><span class="badge badge-gray">${v.tipo}</span></td>
        <td>${v.combustible}</td>
        <td>${v.operador_nombre || '—'}</td>
        <td><span class="badge ${badges[v.estado]||'badge-gray'}">${v.estado}</span></td>
        <td>${Number(v.km_actual).toLocaleString()} km</td>
        <?php if(can('edit')): ?>
        <td><div class="action-btns">
          <button class="btn btn-ghost btn-sm" onclick="verPerfil(${v.id})" title="Perfil 360">📋</button>
          <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(v)})'>✏️</button>
          <?php if(can('delete')): ?>
          <button class="btn btn-danger btn-sm" onclick="eliminar(${v.id})">🗑️</button>
          <?php endif; ?>
        </div></td>
        <?php endif; ?>
      </tr>`).join('');
  } catch(e) {}
}

function abrirNuevo() {
  document.getElementById('modal-veh-title').textContent = '🚗 Nuevo Vehículo';
  resetForm('modal-veh');
  openModal('modal-veh');
}

function editar(v) {
  document.getElementById('modal-veh-title').textContent = '✏️ Editar Vehículo';
  fillForm('modal-veh', {
    id: v.id, placa: v.placa, marca: v.marca, modelo: v.modelo,
    anio: v.anio, tipo: v.tipo, combustible: v.combustible,
    km_actual: v.km_actual, color: v.color, vin: v.vin,
    estado: v.estado, operador_id: v.operador_id,
    venc_seguro: v.venc_seguro, notas: v.notas
  });
  openModal('modal-veh');
}

async function guardar() {
  const data = getForm('modal-veh');
  if (!data.placa || !data.marca || !data.modelo) { toast('Placa, Marca y Modelo son obligatorios', 'error'); return; }
  try {
    const method = data.id ? 'PUT' : 'POST';
    await api('/api/vehiculos.php', method, data);
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

async function verPerfil(id) {
  const cnt = document.getElementById('profile-content');
  cnt.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div><div class="empty-title">Cargando...</div></div>';
  openModal('modal-profile');
  try {
    const d = await api(`/api/vehiculos.php?action=profile&id=${id}`);
    const v = d.vehiculo;
    const t = d.totales;
    const badges = {'Activo':'badge-green','En mantenimiento':'badge-orange','Fuera de servicio':'badge-red'};
    let html = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div><strong style="color:var(--accent)">${v.placa}</strong> — ${v.marca} ${v.modelo} ${v.anio||''}</div>
        <div style="text-align:right"><span class="badge ${badges[v.estado]||'badge-gray'}">${v.estado}</span> — ${Number(v.km_actual).toLocaleString()} km</div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px;">
        <div class="kpi-card" style="padding:10px;text-align:center"><div class="kpi-value" style="font-size:18px">${t.total_asignaciones}</div><div class="kpi-sub">Asignaciones</div></div>
        <div class="kpi-card" style="padding:10px;text-align:center"><div class="kpi-value" style="font-size:18px">${t.total_mantenimientos}</div><div class="kpi-sub">Mantenimientos</div></div>
        <div class="kpi-card" style="padding:10px;text-align:center"><div class="kpi-value" style="font-size:18px">${Number(t.total_litros).toFixed(0)} L</div><div class="kpi-sub">Litros total</div></div>
        <div class="kpi-card" style="padding:10px;text-align:center"><div class="kpi-value" style="font-size:18px">$${Number(t.gasto_combustible).toFixed(0)}</div><div class="kpi-sub">Gasto combustible</div></div>
      </div>`;

    if (d.asignacion_activa) {
      const a = d.asignacion_activa;
      html += `<div class="alert-item" style="margin-bottom:8px"><div class="alert-dot"></div><div class="alert-text"><strong>Asignación activa:</strong> ${a.operador_nombre} — desde ${a.start_at}</div></div>`;
    }
    if (d.mantenimiento_activo) {
      const m = d.mantenimiento_activo;
      html += `<div class="alert-item critical" style="margin-bottom:8px"><div class="alert-dot"></div><div class="alert-text"><strong>Mantenimiento activo:</strong> ${m.tipo} — ${m.proveedor_nombre||'Sin taller'} (${m.estado})</div></div>`;
    }

    if (d.historial_mantenimientos.length) {
      html += '<div class="section-title" style="margin:12px 0 6px">🔧 Últimos mantenimientos</div><table><thead><tr><th>Fecha</th><th>Tipo</th><th>Costo</th><th>Estado</th><th>Proveedor</th></tr></thead><tbody>';
      d.historial_mantenimientos.forEach(m => {
        html += `<tr><td>${m.fecha}</td><td>${m.tipo}</td><td>$${Number(m.costo).toFixed(2)}</td><td><span class="badge">${m.estado}</span></td><td>${m.proveedor_nombre||'—'}</td></tr>`;
      });
      html += '</tbody></table>';
    }

    if (d.historial_combustible.length) {
      html += '<div class="section-title" style="margin:12px 0 6px">⛽ Últimas cargas</div><table><thead><tr><th>Fecha</th><th>Litros</th><th>Total</th><th>KM</th></tr></thead><tbody>';
      d.historial_combustible.forEach(f => {
        html += `<tr><td>${f.fecha}</td><td>${Number(f.litros).toFixed(1)} L</td><td>$${Number(f.total).toFixed(2)}</td><td>${f.km?Number(f.km).toLocaleString()+' km':'—'}</td></tr>`;
      });
      html += '</tbody></table>';
    }

    document.getElementById('profile-title').textContent = `📋 Perfil: ${v.placa} — ${v.marca} ${v.modelo}`;
    cnt.innerHTML = html;
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
