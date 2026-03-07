// ═══════════════════════════════════════════════
// FlotaControl — app.js
// ═══════════════════════════════════════════════

// ── TOAST ──────────────────────────────────────
function toast(msg, type = 'success') {
  const icons = { success: '✅', error: '❌', warning: '⚠️' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// ── API HELPER ─────────────────────────────────
async function api(url, method = 'GET', data = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  };
  if (data && method !== 'GET') opts.body = JSON.stringify(data);
  try {
    const res = await fetch(url, opts);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Error en la petición');
    return json;
  } catch (e) {
    toast(e.message, 'error');
    throw e;
  }
}

// ── MODAL ──────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); const f = m.querySelector('input:not([type=hidden])'); if(f) setTimeout(()=>f.focus(),100); }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}
// Close on backdrop
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-bg')) e.target.classList.remove('open');
});
// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
});

// ── FORM HELPERS ───────────────────────────────
function getForm(modalId) {
  const data = {};
  document.querySelectorAll(`#${modalId} [name]`).forEach(el => {
    data[el.name] = el.value.trim();
  });
  return data;
}
function fillForm(modalId, data) {
  Object.entries(data).forEach(([k, v]) => {
    const el = document.querySelector(`#${modalId} [name="${k}"]`);
    if (el) el.value = v ?? '';
  });
}
function resetForm(modalId) {
  document.querySelectorAll(`#${modalId} input:not([type=hidden]):not([type=radio]):not([type=checkbox]), #${modalId} select, #${modalId} textarea`)
    .forEach(el => el.value = '');
  // Reset radio buttons to their default checked state (preserving value attributes)
  document.querySelectorAll(`#${modalId} input[type=radio]`).forEach(el => {
    el.checked = el.defaultChecked;
  });
  // Uncheck all checkboxes
  document.querySelectorAll(`#${modalId} input[type=checkbox]`).forEach(el => {
    el.checked = false;
  });
  // Set today for date inputs
  const today = new Date().toISOString().split('T')[0];
  document.querySelectorAll(`#${modalId} input[type=date]`).forEach(el => el.value = today);
}

// ── CONFIRM DELETE ─────────────────────────────
function confirmDelete(msg, cb) {
  if (confirm(msg || '¿Estás seguro de eliminar este registro?')) cb();
}

// ── DEBOUNCE ───────────────────────────────────
function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// ── BAR CHART ──────────────────────────────────
function renderBarChart(containerId, data, { unit = '', color = '#e8ff47', emptyText = 'Sin datos' } = {}) {
  const el = document.getElementById(containerId);
  if (!el) return;
  if (!data || !data.length) {
    el.innerHTML = `<div class="empty" style="padding:16px"><div class="empty-icon" style="font-size:28px">📉</div><div style="font-size:13px">${emptyText}</div></div>`;
    return;
  }
  const max = Math.max(...data.map(d => d.value), 1);
  el.innerHTML = data.map(d => `
    <div class="bar-row">
      <div class="bar-label" title="${d.label}">${d.label}</div>
      <div class="bar-track"><div class="bar-fill" style="width:${(d.value/max*100).toFixed(1)}%;background:${color}"></div></div>
      <div class="bar-val">${unit === '$' ? '$' + Number(d.value).toFixed(0) : Number(d.value).toFixed(1) + (unit?' '+unit:'')}</div>
    </div>`).join('');
}

// ── ATTACHMENTS WIDGET ─────────────────────────
/**
 * Widget reutilizable de adjuntos.
 * Uso: new AttachmentWidget(containerId, entidad, entidadId)
 *  - .load()           → carga adjuntos existentes
 *  - .setEntityId(id)  → cambia entidad_id (al crear un registro nuevo)
 *  - .reset()          → limpia la lista
 */
class AttachmentWidget {
  constructor(containerId, entidad, entidadId = 0) {
    this.container = document.getElementById(containerId);
    this.entidad = entidad;
    this.entidadId = entidadId;
    this.attachments = [];
    this.pendingFiles = []; // files to upload on save
    if (this.container) this.render();
  }

  setEntityId(id) { this.entidadId = id; }

  reset() {
    this.entidadId = 0;
    this.attachments = [];
    this.pendingFiles = [];
    if (this.container) this.render();
  }

  async load() {
    if (!this.entidadId) { this.render(); return; }
    try {
      const res = await fetch(`/api/attachments.php?entidad=${this.entidad}&entidad_id=${this.entidadId}`);
      if (res.ok) {
        const d = await res.json();
        this.attachments = d.attachments || [];
      }
    } catch(e) {}
    this.render();
  }

  render() {
    if (!this.container) return;
    const isImg = (m) => m && m.startsWith('image/');
    const fmtSize = (b) => b > 1048576 ? (b/1048576).toFixed(1)+' MB' : (b/1024).toFixed(0)+' KB';

    let html = `<div class="att-widget">
      <div class="att-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <span style="font-size:13px;font-weight:600;color:var(--accent2)">📎 Adjuntos (${this.attachments.length})</span>
        <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0">
          + Archivo <input type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="window._attWidget_${this.container.id}.onFiles(this.files)">
        </label>
      </div>`;

    if (this.pendingFiles.length) {
      html += `<div style="margin-bottom:6px;font-size:11px;color:var(--accent)">⏳ Pendientes de subir: ${this.pendingFiles.length} archivo(s)</div>`;
      html += this.pendingFiles.map((f,i) => `<div class="att-item pending" style="display:flex;align-items:center;gap:8px;padding:4px 8px;background:rgba(232,255,71,.08);border-radius:6px;margin-bottom:4px">
        <span style="font-size:12px">${isImg(f.type)?'🖼️':'📄'}</span>
        <span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${f.name}</span>
        <span style="font-size:11px;color:var(--text2)">${fmtSize(f.size)}</span>
        <button class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px" onclick="window._attWidget_${this.container.id}.removePending(${i})">✕</button>
      </div>`).join('');
    }

    if (this.attachments.length) {
      html += this.attachments.map(a => `<div class="att-item" style="display:flex;align-items:center;gap:8px;padding:4px 8px;border-radius:6px;margin-bottom:4px;background:rgba(255,255,255,.04)">
        <span style="font-size:12px">${isImg(a.mime_type)?'🖼️':'📄'}</span>
        <a href="/api/attachments.php?action=download&id=${a.id}" target="_blank" style="flex:1;font-size:12px;color:var(--accent2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${a.original_name}">${a.original_name}</a>
        <span style="font-size:11px;color:var(--text2)">${fmtSize(a.size_bytes)}</span>
        <button class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px;color:var(--danger)" onclick="window._attWidget_${this.container.id}.deleteAtt(${a.id})" title="Eliminar">🗑️</button>
      </div>`).join('');
    } else if (!this.pendingFiles.length) {
      html += `<div style="font-size:12px;color:var(--text2);padding:8px;text-align:center">Sin adjuntos</div>`;
    }

    html += '</div>';
    this.container.innerHTML = html;
    window[`_attWidget_${this.container.id}`] = this;
  }

  onFiles(fileList) {
    for (const f of fileList) this.pendingFiles.push(f);
    this.render();
  }

  removePending(idx) {
    this.pendingFiles.splice(idx, 1);
    this.render();
  }

  async deleteAtt(id) {
    if (!confirm('¿Eliminar este adjunto?')) return;
    try {
      await fetch(`/api/attachments.php?id=${id}`, {method:'DELETE'});
      this.attachments = this.attachments.filter(a => a.id !== id);
      this.render();
      toast('Adjunto eliminado', 'warning');
    } catch(e) { toast('Error al eliminar adjunto', 'error'); }
  }

  /** Upload all pending files. Call after saving the parent entity. */
  async uploadPending(entidadId) {
    if (entidadId) this.entidadId = entidadId;
    if (!this.pendingFiles.length || !this.entidadId) return;
    const fd = new FormData();
    fd.append('entidad', this.entidad);
    fd.append('entidad_id', this.entidadId);
    for (const f of this.pendingFiles) fd.append('archivo[]', f);
    try {
      const res = await fetch('/api/attachments.php', {method:'POST', body: fd});
      if (!res.ok) throw new Error('Error al subir');
      const d = await res.json();
      this.pendingFiles = [];
      toast(`${d.uploaded.length} adjunto(s) subido(s)`);
      await this.load(); // refresh from server
    } catch(e) { toast('Error al subir adjuntos', 'error'); }
  }

  hasPending() { return this.pendingFiles.length > 0; }
  count() { return this.attachments.length + this.pendingFiles.length; }
}

// ── CALC COMBUSTIBLE TOTAL ─────────────────────
function calcCombTotal() {
  const l = parseFloat(document.querySelector('[name="litros"]')?.value) || 0;
  const c = parseFloat(document.querySelector('[name="costo_litro"]')?.value) || 0;
  const t = document.querySelector('[name="total"]');
  if (t) t.value = (l * c).toFixed(2);
}

// ── PAGINATION HELPER ──────────────────────────
class Paginator {
  constructor(containerId, renderFn, perPage = 25) {
    this.container = document.getElementById(containerId);
    this.renderFn = renderFn;
    this.perPage = perPage;
    this.page = 1;
    this.total = 0;
  }
  setTotal(n) {
    this.total = n;
    this.renderPager();
  }
  renderPager() {
    const pages = Math.ceil(this.total / this.perPage);
    if (!this.container || pages <= 1) { if(this.container) this.container.innerHTML = ''; return; }
    let html = `<div style="display:flex;gap:6px;align-items:center;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--border)">`;
    html += `<span style="font-size:12px;color:var(--text2);margin-right:8px">${this.total} registros</span>`;
    for (let i = 1; i <= pages; i++) {
      html += `<button onclick="window._pag_${this.container.id}_page(${i})" class="btn btn-sm ${i===this.page?'btn-primary':'btn-ghost'}">${i}</button>`;
    }
    html += `</div>`;
    this.container.innerHTML = html;
    window[`_pag_${this.container.id}_page`] = (p) => { this.page = p; this.renderFn(); };
  }
}
