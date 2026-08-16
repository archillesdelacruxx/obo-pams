/* ==========================================================================
   PAMS — User Module Components
   Renders the permission-aware sidebar, header, and shared UI components
   for all user module pages.
   ========================================================================== */

/* --------------------------------------------------------------------------
   User navigation items — only rendered if present in USER_SESSION.modules
   -------------------------------------------------------------------------- */
const USER_NAV_ITEMS = [
  { key: 'dashboard',                label: 'Dashboard',                page: 'dashboard.php',                     icon: 'grid',         section: 'Main' },
  { key: 'order-of-payment',         label: 'Order of Payment',         page: 'order-of-payment.php',              icon: 'file-text',    section: 'Modules' },
  { key: 'op-records',               label: 'OP Records',               page: 'op-records.php',                    icon: 'layers',       section: 'Modules' },
  { key: 'permit-workflow',          label: 'Permit Workflow',          page: 'permit-workflow.php',               icon: 'git-branch',   section: 'Modules' },
  { key: 'workflow-details',         label: 'Workflow Details',         page: 'workflow-details.php',              icon: 'git-branch',   section: 'Modules' },
  { key: 'permit-approval-encoding', label: 'Permit Approval Encoding', page: 'permit-approval-encoding.php',      icon: 'award',        section: 'Modules' },
  { key: 'permit-approval-records',  label: 'Permit Approval Records',  page: 'permit-approval-records.php',       icon: 'layers',       section: 'Modules' },
  { key: 'releasing',                label: 'Releasing Plans',          page: 'releasing.php',                     icon: 'package',      section: 'Modules' },
  { key: 'releasing-records',        label: 'Releasing Records',        page: 'releasing-records.php',             icon: 'layers',       section: 'Modules' },
  { key: 'inspection-checklist',     label: 'Ocular Inspection Checklist', page: 'inspection-checklist.php',      icon: 'clipboard',    section: 'Inspection Management' },
  { key: 'inspection-reports',       label: 'Monitoring Reports',       page: 'inspection-reports.php',            icon: 'activity',     section: 'Inspection Management' },
  { key: 'announcements',            label: 'Announcements',            page: 'announcements.php',                 icon: 'megaphone',    section: 'Account' },
  { key: 'settings',                 label: 'Profile Settings',        page: 'settings.php',                      icon: 'settings',     section: 'Account' }
];

/* --------------------------------------------------------------------------
   Extended icon set for user module
   -------------------------------------------------------------------------- */
const USER_NAV_ICONS = {
  grid:          '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
  'file-text':   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
  'git-branch':  '<line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
  'check-circle':'<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  package:       '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
  bell:          '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
  megaphone:     '<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
  settings:      '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  user:          '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  chevron:       '<path d="m6 9 6 6 6-6"/>',
  logout:        '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
  search:        '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  'arrow-right': '<path d="M5 12h14M13 6l6 6-6 6"/>',
  refresh:       '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
  download:      '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
  printer:       '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
  eye:           '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/>',
  edit:          '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
  plus:          '<path d="M12 5v14M5 12h14"/>',
  clock:         '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  calendar:      '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  'bar-chart':   '<path d="M4 20V10M12 20V4M20 20v-7"/>',
  'trending-up': '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
  filter:        '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
  award:         '<path d="M12 15l-2 5L8 8l-7 3 5-5M12 15l2 5 2-7 7 3-5-5M12 15V3"/>',
  layers:        '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
  'x':           '<path d="M18 6 6 18M6 6l12 12"/>',
  'activity':    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  'shield-check':'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
  'clipboard':   '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 0-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
  'activity':    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  'calendar':    '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  'trash':       '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/>',
  'users':       '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  'check':       '<polyline points="20 6 9 17 4 12"/>',
  'check-done':  '<path d="M3 15l5 5L21 6"/><path d="M9 15l3 3 8-9"/>'
};

function userIcon(name, cls = '') {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${cls}">${USER_NAV_ICONS[name] || ''}</svg>`;
}

/* --------------------------------------------------------------------------
    Count unread notifications for badge
    -------------------------------------------------------------------------- */
let _unreadCount = 0;

async function getUnreadCount() {
  try {
    const res = await apiGet('notifications', 'unread-count');
    _unreadCount = res.count || 0;
  } catch (e) { _unreadCount = 0; }
  return _unreadCount;
}

function updateNotifBadge() {
  const badgeEl = document.querySelector('.header-badge-btn .dot');
  if (badgeEl) badgeEl.style.display = _unreadCount > 0 ? '' : 'none';
}

/* --------------------------------------------------------------------------
    Build permission-aware sidebar (server-rendered by user-shell.php)
    -------------------------------------------------------------------------- */
function renderUserSidebar(activeKey) { return ''; }

/* --------------------------------------------------------------------------
   Build header
   -------------------------------------------------------------------------- */
function renderUserHeader(pageTitle) {
  return `
  <header class="header">
    <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <div class="header-search">
      ${userIcon('search')}
      <input type="text" placeholder="Search permits, records…" id="globalSearch">
      <kbd>/</kbd>
    </div>
    <div class="header-right">
      <div class="dropdown-wrap">
        <button class="icon-btn header-badge-btn" id="notifBtn" aria-label="Notifications">
          ${userIcon('bell')}<span class="dot" style="display:none;"></span>
        </button>
        <div class="dropdown-panel" id="notifPanel"></div>
      </div>
      <div class="dropdown-wrap">
        <div class="profile-trigger" id="profileTrigger">
          <div class="avatar sm">${initials(USER_SESSION.name)}</div>
          <div class="info">
            <strong>${USER_SESSION.name.split(' ')[0]} ${USER_SESSION.name.split(' ').pop()}</strong>
            <span>User</span>
          </div>
          ${userIcon('chevron')}
        </div>
        <div class="dropdown-panel profile-menu" id="profilePanel">
<div class="profile-menu-item" data-user-nav="../settings.php">${userIcon('settings')} Profile Settings</div>
          <hr class="divider" style="margin:6px 0;">
          <div class="profile-menu-item danger" id="userLogoutTrigger">${userIcon('logout')} Logout</div>
        </div>
      </div>
    </div>
  </header>`;
}

/* --------------------------------------------------------------------------
    Notification dropdown panel content
    -------------------------------------------------------------------------- */
let _cachedNotifs = [];

async function renderUserNotifPanel() {
  try {
    const res = await apiGet('notifications', 'list');
    _cachedNotifs = res.data || [];
  } catch (e) { _cachedNotifs = []; }
  const unreadNotifs = _cachedNotifs.filter(n => !n.is_read);
  const items = unreadNotifs.slice(0, 5);
  if (!items.length) return '<div class="notif-empty" style="padding:24px;text-align:center;color:var(--gray-400);">No notifications</div>';
  return `
    <div class="notif-head">
      <strong>Notifications</strong>
      <span>${unreadNotifs.length} unread</span>
    </div>
    <div class="notif-list">
      ${items.map(n => `
        <div class="notif-item unread" data-notif-item="1" data-notif-id="${n.id}" data-notif-record="${n.record_id || ''}" data-notif-module="${n.module_name || ''}" style="cursor:pointer;">
          <div class="n-icon icon-${n.module_name === 'announcement' ? 'blue' : n.module_name === 'approved' ? 'green' : 'blue'}">
            ${userIcon(n.module_name === 'announcement' ? 'megaphone' : n.module_name === 'approved' ? 'check-circle' : 'bell')}
          </div>
          <div class="n-content">
            <strong>${n.title}</strong>
            <p>${(n.message || '').substring(0, 75)}…</p>
            <time>${timeAgo(n.created_at)}</time>
          </div>
        </div>`).join('')}
    </div>
    <div class="notif-footer">
      <a href="announcements.php" data-user-nav="announcements.php">View all announcements</a>
    </div>`;
}

/* --------------------------------------------------------------------------
   Mount the shell (sidebar + header + modal root)
   -------------------------------------------------------------------------- */
function mountUserShell(activeKey, pageTitle) {
  $('#sidebarRoot').innerHTML = renderUserSidebar(activeKey);
  $('#headerRoot').innerHTML = renderUserHeader(pageTitle);
  document.body.insertAdjacentHTML('beforeend', `
    <div class="modal-wrap" id="modalRoot">
      <div class="backdrop" data-close-modal></div>
      <div class="modal-box" id="modalBox"></div>
    </div>`);
  initUserSidebar();
  initUserHeaderDropdowns();
  initUserNavRouting();
}

/* --------------------------------------------------------------------------
   Sidebar toggle (collapse / mobile)
   -------------------------------------------------------------------------- */
function initUserSidebar() {
  const shell = $('#appShell');
  const burgerBtn = $('#mobileMenuBtn');

  // One burger button controls both desktop collapse AND mobile slide-in
  burgerBtn?.addEventListener('click', e => {
    e.stopPropagation();
    if (window.innerWidth <= 960) {
      shell.classList.toggle('mobile-open');
    } else {
      shell.classList.toggle('collapsed');
    }
  });

  // Close mobile sidebar when clicking outside
  document.addEventListener('click', e => {
    if (window.innerWidth <= 960 && shell.classList.contains('mobile-open')) {
      if (!e.target.closest('.sidebar') && !e.target.closest('#mobileMenuBtn')) {
        shell.classList.remove('mobile-open');
      }
    }
  });
}

/* --------------------------------------------------------------------------
   Header dropdown logic
   -------------------------------------------------------------------------- */
function initUserHeaderDropdowns() {
  const notifBtn   = $('#notifBtn');
  const notifPanel = $('#notifPanel');
  const profileTrigger = $('#profileTrigger');
  const profilePanel   = $('#profilePanel');

  async function loadNotifPanel() {
    if (notifPanel) {
      notifPanel.innerHTML = await renderUserNotifPanel();
      bindNotifPanelItems(notifPanel);
    }
  }

  function bindNotifPanelItems(panel) {
    panel.querySelectorAll('[data-notif-item]').forEach(el => {
      el.addEventListener('click', async () => {
        const id = el.dataset.notifId;
        const recordId = el.dataset.notifRecord;
        const moduleName = el.dataset.notifModule;
        if (id && el.classList.contains('unread')) {
          await apiPost('notifications', 'mark-read', { id }).catch(() => {});
        }
        if (moduleName === 'announcements' && recordId) {
          localStorage.setItem('pams_open_announcement', recordId);
          window.location.href = 'announcements.php';
          return;
        }
        window.location.href = 'announcements.php';
      });
    });
  }

  async function refreshNotifPanel() {
    if (!notifPanel) return;
    await getUnreadCount();
    updateNotifBadge();
    if (notifPanel.classList.contains('open')) {
      notifPanel.innerHTML = await renderUserNotifPanel();
      bindNotifPanelItems(notifPanel);
    }
  }

  loadNotifPanel();
  getUnreadCount().then(updateNotifBadge);

  function closeAll() {
    notifPanel?.classList.remove('open');
    profilePanel?.classList.remove('open');
  }

  notifBtn?.addEventListener('click', e => {
    e.stopPropagation();
    const wasOpen = notifPanel.classList.contains('open');
    closeAll();
    if (!wasOpen) {
      loadNotifPanel();
      notifPanel.classList.add('open');
    }
  });

  profileTrigger?.addEventListener('click', e => {
    e.stopPropagation();
    const wasOpen = profilePanel.classList.contains('open');
    closeAll();
    if (!wasOpen) profilePanel.classList.add('open');
  });

  document.addEventListener('click', closeAll);

  const logoutTrigger = $('#userLogoutTrigger');
  logoutTrigger?.addEventListener('click', e => {
    e.preventDefault();
    openLogoutConfirm('../../logout.php');
  });

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('user-notif-panel', refreshNotifPanel, 10000);
  }
}

/* --------------------------------------------------------------------------
   Navigation routing — handles data-user-nav attributes
   -------------------------------------------------------------------------- */
function initUserNavRouting() {
  document.addEventListener('click', e => {
    const el = e.target.closest('[data-user-nav]');
    if (!el) return;
    if (el.dataset.underDev === '1') {
      e.preventDefault();
      e.stopPropagation();
      const label = el.querySelector('.label')?.textContent || el.getAttribute('aria-label') || 'This module';
      openModal(`
        <div class="modal-head"><h3>Under Development</h3><button class="icon-btn" data-close-modal><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
        <div class="modal-body" style="text-align:center;padding:32px 24px;">
          <div style="width:64px;height:64px;border-radius:50%;background:#fef3c7;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="#d97706" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <h3 style="margin:0 0 8px;">${escapeHtml(label)}</h3>
          <p class="text-sm text-muted">This module is currently under development. Please check back later.</p>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button></div>`);
      return;
    }
    const page = el.dataset.userNav;
    if (!page) return;
    window.location.href = page;
  });

  // Keyboard navigation
  document.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      const el = document.activeElement.closest('[data-user-nav]');
      if (el) el.click();
    }
    if (e.key === '/') {
      const search = $('#globalSearch');
      if (search && document.activeElement !== search) {
        e.preventDefault();
        search.focus();
      }
    }
  });
}

/* --------------------------------------------------------------------------
   Shared table search helper
   -------------------------------------------------------------------------- */
function attachUserSearch(inputEl, rows, matchFn) {
  if (!inputEl) return;
  const handler = debounce((q) => {
    rows().forEach(row => {
      row.el.style.display = matchFn(row, q.toLowerCase()) ? '' : 'none';
    });
  }, 220);
  inputEl.addEventListener('input', e => handler(e.target.value));
}

/* --------------------------------------------------------------------------
   Shared render helpers
   -------------------------------------------------------------------------- */
function statusBadge(status) {
  const map = {
    'Pending':      '<span class="badge-workflow pending">Pending</span>',
    'Under Review': '<span class="badge-workflow in-progress">Under Review</span>',
    'Approved':     '<span class="badge-workflow completed">Approved</span>',
    'Disapproved':  '<span class="badge-workflow disapproved">Disapproved</span>',
    'Released':     '<span class="badge-workflow released">Released</span>',
    'Paid':         '<span class="badge-workflow completed">Paid</span>',
    'in-progress':  '<span class="badge-workflow in-progress">In Process</span>',
    'pending':      '<span class="badge-workflow pending">Pending</span>',
    'completed':    '<span class="badge-workflow completed">Completed</span>',
    'returned':     '<span class="badge-workflow returned">Returned</span>',
    'approved':     '<span class="badge-workflow completed">Approved</span>'
  };
  return map[status] || `<span class="badge badge-neutral">${status || 'Pending'}</span>`;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function businessDaysBetween(startStr, endStr) {
  if (!startStr || !endStr) return 0;
  const start = new Date(startStr + 'T00:00:00');
  const end = new Date(endStr + 'T00:00:00');
  if (isNaN(start) || isNaN(end) || end < start) return 0;
  let days = 0;
  const cur = new Date(start);
  while (cur < end) {
    const dow = cur.getDay(); // 0=Sun ... 6=Sat
    if (dow !== 0 && dow !== 6) days++;
    cur.setDate(cur.getDate() + 1);
  }
  return days;
}

function renderPagination(containerId, total, perPage = 10) {
  const el = $(containerId);
  if (!el) return;
  const pages = Math.ceil(total / perPage);
  const shown = Math.min(perPage, total);
  let btns = '';
  for (let i = 1; i <= Math.min(pages, 5); i++) {
    btns += `<button class="pg-btn ${i === 1 ? 'active' : ''}" onclick="this.closest('.pagination').querySelectorAll('.pg-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active')">${i}</button>`;
  }
  el.innerHTML = `
    <span class="pg-info">Showing 1–${shown} of ${total} records</span>
    <div class="pg-nav">
      <button class="pg-btn" disabled>${userIcon('arrow-right', '')}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transform:rotate(180deg);width:14px;height:14px"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </button>
      ${btns}
      <button class="pg-btn">${userIcon('arrow-right', '')}</button>
    </div>`;
}
