<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$db = getDB();
$vehiculos   = $db->query("SELECT id,placa,marca,modelo FROM vehiculos ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
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
  <?php if(can('create')): ?>
  <button class="btn btn-primary" onclick="abrirNuevo()">+ Registrar Carga</button>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Fecha</th><th>Vehículo</th><th>Litros</th><th>Costo/L</th><th>Total</th><th>KM</th><th>Rendimiento</th><th>Tipo</th><th>Proveedor</th>
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
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Observaciones..."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-comb')">Cancelar</button>
      <button class="btn btn-primary" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<script>
const pager = new Paginator('pager-comb', load, 25);
function calcTotal() {
  const l = parseFloat(document.querySelector('#modal-comb [name=litros]')?.value)||0;
  const c = parseFloat(document.querySelector('#modal-comb [name=costo_litro]')?.value)||0;
  document.querySelector('#modal-comb [name=total]').value = (l*c).toFixed(2);
}
async function load() {
  const q   = document.getElementById('search-comb').value;
  const vid = document.getElementById('filter-veh').value;
  const data = await api(`/api/combustible.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&page=${pager.page}&per=${pager.perPage}`);
  pager.setTotal(data.total);
  // Pills
  document.getElementById('stat-pills').innerHTML = `
    <div class="stat-pill">⛽ Total litros: <strong>${Number(data.stats.litros).toFixed(1)}</strong></div>
    <div class="stat-pill">💰 Gasto total: <strong>$${Number(data.stats.gasto).toFixed(2)}</strong></div>
    <div class="stat-pill">📋 Registros: <strong>${data.total}</strong></div>`;
  const badges = {'Lleno':'badge-green','Parcial':'badge-yellow'};
  const tbody = document.getElementById('tbody-comb');
  if (!data.rows.length) { tbody.innerHTML=`<tr><td colspan="10"><div class="empty"><div class="empty-icon">⛽</div><div class="empty-title">Sin registros</div></div></td></tr>`; return; }
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td>${r.fecha}</td>
      <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
      <td>${Number(r.litros).toFixed(1)} L</td>
      <td>$${Number(r.costo_litro).toFixed(2)}</td>
      <td><strong>$${Number(r.total).toFixed(2)}</strong></td>
      <td>${r.km ? Number(r.km).toLocaleString()+' km' : '—'}</td>
      <td><span class="badge ${r.rendimiento ? 'badge-cyan' : 'badge-gray'}">${r.rendimiento ? Number(r.rendimiento).toFixed(1)+' km/L' : '—'}</span></td>
      <td><span class="badge ${badges[r.tipo_carga]||'badge-gray'}">${r.tipo_carga}</span></td>
      <td>${r.proveedor_nombre||'—'}</td>
      <?php if(can('edit')): ?>
      <td><div class="action-btns">
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
  openModal('modal-comb');
}
function editar(r) {
  document.getElementById('modal-comb-title').textContent = '✏️ Editar Carga';
  fillForm('modal-comb', { id:r.id, fecha:r.fecha, vehiculo_id:r.vehiculo_id, litros:r.litros, costo_litro:r.costo_litro, total:r.total, km:r.km, tipo_carga:r.tipo_carga, proveedor_id:r.proveedor_id, notas:r.notas });
  openModal('modal-comb');
}
async function guardar() {
  const d = getForm('modal-comb');
  if (!d.vehiculo_id || !d.litros) { toast('Vehículo y litros son obligatorios','error'); return; }
  await api('/api/combustible.php', d.id?'PUT':'POST', d);
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
</script>
<?php $content = ob_get_clean(); echo render_layout('Control de Combustible','combustible',$content); ?>
