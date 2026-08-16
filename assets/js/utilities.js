/* ==========================================================================
   PAMS — Utilities module
   Small shared helpers used across every page.
   ========================================================================== */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

function apiBase() {
  const depth = location.pathname.includes('/pages/user/') ? '../../' : '../';
  return depth + 'api/index.php';
}

function formatNumber(n){
  return new Intl.NumberFormat('en-US').format(n);
}

function debounce(fn, wait = 250){
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

function timeAgo(dateStr){
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

function initials(name){
  return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/* ---- Toast notifications ---- */
function ensureToastStack(){
  let stack = $('.toast-stack');
  if (!stack){
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  return stack;
}

const ICONS = {
  success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>',
  warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.86 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/></svg>',
  danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
};

function showToast({ title, message, type = 'info', duration = 4200 }){
  const stack = ensureToastStack();
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `
    <div class="a-icon icon-${type === 'info' ? 'blue' : type === 'success' ? 'green' : type === 'warning' ? 'orange' : 'red'}" style="width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
      <span style="width:16px;height:16px;display:block;">${ICONS[type]}</span>
    </div>
    <div>
      <strong>${title}</strong>
      <p>${message || ''}</p>
    </div>
    <button class="icon-btn" aria-label="Dismiss">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>`;
  stack.appendChild(el);
  const dismiss = () => { el.classList.add('out'); setTimeout(() => el.remove(), 220); };
  el.querySelector('button').addEventListener('click', dismiss);
  setTimeout(dismiss, duration);
}

/* ---- Ripple effect for buttons ---- */
function attachRipple(){
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn, .fab, .icon-btn');
    if (!btn) return;
    const rect = btn.getBoundingClientRect();
    const ripple = document.createElement('span');
    const size = Math.max(rect.width, rect.height);
    ripple.className = 'ripple';
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
    btn.style.position = btn.style.position || 'relative';
    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 620);
  });
}

/* ---- Full page loading overlay ---- */
function showPageLoader(){
  let el = $('.loading-overlay');
  if (!el){
    el = document.createElement('div');
    el.className = 'loading-overlay';
    el.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(el);
  }
  requestAnimationFrame(() => el.classList.add('show'));
  return el;
}
function hidePageLoader(){
  const el = $('.loading-overlay');
  if (el) el.classList.remove('show');
}

/* ---- Profile photo upload handler ---- */
function initProfilePhotoUpload(camBtnId, fileInputId, avatarWrap, fallbackName) {
  const camBtn = $('#' + camBtnId);
  const fileInput = $('#' + fileInputId);
  if (!camBtn || !fileInput) return;

  camBtn.addEventListener('click', () => fileInput.click());

  fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('photo', file);
    const m = document.querySelector('meta[name="csrf-token"]');
    if (m) fd.append('_csrf_token', m.getAttribute('content') || '');
    try {
      const res = await fetch(apiBase() + '?module=profile&action=upload-photo', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        const prefix = location.pathname.includes('/pages/user/') ? '../../' : '../';
        const img = new Image();
        img.onload = () => {
          avatarWrap.innerHTML = '<img src="' + prefix + data.path + '" alt="Profile photo" class="avatar-img">'
            + '<div class="cam-btn" id="' + camBtnId + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg></div>';
          initProfilePhotoUpload(camBtnId, fileInputId, avatarWrap, fallbackName);
        };
        img.src = prefix + data.path;
        showToast({ title: 'Photo updated', type: 'success' });
      } else {
        showToast({ title: 'Upload failed', message: data.error || 'Unknown error.', type: 'danger' });
      }
    } catch (e) {
      showToast({ title: 'Upload error', message: 'Could not upload photo.', type: 'danger' });
    }
    fileInput.value = '';
  });
}
