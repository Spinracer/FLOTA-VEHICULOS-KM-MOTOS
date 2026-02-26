<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
require_admin();
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span><input type="text" id="s" placeholder="Buscar en catálogo..." oninput="loadItems()"></div>
  <select id="catalog" onchange="loadItems()" style="max-width:280px"></select>
  <button class="btn btn-primary" onclick="openNew()">+ Nuevo registro</button>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th id="th-clave" style="display:none">Clave</th>
        <th>Nombre</th>
        <th>Descripción</th>
        <th>Activo</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
</div>

<div class="section-title" style="margin-top:18px">⚙️ Configuración global</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Clave</th><th>Valor texto</th><th>Valor numérico</th><th>Descripción</th><th>Acciones</th></tr>
    </thead>
    <tbody id="tbody-settings"></tbody>
  </table>
</div>

<div class="modal-bg" id="modal-cat">
  <div class="modal">
    <div class="modal-title" id="mt">🗂️ Nuevo registro de catálogo</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group" id="fg-clave" style="display:none"><label>Clave</label><input name="clave" placeholder="L, GAL, PZA..."></div>
      <div class="form-group"><label>Nombre *</label><input name="nombre" placeholder="Nombre"></div>
      <div class="form-group full"><label>Descripción</label><textarea name="descripcion" placeholder="Descripción opcional"></textarea></div>
      <div class="form-group"><label>Activo</label><select name="activo"><option value="1">Sí</option><option value="0">No</option></select></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-cat')">Cancelar</button><button class="btn btn-primary" onclick="saveItem()">Guardar</button></div>
  </div>
</div>

<div class="modal-bg" id="modal-setting">
  <div class="modal">
    <div class="modal-title" id="mts">⚙️ Configuración</div>
    <div class="form-grid">
      <input type="hidden" name="id">
      <div class="form-group"><label>Clave *</label><input name="key_name" placeholder="fuel.anomaly_threshold"></div>
      <div class="form-group"><label>Valor texto</label><input name="value_text" placeholder="texto opcional"></div>
      <div class="form-group"><label>Valor numérico</label><input name="value_num" type="number" step="0.01" placeholder="0"></div>
      <div class="form-group full"><label>Descripción</label><textarea name="description" placeholder="Descripción del parámetro"></textarea></div>
    </div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modal-setting')">Cancelar</button><button class="btn btn-primary" onclick="saveSetting()">Guardar</button></div>
  </div>
</div>

<script>
let catalogs = [];

function currentCatalog(){
  return document.getElementById('catalog').value;
}

function toggleCatalogFields(){
  const isUnits = currentCatalog() === 'unidades';
  document.getElementById('fg-clave').style.display = isUnits ? '' : 'none';
  document.getElementById('th-clave').style.display = isUnits ? '' : 'none';
}

async function loadCatalogs(){
  const data = await api('/api/catalogos.php?type=catalogs');
  catalogs = data.rows;
  const sel = document.getElementById('catalog');
  sel.innerHTML = catalogs.map(c => `<option value="${c.key}">${c.label}</option>`).join('');
  toggleCatalogFields();
}

async function loadItems(){
  toggleCatalogFields();
  const q = document.getElementById('s').value;
  const cat = currentCatalog();
  const data = await api(`/api/catalogos.php?type=items&catalog=${encodeURIComponent(cat)}&q=${encodeURIComponent(q)}`);
  const tbody = document.getElementById('tbody');
  const isUnits = cat === 'unidades';

  if(!data.rows.length){
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty"><div class="empty-icon">🗂️</div><div class="empty-title">Sin registros</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td>${r.id}</td>
      <td style="display:${isUnits?'':'none'}">${r.clave || '—'}</td>
      <td>${r.nombre || '—'}</td>
      <td class="td-truncate">${r.descripcion || '—'}</td>
      <td><span class="badge ${String(r.activo)==='1'?'badge-green':'badge-gray'}">${String(r.activo)==='1'?'Sí':'No'}</span></td>
      <td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" onclick='editItem(${JSON.stringify(r)})'>✏️</button>
        <button class="btn btn-danger btn-sm" onclick="delItem(${r.id})">🗑️</button>
      </div></td>
    </tr>`).join('');
}

function openNew(){
  document.getElementById('mt').textContent = '🗂️ Nuevo registro de catálogo';
  resetForm('modal-cat');
  document.querySelector('#modal-cat [name=activo]').value = '1';
  toggleCatalogFields();
  openModal('modal-cat');
}

function editItem(r){
  document.getElementById('mt').textContent = '✏️ Editar registro de catálogo';
  fillForm('modal-cat', {
    id: r.id,
    clave: r.clave,
    nombre: r.nombre,
    descripcion: r.descripcion,
    activo: r.activo
  });
  toggleCatalogFields();
  openModal('modal-cat');
}

async function saveItem(){
  const d = getForm('modal-cat');
  const payload = { ...d, type:'item', catalog: currentCatalog() };
  if(!payload.nombre){ toast('El nombre es obligatorio','error'); return; }
  await api('/api/catalogos.php', d.id ? 'PUT' : 'POST', payload);
  toast(d.id ? 'Registro actualizado' : 'Registro creado');
  closeModal('modal-cat');
  loadItems();
}

async function delItem(id){
  confirmDelete('¿Eliminar este registro de catálogo?', async () => {
    await api(`/api/catalogos.php?catalog=${encodeURIComponent(currentCatalog())}&id=${id}`, 'DELETE');
    toast('Registro eliminado','warning');
    loadItems();
  });
}

async function loadSettings(){
  const data = await api('/api/catalogos.php?type=settings');
  const tbody = document.getElementById('tbody-settings');
  if(!data.rows.length){
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty"><div class="empty-icon">⚙️</div><div class="empty-title">Sin parámetros</div></div></td></tr>`;
    return;
  }
  tbody.innerHTML = data.rows.map(r => `
    <tr>
      <td>${r.key_name}</td>
      <td>${r.value_text || '—'}</td>
      <td>${r.value_num ?? '—'}</td>
      <td class="td-truncate">${r.description || '—'}</td>
      <td><button class="btn btn-ghost btn-sm" onclick='editSetting(${JSON.stringify(r)})'>✏️</button></td>
    </tr>`).join('');
}

function newSetting(){
  document.getElementById('mts').textContent = '⚙️ Nueva configuración';
  resetForm('modal-setting');
  openModal('modal-setting');
}

function editSetting(r){
  document.getElementById('mts').textContent = '✏️ Editar configuración';
  fillForm('modal-setting', r);
  openModal('modal-setting');
}

async function saveSetting(){
  const d = getForm('modal-setting');
  if(!d.key_name){ toast('La clave es obligatoria','error'); return; }
  await api('/api/catalogos.php', 'PUT', { ...d, type:'setting' });
  toast('Configuración guardada');
  closeModal('modal-setting');
  loadSettings();
}

document.addEventListener('DOMContentLoaded', async () => {
  await loadCatalogs();
  await loadItems();
  await loadSettings();
  const topbar = document.getElementById('topbar-actions');
  if(topbar){
    const b = document.createElement('button');
    b.className = 'btn btn-primary';
    b.textContent = '+ Parámetro';
    b.onclick = newSetting;
    topbar.appendChild(b);
  }
});
</script>

<?php
$content = ob_get_clean();
echo render_layout('Catálogos y Configuración', 'catalogos', $content);
