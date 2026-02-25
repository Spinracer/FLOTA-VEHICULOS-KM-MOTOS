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
  document.querySelectorAll(`#${modalId} input:not([type=hidden]), #${modalId} select, #${modalId} textarea`)
    .forEach(el => el.value = '');
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
