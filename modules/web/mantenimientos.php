<?php
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/catalogos.php';
require_login();
$db = getDB();
$vehiculos   = $db->query("SELECT id,placa,marca,modelo FROM vehiculos WHERE deleted_at IS NULL ORDER BY placa")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores ORDER BY nombre")->fetchAll();
$tiposMantenimiento = catalogo_items('tipos_mantenimiento');
$unidades = $db->query("SELECT clave,nombre FROM catalogo_unidades WHERE activo=1 ORDER BY nombre")->fetchAll();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span>
    <input type="text" id="s" placeholder="Buscar por placa, tipo..." oninput="load()"></div>
  <select id="fv" onchange="load()" style="max-width:180px">
    <option value="">Todos los vehículos</option>
    <?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'])?></option><?php endforeach; ?>
  </select>
  <select id="fest" onchange="load()" style="max-width:140px">
    <option value="">Todos los estados</option>
    <option>Pendiente</option><option>En proceso</option><option>Completado</option><option>Cancelado</option>
  </select>
  <select id="ftipo" onchange="load()" style="max-width:140px">
    <option value="">Todos los tipos</option>
    <?php foreach($tiposMantenimiento as $tm): ?><option value="<?=htmlspecialchars($tm['nombre'])?>"><?=htmlspecialchars($tm['nombre'])?></option><?php endforeach; ?>
  </select>
  <select id="fprov" onchange="load()" style="max-width:160px">
    <option value="">Todos los talleres</option>
    <?php foreach($proveedores as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option><?php endforeach; ?>
  </select>
  <?php if(can('create')): ?><button class="btn btn-primary" onclick="abrirNuevo()">+ Nueva OT</button><?php endif; ?>
  <button class="btn btn-ghost" id="btnPendApp" onclick="verPendientes()" style="display:none" title="Aprobaciones pendientes">⚠️ <span id="pendAppCount">0</span> pendientes</button>
</div>
<div class="toolbar" style="padding-top:0;gap:8px;flex-wrap:wrap">
  <label style="font-size:12px;color:#8892a4;display:flex;align-items:center;gap:4px">Desde <input type="date" id="ffrom" onchange="load()" style="max-width:140px"></label>
  <label style="font-size:12px;color:#8892a4;display:flex;align-items:center;gap:4px">Hasta <input type="date" id="fto" onchange="load()" style="max-width:140px"></label>
  <label style="font-size:12px;color:#8892a4;display:flex;align-items:center;gap:4px">Costo mín <input type="number" id="fcmin" step="0.01" min="0" oninput="load()" placeholder="0" style="max-width:100px"></label>
  <label style="font-size:12px;color:#8892a4;display:flex;align-items:center;gap:4px">Costo máx <input type="number" id="fcmax" step="0.01" min="0" oninput="load()" placeholder="∞" style="max-width:100px"></label>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Fecha</th><th>Vehículo</th><th>Tipo</th><th>Descripción</th><th>Costo</th><th>Items</th><th>KM</th><th>Proveedor</th><th>Estado</th><th>Aprob.</th><?php if(can('edit')): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody id="tbody"></tbody></table><div id="pgr"></div>
</div>

<!-- ═══ MODAL OT ═══ -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div class="modal-title" id="mtitle">🔧 Nueva Orden de Trabajo</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Fecha *</label><input name="fecha" type="date"></div>
      <div class="form-group"><label>Vehículo *</label><select name="vehiculo_id"><option value="">— Seleccionar —</option><?php foreach($vehiculos as $v): ?><option value="<?=$v['id']?>"><?=htmlspecialchars($v['placa'].' '.$v['marca'].' '.$v['modelo'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Tipo</label><select name="tipo">
        <?php foreach($tiposMantenimiento as $tm): ?><option value="<?=htmlspecialchars($tm['nombre'])?>"><?=htmlspecialchars($tm['nombre'])?></option><?php endforeach; ?>
        <?php if(empty($tiposMantenimiento)): ?><option>Preventivo</option><option>Correctivo</option><?php endif; ?>
      </select></div>
      <div class="form-group"><label>Costo total ($)</label><input name="costo" type="number" step="0.01" placeholder="0.00" id="inputCostoOT" readonly title="Se calcula desde las partidas"></div>
      <div class="form-group"><label>KM al momento</label><input name="km" type="number" step="0.1" placeholder="46500"></div>
      <div class="form-group"><label>Próximo servicio (km)</label><input name="proximo_km" type="number" placeholder="56500"></div>
      <div class="form-group"><label>Proveedor / Taller</label><select name="proveedor_id"><option value="">— Ninguno —</option><?php foreach($proveedores as $p): ?><option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Estado</label><select name="estado" id="selEstadoOT"><option>Pendiente</option><option>En proceso</option><option>Completado</option><option>Cancelado</option></select></div>
      <div class="form-group" id="grpExitKm" style="display:none"><label>KM Salida *</label><input name="exit_km" type="number" step="0.1" placeholder="KM al salir del taller"></div>
      <div class="form-group full" id="grpResumen" style="display:none"><label>Resumen de trabajo *</label><textarea name="resumen" placeholder="Describa los trabajos realizados..."></textarea></div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Detalles del servicio..."></textarea></div>
      <div class="form-group full" id="att-mant-wrap"></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal')">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
  </div>
</div>

<!-- ═══ MODAL PARTIDAS ═══ -->
<div class="modal-bg" id="modalItems">
  <div class="modal" style="max-width:850px">
    <div class="modal-title" id="mtitleItems">📋 Partidas de OT #<span id="itemsOTId"></span></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div><strong style="color:#e8ff47">Total: $<span id="itemsTotal">0.00</span></strong></div>
      <?php if(can('create')): ?><button class="btn btn-primary btn-sm" id="btnAddItem" onclick="abrirNuevoItem()">+ Agregar partida</button><?php endif; ?>
    </div>
    <div class="table-wrap" style="max-height:400px;overflow-y:auto">
      <table><thead><tr><th>Descripción</th><th>Cant.</th><th>Unidad</th><th>P.Unit.</th><th>Subtotal</th><th>Notas</th><?php if(can('edit')): ?><th>Acc.</th><?php endif; ?></tr></thead>
      <tbody id="tbodyItems"></tbody></table>
    </div>
    <div id="att-items-wrap" style="margin-top:12px"></div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalItems')">Cerrar</button></div>
  </div>
</div>

<!-- ═══ MODAL NUEVA PARTIDA ═══ -->
<div class="modal-bg" id="modalItem">
  <div class="modal" style="max-width:600px">
    <div class="modal-title" id="mtitleItem">➕ Nueva Partida</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group full"><label>Descripción *</label><input name="descripcion" placeholder="Aceite de motor 5W-30"></div>
      <div class="form-group"><label>Componente</label><select name="component_id" id="selComponentItem"><option value="">— Sin componente —</option></select></div>
      <div class="form-group"><label>Cantidad</label><input name="cantidad" type="number" step="0.01" min="0.01" value="1"></div>
      <div class="form-group"><label>Unidad</label><select name="unidad">
        <?php foreach($unidades as $u): ?><option value="<?=htmlspecialchars($u['clave'])?>"><?=htmlspecialchars($u['nombre'])?> (<?=htmlspecialchars($u['clave'])?>)</option><?php endforeach; ?>
        <?php if(empty($unidades)): ?><option value="PZA">Pieza</option><option value="L">Litros</option><option value="SERV">Servicio</option><?php endif; ?>
      </select></div>
      <div class="form-group"><label>Precio unitario ($)</label><input name="precio_unitario" type="number" step="0.01" min="0" value="0"></div>
      <div class="form-group"><label>Subtotal</label><input id="previewSubtotal" readonly disabled></div>
      <div class="form-group full"><label>Notas</label><textarea name="notas" placeholder="Opcional..."></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalItem')">Cancelar</button><button class="btn btn-primary" onclick="guardarItem()">Guardar</button></div>
  </div>
</div>

<script>
let currentMantId = null;
let currentMantEstado = null;
const pager=new Paginator('pgr',load,25);
const EB={'Completado':'badge-green','En proceso':'badge-orange','Pendiente':'badge-blue','Cancelado':'badge-red'};
const attMant = new AttachmentWidget('att-mant-wrap', 'mantenimientos');

async function load(){
  const q=document.getElementById('s').value,vid=document.getElementById('fv').value,est=document.getElementById('fest').value;
  const tipo=document.getElementById('ftipo').value,prov=document.getElementById('fprov').value;
  const from=document.getElementById('ffrom').value,to=document.getElementById('fto').value;
  const cmin=document.getElementById('fcmin').value,cmax=document.getElementById('fcmax').value;
  let url=`/api/mantenimientos.php?q=${encodeURIComponent(q)}&vehiculo_id=${vid}&estado=${encodeURIComponent(est)}&page=${pager.page}&per=${pager.perPage}`;
  if(tipo)url+=`&tipo=${encodeURIComponent(tipo)}`;
  if(prov)url+=`&proveedor_id=${prov}`;
  if(from)url+=`&from=${from}`;
  if(to)url+=`&to=${to}`;
  if(cmin)url+=`&costo_min=${cmin}`;
  if(cmax)url+=`&costo_max=${cmax}`;
  const data=await api(url);
  pager.setTotal(data.total);
  const tbody=document.getElementById('tbody');
  if(!data.rows.length){tbody.innerHTML=`<tr><td colspan="11"><div class="empty"><div class="empty-icon">🔧</div><div class="empty-title">Sin mantenimientos</div></div></td></tr>`;return;}
  const AB={'aprobada':'badge-green','pendiente':'badge-orange','rechazada':'badge-red','no_requerida':'badge-gray'};
  tbody.innerHTML=data.rows.map(r=>{
    const apEst = r.aprobacion_estado || 'no_requerida';
    const apBadge = `<span class="badge ${AB[apEst]||'badge-gray'}">${apEst.replace('_',' ')}</span>`;
    const approveBtn = apEst === 'pendiente' ? `<button class="btn btn-ghost btn-sm" onclick="aprobar(${r.id})" title="Aprobar/Rechazar">⚖️</button>` : '';
    return `<tr>
    <td>${r.fecha}</td>
    <td><strong style="color:var(--accent2)">${r.placa||''} ${r.marca||''}</strong></td>
    <td><span class="badge badge-yellow">${r.tipo}</span></td>
    <td class="td-truncate">${r.descripcion||'—'}</td>
    <td><strong style="color:var(--green)">L ${Number(r.costo).toFixed(2)}</strong></td>
    <td><button class="btn btn-ghost btn-sm" onclick="verItems(${r.id},'${r.estado}')" title="Ver partidas">📋 ${r.items_count||0}</button></td>
    <td>${r.km?Number(r.km).toLocaleString()+' km':'—'}</td>
    <td>${r.proveedor_nombre||'—'}</td>
    <td><span class="badge ${EB[r.estado]||'badge-gray'}">${r.estado}</span></td>
    <td>${apBadge}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      ${approveBtn}
      <button class="btn btn-ghost btn-sm" onclick="window.open('/print.php?type=mantenimiento&id=${r.id}','_blank')" title="Imprimir PDF">🖨️</button>
      <button class="btn btn-ghost btn-sm" onclick='editar(${JSON.stringify(r)})'>✏️</button>
      <?php if(can('delete')): ?><button class="btn btn-danger btn-sm" onclick="del(${r.id})">🗑️</button><?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`;}).join('');
}

function abrirNuevo(){
  document.getElementById('mtitle').textContent='🔧 Nueva Orden de Trabajo';
  document.getElementById('inputCostoOT').removeAttribute('readonly');
  resetForm('modal');openModal('modal');
  attMant.reset();
  toggleCierreFields();
}
function editar(r){
  document.getElementById('mtitle').textContent='✏️ Editar OT #'+r.id;
  document.getElementById('inputCostoOT').setAttribute('readonly','');
  fillForm('modal',{id:r.id,fecha:r.fecha,vehiculo_id:r.vehiculo_id,tipo:r.tipo,costo:r.costo,km:r.km,exit_km:r.exit_km||'',proximo_km:r.proximo_km,proveedor_id:r.proveedor_id,estado:r.estado,resumen:r.resumen||'',descripcion:r.descripcion});
  openModal('modal');
  attMant.setEntityId(r.id);
  attMant.load();
  toggleCierreFields();
  loadComponents(r.vehiculo_id);
}

function toggleCierreFields(){
  const est=document.getElementById('selEstadoOT').value;
  const show=est==='Completado';
  document.getElementById('grpExitKm').style.display=show?'':'none';
  document.getElementById('grpResumen').style.display=show?'':'none';
}
document.getElementById('selEstadoOT').addEventListener('change', toggleCierreFields);
async function guardar(){
  const d=getForm('modal');
  if(!d.vehiculo_id){toast('Selecciona un vehículo','error');return;}
  const res = await api('/api/mantenimientos.php',d.id?'PUT':'POST',d);
  const savedId = d.id || res.id;
  if (attMant.hasPending() && savedId) {
    await attMant.uploadPending(savedId);
  }
  toast(d.id?'OT actualizada':'OT registrada');
  closeModal('modal');load();
}
async function del(id){confirmDelete('¿Eliminar este mantenimiento?',async()=>{await api(`/api/mantenimientos.php?id=${id}`,'DELETE');toast('Eliminado','warning');load();});}

/* ═══ PARTIDAS ═══ */
async function verItems(mantId, mantEstado) {
  currentMantId = mantId;
  currentMantEstado = mantEstado || '';
  document.getElementById('itemsOTId').textContent = mantId;
  // Hide add button if OT is completed
  const addBtn = document.getElementById('btnAddItem');
  if (addBtn) addBtn.style.display = (currentMantEstado === 'Completado') ? 'none' : '';
  openModal('modalItems');
  await loadItems();
  // Load attachments for this OT
  const attItems = new AttachmentWidget('att-items-wrap', 'mantenimientos', mantId);
  attItems.load();
}

async function loadItems() {
  if (!currentMantId) return;
  const data = await api(`/api/mantenimientos.php?action=items&mantenimiento_id=${currentMantId}`);
  document.getElementById('itemsTotal').textContent = Number(data.total_items).toFixed(2);
  const tbody = document.getElementById('tbodyItems');
  if (!data.items.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-icon">📋</div><div class="empty-title">Sin partidas aún</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.items.map(i => `<tr>
    <td>${i.descripcion}</td>
    <td>${Number(i.cantidad).toFixed(2)}</td>
    <td>${i.unidad}</td>
    <td>L ${Number(i.precio_unitario).toFixed(2)}</td>
    <td><strong style="color:var(--green)">L ${Number(i.subtotal).toFixed(2)}</strong></td>
    <td class="td-truncate">${i.notas||'—'}</td>
    <?php if(can('edit')): ?><td><div class="action-btns">
      ${currentMantEstado !== 'Completado' ? `<button class="btn btn-ghost btn-sm" onclick='editarItem(${JSON.stringify(i)})'>✏️</button>` : ''}
      <?php if(can('delete')): ?>${currentMantEstado !== 'Completado' ? `<button class="btn btn-danger btn-sm" onclick="delItem(${i.id})">🗑️</button>` : ''} <?php endif; ?>
    </div></td><?php endif; ?>
  </tr>`).join('');
}

function abrirNuevoItem() {
  document.getElementById('mtitleItem').textContent = '➕ Nueva Partida';
  resetForm('modalItem');
  openModal('modalItem');
}

function editarItem(i) {
  document.getElementById('mtitleItem').textContent = '✏️ Editar Partida';
  fillForm('modalItem', {id: i.id, descripcion: i.descripcion, cantidad: i.cantidad, unidad: i.unidad, precio_unitario: i.precio_unitario, notas: i.notas});
  openModal('modalItem');
  calcSubtotal();
}

async function guardarItem() {
  const d = getForm('modalItem');
  if (!d.descripcion) { toast('La descripción es obligatoria', 'error'); return; }
  await api(`/api/mantenimientos.php?action=items&mantenimiento_id=${currentMantId}`, d.id ? 'PUT' : 'POST', d);
  toast(d.id ? 'Partida actualizada' : 'Partida agregada');
  closeModal('modalItem');
  loadItems();
  load(); // refrescar tabla principal con nuevo costo
}

async function delItem(id) {
  confirmDelete('¿Eliminar esta partida?', async () => {
    await api(`/api/mantenimientos.php?action=items&mantenimiento_id=${currentMantId}&item_id=${id}`, 'DELETE');
    toast('Partida eliminada', 'warning');
    loadItems();
    load();
  });
}

// Preview subtotal en modal de partida
function calcSubtotal() {
  const form = document.getElementById('modalItem');
  const cant = parseFloat(form.querySelector('[name="cantidad"]').value) || 0;
  const pu   = parseFloat(form.querySelector('[name="precio_unitario"]').value) || 0;
  document.getElementById('previewSubtotal').value = 'L ' + (cant * pu).toFixed(2);
}
document.getElementById('modalItem').querySelector('[name="cantidad"]')?.addEventListener('input', calcSubtotal);
document.getElementById('modalItem').querySelector('[name="precio_unitario"]')?.addEventListener('input', calcSubtotal);

// ═══ Aprobaciones ═══
async function checkPendingApprovals() {
  try {
    const data = await api('/api/mantenimientos.php?action=pending_approvals');
    const rows = data.rows || [];
    const btn = document.getElementById('btnPendApp');
    const cnt = document.getElementById('pendAppCount');
    if (btn && cnt) {
      cnt.textContent = rows.length;
      btn.style.display = rows.length > 0 ? '' : 'none';
    }
  } catch(e) {}
}

async function verPendientes() {
  try {
    const data = await api('/api/mantenimientos.php?action=pending_approvals');
    const rows = data.rows || [];
    if (!rows.length) { toast('No hay aprobaciones pendientes'); return; }
    const list = rows.map(r => `OT #${r.id} — ${r.placa} — ${r.tipo} — L ${Number(r.costo).toFixed(2)}`).join('\n');
    alert('Aprobaciones pendientes:\n\n' + list);
  } catch(e) { toast('Error al cargar pendientes','error'); }
}

async function aprobar(mantId) {
  const decision = prompt('Escriba "aprobar" para aprobar o "rechazar" para rechazar la OT #' + mantId);
  if (!decision) return;
  const dec = decision.trim().toLowerCase();
  if (dec !== 'aprobar' && dec !== 'rechazar') { toast('Escriba "aprobar" o "rechazar"','error'); return; }
  const comentario = prompt('Comentario (opcional):') || '';
  try {
    await api('/api/mantenimientos.php?action=aprobaciones', 'POST', {
      mantenimiento_id: mantId,
      decision: dec === 'aprobar' ? 'aprobado' : 'rechazado',
      comentario: comentario
    });
    toast(dec === 'aprobar' ? 'OT aprobada' : 'OT rechazada');
    load();
    checkPendingApprovals();
  } catch(e) { toast('Error: ' + (e.message || 'Error desconocido'),'error'); }
}

// ═══ Componentes para items ═══
async function loadComponents(vehiculoId) {
  const sel = document.getElementById('selComponentItem');
  if (!sel) return;
  sel.innerHTML = '<option value="">— Sin componente —</option>';
  if (!vehiculoId) return;
  try {
    const data = await api(`/api/componentes.php?vehiculo_id=${vehiculoId}`);
    (data.rows || []).forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${c.tipo}: ${c.marca||''} ${c.modelo||''} ${c.numero_serie||''}</option>`;
    });
  } catch(e) {}
}

document.addEventListener('DOMContentLoaded', () => { load(); checkPendingApprovals(); });
</script>
<?php $content=ob_get_clean(); echo render_layout('Mantenimientos / OT','mantenimientos',$content); ?>
