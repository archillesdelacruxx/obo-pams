/* ==========================================================================
   PAMS — App initialization
   Detects the current page (via body[data-page]) and wires up shared shell
   plus page-specific logic. This is the only file each page needs to load
   after the shared modules.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  attachRipple();
  initSidebar();
  initHeaderDropdowns();
  initNavRouting();

  const page = document.body.getAttribute('data-page');
  const initFn = {
    dashboard: initDashboardPage,
    reports: initReportsPage,
    users: initUserManagementPage,
    settings: initSettingsPage,
    profile: initProfilePage
  }[page];

  if (initFn) initFn();
});

/* ---------------------------- DASHBOARD ---------------------------- */
async function initDashboardPage(){

  try {
    const actRes = await apiGet('activity', 'list', { per_page: 6 });
    const activities = actRes.data || [];
    $('#recentActivities').innerHTML = activities.map(a => `
      <div class="activity-item">
        <div class="a-icon icon-blue">${icon('bell')}</div>
        <div><p><b>${escapeHtml(a.user_name || 'System')}</b> ${a.description || a.action}</p><time>${timeAgo(a.created_at)}</time></div>
      </div>`).join('');
  } catch (e) { $('#recentActivities').innerHTML = '<div class="activity-item"><div>No recent activity.</div></div>'; }

  try {
    const annRes = await apiGet('announcements', 'list');
    const announcements = (annRes.data || []).slice(0, 3);
    $('#announcements').innerHTML = announcements.map(a => `
      <div class="announcement">
        <span class="a-tag">${a.created_by ? 'Announcement' : 'System'}</span>
        <h5>${escapeHtml(a.title)}</h5>
        <p>${escapeHtml((a.content || '').substring(0, 120))}</p>
      </div>`).join('');
  } catch (e) { $('#announcements').innerHTML = ''; }

  try {
    const notifRes = await apiGet('notifications', 'list');
    const notifs = (notifRes.data || []).slice(0, 5);
    const feed = $('#notifFeed');
    if (feed) {
      feed.innerHTML = notifs.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}">
          <div class="n-icon icon-${n.module_name === 'announcement' ? 'blue' : n.module_name === 'approved' ? 'green' : n.module_name === 'record' ? 'orange' : 'blue'}">
            ${icon(n.module_name === 'announcement' ? 'megaphone' : n.module_name === 'approved' ? 'check-circle' : n.module_name === 'record' ? 'file-text' : 'bell')}
          </div>
          <div class="n-content">
            <strong>${escapeHtml(n.title)}</strong>
            <p>${escapeHtml((n.message || '').substring(0, 75))}</p>
            <time>${timeAgo(n.created_at)}</time>
          </div>
        </div>`).join('');
    }
  } catch (e) {}

  $('#notifShortcut')?.addEventListener('click', (e) => {
    e.preventDefault();
    $('#notifBtn')?.click();
  });

  const dash = window.DASHBOARD_DATA || null;

  if (dash && dash.monthly && dash.monthly.length) {
    renderBarChart($('#monthlyChart'), dash.monthly.map(m => ({ label: m.label, value: m.value })));
  } else {
    renderBarChart($('#monthlyChart'), [
      { label: 'Feb', value: 62 }, { label: 'Mar', value: 74 }, { label: 'Apr', value: 58 },
      { label: 'May', value: 91 }, { label: 'Jun', value: 83 }, { label: 'Jul', value: 97 }
    ]);
  }

  renderLineChart($('#yearlyChart'), dash && dash.yearly ? dash.yearly : [40, 55, 48, 63, 59, 72, 68, 80, 76, 88, 84, 95]);

  initStatTooltips();

  $$('.seg-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.parentElement.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
}

function initStatTooltips(){
  if (!window.DASHBOARD_DATA || !window.DASHBOARD_DATA.cards) return;
  const dataByKey = {};
  window.DASHBOARD_DATA.cards.forEach(c => { dataByKey[c.key] = c; });

  let tip = document.getElementById('statTooltip');
  if (!tip) {
    tip = document.createElement('div');
    tip.id = 'statTooltip';
    tip.className = 'stat-tooltip';
    document.body.appendChild(tip);
  }

  $$('.stat-card[data-card]').forEach(card => {
    card.addEventListener('mouseenter', () => {
      const d = dataByKey[card.getAttribute('data-card')];
      if (!d) return;
      tip.innerHTML = buildStatTooltip(d);
      tip.classList.add('show');
      positionStatTooltip(card, tip);
    });
    card.addEventListener('mouseleave', () => tip.classList.remove('show'));
  });
}

function buildStatTooltip(d){
  let body;
  if (d.type === 'bars') {
    const max = Math.max(1, ...d.values);
    body = '<div class="tt-bars">' + d.values.map((v, i) =>
      '<div class="tt-bar" style="height:' + Math.round(v / max * 100) + '%;"><b>' + v + '</b><span>' + d.months[i] + '</span></div>'
    ).join('') + '</div>';
  } else {
    body = '<div class="tt-rows">' + (d.items || []).map(it =>
      '<div class="tt-row"><span class="tt-dot" style="background:' + it.color + ';"></span>' + escapeHtml(it.label) + '<strong>' + formatNumber(it.value) + '</strong></div>'
    ).join('') + '</div>';
  }
  return '<div class="tt-head"><span class="tt-title">' + escapeHtml(d.title) + '</span><span class="tt-total">' + formatNumber(d.total) + '</span></div>'
    + (d.subtitle ? '<div class="tt-sub">' + escapeHtml(d.subtitle) + '</div>' : '')
    + body;
}

function positionStatTooltip(card, tip){
  const r = card.getBoundingClientRect();
  const tw = tip.offsetWidth, th = tip.offsetHeight;
  let left = r.left + r.width / 2 - tw / 2;
  left = Math.max(8, Math.min(left, window.innerWidth - tw - 8));
  let top = r.top - th - 10;
  const placeBelow = top < 8;
  if (placeBelow) top = r.bottom + 10;
  tip.style.left = left + 'px';
  tip.style.top = top + 'px';
  tip.dataset.dir = placeBelow ? 'below' : 'above';
}

function renderBarChart(container, data){
  if (!container) return;
  const max = Math.max(...data.map(d => d.value));
  container.innerHTML = data.map((d, i) => `
    <div class="bar-col">
      <div class="bar" style="height:${(d.value / max * 100)}%; animation-delay:${i * 60}ms;"></div>
      <span>${d.label}</span>
    </div>`).join('');
}

function renderLineChart(svg, values){
  if (!svg) return;
  const w = 600, h = 200, pad = 10;
  const max = Math.max(...values), min = Math.min(...values);
  const stepX = (w - pad * 2) / (values.length - 1);
  const pts = values.map((v, i) => {
    const x = pad + i * stepX;
    const y = h - pad - ((v - min) / (max - min || 1)) * (h - pad * 2);
    return [x, y];
  });
  const line = pts.map((p, i) => (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  const area = line + ` L${pts[pts.length - 1][0]},${h} L${pts[0][0]},${h} Z`;
  const gridLines = [0.25, 0.5, 0.75].map(f => `<line class="grid-line" x1="0" x2="${w}" y1="${h * f}" y2="${h * f}"/>`).join('');
  const dots = pts.map(p => `<circle class="pt" cx="${p[0]}" cy="${p[1]}" r="3.5"/>`).join('');
  svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
  svg.innerHTML = `
    <defs><linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#1D5FD6" stop-opacity=".22"/>
      <stop offset="100%" stop-color="#1D5FD6" stop-opacity="0"/>
    </linearGradient></defs>
    ${gridLines}
    <path class="area-path" d="${area}"/>
    <path class="line-path" d="${line}"/>
    ${dots}`;
}

/* ---------------------------- REPORTS ---------------------------- */
async function initReportsPage(){
  const tbody = $('#reportsTableBody');

  async function loadReports() {
    try {
      const res = await apiGet('dashboard', 'staff-summary');
      const staff = res.data || [];
      tbody.innerHTML = staff.map(u => `
        <tr>
          <td class="cell-user">
            <div class="avatar sm">${initials(u.name)}</div>
            <div><strong>${escapeHtml(u.name)}</strong><span>@${escapeHtml(u.username)}</span></div>
          </td>
          <td>${formatNumber(u.op || 0)}</td>
          <td>${formatNumber(u.workflow || 0)}</td>
          <td>${formatNumber(u.approved || 0)}</td>
          <td>${formatNumber(u.releasing || 0)}</td>
          <td><b>${formatNumber((u.op || 0) + (u.workflow || 0) + (u.approved || 0) + (u.releasing || 0))}</b></td>
        </tr>`).join('') || '<tr><td colspan="6" style="text-align:center;padding:48px;color:var(--gray-400);">No data available</td></tr>';

      const totals = staff.reduce((acc, u) => {
        acc.op += u.op || 0; acc.workflow += u.workflow || 0; acc.approved += u.approved || 0; acc.releasing += u.releasing || 0;
        return acc;
      }, { op: 0, workflow: 0, approved: 0, releasing: 0 });
      $('#totalOP').textContent = formatNumber(totals.op);
      $('#totalWorkflow').textContent = formatNumber(totals.workflow);
      $('#totalApproved').textContent = formatNumber(totals.approved);
      $('#totalReleasing').textContent = formatNumber(totals.releasing);
      $('#totalOverall').textContent = formatNumber(totals.op + totals.workflow + totals.approved + totals.releasing);
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:48px;color:var(--gray-400);">Failed to load reports data</td></tr>';
    }
  }

  await loadReports();

  attachSearch($('#reportsSearch'), () => $$('#reportsTableBody tr').map(el => ({ el })), (row, q) => {
    return row.el.textContent.toLowerCase().includes(q);
  });

  $('#exportBtn')?.addEventListener('click', async () => {
    showToast({ title: 'Exporting', message: 'Preparing CSV download...', type: 'info' });
    try {
      const res = await apiGet('dashboard', 'export-csv');
      if (res.csv) {
        const blob = new Blob([res.csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'pams-report.csv'; a.click();
        URL.revokeObjectURL(url);
        showToast({ title: 'Export complete', message: 'CSV downloaded.', type: 'success' });
      }
    } catch (e) { showToast({ title: 'Export failed', message: 'Could not generate report.', type: 'error' }); }
  });
  $('#printBtn')?.addEventListener('click', () => window.print());
}

/* ---------------------------- USER MANAGEMENT ---------------------------- */
async function initUserManagementPage(){
  const tbody = $('#usersTableBody');
  let users = [];

  const statusBadge = (s) => {
    const st = (s || '').toLowerCase();
    return st === 'active' || st === '1' ? '<span class="badge badge-success">Active</span>'
      : '<span class="badge badge-neutral">Inactive</span>';
  };

  const renderRows = (rows) => {
    tbody.innerHTML = rows.map(u => {
      const modules = u.granted_modules ? u.granted_modules.split(',').filter(Boolean) : [];
      return `<tr data-id="${u.id}">
        <td class="cell-user">
          <div class="avatar sm">${initials(u.full_name)}</div>
          <div><strong>${escapeHtml(u.full_name)}</strong><span>@${escapeHtml(u.username || '')}</span></div>
        </td>
        <td>${statusBadge(u.is_active)}</td>
        <td><div class="module-tags">${modules.length ? modules.map(m => `<span class="module-tag">${escapeHtml(m)}</span>`).join('') : '<span class="text-muted">—</span>'}</div></td>
        <td>${u.last_login ? u.last_login : '—'}</td>
        <td>
          <div class="row-actions">
            <button class="icon-btn" data-action="view" aria-label="View">${icon('user')}</button>
            <button class="icon-btn" data-action="edit" aria-label="Edit">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
            </button>
            <button class="icon-btn" data-action="reset" aria-label="Reset password">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
            </button>
            <button class="icon-btn" data-action="delete" aria-label="Delete" style="color:var(--danger);">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
    $('#userCount').textContent = rows.length;
  };

  async function loadUsers() {
    try {
      const res = await apiGet('users', 'list');
      users = res.data || [];
      renderRows(users);
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:48px;color:var(--gray-400);">Failed to load users</td></tr>';
    }
  }

  await loadUsers();

  attachSearch($('#usersSearch'), () => $$('#usersTableBody tr').map(el => ({ el })), (row, q) => row.el.textContent.toLowerCase().includes(q));

  tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const row = btn.closest('tr');
    const user = users.find(u => u.id == row.dataset.id);
    if (!user) return;
    const action = btn.dataset.action;

    if (action === 'delete'){
      openConfirm({
        title: `Delete ${escapeHtml(user.full_name)}?`,
        message: 'This account and its module access will be permanently removed. This cannot be undone.',
        confirmLabel: 'Delete Account',
        tone: 'danger',
        onConfirm: async () => {
          closeModal();
          try {
            await apiPost('users', 'delete', { id: user.id });
            row.remove();
            showToast({ title: 'User deleted', message: `${escapeHtml(user.full_name)} was removed from PAMS.`, type: 'success' });
          } catch (err) {
            showToast({ title: 'Error', message: 'Failed to delete user.', type: 'error' });
          }
        }
      });
    } else if (action === 'reset'){
      openConfirm({
        title: 'Reset password?',
        message: `A temporary password will be generated for ${escapeHtml(user.full_name)} and must be changed on next login.`,
        confirmLabel: 'Reset Password',
        tone: 'primary',
        icon: 'key',
        onConfirm: async () => {
          closeModal();
          try {
            await apiPost('users', 'reset-password', { id: user.id });
            showToast({ title: 'Password reset', message: 'Temporary password sent to the user record.', type: 'success' });
          } catch (err) {
            showToast({ title: 'Error', message: 'Failed to reset password.', type: 'error' });
          }
        }
      });
    } else if (action === 'view' || action === 'edit'){
      openUserFormModal(user, action === 'view');
    }
  });

  $('#createUserBtn')?.addEventListener('click', () => openUserFormModal(null, false));
}

const ALL_MODULES = [
  { key: 'order-of-payment', label: 'Order of Payment', desc: 'Encode and track OP records.' },
  { key: 'op-records', label: 'OP Records', desc: 'View all encoded OP records.' },
  { key: 'permit-workflow', label: 'Permit Workflow', desc: 'Track permit processing lifecycle.' },
  { key: 'workflow-details', label: 'Workflow Details', desc: 'View detailed workflow rounds.' },
  { key: 'permit-approval-encoding', label: 'Permit Approval Encoding', desc: 'Encode permit approvals.' },
  { key: 'permit-approval-records', label: 'Permit Approval Records', desc: 'View approval records.' },
  { key: 'releasing', label: 'Releasing Plans', desc: 'Manage folder releasing.' },
  { key: 'releasing-records', label: 'Releasing Records', desc: 'View release records.' },
];

function moduleSwitchRows(activeKeys = []){
  return ALL_MODULES.map(m => `
    <div class="module-switch-row">
      <div class="m-info"><strong>${m.label}</strong><span>${m.desc}</span></div>
      <label class="switch">
        <input type="checkbox" name="mod_${m.key}" ${activeKeys.includes(m.key) ? 'checked' : ''}>
        <span class="track"></span>
      </label>
    </div>`).join('');
}

function openUserFormModal(user, readOnly){
  const isNew = !user;
  const title = isNew ? 'Create New User' : readOnly ? 'User Details' : `Edit ${escapeHtml(user.full_name)}`;
  const activeModules = user && user.granted_modules ? user.granted_modules.split(',').filter(Boolean) : [];

  openModal(`
    <div class="modal-head">
      <h3>${title}</h3>
      <button class="icon-btn" data-close-modal aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="userForm">
        <div class="form-grid">
          <div class="form-group full">
            <label>Full Name</label>
            <input class="form-control" name="fullName" value="${user ? escapeHtml(user.full_name) : ''}" ${readOnly ? 'disabled' : ''} placeholder="Juan D. Cruz">
          </div>
          <div class="form-group">
            <label>Username</label>
            <input class="form-control" name="username" value="${user ? escapeHtml(user.username) : ''}" ${readOnly ? 'disabled' : ''} placeholder="jcruz">
          </div>
          <div class="form-group">
            <label>${isNew ? 'Temporary Password' : 'Reset Password'}</label>
            <div class="input-affix">
              <input class="form-control" type="password" name="password" ${readOnly ? 'disabled' : ''} placeholder="••••••••">
              <button type="button" id="userPwToggle" ${readOnly ? 'style="display:none"' : ''}>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
        </div>
        <hr class="divider">
        <label style="font-size:12.5px;font-weight:700;color:var(--gray-700);">Module Access</label>
        <p class="text-xs text-muted" style="margin:4px 0 8px;">Toggle ON to show a module in this user's sidebar.</p>
        <div class="module-switch-list">${moduleSwitchRows(activeModules)}</div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" data-close-modal>${readOnly ? 'Close' : 'Cancel'}</button>
      ${readOnly ? '' : `<button class="btn btn-primary" id="saveUserBtn">${isNew ? 'Create User' : 'Save Changes'}</button>`}
    </div>`, { size: 'wide' });

  const pwBtn = $('#userPwToggle');
  const pwInput = $('#userForm [name="password"]');
  if (pwBtn && pwInput) initPasswordToggle(pwBtn, pwInput);

  $('#saveUserBtn')?.addEventListener('click', async () => {
    const form = $('#userForm');
    if (isNew && !validateCreateUserForm(form)) return;
    closeModal();
    if (isNew) {
      try {
        const data = Object.fromEntries(new FormData(form));
        const modInputs = form.querySelectorAll('[name^="mod_"]');
        data.modules = Array.from(modInputs).filter(i => i.checked).map(i => i.name.replace('mod_', ''));
        await apiPost('users', 'create', data);
        showToast({ title: 'User created', message: 'The new account is ready to sign in.', type: 'success' });
      } catch (err) {
        showToast({ title: 'Error', message: 'Failed to create user.', type: 'error' });
      }
    } else {
      showToast({ title: 'Changes saved', message: 'User record has been updated.', type: 'success' });
    }
  });
}

/* ---------------------------- SETTINGS ---------------------------- */
async function initSettingsPage(){
  const moduleList = $('#systemModuleList');

  async function loadModules() {
    try {
      const res = await apiGet('settings', 'modules');
      const modules = res.data || [];
      moduleList.innerHTML = modules.map(m => `
        <div class="module-switch-row">
          <div class="m-info">
            <strong>${escapeHtml(m.label)}</strong>
            <span>System status — ${m.status === 'active' ? 'Operational' : 'Under maintenance'}</span>
          </div>
          <label class="switch">
            <input type="checkbox" data-module="${escapeHtml(m.key)}" ${m.status === 'active' ? 'checked' : ''}>
            <span class="track"></span>
          </label>
        </div>`).join('');
    } catch (e) {
      moduleList.innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-400);">Failed to load modules</div>';
    }
  }

  await loadModules();

  moduleList.addEventListener('change', async (e) => {
    const input = e.target.closest('[data-module]');
    if (!input) return;
    const key = input.dataset.module;
    const status = input.checked ? 'active' : 'maintenance';
    try {
      const res = await apiPost('settings', 'toggle-module', { key, status });
      if (res.success) {
        showToast({
          title: `${key} ${status === 'active' ? 'enabled' : 'set to maintenance'}`,
          message: status === 'active' ? 'Module is now available.' : 'Users will see a maintenance notice.',
          type: status === 'active' ? 'success' : 'warning'
        });
      }
    } catch (err) {
      input.checked = !input.checked;
      showToast({ title: 'Error', message: 'Failed to update module status.', type: 'error' });
    }
  });

  $('#generalSettingsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.target));
      await apiPost('settings', 'update', data);
      showToast({ title: 'Settings saved', message: 'General system settings have been updated.', type: 'success' });
    } catch (err) {
      showToast({ title: 'Error', message: 'Failed to save settings.', type: 'error' });
    }
  });
}

/* ---------------------------- PROFILE ---------------------------- */
async function initProfilePage(){
  const fullName = document.body.getAttribute('data-full-name') || 'Admin';
  const initialEl = $('#profileAvatarInitials');
  if (initialEl) initialEl.textContent = initials(fullName);
  const photoWrap = $('#profilePhotoWrap');

  initProfilePhotoUpload('profileCamBtn', 'profilePhotoInput', photoWrap, fullName);

  $('#profileForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const data = Object.fromEntries(new FormData(e.target));
      await apiPost('profile', 'update', data);
      showToast({ title: 'Profile updated', message: 'Your changes have been saved.', type: 'success' });
    } catch (err) {
      showToast({ title: 'Error', message: 'Failed to update profile.', type: 'error' });
    }
  });

  $('#changePasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const np = $('#newPassword'), cp = $('#confirmPassword');
    clearFieldError(np); clearFieldError(cp);
    if (np.value.length < 6){ setFieldError(np, 'Minimum 6 characters.'); return; }
    if (np.value !== cp.value){ setFieldError(cp, 'Passwords do not match.'); return; }
    try {
      const data = { new_password: np.value };
      await apiPost('profile', 'change-password', data);
      showToast({ title: 'Password changed', message: 'Use your new password next time you sign in.', type: 'success' });
      np.value = ''; cp.value = '';
    } catch (err) {
      showToast({ title: 'Error', message: 'Failed to change password.', type: 'error' });
    }
  });
}
