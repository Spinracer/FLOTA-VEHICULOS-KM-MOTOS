// ═══════════════════════════════════════════════
// FlotaControl — app.js
// ═══════════════════════════════════════════════

// ── TOAST ──────────────────────────────────────
function toast(msg, type = 'success') {
  const icons = { success: '✅', error: '❌', warning: '⚠️' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  const iconSpan = document.createElement('span');
  iconSpan.textContent = icons[type]||'ℹ️';
  const msgSpan = document.createElement('span');
  msgSpan.textContent = msg;
  el.appendChild(iconSpan);
  el.appendChild(msgSpan);
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// ── API HELPER ─────────────────────────────────
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

async function api(url, method = 'GET', data = null) {
  const opts = {
    method,
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': getCsrfToken()
    }
  };
  if (data && method !== 'GET') opts.body = JSON.stringify(data);
  try {
    const res = await fetch(url, opts);
    if (res.status === 401) { window.location.href = '/index.php'; return; }
    const text = await res.text();
    let json;
    try {
      json = text ? JSON.parse(text) : {};
    } catch (parseErr) {
      console.error('API response not JSON:', text.substring(0, 500));
      throw new Error(res.ok ? 'Respuesta inválida del servidor' : `Error ${res.status}: ${text.substring(0, 200)}`);
    }
    if (!res.ok) throw new Error(json.error || `Error ${res.status}`);
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
    // For radio buttons, only capture the checked one
    if (el.type === 'radio') {
      if (el.checked) data[el.name] = el.value.trim();
      else if (!(el.name in data)) data[el.name] = '';
      return;
    }
    // For checkboxes, capture checked state
    if (el.type === 'checkbox') {
      data[el.name] = el.checked ? (el.value || '1') : '0';
      return;
    }
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
      const res = await fetch(`/api/attachments.php?entidad=${this.entidad}&entidad_id=${this.entidadId}`, {credentials:'include'});
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
          + Archivo <input type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" style="display:none" onchange="window['_attWidget_${this.container.id}'].onFiles(this.files)">
        </label>
      </div>`;

    if (this.pendingFiles.length) {
      html += `<div style="margin-bottom:6px;font-size:11px;color:var(--accent)">⏳ Pendientes de subir: ${this.pendingFiles.length} archivo(s)</div>`;
      html += `<div class="att-pending-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:6px;margin-bottom:6px">`;
      html += this.pendingFiles.map((f,i) => {
        const preview = isImg(f.type) ? `<img src="${URL.createObjectURL(f)}" style="width:100%;height:60px;object-fit:cover;display:block;border-radius:4px 4px 0 0" alt="">` : `<div style="height:60px;display:flex;align-items:center;justify-content:center;font-size:24px">📄</div>`;
        return `<div style="border-radius:6px;overflow:hidden;background:rgba(232,255,71,.08);border:1px solid var(--accent)">
          ${preview}
          <div style="display:flex;align-items:center;gap:4px;padding:2px 4px">
            <span style="flex:1;font-size:9px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${f.name}</span>
            <button class="btn btn-ghost btn-sm" style="padding:1px 4px;font-size:10px" onclick="window['_attWidget_${this.container.id}'].removePending(${i})">✕</button>
          </div>
        </div>`;
      }).join('');
      html += `</div>`;
    }

    if (this.attachments.length) {
      html += `<div class="att-gallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:6px;margin-bottom:6px">`;
      html += this.attachments.map(a => {
        const url = `/api/attachments.php?action=download&id=${a.id}`;
        if (isImg(a.mime_type)) {
          return `<div style="position:relative;border-radius:6px;overflow:hidden;background:var(--bg2);border:1px solid var(--border)">
            <a href="${url}" target="_blank"><img src="${url}" alt="${a.original_name}" style="width:100%;height:80px;object-fit:cover;display:block" loading="lazy"></a>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:2px 4px">
              <span style="font-size:9px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">${a.original_name}</span>
              <button class="btn btn-ghost btn-sm" style="padding:1px 4px;font-size:10px;color:var(--danger)" onclick="window['_attWidget_${this.container.id}'].deleteAtt(${a.id})" title="Eliminar">🗑️</button>
            </div>
          </div>`;
        }
        return `<div class="att-item" style="display:flex;align-items:center;gap:8px;padding:4px 8px;border-radius:6px;margin-bottom:4px;background:rgba(255,255,255,.04)">
          <span style="font-size:12px">📄</span>
          <a href="${url}" target="_blank" style="flex:1;font-size:12px;color:var(--accent2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${a.original_name}">${a.original_name}</a>
          <span style="font-size:11px;color:var(--text2)">${fmtSize(a.size_bytes)}</span>
          <button class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px;color:var(--danger)" onclick="window['_attWidget_${this.container.id}'].deleteAtt(${a.id})" title="Eliminar">🗑️</button>
        </div>`;
      }).join('');
      html += `</div>`;
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
      await fetch(`/api/attachments.php?id=${id}`, {method:'DELETE', credentials:'include', headers:{'X-CSRF-Token': getCsrfToken()}});
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
    fd.append('_csrf_token', getCsrfToken());
    try {
      const res = await fetch('/api/attachments.php', {method:'POST', credentials:'include', headers:{'X-CSRF-Token': getCsrfToken()}, body: fd});
      const text = await res.text();
      let d;
      try { d = JSON.parse(text); } catch(_) { throw new Error(text || 'Respuesta vacía del servidor'); }
      if (!res.ok) throw new Error(d.error || 'Error al subir');
      this.pendingFiles = [];
      toast(`${d.uploaded.length} adjunto(s) subido(s)`);
      await this.load();
    } catch(e) { toast('Error adjuntos: ' + e.message, 'error'); console.error('Upload error:', e); }
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
