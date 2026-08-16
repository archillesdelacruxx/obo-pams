/* ==========================================================================
   PAMS — Realtime engine
   Polls lightweight endpoints so tables, badges, and dashboards stay fresh
   across all admin and user sessions without full page reloads.
   ========================================================================== */
window.PAMS_REALTIME = (function () {
  const feeds = [];
  let started = false;

  function register(key, fn, interval) {
    feeds.push({ key, fn, interval: interval || 12000, next: 0 });
  }

  function anyModalOpen() {
    const list = document.querySelectorAll('.modal-wrap, .modal-root, [data-modal]');
    for (let i = 0; i < list.length; i++) {
      if (list[i].classList.contains('open')) return true;
    }
    return false;
  }

  function isBusy() {
    if (document.hidden) return true;
    if (anyModalOpen()) return true;
    const el = document.activeElement;
    if (el && ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(el.tagName) !== -1) return true;
    return false;
  }

  async function tick() {
    if (document.hidden) return;
    const now = Date.now();
    for (const f of feeds) {
      if (now < f.next) continue;
      f.next = now + f.interval;
      if (isBusy()) { f.next = now + 4000; continue; }
      try { await f.fn(); } catch (e) { /* keep polling */ }
    }
  }

  function pollBadge() {
    if (document.hidden) return;
    apiGet('notifications', 'unread-count')
      .then(res => {
        const count = (res && res.count) || 0;
        const label = count > 99 ? '99+' : count;
        const dot = document.querySelector('.header-badge-btn .dot');
        if (dot) dot.style.display = count > 0 ? '' : 'none';
        const sb = document.getElementById('sidebarNotifBadge');
        if (sb) {
          sb.textContent = label;
          sb.style.display = count > 0 ? '' : 'none';
          sb.classList.toggle('has-items', count > 0);
        }
      })
      .catch(() => {});
  }

  function recomputeSidebarSections() {
    const sidebar = document.getElementById('userSidebar');
    if (!sidebar) return;
    sidebar.querySelectorAll('[data-nav-section]').forEach(label => {
      let visible = false;
      let el = label.nextElementSibling;
      while (el && !el.hasAttribute('data-nav-section')) {
        if (el.classList.contains('nav-item') && !el.hasAttribute('hidden')) { visible = true; break; }
        el = el.nextElementSibling;
      }
      if (visible) label.removeAttribute('hidden');
      else label.setAttribute('hidden', '');
    });
  }

  /* Reflects live module-permission changes (admin toggles) in the user sidebar. */
  function syncSidebarPermissions() {
    const sidebar = document.getElementById('userSidebar');
    if (!sidebar || document.hidden) return;
    apiGet('me', 'permissions')
      .then(res => {
        if (!res || !Array.isArray(res.granted)) return;
        const granted = new Set(res.granted);
        sidebar.querySelectorAll('.nav-item[data-module-key]').forEach(item => {
          const has = granted.has(item.getAttribute('data-module-key'));
          if (has === item.hasAttribute('hidden')) {
            if (has) item.removeAttribute('hidden');
            else item.setAttribute('hidden', '');
          }
        });
        recomputeSidebarSections();
      })
      .catch(() => {});
  }

  function start() {
    if (started) return;
    started = true;
    if (document.getElementById('userSidebar')) {
      register('sidebar-perms', syncSidebarPermissions, 20000);
    }
    setTimeout(pollBadge, 1500);
    setInterval(tick, 4000);
    setInterval(pollBadge, 8000);
  }

  return { register, start };
})();

document.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => window.PAMS_REALTIME.start(), 800);
});
