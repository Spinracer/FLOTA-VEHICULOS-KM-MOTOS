<?php
require_once __DIR__ . '/../../includes/layout.php';
require_login();
require_role('coordinador_it', 'admin');
ob_start();
?>
<div class="toolbar">
  <div class="search-wrap"><span class="search-icon">🔍</span>
    <input type="text" id="s" placeholder="Buscar en auditoría..." oninput="load()"></div>
  <select id="fEntidad" onchange="load()" style="max-width:180px">
    <option value="">Todas las entidades</option>
  </select>
  <select id="fAccion" onchange="load()" style="max-width:160px">
    <option value="">Todas las acciones</option>
  </select>
  <input type="date" id="fDesde" onchange="load()" title="Desde" style="max-width:160px">
  <input type="date" id="fHasta" onchange="load()" title="Hasta" style="max-width:160px">
  <div class="export-group" style="display:inline-flex;gap:4px;margin-left:auto;">
    <button class="btn btn-primary btn-sm" onclick="exportAudit('csv')" title="Exportar CSV con filtros">📥 CSV</button>
    <button class="btn btn-primary btn-sm" onclick="exportAudit('xlsx')" title="Exportar Excel con filtros" style="background:#217346;border-color:#217346;">📊 XLSX</button>
    <button class="btn btn-primary btn-sm" onclick="exportAudit('pdf')" title="Exportar PDF con filtros" style="background:#d32f2f;border-color:#d32f2f;">📄 PDF</button>
    <button class="btn btn-ghost btn-sm" onclick="exportAudit('csv',true)" title="Exportar TODO sin filtros">📦 Todo</button>
  </div>
</div>
<div class="table-wrap">
  <table><thead><tr><th>Fecha</th><th>Usuario</th><th>Rol</th><th>Entidad</th><th>ID</th><th>Acción</th><th>IP</th><th>Detalle</th></tr></thead>
  <tbody id="tbody"></tbody></table>
  <div id="pgr"></div>
</div>

<!-- MODAL DETALLE -->
<div class="modal-bg" id="modalDetalle">
  <div class="modal" style="max-width:750px">
    <div class="modal-title">🔍 Detalle de Auditoría</div>
    <div id="detalleContent" style="font-size:13px;line-height:1.5"></div>
    <div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal('modalDetalle')">Cerrar</button></div>
  </div>
</div>

<script>
const pager = new Paginator('pgr', load, 50);
const AB = {create:'badge-green',update:'badge-blue',delete:'badge-red',soft_delete:'badge-orange',estado_change:'badge-yellow',login:'badge-cyan',logout:'badge-gray',odometro_override:'badge-orange',export_csv:'badge-cyan'};

async function load() {
  const q      = document.getElementById('s').value;
  const ent    = document.getElementById('fEntidad').value;
  const acc    = document.getElementById('fAccion').value;
  const desde  = document.getElementById('fDesde').value;
  const hasta  = document.getElementById('fHasta').value;
  const url    = `/api/auditoria.php?q=${encodeURIComponent(q)}&entidad=${encodeURIComponent(ent)}&accion=${encodeURIComponent(acc)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}&page=${pager.page}&per=${pager.perPage}`;
  const data   = await api(url);
  pager.setTotal(data.total);

  // Poblar selects de filtro si están vacíos
  const selEnt = document.getElementById('fEntidad');
  if (selEnt.options.length <= 1 && data.entidades) {
    data.entidades.forEach(e => { const o = new Option(e, e); selEnt.appendChild(o); });
  }
  const selAcc = document.getElementById('fAccion');
  if (selAcc.options.length <= 1 && data.acciones) {
    data.acciones.forEach(a => { const o = new Option(a, a); selAcc.appendChild(o); });
  }

  const tbody = document.getElementById('tbody');
  if (!data.rows.length) {
    tbody.innerHTML = '<tr><td colspan="8"><div class="empty"><div class="empty-icon">📜</div><div class="empty-title">Sin registros de auditoría</div></div></td></tr>';
    return;
  }
  tbody.innerHTML = data.rows.map(r => `<tr>
    <td style="white-space:nowrap;font-size:12px">${r.created_at}</td>
    <td>${r.user_email || '—'}</td>
    <td><span class="badge badge-gray">${r.user_rol || '—'}</span></td>
    <td><strong>${r.entidad}</strong></td>
    <td>${r.entidad_id || '—'}</td>
    <td><span class="badge ${AB[r.accion]||'badge-gray'}">${r.accion}</span></td>
    <td style="font-size:11px">${r.ip || '—'}</td>
    <td><button class="btn btn-ghost btn-sm" onclick='verDetalle(${JSON.stringify(r).replace(/'/g,"&#39;")})'>👁️</button></td>
  </tr>`).join('');
}

function verDetalle(r) {
  let html = `<div style="display:grid;grid-template-columns:120px 1fr;gap:6px 12px;margin-bottom:16px">
    <strong>Fecha:</strong><span>${r.created_at}</span>
    <strong>Usuario:</strong><span>${r.user_email||'—'} (${r.user_rol||'—'})</span>
    <strong>Entidad:</strong><span>${r.entidad} #${r.entidad_id||'—'}</span>
    <strong>Acción:</strong><span>${r.accion}</span>
    <strong>IP:</strong><span>${r.ip||'—'}</span>
  </div>`;

  if (r.antes && Object.keys(r.antes).length) {
    html += `<h4 style="color:#ff4757;margin:10px 0 6px">Antes:</h4>
    <pre style="background:#181c24;padding:10px;border-radius:8px;overflow-x:auto;font-size:12px;color:#8892a4;max-height:200px">${JSON.stringify(r.antes, null, 2)}</pre>`;
  }
  if (r.despues && Object.keys(r.despues).length) {
    html += `<h4 style="color:#2ed573;margin:10px 0 6px">Después:</h4>
    <pre style="background:#181c24;padding:10px;border-radius:8px;overflow-x:auto;font-size:12px;color:#8892a4;max-height:200px">${JSON.stringify(r.despues, null, 2)}</pre>`;
  }
  if (r.meta && Object.keys(r.meta).length) {
    html += `<h4 style="color:#e8ff47;margin:10px 0 6px">Metadata:</h4>
    <pre style="background:#181c24;padding:10px;border-radius:8px;overflow-x:auto;font-size:12px;color:#8892a4">${JSON.stringify(r.meta, null, 2)}</pre>`;
  }

  document.getElementById('detalleContent').innerHTML = html;
  openModal('modalDetalle');
}

function exportAudit(format, all) {
  let qs = `format=${format}`;
  if (!all) {
    const q     = document.getElementById('s').value;
    const ent   = document.getElementById('fEntidad').value;
    const acc   = document.getElementById('fAccion').value;
    const desde = document.getElementById('fDesde').value;
    const hasta = document.getElementById('fHasta').value;
    if (q)     qs += `&q=${encodeURIComponent(q)}`;
    if (ent)   qs += `&entidad=${encodeURIComponent(ent)}`;
    if (acc)   qs += `&accion=${encodeURIComponent(acc)}`;
    if (desde) qs += `&desde=${encodeURIComponent(desde)}`;
    if (hasta) qs += `&hasta=${encodeURIComponent(hasta)}`;
  }
  qs += '&export=1';
  if (format === 'pdf') {
    window.open(`/api/auditoria.php?${qs}`, '_blank');
  } else {
    window.location.href = `/api/auditoria.php?${qs}`;
  }
}

document.addEventListener('DOMContentLoaded', load);
</script>
<?php $content = ob_get_clean(); echo render_layout('Auditoría', 'auditoria', $content); ?>
