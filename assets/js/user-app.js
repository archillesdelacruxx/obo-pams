/* ==========================================================================
   PAMS — User Module App (DB-backed)
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  attachRipple();
  initUserSidebar();
  initUserHeaderDropdowns();
  initUserNavRouting();

  const page = document.body.getAttribute('data-page');
  const initFn = {
    'user-dashboard': initUserDashboardPage,
    'order-of-payment': initOrderOfPaymentPage,
    'op-records': initOpRecordsPage,
    'permit-workflow': initPermitWorkflowPage,
    'workflow-details': initWorkflowDetailsPage,
    'releasing': initReleasingPage,
    'releasing-records': initReleasingRecordsPage,
    'announcements': initAnnouncementsPage,
    'permit-approval-encoding': initPermitApprovalEncodingPage,
    'permit-encoding-form': initPermitEncodingFormPage,
    'permit-approval-records': initPermitApprovalRecordsPage,
    'inspection-schedule': initInspectionSchedulePage,
    'inspection-checklist': initInspectionChecklistPage,
    'inspection-reports': initInspectionReportsPage,
    'inspection-review': initInspectionReviewPage,
    'team-leaders': initTeamLeadersPage,
    'inspection-history': initInspectionHistoryPage,
    'user-settings': initUserSettingsPage,
    'user-profile': initUserProfilePage
  }[page];

  if (initFn) initFn();
});

function getGreeting() {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
}

/* ==========================================================================
   DASHBOARD
   ========================================================================== */
async function initUserDashboardPage() {
  const firstName = document.body.dataset.firstName || 'User';
  const greetEl = $('#userGreeting');
  if (greetEl) greetEl.textContent = `${getGreeting()}, ${firstName} 👋`;

  await Promise.all([
    loadUserStats().catch(() => {}),
    loadUserAnnouncements().catch(() => {}),
    loadUserAnalytics().catch(() => {})
  ]);

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('user-dashboard-stats', loadUserStats, 10000);
    window.PAMS_REALTIME.register('user-dashboard-announcements', loadUserAnnouncements, 30000);
    window.PAMS_REALTIME.register('user-dashboard-analytics', loadUserAnalytics, 15000);
  }

  const toggle = $('#analyticsPeriodToggle');
  if (toggle) {
    toggle.addEventListener('click', (e) => {
      const btn = e.target.closest('.seg-btn');
      if (!btn || !toggle.contains(btn)) return;
      toggle.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      loadUserAnalytics().catch(() => {});
    });
  }
}

const ANALYTICS_MODULES = [
  { key: 'op',       label: 'Order of Payment', color: '#5B8DEF' },
  { key: 'workflow', label: 'Permit Workflow',  color: '#F0A93B' },
  { key: 'approval', label: 'Permit Approved',  color: '#22A55A' },
  { key: 'releasing',label: 'Releasing',        color: '#8A94A6' }
];

function getAnalyticsModules() {
  const hasPerm = key => getPermSet().has(key);
  const modules = [];
  if (hasPerm('order-of-payment')) modules.push(ANALYTICS_MODULES[0]);
  if (hasPerm('permit-workflow')) modules.push(ANALYTICS_MODULES[1]);
  if (hasPerm('permit-approval-encoding') || hasPerm('permit-approval-records')) modules.push(ANALYTICS_MODULES[2]);
  if (hasPerm('releasing')) modules.push(ANALYTICS_MODULES[3]);
  return modules;
}

function renderAnalyticsChart(periodData, modules) {
  const maxCount = Math.max(1, ...periodData.flatMap(d => modules.map(m => d[m.key] ?? 0)));
  return periodData.map(d => {
    const bars = modules.map(m => {
      const c = d[m.key] ?? 0;
      const h = c > 0 ? Math.max(4, Math.round((c / maxCount) * 100)) : 2;
      return `<div class="bar" style="height:${h}%;background:${m.color};" title="${m.label}: ${c}"></div>`;
    }).join('');
    return `<div class="bar-group"><div class="bars-row">${bars}</div><span class="day-label">${d.label}</span></div>`;
  }).join('');
}

function renderAnalyticsLegend(modules) {
  return modules.map(m => `<div class="al-item"><i style="background:${m.color};"></i>${m.label}</div>`).join('');
}

async function loadUserAnalytics() {
  const body = $('#userAnalyticsBody');
  if (!body) return;
  const modules = getAnalyticsModules();
  if (!modules.length) {
    body.innerHTML = '<p style="text-align:center;padding:30px 0;color:var(--gray-400);font-size:13px;">No module access to show analytics.</p>';
    return;
  }

  const res = await apiGet('dashboard', 'trends').catch(() => null);
  const trends = res && res.data ? res.data : {};

  let period = 'week';
  const activeBtn = $('#analyticsPeriodToggle .seg-btn.active');
  if (activeBtn) period = activeBtn.dataset.period;

  const chartEl = document.createElement('div');
  chartEl.className = 'bar-chart grouped';
  if (period === 'month') chartEl.classList.add('monthly');
  const periodData = (trends[modules[0].key] || {})[period] || [];
  chartEl.innerHTML = renderAnalyticsChart(periodData, modules);

  const legendEl = document.createElement('div');
  legendEl.className = 'analytics-legend';
  legendEl.innerHTML = renderAnalyticsLegend(modules);

  body.innerHTML = '';
  body.appendChild(chartEl);
  body.appendChild(legendEl);
}

function getPermSet() {
  if (!window.__permSet) {
    let perms = [];
    try { perms = JSON.parse(document.body.dataset.permissions || '[]'); } catch (e) {}
    window.__permSet = new Set(perms);
  }
  return window.__permSet;
}

async function loadUserStats() {
  const hasPerm = key => getPermSet().has(key);

  const [statsRes, opRes, workflowRes] = await Promise.all([
    apiGet('dashboard', 'stats'),
    apiGet('op', 'list', { per_page: 5 }).catch(() => ({ data: [] })),
    apiGet('workflow', 'list', {}).catch(() => ({ data: [] }))
  ]);

  const stats = statsRes.data || {};

  const moduleCards = [];
  if (hasPerm('order-of-payment') && stats.op) {
    moduleCards.push({ title: 'Order of Payment', icon: 'file-text', color: 'blue', data: stats.op });
  }
  if (hasPerm('permit-workflow') && stats.workflow) {
    moduleCards.push({ title: 'Permit Workflow', icon: 'git-branch', color: 'blue', data: stats.workflow });
  }
  if ((hasPerm('permit-approval-encoding') || hasPerm('permit-approval-records')) && stats.approval) {
    moduleCards.push({ title: 'Permit Approved', icon: 'check-circle', color: 'green', data: stats.approval });
  }
  if (hasPerm('releasing') && stats.releasing) {
    moduleCards.push({ title: 'Releasing', icon: 'package', color: 'orange', data: stats.releasing });
  }
  if (hasPerm('inspection-checklist') && stats.inspection) {
    moduleCards.push({ title: 'Inspections Conducted', icon: 'clipboard', color: 'green', data: stats.inspection });
  }

  const statGrid = $('#userStatGrid');
  if (statGrid) {
    const periods = [
      { key: 'week', label: 'This Week' },
      { key: 'month', label: 'This Month' },
      { key: 'year', label: 'This Year' }
    ];
    const allCards = [];
    moduleCards.forEach(c => {
      periods.forEach(p => {
        allCards.push({ title: `${c.title} · ${p.label}`, icon: c.icon, color: c.color, value: c.data[p.key] });
      });
    });
    statGrid.innerHTML = allCards.map(card => `
      <div class="stat-card">
        <div class="top"><div class="icon icon-${card.color}">${userIcon(card.icon)}</div></div>
        <div class="figure">${card.value}</div>
        <div class="label">${card.title}</div>
      </div>`).join('');
  }

  const actFeed = $('#userRecentActivities');
  if (actFeed) {
    const activityItems = [];
    if (hasPerm('order-of-payment') && opRes.data) {
      opRes.data.slice(0, 5).forEach(r => {
        activityItems.push(`<div class="activity-item"><div class="a-icon icon-blue">${userIcon('file-text')}</div><div><p>You encoded <b>${r.official_receipt_no || r.transaction_no}</b> for <b>${r.applicant_name}</b>.</p><time>${timeAgo(r.created_at)}</time></div></div>`);
      });
    }
    if (hasPerm('permit-workflow') && workflowRes.data) {
      workflowRes.data.slice(0, 5).forEach(r => {
        activityItems.push(`<div class="activity-item"><div class="a-icon icon-blue">${userIcon('git-branch')}</div><div><p>Updated workflow for <b>${r.applicant_name}</b> (${r.application_no}).</p><time>${timeAgo(r.created_at)}</time></div></div>`);
      });
    }
    actFeed.innerHTML = activityItems.slice(0, 8).join('') || '<p style="text-align:center;padding:24px;color:var(--gray-400);font-size:13px;">No recent activities.</p>';
  }
}

async function loadUserAnnouncements() {
  const hasPerm = key => getPermSet().has(key);
  const annFeed = $('#userAnnouncementsFeed');
  if (!annFeed || !hasPerm('announcements')) return;

  const annRes = await apiGet('announcements', 'list').catch(() => ({ data: [] }));
  const annData = annRes.data || [];

  annFeed.innerHTML = annData.slice(0, 3).map(a => `
    <div class="announcement">
      <h5>${a.title}</h5>
      <p>${(a.content || '').substring(0, 100)}…</p>
    </div>`).join('');
}

/* ==========================================================================
   ORDER OF PAYMENT ENCODING
   ========================================================================== */
function initOrderOfPaymentPage() {
  const timeInEl = $('#opTimeIn');
  const timeOutEl = $('#opTimeOut');
  const timerVal = $('#timerValue');
  const timerStat = $('#timerStatus');

  function calcElapsed() {
    if (!timeInEl || !timeOutEl) return;
    const [ih, im] = (timeInEl.value || '').split(':').map(Number);
    const [oh, om] = (timeOutEl.value || '').split(':').map(Number);
    if (isNaN(ih) || isNaN(oh)) { if (timerVal) timerVal.textContent = '--:--'; return; }
    let diff = (oh * 60 + om) - (ih * 60 + im);
    if (diff < 0) diff = 0;
    const h = Math.floor(diff / 60), m = diff % 60;
    if (timerVal) timerVal.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    if (timerStat) timerStat.textContent = diff > 0 ? `${h}h ${m}m elapsed` : 'Awaiting time out';
  }

  timeInEl?.addEventListener('change', calcElapsed);
  timeOutEl?.addEventListener('change', calcElapsed);

  $('#opSaveBtn')?.addEventListener('click', async () => {
    const transactionNo = $('#opTransactionNo')?.value.trim() || $('#opOrNumber')?.value.trim();
    const applicantName = $('#opApplicantName')?.value.trim() || $('#opPermitNumber')?.value.trim();
    const permitType = $('#opPermitType')?.value;
    const amount = $('#opAmount')?.value;
    const paymentDate = $('#opPaymentDate')?.value || $('#opDate')?.value;
    if (!transactionNo || !applicantName || !permitType || !amount) {
      showToast({ title: 'Incomplete form', message: 'Please fill in Transaction No., Applicant Name, Permit Type, and Amount.', type: 'warning' });
      return;
    }
    const res = await apiPost('op', 'create', {
      transaction_no: transactionNo,
      applicant_name: applicantName,
      permit_type: permitType,
      amount: amount,
      payment_date: paymentDate || new Date().toISOString().split('T')[0],
      time_in: timeInEl?.value || null,
      time_out: timeOutEl?.value || null,
      official_receipt_no: $('#opOfficialReceiptNo')?.value.trim() || null
    });
    if (res.success) {
      showToast({ title: 'Record saved', message: `Transaction ${transactionNo} encoded successfully.`, type: 'success' });
      [
        '#opTransactionNo', '#opApplicantName', '#opPermitType', '#opAmount',
        '#opPaymentDate', '#opTimeIn', '#opTimeOut', '#opOfficialReceiptNo',
        '#opOrNumber', '#opPermitNumber', '#opDate'
      ].forEach(sel => { const el = $(sel); if (el) el.value = ''; });
      if (timerVal) timerVal.textContent = '--:--';
      if (timerStat) timerStat.textContent = 'Awaiting time in / time out';
      const dateEl = $('#opPaymentDate') || $('#opDate');
      if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
      loadOpRecentRecords();
    } else {
      showToast({ title: 'Error', message: res.error || 'Failed to save record.', type: 'danger' });
    }
  });

  $('#opClearBtn')?.addEventListener('click', () => {
    [
      '#opTransactionNo', '#opApplicantName', '#opPermitType', '#opAmount',
      '#opPaymentDate', '#opTimeIn', '#opTimeOut', '#opOfficialReceiptNo',
      '#opOrNumber', '#opPermitNumber', '#opDate'
    ].forEach(sel => { const el = $(sel); if (el) el.value = ''; });
    if (timerVal) timerVal.textContent = '--:--';
    if (timerStat) timerStat.textContent = 'Awaiting time in / time out';
    const dateEl = $('#opPaymentDate') || $('#opDate');
    if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
  });

  const dateEl = $('#opPaymentDate') || $('#opDate');
  if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];

  loadOpRecentRecords();
}

async function loadOpRecentRecords() {
  const tbody = $('#opRecentTbody');
  if (!tbody) return;
  const res = await apiGet('op', 'list', { per_page: 8 }).catch(() => ({ data: [] }));
  tbody.innerHTML = (res.data || []).map(r => `
    <tr>
      <td class="cell-mono">${r.transaction_no}</td>
      <td>${r.applicant_name}</td>
      <td>${r.permit_type || '—'}</td>
      <td>${r.amount ? Number(r.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) : '—'}</td>
      <td>${statusBadge(r.payment_status || 'Pending')}</td>
      <td>${r.payment_date ? formatDate(r.payment_date) : '—'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="View" onclick="viewOpRecord(${r.id})">${userIcon('eye')}</button>
          <button class="icon-btn" title="Edit" onclick="editOpRecord(${r.id})">${userIcon('edit')}</button>
          <button class="icon-btn" title="Delete" onclick="deleteOpRecord(${r.id})">${userIcon('trash')}</button>
        </div>
      </td>
    </tr>`).join('');
}

async function viewOpRecord(id) {
  const res = await apiGet('op', 'get', { id });
  const r = res.data || null;
  if (!r) { showToast({ title: 'Not found', message: 'Record not found.', type: 'danger' }); return; }
  openModal(`
    <div class="modal-head"><h3>OP Record · ${r.transaction_no}</h3><button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
    <div class="modal-body">
      <div class="detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Transaction No.</label><span style="font-family:var(--font-mono);font-size:14px;font-weight:600;">${r.transaction_no}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Status</label><span>${statusBadge(r.payment_status || 'Pending')}</span></div>
        <div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Applicant Name</label><span style="font-size:14px;font-weight:600;">${r.applicant_name}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Permit Type</label><span style="font-size:13.5px;">${r.permit_type || '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Amount</label><span style="font-family:var(--font-mono);font-size:14px;font-weight:600;">${r.amount ? Number(r.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) : '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Payment Date</label><span style="font-size:13.5px;">${r.payment_date ? formatDate(r.payment_date) : '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">OR No.</label><span style="font-size:13.5px;">${r.official_receipt_no || '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Time In</label><span style="font-size:13.5px;">${r.time_in || '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Time Out</label><span style="font-size:13.5px;">${r.time_out || '—'}</span></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Elapsed</label><span style="font-family:var(--font-mono);font-size:14px;font-weight:600;">${r.elapsed_minutes != null ? Math.floor(r.elapsed_minutes/60)+'h '+r.elapsed_minutes%60+'m' : '—'}</span></div>
        <div style="grid-column:1/-1;border-top:1px solid var(--gray-100);padding-top:12px;margin-top:4px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Encoded By</label><span style="font-size:13.5px;">${r.encoded_by_name || '—'}</span></div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button></div>`, { size: 'wide' });
}

async function editOpRecord(id) {
  const res = await apiGet('op', 'get', { id });
  const r = res.data || null;
  if (!r) { showToast({ title: 'Not found', message: 'Record not found.', type: 'danger' }); return; }
  const permitTypes = ['New Business Permit','Renewal — Food Services','Occupancy Permit','Zoning Clearance','Signage Permit','Ancillary/Accessory'];
  openModal(`
    <div class="modal-head"><h3>Edit OP Record · ${r.transaction_no}</h3><button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Transaction No.</label><input class="form-control" id="opEditTransNo" value="${r.transaction_no}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Status</label><select class="form-control" id="opEditStatus" style="height:36px;font-size:13px;"><option value="Pending" ${r.payment_status === 'Pending' ? 'selected' : ''}>Pending</option><option value="Paid" ${r.payment_status === 'Paid' ? 'selected' : ''}>Paid</option></select></div>
        <div style="grid-column:1/-1;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Applicant Name</label><input class="form-control" id="opEditApplicant" value="${r.applicant_name}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Permit Type</label><select class="form-control" id="opEditPermitType" style="height:36px;font-size:13px;">${permitTypes.map(t => `<option value="${t}" ${r.permit_type === t ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Amount</label><input class="form-control" id="opEditAmount" type="number" step="0.01" value="${r.amount || ''}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Payment Date</label><input class="form-control" id="opEditPayDate" type="date" value="${r.payment_date || ''}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">OR No.</label><input class="form-control" id="opEditOrNo" value="${r.official_receipt_no || ''}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Time In</label><input class="form-control" id="opEditTimeIn" type="time" value="${r.time_in || ''}" style="height:36px;font-size:13px;"></div>
        <div><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Time Out</label><input class="form-control" id="opEditTimeOut" type="time" value="${r.time_out || ''}" style="height:36px;font-size:13px;"></div>
        <div style="grid-column:1/-1;border-top:1px solid var(--gray-100);padding-top:12px;margin-top:4px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-400);display:block;margin-bottom:3px;">Encoded By</label><span style="font-size:13.5px;">${r.encoded_by_name || '—'}</span></div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button><button class="btn btn-primary" id="opEditSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save</button></div>`, { size: 'wide' });
  $('#opEditSaveBtn')?.addEventListener('click', async () => {
    const data = {
      id: r.id,
      transaction_no: $('#opEditTransNo')?.value.trim(),
      applicant_name: $('#opEditApplicant')?.value.trim(),
      permit_type: $('#opEditPermitType')?.value,
      amount: $('#opEditAmount')?.value,
      payment_date: $('#opEditPayDate')?.value,
      payment_status: $('#opEditStatus')?.value,
      official_receipt_no: $('#opEditOrNo')?.value.trim(),
      time_in: $('#opEditTimeIn')?.value || null,
      time_out: $('#opEditTimeOut')?.value || null
    };
    if (!data.transaction_no || !data.applicant_name || !data.permit_type || !data.amount) {
      showToast({ title: 'Incomplete', message: 'Transaction No., Applicant, Permit Type, and Amount are required.', type: 'warning' });
      return;
    }
    const saveBtn = $('#opEditSaveBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    const upd = await apiPost('op', 'update', data);
    if (upd.success) {
      showToast({ title: 'Updated', message: 'Record updated successfully.', type: 'success' });
      closeModal();
      loadOpRecentRecords();
      if (typeof loadOpRecordsTable === 'function') loadOpRecordsTable();
    } else {
      showToast({ title: 'Error', message: upd.error || 'Failed to update.', type: 'danger' });
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save';
    }
  });
}

async function deleteOpRecord(id) {
  if (!confirm('Delete this OP record?')) return;
  const res = await apiPost('op', 'delete', { id });
  if (res.success) { showToast({ title: 'Deleted', type: 'success', message: 'Record deleted.' }); loadOpRecentRecords(); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error }); }
}

/* ==========================================================================
   OP RECORDS
   ========================================================================== */
async function initOpRecordsPage() {
  await loadOpRecordsTable();

  $('#opRecordsRefresh')?.addEventListener('click', () => {
    loadOpRecordsTable();
    showToast({ title: 'Refreshed', message: 'Table updated.', type: 'info' });
  });
  $('#opRecordsExport')?.addEventListener('click', () => {
    const search = $('#opRecordsSearch')?.value?.trim() || '';
    const params = new URLSearchParams({ module: 'export', action: 'csv', table: 'order_of_payment' });
    if (search) params.set('search', search);
    window.open(`../../api/index.php?${params.toString()}`, '_blank');
  });

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('op-records-table', loadOpRecordsTable, 12000);
  }
}

function applyOpRecordsSearch() {
  const searchInput = $('#opRecordsSearch');
  if (!searchInput) return;
  const q = searchInput.value.toLowerCase();
  $$('#opRecordsTbody tr').forEach(row => {
    row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

async function loadOpRecordsTable() {
  const tbody = $('#opRecordsTbody');
  if (!tbody) return;
  const res = await apiGet('op', 'list').catch(() => ({ data: [] }));
  const rows = res.data || [];

  tbody.innerHTML = rows.map(r => `
    <tr>
      <td class="cell-mono">${r.transaction_no}</td>
      <td>${r.applicant_name}</td>
      <td>${r.permit_type || '—'}</td>
      <td>${r.amount ? Number(r.amount).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) : '—'}</td>
      <td>${statusBadge(r.payment_status || 'Pending')}</td>
      <td>${r.official_receipt_no || '—'}</td>
      <td>${r.payment_date ? formatDate(r.payment_date) : '—'}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="View" onclick="viewOpRecord(${r.id})">${userIcon('eye')}</button>
          <button class="icon-btn" title="Edit" onclick="editOpRecord(${r.id})">${userIcon('edit')}</button>
          <button class="icon-btn" title="Delete" onclick="deleteOpRecord(${r.id})">${userIcon('trash')}</button>
        </div>
      </td>
    </tr>`).join('');

  const searchInput = $('#opRecordsSearch');
  if (searchInput && !searchInput.dataset.opBound) {
    searchInput.dataset.opBound = '1';
    searchInput.addEventListener('input', debounce(applyOpRecordsSearch, 220));
  }
  applyOpRecordsSearch();

  renderPagination('#opRecordsPagination', rows.length);
}

/* ==========================================================================
   PERMIT WORKFLOW
   ========================================================================== */
async function initPermitWorkflowPage() {
  await loadWorkflowTable();

  $('#createWorkflowBtn')?.addEventListener('click', () => openCreateWorkflowModal(() => loadWorkflowTable()));
  $('#workflowRefresh')?.addEventListener('click', () => { loadWorkflowTable(); showToast({ title: 'Refreshed', type: 'info', message: 'Workflow table updated.' }); });
  $('#workflowExport')?.addEventListener('click', () => { window.location.href = `${API_BASE}?module=workflow&action=export`; });

  const searchInput = $('#workflowSearch');
  if (searchInput) {
    const handler = debounce((q) => { loadWorkflowTable(q); }, 300);
    searchInput.addEventListener('input', (e) => handler(e.target.value));
  }

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('workflow-table', () => loadWorkflowTable(($('#workflowSearch')?.value || '').trim()), 12000);
  }
}

async function loadWorkflowTable(search = '') {
  const tbody = $('#workflowTbody');
  if (!tbody) return;
  const res = await apiGet('workflow', 'list', search ? { search } : {}).catch(() => ({ data: [] }));
  const rows = res.data || [];
  const isAdmin = document.body.dataset.isAdmin === '1';

  tbody.innerHTML = rows.map((r, idx) => {
    const lastIn = r.latest_last_in ? formatDate(r.latest_last_in) : '—';
    const noLastOut = Number(r.latest_no_last_out) === 1;
    const lastOutHtml = noLastOut
      ? '<span style="color:var(--gray-400);font-style:italic;">No last out date for this round</span>'
      : (r.latest_last_out ? formatDate(r.latest_last_out) : (r.latest_last_in ? '<span style="color:var(--gray-400);font-style:italic;">In progress</span>' : '—'));
    const procDays = noLastOut ? '—' : (r.latest_processing_days ?? 0);
    const procDaysHtml = procDays === '—' ? '—' : `${procDays} day${procDays !== 1 ? 's' : ''}`;
    const tat = r.total_tat || 0;
    const actions = [];
    actions.push(`<button class="icon-btn" title="Edit" onclick="event.stopPropagation();editWorkflow(${r.id})">${userIcon('edit')}</button>`);
    actions.push(`<button class="icon-btn" title="Print" onclick="event.stopPropagation();printWorkflow(${r.id})">${userIcon('printer')}</button>`);
    if (isAdmin) {
      actions.push(`<button class="icon-btn" title="Delete" onclick="event.stopPropagation();deleteWorkflow(${r.id})">${userIcon('trash')}</button>`);
    }
    return `<tr style="cursor:pointer" title="Click to view details" onclick="window.location.href='workflow-details.php?id=${r.id}'">
      <td class="cell-mono">${idx + 1}</td>
      <td class="cell-mono">${r.application_no}</td>
      <td class="cell-name" title="${r.applicant_name}">${r.applicant_name}</td>
      <td><span class="round-chip">Round ${r.current_round || 1}</span></td>
      <td>${lastIn}</td>
      <td>${lastOutHtml}</td>
      <td class="tat-days">${procDaysHtml}</td>
      <td>${statusBadge(r.status)}</td>
      <td><span style="font-family:var(--font-mono);font-weight:700;">${tat} days</span></td>
      <td><div class="row-actions">${actions.join('')}</div></td>
    </tr>`;
  }).join('');

  const rc = $('#workflowRecordCount');
  if (rc) rc.textContent = rows.length + ' record' + (rows.length !== 1 ? 's' : '');
}

async function editWorkflow(id) {
  const res = await apiGet('workflow', 'get', { id });
  const r = res.data;
  if (!r) { showToast({ title: 'Not found', message: 'Workflow not found.', type: 'danger' }); return; }
  openUserModal(`
    <div class="modal-head"><h3>Edit Workflow · ${r.permit_no || r.application_no}</h3><button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button></div>
    <div class="modal-body">
      <form id="editWorkflowForm">
        <div class="form-grid">
          <div class="form-group"><label>Permit No.</label><input class="form-control" id="editPermitNo" value="${r.permit_no || ''}"></div>
          <div class="form-group"><label>Applicant Name:</label><input class="form-control" id="editApplicant" value="${r.applicant_name || ''}"></div>
          <div class="form-group"><label>App. No.</label><input class="form-control" id="editAppNo" value="${r.application_no || ''}"></div>
          <div class="form-group"><label>Application</label><select class="form-control" id="editApplication">${['BP','OC','FP','DP','EXCP','EP','ELP','MP','PP/SS','SIP','TEMP'].map(t => `<option value="${t}" ${r.project_type === t ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
          <div class="form-group"><label>Permit Type</label><select class="form-control" id="editPermitType"><option value="">Select</option><option value="Building" ${r.permit_type === 'Building' ? 'selected' : ''}>Building</option><option value="Ancillary/Accessory" ${r.permit_type === 'Ancillary/Accessory' ? 'selected' : ''}>Ancillary/Accessory</option><option value="Occupancy" ${r.permit_type === 'Occupancy' ? 'selected' : ''}>Occupancy</option></select></div>
          <div class="form-group"><label>Assessment Approval</label><input class="form-control" id="editAssessment" value="${r.assessment_approval || ''}"></div>
          <div class="form-group"><label>Date Paid</label><input class="form-control" type="date" id="editDatePaid" value="${r.date_paid || ''}"></div>
          <div class="form-group"><label>Released</label><input class="form-control" type="date" id="editReleased" value="${r.released || ''}"></div>
          <div class="form-group"><label>First In Date</label><input class="form-control" type="date" id="editFirstIn" value="${r.first_in || ''}"></div>
          <div class="form-group"><label>Status</label><select class="form-control" id="editStatus"><option value="Pending" ${r.status === 'Pending' ? 'selected' : ''}>Pending</option><option value="Under Review" ${r.status === 'Under Review' ? 'selected' : ''}>Under Review</option><option value="Approved" ${r.status === 'Approved' ? 'selected' : ''}>Approved</option><option value="Disapproved" ${r.status === 'Disapproved' ? 'selected' : ''}>Disapproved</option><option value="Released" ${r.status === 'Released' ? 'selected' : ''}>Released</option></select></div>
        </div>
      </form>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Cancel</button><button class="btn btn-primary" id="saveEditWorkflowBtn">${userIcon('check')} Save</button></div>`);
  $('#saveEditWorkflowBtn')?.addEventListener('click', async () => {
    const data = {
      id: r.id,
      permit_no: $('#editPermitNo')?.value.trim(),
      applicant_name: $('#editApplicant')?.value.trim(),
      application_no: $('#editAppNo')?.value.trim(),
      project_type: $('#editApplication')?.value,
      permit_type: $('#editPermitType')?.value,
      assessment_approval: $('#editAssessment')?.value.trim(),
      date_paid: $('#editDatePaid')?.value || null,
      released: $('#editReleased')?.value || null,
      first_in: $('#editFirstIn')?.value || null,
      status: $('#editStatus')?.value
    };
    const upd = await apiPost('workflow', 'update', data);
    if (upd.success) { closeUserModal(); showToast({ title: 'Updated', type: 'success', message: 'Workflow updated.' }); loadWorkflowTable(); }
    else { showToast({ title: 'Error', type: 'danger', message: upd.error || 'Failed to update.' }); }
  });
}

function printWorkflow(id) {
  window.open('../user/workflow-details.php?id=' + id + '&print=1', '_blank');
}

async function deleteWorkflow(id) {
  if (!confirm('Delete this workflow? All rounds will also be deleted.')) return;
  const res = await apiPost('workflow', 'delete', { id });
  if (res.success) { showToast({ title: 'Deleted', type: 'success', message: 'Workflow deleted.' }); loadWorkflowTable(); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error }); }
}

function openCreateWorkflowModal(onSuccess) {
  const todayStr = new Date().toISOString().split('T')[0];
  openUserModal(`
    <div class="modal-head">
      <h6>Create New Permit Workflow</h6>
      <button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button>
    </div>
    <div class="modal-body">
      <form id="createWorkflowForm">
        <div class="form-grid">
          <div class="form-group"><label>Permit No. <span class="req">*</span></label><input class="form-control" type="text" id="newWorkflowPermitNo" placeholder="e.g. BPLO-2026-0950"></div>
          <div class="form-group"><label>Applicant Name: <span class="req">*</span></label><input class="form-control" type="text" id="newWorkflowApplicant" placeholder="e.g. Maria Santos Cruz"></div>
          <div class="form-group"><label>App. No. <span class="req">*</span></label><input class="form-control" type="text" id="newWorkflowAppNo" placeholder="e.g. APP-2026-0950"></div>
          <div class="form-group"><label>Application <span class="req">*</span></label>
            <select class="form-control" id="newWorkflowApplication">
              <option value="">Select</option>
              <option value="BP">BP</option><option value="OC">OC</option><option value="FP">FP</option>
              <option value="DP">DP</option><option value="EXCP">EXCP</option><option value="EP">EP</option>
              <option value="ELP">ELP</option><option value="MP">MP</option><option value="PP/SS">PP/SS</option>
              <option value="SIP">SIP</option><option value="TEMP">TEMP</option>
            </select>
          </div>
          <div class="form-group"><label>Permit Type <span class="req">*</span></label>
            <select class="form-control" id="newWorkflowPermitType">
              <option value="">Select</option>
              <option value="Building">Building</option>
              <option value="Ancillary/Accessory">Ancillary/Accessory</option>
              <option value="Occupancy">Occupancy</option>
            </select>
          </div>
          <div class="form-group"><label>Assessment Approval</label><input class="form-control" type="text" id="newWorkflowAssessment" placeholder="e.g. Approved by City Engineer"></div>
          <div class="form-group"><label>Date Paid</label><input class="form-control" type="date" id="newWorkflowDatePaid" value="${todayStr}"></div>
          <div class="form-group"><label>Released</label><input class="form-control" type="date" id="newWorkflowReleased"></div>
          <div class="form-group"><label>First In Date <span class="req">*</span></label><input class="form-control" type="date" id="newWorkflowFirstIn" value="${todayStr}"></div>
          <div class="form-group"><label>Status <span class="req">*</span></label>
            <select class="form-control" id="newWorkflowStatus">
              <option value="">Select</option>
              <option value="Pending">Pending</option>
              <option value="Under Review">Under Review</option>
              <option value="Approved">Approved</option>
            </select>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" data-close-modal>Cancel</button>
      <button class="btn btn-primary" id="saveNewWorkflowBtn">${userIcon('plus')} Create Workflow</button>
    </div>`);

  $('#saveNewWorkflowBtn')?.addEventListener('click', async () => {
    const permitNo = $('#newWorkflowPermitNo')?.value.trim();
    const app = $('#newWorkflowApplicant')?.value.trim();
    const appNo = $('#newWorkflowAppNo')?.value.trim();
    const application = $('#newWorkflowApplication')?.value;
    const permitType = $('#newWorkflowPermitType')?.value;
    const assessment = $('#newWorkflowAssessment')?.value.trim() || '';
    const datePaid = $('#newWorkflowDatePaid')?.value || null;
    const released = $('#newWorkflowReleased')?.value || null;
    const tat = 0;
    const firstIn = $('#newWorkflowFirstIn')?.value;
    const status = $('#newWorkflowStatus')?.value || '';
    if (!permitNo || !app || !appNo || !application || !permitType || !firstIn || !status) {
      showToast({ title: 'Incomplete form', message: 'Please fill in all required fields (marked with *).', type: 'warning' });
      return;
    }
    const res = await apiPost('workflow', 'create', {
      permit_no: permitNo,
      applicant_name: app,
      application_no: appNo,
      project_type: application,
      permit_type: permitType,
      assessment_approval: assessment,
      date_paid: datePaid,
      released: released,
      first_in: firstIn,
      status: status
    });
    if (res.success) {
      closeUserModal();
      showToast({ title: 'Workflow Created!', message: `Permit ${permitNo} has been added.`, type: 'success' });
      if (typeof onSuccess === 'function') onSuccess();
    } else {
      showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to create workflow.' });
    }
  });
}

/* ==========================================================================
   WORKFLOW DETAILS
   ========================================================================== */
async function initWorkflowDetailsPage() {
  const params = new URLSearchParams(window.location.search);
  const workflowId = params.get('id');

  const loadingEl = $('#wdLoading');
  const errorEl = $('#wdError');
  const emptyEl = $('#wdEmpty');
  const contentEl = $('#wdContent');

  if (loadingEl) loadingEl.style.display = '';
  if (errorEl) errorEl.style.display = 'none';
  if (emptyEl) emptyEl.style.display = 'none';
  if (contentEl) contentEl.style.display = 'none';

  let res;
  try {
    res = await apiGet('workflow', 'get', { id: workflowId || 0 });
  } catch (e) {
    if (loadingEl) loadingEl.style.display = 'none';
    if (errorEl) errorEl.style.display = '';
    return;
  }
  const data = res.data;

  if (loadingEl) loadingEl.style.display = 'none';

  if (!data || !data.id) {
    if (emptyEl) emptyEl.style.display = '';
    return;
  }

  if (contentEl) contentEl.style.display = '';

  $('#wdPermitNumber') && ($('#wdPermitNumber').textContent = data.permit_no || data.application_no || '—');
  $('#wdApplicant') && ($('#wdApplicant').textContent = data.applicant_name || '—');
  $('#wdAppNo') && ($('#wdAppNo').textContent = data.application_no || '—');
  $('#wdApplication') && ($('#wdApplication').textContent = data.project_type || '—');
  $('#wdType') && ($('#wdType').textContent = data.permit_type || data.current_stage || '—');
  $('#wdCurrentRound') && ($('#wdCurrentRound').textContent = `Round ${data.current_round || 1}`);

  const statusEl = $('#wdStatus');
  if (statusEl) statusEl.innerHTML = statusBadge(data.status);

  const isAdmin = document.body.dataset.isAdmin === '1';
  const tbody = $('#wdTimelineTbody');
  if (tbody && data.rounds && data.rounds.length) {
    tbody.innerHTML = data.rounds.map(r => {
      const lastIn = r.last_in ? formatDate(r.last_in) : '—';
      const noLastOut = Number(r.no_last_out) === 1;
      const lastOutHtml = noLastOut
        ? '<span style="color:var(--gray-400);font-style:italic;">No last out date for this round</span>'
        : (r.last_out ? formatDate(r.last_out) : '<span style="color:var(--gray-400);font-style:italic;">In progress</span>');
      const roundTat = noLastOut ? 0 : businessDaysBetween(r.last_in, r.last_out);
      const roundTatHtml = noLastOut ? '—' : `${roundTat} day${roundTat !== 1 ? 's' : ''}`;
      const actions = [];
      actions.push(`<button class="icon-btn" title="Edit Round" onclick="editRound(${data.id}, ${r.round_number})">${userIcon('edit')}</button>`);
      if (isAdmin) {
        actions.push(`<button class="icon-btn" title="Delete Round" onclick="deleteRound(${data.id}, ${r.round_number})">${userIcon('trash')}</button>`);
      }
      return `<tr>
        <td><span class="round-badge ${r.round_number === data.current_round ? 'current' : ''}">Round ${r.round_number}</span></td>
        <td class="cell-mono">${data.application_no || '—'}</td>
        <td>${lastIn}</td>
        <td>${lastOutHtml}</td>
        <td class="tat-days">${roundTatHtml}</td>
        <td class="remarks-cell">${r.remarks || ''}</td>
        <td><div class="row-actions">${actions.join('')}</div></td>
      </tr>`;
    }).join('');
  } else if (tbody) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--gray-400);font-style:italic;">No rounds yet. Click "Add Round" to start.</td></tr>';
  }

const roundsList = data.rounds || [];
  const firstInDate = roundsList.length ? roundsList[0].last_in : null;
  const lastValidOutRound = roundsList.filter(r => r.last_out && Number(r.no_last_out) !== 1).pop();
  const lastOutDate = lastValidOutRound ? lastValidOutRound.last_out : null;
  const totalTat = businessDaysBetween(firstInDate, lastOutDate);
  $('#wdTotalRounds') && ($('#wdTotalRounds').textContent = (data.rounds || []).length || '0');
  $('#wdTotalTat') && ($('#wdTotalTat').textContent = totalTat + ' days');
  $('#wdLastUpdated') && ($('#wdLastUpdated').textContent = data.updated_at ? formatDate(data.updated_at) : '—');

  $('#addRoundBtn')?.addEventListener('click', () => openAddRoundModal(data));

  if (params.get('print') === '1') {
    setTimeout(() => window.print(), 500);
  }
}

function openAddRoundModal(data) {
  const nextRound = (data.rounds || []).length + 1;
  const todayStr = new Date().toISOString().split('T')[0];
  openUserModal(`
    <div class="modal-head">
      <h3>Add Round ${nextRound}</h3>
      <button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button>
    </div>
    <div class="modal-body">
      <form id="addRoundForm">
        <div class="form-grid">
          <div class="form-group"><label>Last In Date</label><input class="form-control" type="date" id="newRoundIn" value="${todayStr}"></div>
          <div class="form-group"><label>Last Out Date</label><input class="form-control" type="date" id="newRoundOut"></div>
          <div class="form-group full"><label class="checkbox-label"><input type="checkbox" id="newRoundNoOut"> No last out date for this round</label></div>
          <div class="form-group full"><label>Remarks</label><textarea class="form-control" id="newRoundRemarks" rows="3" placeholder="Enter remarks…"></textarea></div>
        </div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" data-close-modal>Cancel</button>
      <button class="btn btn-primary" id="saveRoundBtn">${userIcon('plus')} Add Round</button>
    </div>`);

  const toggleNewRoundOut = () => {
    const outEl = $('#newRoundOut');
    const chk = $('#newRoundNoOut');
    if (outEl && chk) {
      outEl.disabled = chk.checked;
      if (chk.checked) outEl.value = '';
    }
  };
  $('#newRoundNoOut')?.addEventListener('change', toggleNewRoundOut);

  $('#saveRoundBtn')?.addEventListener('click', async () => {
    const lastIn = $('#newRoundIn')?.value;
    const noLastOut = !!$('#newRoundNoOut')?.checked;
    const lastOut = noLastOut ? null : ($('#newRoundOut')?.value || null);
    const remarks = $('#newRoundRemarks')?.value.trim() || '';
    const res = await apiPost('workflow', 'add-round', { workflow_id: data.id, last_in: lastIn, last_out: lastOut, no_last_out: noLastOut ? 1 : 0, remarks });
    if (res.success) {
      closeUserModal();
      if (lastOut) {
        confirmApproveStatus(data.id, nextRound);
      } else {
        showToast({ title: 'Round added', message: `Round ${nextRound} added to ${data.application_no}.`, type: 'success' });
        setTimeout(() => window.location.reload(), 500);
      }
    } else {
      showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to add round.' });
    }
});
}

async function editRound(workflowId, roundNumber) {
  const res = await apiGet('workflow', 'get', { id: workflowId });
  const data = res.data;
  if (!data) { showToast({ title: 'Error', message: 'Workflow not found.', type: 'danger' }); return; }
  const round = (data.rounds || []).find(r => r.round_number === roundNumber);
  if (!round) { showToast({ title: 'Error', message: 'Round not found.', type: 'danger' }); return; }
  openUserModal(`
    <div class="modal-head"><h3>Edit Round ${roundNumber}</h3><button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button></div>
    <div class="modal-body">
      <form id="editRoundForm">
        <div class="form-grid">
          <div class="form-group"><label>Last In Date</label><input class="form-control" type="date" id="editRoundIn" value="${round.last_in || ''}"></div>
          <div class="form-group"><label>Last Out Date</label><input class="form-control" type="date" id="editRoundOut" value="${round.last_out || ''}"></div>
          <div class="form-group full"><label class="checkbox-label"><input type="checkbox" id="editRoundNoOut" ${Number(round.no_last_out) === 1 ? 'checked' : ''}> No last out date for this round</label></div>
          <div class="form-group"><label>TAT (Auto)</label><input class="form-control" type="number" id="editRoundDays" value="0" min="0" readonly title="Auto-calculated from dates (excludes weekends)"></div>
          <div class="form-group full"><label>Remarks</label><textarea class="form-control" id="editRoundRemarks" rows="3" placeholder="Enter remarks…">${round.remarks || ''}</textarea></div>
        </div>
      </form>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Cancel</button><button class="btn btn-primary" id="saveEditRoundBtn">${userIcon('check')} Save</button></div>`);
  const syncRoundDays = () => {
    const daysEl = $('#editRoundDays');
    const noOut = !!$('#editRoundNoOut')?.checked;
    if (daysEl) daysEl.value = noOut ? 0 : businessDaysBetween($('#editRoundIn')?.value, $('#editRoundOut')?.value);
  };
  const toggleEditRoundOut = () => {
    const outEl = $('#editRoundOut');
    const chk = $('#editRoundNoOut');
    if (outEl && chk) {
      outEl.disabled = chk.checked;
      if (chk.checked) outEl.value = '';
    }
    syncRoundDays();
  };
  $('#editRoundNoOut')?.addEventListener('change', toggleEditRoundOut);
  $('#editRoundIn')?.addEventListener('change', syncRoundDays);
  $('#editRoundOut')?.addEventListener('change', syncRoundDays);
  toggleEditRoundOut();
  $('#saveEditRoundBtn')?.addEventListener('click', async () => {
    const lastIn = $('#editRoundIn')?.value || null;
    const noLastOut = !!$('#editRoundNoOut')?.checked;
    const lastOut = noLastOut ? null : ($('#editRoundOut')?.value || null);
    const remarks = $('#editRoundRemarks')?.value.trim() || '';
    const upd = await apiPost('workflow', 'update-round', { workflow_id: workflowId, round_number: roundNumber, last_in: lastIn, last_out: lastOut, no_last_out: noLastOut ? 1 : 0, remarks });
    if (upd.success) {
      closeUserModal();
      if (lastOut) {
        confirmApproveStatus(workflowId, roundNumber);
      } else {
        showToast({ title: 'Updated', type: 'success', message: `Round ${roundNumber} updated.` });
        setTimeout(() => window.location.reload(), 500);
      }
    }
    else { showToast({ title: 'Error', type: 'danger', message: upd.error || 'Failed to update round.' }); }
  });
}

async function deleteRound(workflowId, roundNumber) {
  if (!confirm(`Delete Round ${roundNumber}? This cannot be undone.`)) return;
  const res = await apiPost('workflow', 'delete-round', { workflow_id: workflowId, round_number: roundNumber });
  if (res.success) { showToast({ title: 'Deleted', type: 'success', message: `Round ${roundNumber} deleted.` }); setTimeout(() => window.location.reload(), 500); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to delete round.' }); }
}

function confirmApproveStatus(workflowId, roundNumber) {
  openUserModal(`
    <div class="modal-head"><h3>Round ${roundNumber} is complete</h3><button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button></div>
    <div class="modal-body">
      <p>This round has a last out date. Change the workflow status to <strong>Approved</strong>?</p>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" id="cancelApproveStatusBtn">Cancel</button><button class="btn btn-primary" id="confirmApproveStatusBtn">${userIcon('check')} Approve</button></div>`);
  $('#confirmApproveStatusBtn')?.addEventListener('click', async () => {
    const res = await apiPost('workflow', 'update-status', { workflow_id: workflowId, status: 'Approved', stage: 'Completed' });
    closeUserModal();
    if (res.success) {
      showToast({ title: 'Approved', type: 'success', message: 'Workflow marked as Approved.' });
    } else {
      showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to update status.' });
    }
    setTimeout(() => window.location.reload(), 500);
  });
  $('#cancelApproveStatusBtn')?.addEventListener('click', () => {
    closeUserModal();
    setTimeout(() => window.location.reload(), 300);
  });
}

/* ==========================================================================
   PERMIT APPROVAL
   ========================================================================== */
const PERMIT_TYPE_OPTIONS = {
  building: ['Building Permit'],
  occupancy: ['Occupancy Permit', 'Certificate of Occupancy', 'Business Permit', 'Permit to Operate'],
  sign: ['Sign Permit'],
  mechanical: ['Mechanical Permit'],
  fencing: ['Fencing Permit'],
  plumbing: ['Plumbing / Sanitary'],
  coe: ['COE Certificate of Operation'],
  cfei: ['CFEI'],
  electrical: ['Electrical Permit'],
  electronics: ['Electronics Permit'],
  excavation: ['Excavation Permit'],
  demolition: ['Demolition Permit'],
  'temporary-sidewalk': ['Temporary Sidewalk Permit']
};

function initPermitApprovalEncodingPage() {
  $('#createWorkflowFromApprovedBtn')?.addEventListener('click', () => openCreateWorkflowModal(() => loadApprovalTable()));
  loadApprovalTable();
}

function initPermitEncodingFormPage() {
  const typeKey = document.body.dataset.type || '';

  function clearForm(selectors) {
    selectors.forEach(sel => { const el = $(sel); if (el) el.value = ''; });
  }

  function handleSave(res, message, flds) {
    if (res.success) {
      showToast({ title: 'Approval saved', message, type: 'success' });
      clearForm(flds);
      setTimeout(() => { window.location.href = 'permit-approval-encoding.php'; }, 1200);
    } else {
      showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to save approval.' });
    }
  }

  if (typeKey === 'building') {
    const flds = ['#bBpNo', '#bApplicant', '#bLocation', '#bOccType', '#bDateReceived', '#bDateApproved'];
    $('#buildingClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#buildingSaveBtn')?.addEventListener('click', async () => {
      const bpNo = $('#bBpNo')?.value.trim();
      const applicant = $('#bApplicant')?.value.trim();
      const location = $('#bLocation')?.value.trim();
      const occType = $('#bOccType')?.value.trim();
      const dateReceived = $('#bDateReceived')?.value;
      const dateApproved = $('#bDateApproved')?.value;
      if (!bpNo || !applicant || !location || !occType || !dateReceived || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Building Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: bpNo,
        applicant_name: applicant,
        permit_type: 'Building Permit',
        approval_date: dateApproved,
        tat: 0,
        bp_no: bpNo,
        location,
        type_of_occupancy: occType,
        date_received: dateReceived,
        date_approved: dateApproved
      });
      handleSave(res, `Building permit ${bpNo} approved.`, flds);
    });
  } else if (typeKey === 'occupancy') {
    const flds = ['#oOpNo', '#oApplicant', '#oDateReceived', '#oDateApproved'];
    $('#occupancyClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#occupancySaveBtn')?.addEventListener('click', async () => {
      const opNo = $('#oOpNo')?.value.trim();
      const applicant = $('#oApplicant')?.value.trim();
      const dateReceived = $('#oDateReceived')?.value;
      const dateApproved = $('#oDateApproved')?.value;
      if (!opNo || !applicant || !dateReceived || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Occupancy Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: opNo,
        applicant_name: applicant,
        permit_type: 'Occupancy Permit',
        approval_date: dateApproved,
        tat: 0,
        location: '',
        type_of_occupancy: '',
        date_received: dateReceived,
        date_approved: dateApproved
      });
      handleSave(res, `Occupancy permit ${opNo} approved.`, flds);
    });
  } else if (typeKey === 'mechanical') {
    const flds = ['#mMpNo', '#mApplicant', '#mLocation', '#mOccType', '#mBldgCost', '#mMaNo', '#mIncharge', '#mOrNo', '#mFees', '#mDatePaid', '#mReceivedBy'];
    $('#mechanicalClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#mechanicalSaveBtn')?.addEventListener('click', async () => {
      const mpNo = $('#mMpNo')?.value.trim();
      const applicant = $('#mApplicant')?.value.trim();
      const location = $('#mLocation')?.value.trim();
      const occType = $('#mOccType')?.value.trim();
      const bldgCost = $('#mBldgCost')?.value;
      const maNo = $('#mMaNo')?.value.trim();
      const incharge = $('#mIncharge')?.value.trim();
      const orNo = $('#mOrNo')?.value.trim();
      const fees = $('#mFees')?.value;
      const datePaid = $('#mDatePaid')?.value;
      const receivedBy = $('#mReceivedBy')?.value.trim();
      if (!mpNo || !applicant || !location || !occType || !bldgCost || !maNo || !incharge || !orNo || !fees || !datePaid || !receivedBy) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Mechanical Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: mpNo,
        applicant_name: applicant,
        permit_type: 'Mechanical Permit',
        approval_date: null,
        tat: 0,
        location,
        type_of_occupancy: occType,
        bldg_cost: parseFloat(bldgCost),
        permit_no: maNo,
        incharge,
        or_no: orNo,
        fees: parseFloat(fees),
        date_paid: datePaid,
        received_by: receivedBy
      });
      handleSave(res, `Mechanical permit ${mpNo} approved.`, flds);
    });
  } else if (typeKey === 'plumbing') {
    const flds = ['#pPlSpNo', '#pApplicant', '#pLocation', '#pOccType', '#pBldgCost', '#pFees', '#pOrNo', '#pPlSpAppNo', '#pDatePaid', '#pIncharge', '#pReceivedBy', '#pDateApproved'];
    $('#plumbingClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#plumbingSaveBtn')?.addEventListener('click', async () => {
      const plSpNo = $('#pPlSpNo')?.value.trim();
      const applicant = $('#pApplicant')?.value.trim();
      const location = $('#pLocation')?.value.trim();
      const occType = $('#pOccType')?.value.trim();
      const bldgCost = $('#pBldgCost')?.value;
      const fees = $('#pFees')?.value;
      const orNo = $('#pOrNo')?.value.trim();
      const plSpAppNo = $('#pPlSpAppNo')?.value.trim();
      const datePaid = $('#pDatePaid')?.value;
      const incharge = $('#pIncharge')?.value.trim();
      const receivedBy = $('#pReceivedBy')?.value.trim();
      const dateApproved = $('#pDateApproved')?.value;
      if (!plSpNo || !applicant || !location || !occType || !bldgCost || !fees || !orNo || !plSpAppNo || !datePaid || !incharge || !receivedBy || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Plumbing/Sanitary Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: plSpNo,
        applicant_name: applicant,
        permit_type: 'Plumbing / Sanitary',
        approval_date: dateApproved,
        tat: 0,
        location,
        type_of_occupancy: occType,
        bldg_cost: parseFloat(bldgCost),
        fees: parseFloat(fees),
        or_no: orNo,
        permit_no: plSpAppNo,
        date_paid: datePaid,
        incharge,
        received_by: receivedBy,
        date_approved: dateApproved
      });
      handleSave(res, `Plumbing/Sanitary permit ${plSpNo} approved.`, flds);
    });
  } else if (typeKey === 'sign') {
    const flds = ['#sSignNo', '#sApplicant', '#sLocation', '#sFees', '#sOrNo', '#sDatePaid', '#sReceivedBy', '#sDateOop', '#sDateApproved'];
    $('#signClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#signSaveBtn')?.addEventListener('click', async () => {
      const signNo = $('#sSignNo')?.value.trim();
      const applicant = $('#sApplicant')?.value.trim();
      const location = $('#sLocation')?.value.trim();
      const fees = $('#sFees')?.value;
      const orNo = $('#sOrNo')?.value.trim();
      const datePaid = $('#sDatePaid')?.value;
      const receivedBy = $('#sReceivedBy')?.value.trim();
      const dateOop = $('#sDateOop')?.value;
      const dateApproved = $('#sDateApproved')?.value;
      if (!signNo || !applicant || !location || !fees || !orNo || !datePaid || !receivedBy || !dateOop || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Sign Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: signNo,
        applicant_name: applicant,
        permit_type: 'Sign Permit',
        approval_date: dateApproved,
        tat: 0,
        location,
        fees: parseFloat(fees),
        or_no: orNo,
        date_paid: datePaid,
        received_by: receivedBy,
        date_oop: dateOop,
        date_approved: dateApproved
      });
      handleSave(res, `Sign permit ${signNo} approved.`, flds);
    });
  } else if (typeKey === 'electronics') {
    const flds = ['#eceNo', '#eceApplicant', '#eceLocation', '#eceOccType', '#eceContractor', '#eceOthers', '#eceSurcharge', '#eceBldgCost', '#eceFees', '#eceEceaNo', '#eceOrNo', '#eceDatePaid', '#eceDateOop', '#eceDateApproved'];
    $('#electronicsClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#electronicsSaveBtn')?.addEventListener('click', async () => {
      const eceNo = $('#eceNo')?.value.trim();
      const applicant = $('#eceApplicant')?.value.trim();
      const location = $('#eceLocation')?.value.trim();
      const occType = $('#eceOccType')?.value.trim();
      const contractor = $('#eceContractor')?.value.trim();
      const others = $('#eceOthers')?.value;
      const surcharge = $('#eceSurcharge')?.value;
      const bldgCost = $('#eceBldgCost')?.value;
      const fees = $('#eceFees')?.value;
      const eceaNo = $('#eceEceaNo')?.value.trim();
      const orNo = $('#eceOrNo')?.value.trim();
      const datePaid = $('#eceDatePaid')?.value;
      const dateOop = $('#eceDateOop')?.value;
      const dateApproved = $('#eceDateApproved')?.value;
      if (!eceNo || !applicant || !location || !occType || !contractor || !others || !surcharge || !bldgCost || !fees || !eceaNo || !orNo || !datePaid || !dateOop || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Electronics Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: eceNo,
        applicant_name: applicant,
        permit_type: 'Electronics Permit',
        approval_date: dateApproved,
        tat: 0,
        location,
        type_of_occupancy: occType,
        contractor,
        land_others: parseFloat(others),
        surcharge: parseFloat(surcharge),
        bldg_cost: parseFloat(bldgCost),
        fees: parseFloat(fees),
        permit_no: eceaNo,
        or_no: orNo,
        date_paid: datePaid,
        date_oop: dateOop,
        date_approved: dateApproved
      });
      handleSave(res, `Electronics permit ${eceNo} approved.`, flds);
    });
  } else if (typeKey === 'electrical') {
    const flds = ['#eEpNo', '#eApplicant', '#eLocation', '#eOccType', '#eCost', '#eOrNo', '#eEaNo', '#eFees', '#eDatePaid', '#eCharge', '#eReceivedBy', '#eDateApproved'];
    $('#electricalClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#electricalSaveBtn')?.addEventListener('click', async () => {
      const epNo = $('#eEpNo')?.value.trim();
      const applicant = $('#eApplicant')?.value.trim();
      const location = $('#eLocation')?.value.trim();
      const occType = $('#eOccType')?.value.trim();
      const cost = $('#eCost')?.value;
      const orNo = $('#eOrNo')?.value.trim();
      const eaNo = $('#eEaNo')?.value.trim();
      const fees = $('#eFees')?.value;
      const datePaid = $('#eDatePaid')?.value;
      const charge = $('#eCharge')?.value;
      const receivedBy = $('#eReceivedBy')?.value.trim();
      const dateApproved = $('#eDateApproved')?.value;
      if (!epNo || !applicant || !location || !occType || !cost || !orNo || !eaNo || !fees || !datePaid || !charge || !receivedBy || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the Electrical Permit.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: epNo,
        applicant_name: applicant,
        permit_type: 'Electrical Permit',
        approval_date: dateApproved,
        tat: 0,
        location,
        type_of_occupancy: occType,
        bldg_cost: parseFloat(cost),
        or_no: orNo,
        permit_no: eaNo,
        fees: parseFloat(fees),
        date_paid: datePaid,
        incharge: charge,
        received_by: receivedBy,
        date_approved: dateApproved
      });
      handleSave(res, `Electrical permit ${epNo} approved.`, flds);
    });
  } else if (typeKey === 'coe') {
    const flds = ['#cNo', '#cApplicant', '#cLocation', '#cMpNo', '#cOccType', '#cPme', '#cFees', '#cOrNo', '#cDatePaid', '#cReceivedBy', '#cDateApproved'];
    $('#coeClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#coeSaveBtn')?.addEventListener('click', async () => {
      const no = $('#cNo')?.value.trim();
      const applicant = $('#cApplicant')?.value.trim();
      const location = $('#cLocation')?.value.trim();
      const mpNo = $('#cMpNo')?.value.trim();
      const occType = $('#cOccType')?.value.trim();
      const pme = $('#cPme')?.value.trim();
      const fees = $('#cFees')?.value;
      const orNo = $('#cOrNo')?.value.trim();
      const datePaid = $('#cDatePaid')?.value;
      const receivedBy = $('#cReceivedBy')?.value.trim();
      const dateApproved = $('#cDateApproved')?.value;
      if (!no || !applicant || !location || !mpNo || !occType || !pme || !fees || !orNo || !datePaid || !receivedBy || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for the COE.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: no,
        applicant_name: applicant,
        permit_type: 'COE Certificate of Operation',
        approval_date: dateApproved,
        tat: 0,
        location,
        permit_no: mpNo,
        type_of_occupancy: occType,
        incharge: pme,
        fees: parseFloat(fees),
        or_no: orNo,
        date_paid: datePaid,
        received_by: receivedBy,
        date_approved: dateApproved
      });
      handleSave(res, `COE ${no} approved.`, flds);
    });
  } else if (typeKey === 'cfei') {
    const flds = ['#cfNo', '#cfApplicant', '#cfLocation', '#cfOccType', '#cfPee', '#cfIncharge', '#cfDateReceived', '#cfDateApproved'];
    $('#cfeiClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#cfeiSaveBtn')?.addEventListener('click', async () => {
      const cfNo = $('#cfNo')?.value.trim();
      const applicant = $('#cfApplicant')?.value.trim();
      const location = $('#cfLocation')?.value.trim();
      const occType = $('#cfOccType')?.value.trim();
      const pee = $('#cfPee')?.value.trim();
      const incharge = $('#cfIncharge')?.value.trim();
      const dateReceived = $('#cfDateReceived')?.value;
      const dateApproved = $('#cfDateApproved')?.value;
      if (!cfNo || !applicant || !location || !occType || !pee || !incharge || !dateReceived || !dateApproved) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields for CFEI.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: cfNo,
        applicant_name: applicant,
        permit_type: 'CFEI',
        approval_date: dateApproved,
        tat: 0,
        location,
        type_of_occupancy: occType,
        contractor: pee,
        incharge,
        date_received: dateReceived,
        date_approved: dateApproved
      });
      handleSave(res, `CFEI ${cfNo} approved.`, flds);
    });
  } else if (typeKey === 'fencing' || typeKey === 'excavation' || typeKey === 'demolition') {
    const permitTypeName = (PERMIT_TYPE_OPTIONS[typeKey] || [typeKey])[0];
    const flds = ['#fFpNo', '#fApplicant', '#fLocation', '#fOccType', '#fContractor', '#fLandOthers', '#fSurcharge', '#fArea', '#fCost', '#fLineGrade', '#fFees', '#fFanNo', '#fDatePaid', '#fOrNo', '#fReceivedBy', '#fDateApproved'];
    $('#fencingClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#fencingSaveBtn')?.addEventListener('click', async () => {
      const fpNo = $('#fFpNo')?.value.trim();
      const applicant = $('#fApplicant')?.value.trim();
      const location = $('#fLocation')?.value.trim();
      const occType = $('#fOccType')?.value.trim();
      const contractor = $('#fContractor')?.value.trim();
      const landOthers = $('#fLandOthers')?.value;
      const surcharge = $('#fSurcharge')?.value;
      const area = $('#fArea')?.value;
      const cost = $('#fCost')?.value;
      const lineGrade = $('#fLineGrade')?.value.trim();
      const fees = $('#fFees')?.value;
      const fanNo = $('#fFanNo')?.value.trim();
      const datePaid = $('#fDatePaid')?.value;
      const orNo = $('#fOrNo')?.value.trim();
      const receivedBy = $('#fReceivedBy')?.value.trim();
      const dateApproved = $('#fDateApproved')?.value;
      if (!fpNo || !applicant || !location || !occType || !contractor || !landOthers || !surcharge || !area || !cost || !lineGrade || !fees || !fanNo || !datePaid || !orNo || !receivedBy || !dateApproved) {
        showToast({ title: 'Incomplete form', message: `Please fill in all required fields for the ${permitTypeName}.`, type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: fpNo,
        applicant_name: applicant,
        permit_type: permitTypeName,
        approval_date: dateApproved,
        tat: 0,
        location,
        type_of_occupancy: occType,
        contractor,
        land_others: parseFloat(landOthers),
        surcharge: parseFloat(surcharge),
        area: parseFloat(area),
        bldg_cost: parseFloat(cost),
        line_grade: lineGrade,
        fees: parseFloat(fees),
        permit_no: fanNo,
        date_paid: datePaid,
        or_no: orNo,
        received_by: receivedBy,
        date_approved: dateApproved
      });
      handleSave(res, `${permitTypeName} ${fpNo} approved.`, flds);
    });
  } else {
    const sel = $('#gPermitType');
    const opts = PERMIT_TYPE_OPTIONS[typeKey] || [typeKey.replace(/-/g, ' ').replace(/^\w/, c => c.toUpperCase())];
    if (sel) {
      sel.innerHTML = opts.map(t => `<option value="${t}">${t}</option>`).join('');
      sel.value = opts[0];
    }
    const flds = ['#gAppNo', '#gApplicant', '#gPermitType', '#gDate', '#gTat'];
    $('#gClearBtn')?.addEventListener('click', () => clearForm(flds));
    $('#gSaveBtn')?.addEventListener('click', async () => {
      const applicationNo = $('#gAppNo')?.value.trim();
      const applicantName = $('#gApplicant')?.value.trim();
      const permitType = $('#gPermitType')?.value;
      const approvalDate = $('#gDate')?.value;
      const tatVal = $('#gTat')?.value;
      if (!applicationNo || !applicantName || !permitType || !approvalDate) {
        showToast({ title: 'Incomplete form', message: 'Please fill in all required fields.', type: 'warning' });
        return;
      }
      const res = await apiPost('approval', 'create', {
        application_no: applicationNo,
        applicant_name: applicantName,
        permit_type: permitType,
        approval_date: approvalDate,
        tat: tatVal ? parseInt(tatVal) : 0
      });
      handleSave(res, `Application ${applicationNo} approved.`, flds);
    });
  }
}

async function loadApprovalTable() {
  const tbody = $('#approvedTbody');
  if (!tbody) return;
  const res = await apiGet('approval', 'list').catch(() => ({ data: [] }));
  const rows = (res.data || []).slice(0, 5);
  tbody.innerHTML = rows.map(r => `
    <tr>
      <td class="cell-mono">${r.application_no}</td>
      <td>${r.applicant_name}</td>
      <td>${r.permit_type || '—'}</td>
      <td>${r.approval_date ? formatDate(r.approval_date) : '—'}</td>
      <td><span style="font-family:var(--font-mono);font-weight:700;">${r.tat} days</span></td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" title="Delete" onclick="deleteApproval(${r.id})">${userIcon('x')}</button>
        </div>
      </td>
    </tr>`).join('');
}

async function deleteApproval(id) {
  if (!confirm('Delete this approval record?')) return;
  const res = await apiPost('approval', 'delete', { id });
  if (res.success) { showToast({ title: 'Deleted', type: 'success', message: 'Approval record deleted.' }); loadApprovalTable(); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error }); }
}

function initPermitApprovalRecordsPage() {
  $('#recordsRefresh')?.addEventListener('click', () => {
    loadApprovalRecordsTable();
    showToast({ title: 'Refreshed', type: 'info', message: 'Records updated.' });
  });

  $('#recordsSearch')?.addEventListener('input', debounce(() => loadApprovalRecordsTable(), 300));
  $('#recordsDateFrom')?.addEventListener('change', loadApprovalRecordsTable);
  $('#recordsDateTo')?.addEventListener('change', loadApprovalRecordsTable);

  $('#recordsExport')?.addEventListener('click', () => {
    const params = new URLSearchParams({ module: 'export', action: 'csv', table: 'permit_approval' });
    const search = $('#recordsSearch')?.value.trim();
    const from = $('#recordsDateFrom')?.value;
    const to = $('#recordsDateTo')?.value;
    if (search) params.set('search', search);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);
    window.open(`../../api/index.php?${params.toString()}`, '_blank');
  });

  loadApprovalRecordsTable();

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('approval-records', loadApprovalRecordsTable, 12000);
  }
}

async function loadApprovalRecordsTable() {
  const tbody = $('#approvedRecordsTbody');
  if (!tbody) return;
  const params = {};
  const search = $('#recordsSearch')?.value.trim();
  const from = $('#recordsDateFrom')?.value;
  const to = $('#recordsDateTo')?.value;
  if (search) params.search = search;
  if (from) params.date_from = from;
  if (to) params.date_to = to;
  const res = await apiGet('approval', 'list', params).catch(() => ({ data: [] }));
  const rows = res.data || [];
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:28px;">No records found.</td></tr>';
  } else {
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td class="cell-mono">${r.application_no}</td>
        <td>${r.applicant_name}</td>
        <td>${r.permit_type || '—'}</td>
        <td>${r.approval_date ? formatDate(r.approval_date) : '—'}</td>
        <td>${r.approved_by_name || '—'}</td>
      </tr>`).join('');
  }
}

/* ==========================================================================
   RELEASING
   ========================================================================== */
function initReleasingPage() {
  const relDateEl = $('#relDate');
  if (relDateEl) relDateEl.value = new Date().toISOString().split('T')[0];

  $('#relSaveBtn')?.addEventListener('click', async () => {
    const permitAppNo = $('#relPermitAppNo')?.value.trim() || $('#relPermitNumber')?.value.trim();
    const applicant = $('#relApplicant')?.value.trim();
    const dateReleased = relDateEl?.value || new Date().toISOString().split('T')[0];
    const claimedBy = $('#relClaimedBy')?.value.trim() || '';
    const timeReleased = $('#relTimeReleased')?.value || null;
    if (!permitAppNo || !applicant) {
      showToast({ title: 'Incomplete form', message: 'Please fill in all required fields.', type: 'warning' });
      return;
    }
    const res = await apiPost('releasing', 'create', {
      permit_application_no: permitAppNo,
      applicant_name: applicant,
      date_released: dateReleased,
      claimed_by: claimedBy,
      time_released: timeReleased
    });
    if (res.success) {
      showToast({ title: 'Record saved', message: `Release record for ${applicant} saved.`, type: 'success' });
      ['#relDate', '#relPermitAppNo', '#relPermitNumber', '#relApplicant', '#relClaimedBy', '#relTimeReleased'].forEach(sel => { const el = $(sel); if (el) el.value = ''; });
      if (relDateEl) relDateEl.value = new Date().toISOString().split('T')[0];
      loadRelTodayRecords();
    } else {
      showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to save.' });
    }
  });

  $('#relClearBtn')?.addEventListener('click', () => {
    ['#relDate', '#relPermitAppNo', '#relPermitNumber', '#relApplicant', '#relClaimedBy', '#relTimeReleased'].forEach(sel => { const el = $(sel); if (el) el.value = ''; });
    if (relDateEl) relDateEl.value = new Date().toISOString().split('T')[0];
  });

  loadRelTodayRecords();

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('releasing-today', loadRelTodayRecords, 12000);
  }
}

async function loadRelTodayRecords() {
  const miniTbody = $('#relTodayTbody');
  if (!miniTbody) return;
  const res = await apiGet('releasing', 'list').catch(() => ({ data: [] }));
  const today = new Date().toISOString().split('T')[0];
  const todayRecords = (res.data || []).filter(r => r.date_released === today);
  if (!todayRecords.length) {
    miniTbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:24px;">No releases recorded today.</td></tr>';
  } else {
    miniTbody.innerHTML = todayRecords.map(r => `
      <tr>
        <td class="cell-mono">${r.permit_application_no}</td>
        <td>${r.applicant_name}</td>
        <td>${r.claimed_by || '—'}</td>
        <td>${r.time_released ? r.time_released.substring(0, 5) : '—'}</td>
      </tr>`).join('');
  }
}

/* ==========================================================================
   RELEASING RECORDS
   ========================================================================== */
async function initReleasingRecordsPage() {
  await loadReleasingRecordsTable();
  $('#relRecordsRefresh')?.addEventListener('click', () => { loadReleasingRecordsTable(); showToast({ title: 'Refreshed', type: 'info', message: 'Table updated.' }); });
  $('#relRecordsPrint')?.addEventListener('click', () => window.print());
  $('#relRecordsExport')?.addEventListener('click', () => {
    window.open('../../api/index.php?module=export&action=csv&table=releasing', '_blank');
  });

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('releasing-records', loadReleasingRecordsTable, 12000);
  }
}

async function loadReleasingRecordsTable() {
  const tbody = $('#relRecordsTbody');
  if (!tbody) return;
  const res = await apiGet('releasing', 'list').catch(() => ({ data: [] }));
  const records = res.data || [];
  const today = new Date().toISOString().split('T')[0];
  const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
  const monthStart = today.substring(0, 7);
  $('#relRecToday') && ($('#relRecToday').textContent = records.filter(r => r.date_released === today).length);
  $('#relRecWeek') && ($('#relRecWeek').textContent = records.filter(r => r.date_released >= weekAgo).length);
  $('#relRecMonth') && ($('#relRecMonth').textContent = records.filter(r => (r.date_released || '').startsWith(monthStart)).length);
  $('#relRecYear') && ($('#relRecYear').textContent = records.length);
  tbody.innerHTML = records.map(r => `
    <tr>
      <td>${r.date_released ? formatDate(r.date_released) : '—'}</td>
      <td class="cell-mono">${r.permit_application_no}</td>
      <td>${r.applicant_name}</td>
      <td>${r.claimed_by || '—'}</td>
      <td>${r.time_released ? r.time_released.substring(0, 5) : '—'}</td>
      <td><button class="icon-btn" title="Delete" onclick="deleteReleasing(${r.id})">${userIcon('x')}</button></td>
    </tr>`).join('');
}

async function deleteReleasing(id) {
  if (!confirm('Delete this release record?')) return;
  const res = await apiPost('releasing', 'delete', { id });
  if (res.success) { showToast({ title: 'Deleted', type: 'success' }); loadReleasingRecordsTable(); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error }); }
}

/* ==========================================================================
   NOTIFICATIONS
   ========================================================================== */
const MODULE_LABELS = {
  'op': 'Payment',
  'workflow': 'Workflow',
  'approved': 'Approved',
  'releasing': 'Release',
  'announcement': 'Announcement',
  'login': 'Login',
  'system': 'System'
};

const MODULE_COLORS = {
  'op': 'var(--color-primary)',
  'workflow': 'var(--color-primary)',
  'approved': 'var(--success)',
  'releasing': 'var(--success)',
  'announcement': 'var(--danger)',
  'login': '#6b7280',
  'system': '#e6a817'
};

/* ==========================================================================
   ANNOUNCEMENTS
   ========================================================================== */
let _announcementsCache = [];

async function initAnnouncementsPage() {
  await loadAnnouncementsTable();

  const pendingId = localStorage.getItem('pams_open_announcement');
  if (pendingId) {
    localStorage.removeItem('pams_open_announcement');
    const ann = _announcementsCache.find(a => String(a.id) === String(pendingId));
    if (ann) openAnnouncementModal(ann);
  }

  if (window.PAMS_REALTIME) {
    window.PAMS_REALTIME.register('announcements-page', loadAnnouncementsTable, 20000);
  }
}

function openAnnouncementModal(a) {
  apiPost('notifications', 'mark-read', { record_id: a.id }).catch(() => {});
  openModal(`
    <div class="modal-head">
      <h3>${a.title}</h3>
      <button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;gap:10px;align-items:center;">
          <span style="font-size:12px;color:var(--gray-400);">${a.created_at ? formatDate(a.created_at) : ''}</span>
        </div>
        <p style="font-size:14px;line-height:1.6;color:var(--gray-700);">${a.content}</p>
        <div style="font-size:12px;color:var(--gray-500);border-top:1px solid var(--gray-100);padding-top:12px;">
          Posted by <strong>${a.posted_by_name || '—'}</strong>
        </div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button></div>`);
}

async function loadAnnouncementsTable() {
  const tbody = $('#announcementsTbody');
  if (!tbody) return;

  const res = await apiGet('announcements', 'list').catch(() => ({ data: [] }));
  const announcements = res.data || [];
  _announcementsCache = announcements;

  tbody.innerHTML = announcements.map((a, i) => `
    <tr style="cursor:pointer;" data-index="${i}">
      <td><strong>${a.title}</strong></td>
      <td>${a.posted_by_name || '—'}</td>
      <td style="white-space:nowrap;">${a.created_at ? formatDate(a.created_at) : '—'}</td>
    </tr>`).join('');

  tbody.querySelectorAll('tr').forEach((row, i) => {
    row.addEventListener('click', () => {
      const a = announcements[i];
      if (!a) return;
      openAnnouncementModal(a);
    });
  });
}

/* ==========================================================================
   INSPECTION MANAGEMENT
   ========================================================================== */
function initInspectionSchedulePage() {
  const tbody = $('#inschTbody');
  if (!tbody) return;
  let page = 1;
  const perPage = 10;

  function resetForm() {
    $('#inschId').value = '';
    ['#inschAppNo', '#inschPermitNo', '#inschProjectTitle', '#inschLocation', '#inschApplicant', '#inschOwner', '#inschContact', '#inschRemarks'].forEach(s => { const el = $(s); if (el) el.value = ''; });
    $('#inschDate').value = '';
    $('#inschTime').value = '';
    $('#inschInspector').value = '';
    $('#inschStatus').value = 'Scheduled';
    $('#scheduleFormTitle').textContent = 'New Schedule';
    $('#inschSaveBtn').innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Schedule';
  }

  async function loadSchedules() {
    const params = { page, per_page: perPage };
    const search = $('#inschSearch')?.value.trim() || '';
    const status = $('#inschStatusFilter')?.value || '';
    if (search) params.search = search;
    if (status) params.status = status;
    const res = await apiGet('inspection', 'schedules/list', params).catch(() => ({ data: [], total: 0 }));
    const rows = res.data || [];
    const total = res.total || 0;
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td class="cell-mono">${escapeHtml(r.application_no)}</td>
        <td><strong>${escapeHtml(r.project_title)}</strong>${r.permit_no ? `<br><span class="text-xs text-muted">${escapeHtml(r.permit_no)}</span>` : ''}</td>
        <td>${escapeHtml(r.applicant_name)}</td>
        <td>${r.scheduled_date ? formatDate(r.scheduled_date) : '—'}${r.scheduled_time ? ` <span class="text-xs text-muted">${r.scheduled_time}</span>` : ''}</td>
        <td>${escapeHtml(r.inspector_name || '—')}</td>
        <td>${statusBadge(r.status || 'Scheduled')}</td>
        <td>
          <div class="row-actions">
            <button class="icon-btn" title="Start inspection" onclick="startInspection(${r.id})">${userIcon('edit')}</button>
            <button class="icon-btn" title="Edit schedule" onclick="editSchedule(${r.id})">${userIcon('eye')}</button>
            <button class="icon-btn" title="Delete" onclick="deleteSchedule(${r.id})">${userIcon('trash')}</button>
          </div>
        </td>
      </tr>`).join('') || '<tr><td colspan="7" style="text-align:center;padding:48px;color:var(--gray-400);">No schedules found.</td></tr>';
    const pi = $('#inschPageInfo');
    if (pi) pi.textContent = total ? `Showing ${(page - 1) * perPage + 1}–${Math.min(page * perPage, total)} of ${total}` : 'No records';
    $('#inschPrev').disabled = page <= 1;
    $('#inschNext').disabled = page * perPage >= total;
  }

  $('#inschSaveBtn')?.addEventListener('click', async () => {
    const id = $('#inschId')?.value;
    const appNo = $('#inschAppNo')?.value.trim();
    const title = $('#inschProjectTitle')?.value.trim();
    const applicant = $('#inschApplicant')?.value.trim();
    if (!appNo || !title || !applicant) {
      showToast({ title: 'Incomplete form', message: 'Application No., Project Title, and Applicant are required.', type: 'warning' });
      return;
    }
    const payload = {
      application_no: appNo,
      permit_no: $('#inschPermitNo')?.value.trim() || null,
      project_title: title,
      project_location: $('#inschLocation')?.value.trim() || null,
      applicant_name: applicant,
      owner_representative: $('#inschOwner')?.value.trim() || null,
      contact_number: $('#inschContact')?.value.trim() || null,
      scheduled_date: $('#inschDate')?.value || null,
      scheduled_time: $('#inschTime')?.value || null,
      inspector_id: $('#inschInspector')?.value || null,
      status: $('#inschStatus')?.value || 'Scheduled',
      remarks: $('#inschRemarks')?.value.trim() || null
    };
    const res = id
      ? await apiPost('inspection', 'schedules/update', { ...payload, id })
      : await apiPost('inspection', 'schedules/create', payload);
    if (res.success) {
      showToast({ title: 'Saved', message: res.message, type: 'success' });
      resetForm();
      loadSchedules();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });

  $('#inschClearBtn')?.addEventListener('click', resetForm);
  $('#inschRefreshBtn')?.addEventListener('click', loadSchedules);
  $('#inschSearch')?.addEventListener('input', debounce(() => { page = 1; loadSchedules(); }, 250));
  $('#inschStatusFilter')?.addEventListener('change', () => { page = 1; loadSchedules(); });
  $('#inschPrev')?.addEventListener('click', () => { if (page > 1) { page--; loadSchedules(); } });
  $('#inschNext')?.addEventListener('click', () => { page++; loadSchedules(); });

  loadSchedules();
}

function fillScheduleForm(r) {
  $('#inschId').value = r.id;
  $('#inschAppNo').value = r.application_no || '';
  $('#inschPermitNo').value = r.permit_no || '';
  $('#inschProjectTitle').value = r.project_title || '';
  $('#inschLocation').value = r.project_location || '';
  $('#inschApplicant').value = r.applicant_name || '';
  $('#inschOwner').value = r.owner_representative || '';
  $('#inschContact').value = r.contact_number || '';
  $('#inschDate').value = r.scheduled_date || '';
  $('#inschTime').value = r.scheduled_time || '';
  $('#inschInspector').value = r.inspector_id || '';
  $('#inschStatus').value = r.status || 'Scheduled';
  $('#inschRemarks').value = r.remarks || '';
  $('#scheduleFormTitle').textContent = 'Edit Schedule';
  $('#inschSaveBtn').innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Update Schedule';
}

async function editSchedule(id) {
  const res = await apiGet('inspection', 'schedules/list', { id, per_page: 100 });
  const r = Array.isArray(res.data) ? res.data.find(d => d.id == id) : null;
  if (!r) { showToast({ title: 'Not found', message: 'Schedule not found.', type: 'danger' }); return; }
  fillScheduleForm(r);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteSchedule(id) {
  openConfirm({
    title: 'Delete schedule?',
    message: 'This inspection schedule will be permanently removed.',
    confirmLabel: 'Delete',
    onConfirm: async () => {
      const res = await apiPost('inspection', 'schedules/delete', { id });
      closeModal();
      if (res.success) { showToast({ title: 'Deleted', message: 'Schedule removed.', type: 'success' }); initInspectionSchedulePage(); }
      else showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });
}

function startInspection(id) {
  window.location.href = 'inspection-checklist.php?schedule_id=' + id;
}

/* --------------------------------------------------------------------------
   Inline modal helpers (for report / detail modals declared in page markup)
   -------------------------------------------------------------------------- */
function bindInlineModal(modalId) {
  const wrap = $(modalId);
  if (!wrap) return;
  wrap.addEventListener('click', (e) => {
    if (e.target.matches('.backdrop') || e.target.closest('[data-close-modal]')) {
      wrap.classList.remove('open');
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
    }
  });
}
function openInlineModal(modalId) {
  const w = $(modalId);
  if (w) {
    w.classList.add('open');
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
  }
}

/* --------------------------------------------------------------------------
   Shared render helpers for inspection checklist / report documents
   -------------------------------------------------------------------------- */
const INSPECTION_STATUS_FLOW = ['Draft', 'Under Review', 'Approved', 'Completed'];

function imgPath(p) {
  return p ? ('../../' + p) : '';
}

function renderChecklistResultTable(results, editable, opts) {
  const byCat = {};
  (results || []).forEach(r => {
    (byCat[r.category] = byCat[r.category] || []).push(r);
  });
  const cats = Object.keys(byCat);
  const pass = (results || []).filter(r => r.result === 'Pass').length;
  const fail = (results || []).filter(r => r.result === 'Fail').length;
  const na = (results || []).filter(r => r.result === 'N/A').length;

  const markFor = (r) => {
    if (r.item_type === 'checkbox') {
      return r.result === 'Pass'
        ? '<span class="badge badge-success">✓</span>'
        : '<span class="badge badge-neutral">✗</span>';
    }
    return r.result === 'Pass' ? '<span class="badge badge-success">Pass</span>'
      : r.result === 'Fail' ? '<span class="badge badge-danger">Fail</span>'
      : '<span class="badge badge-neutral">N/A</span>';
  };

  const isSb = (r) => r.item_type === 'checkbox' && String(r.item_text).indexOf('Setbacks - ') === 0;

  return {
    html: cats.map(cat => {
      const items = byCat[cat];
      const setbacks = items.filter(isSb);
      const others = items.filter(r => !isSb(r));
      let rows = '';
      if (setbacks.length) {
        const cells = setbacks.map(r => `<td class="sb-report-cell"><strong>${escapeHtml(String(r.item_text).replace(/^Setbacks\s*-\s*/, ''))}</strong><div>${markFor(r)}</div>${r.remarks ? `<div class="text-xs text-muted">${escapeHtml(r.remarks)}</div>` : ''}</td>`).join('');
        rows += `<tr class="sb-report-row"><td class="k"><strong>Setbacks</strong></td>${cells}</tr>`;
      }
      rows += others.map(r => `<tr>
        <td>${escapeHtml(r.item_text)}${r.remarks ? `<div class="text-xs text-muted">${escapeHtml(r.remarks)}</div>` : ''}</td>
        <td style="text-align:center;width:110px;">${markFor(r)}</td>
      </tr>`).join('');
      const pct = (opts && opts.mechAccomplishment != null && cat === 'Mechanical Works')
        ? `<span class="mech-pct">% Mechanical: <strong>${escapeHtml(String(opts.mechAccomplishment))}%</strong></span>`
        : '';
      return `<div class="checklist-cat">
        <div class="checklist-cat-head"><h4>${escapeHtml(cat)}</h4>${pct}</div>
        <table class="checklist-table"><thead><tr><th>Inspection Item</th><th style="width:110px;text-align:center;">Result</th></tr></thead><tbody>${rows}</tbody></table>
      </div>`;
    }).join(''),
    summary: `${results.length} item${results.length === 1 ? '' : 's'} · <b>${pass}</b> Pass, <b>${fail}</b> Fail, <b>${na}</b> N/A`
  };
}

function renderReportDoc(rec) {
  const xf = rec.extra_fields || {};
  const xfPct = xf.pct || {};
  const xfRem = xf.remarks || {};

  const catOrder = ['General Safety', 'Architectural Works', 'Civil / Structural Works', 'Electrical Works', 'Mechanical Works', 'Sanitary / Plumbing Works', 'Electronics Works'];

  const catPct = (cat) => {
    const pctVal = cat === 'Mechanical Works'
      ? (xfPct[cat] != null ? xfPct[cat] : rec.mech_accomplishment)
      : xfPct[cat];
    return (pctVal != null && pctVal !== '') ? `${escapeHtml(String(pctVal))}` : '';
  };

  const catRemarks = (cat) => {
    const rem = xfRem[cat] || '';
    return escapeHtml(rem);
  };

  const infoField = (label, value) =>
    `<div class="mr-field"><div class="mr-field-label">${label}</div><div class="mr-field-value">${escapeHtml(value || '')}</div></div>`;

  const inspType = (rec.inspection_type || '').trim();
  const inspLow = inspType.toLowerCase();
  const is1st = inspLow.includes('1st');
  const is2nd = inspLow.includes('2nd');
  const is3rd = inspLow.includes('3rd');
  const isOthers = !is1st && !is2nd && !is3rd && inspType !== '';
  const othersText = isOthers ? inspType : '';
  const inspDisplay = is1st ? '1st Inspection'
    : is2nd ? '2nd Inspection'
    : is3rd ? '3rd Inspection'
    : isOthers ? `Others: ${inspType}`
    : inspType;

  const catHeaders = [
    'General Safety',
    'Architectural Works',
    'Civil / Structural Works',
    'Electrical Works',
    'Mechanical Works',
    'Sanitary / Plumbing Works',
    'Electronics Works'
  ];

  const catHeaderCells = catHeaders.map(h => `<th>${h}</th>`).join('');
  const pctCells = catOrder.map(cat => `<td>${catPct(cat) ? escapeHtml(catPct(cat)) + '%' : ''}</td>`).join('');
  const remCells = catOrder.map(cat => `<td>${catRemarks(cat)}</td>`).join('');

  return `
    <div class="mr-doc">
      <div class="mr-header">
        <div class="mr-doc-code">OBO-ED-FM-32 v1</div>
      </div>

      <div class="mr-title-box">
        <div class="mr-title">Monitoring On-Site Occular Inspection Checklist</div>
        <div class="mr-inspection-checks">
          <span class="mr-insp-display">${escapeHtml(inspDisplay)}</span>
        </div>
      </div>

      <table class="mr-info-box">
        <colgroup>
          <col style="width:26%">
          <col style="width:48%">
          <col style="width:26%">
        </colgroup>
        <tbody>
          <tr>
            <td>${infoField('Application<br>Number', rec.application_no)}</td>
            <td>${infoField('Project Title', rec.project_title)}</td>
            <td></td>
          </tr>
          <tr>
            <td>${infoField('Name of<br>Applicant', rec.owner_representative)}</td>
            <td>${infoField('Architect /<br>Engineer', rec.project_engineer)}</td>
            <td>${infoField('Time Started', rec.time_started)}</td>
          </tr>
          <tr>
            <td>${infoField('Date of<br>Inspection', rec.inspection_date ? formatDate(rec.inspection_date) : '')}</td>
            <td>${infoField('Date of<br>Re-inspection', rec.review_date ? formatDate(rec.review_date) : '')}</td>
            <td>${infoField('Time Finished', rec.time_finished)}</td>
          </tr>
          <tr>
            <td colspan="2">${infoField('Project<br>Location', rec.project_location)}</td>
            <td>${infoField('Completion %', rec.physical_accomplishment != null ? rec.physical_accomplishment + '%' : '')}</td>
          </tr>
        </tbody>
      </table>

      <div class="mr-workcat-box">
        <div class="mr-workcat-title">WORK CATEGORY</div>
        <table class="mr-wc-table">
          <thead>
            <tr><th class="mr-wc-label-cell"></th>${catHeaderCells}</tr>
          </thead>
          <tbody>
            <tr><td class="mr-wc-label-cell" style="font-weight:700;text-align:left;padding-left:8px;">Percentage</td>${pctCells}</tr>
            <tr><td class="mr-wc-label-cell" style="font-weight:700;text-align:left;padding-left:8px;">Remarks</td>${remCells}</tr>
          </tbody>
        </table>
      </div>

    </div>
  `;
}

/* --------------------------------------------------------------------------
   INSPECTION CHECKLIST page
   -------------------------------------------------------------------------- */
async function initInspectionChecklistPage() {
  const params = new URLSearchParams(location.search);
  const editId = params.get('id');
  const fromSchedule = params.get('schedule_id');

  let recordId = editId ? parseInt(editId, 10) : null;
  let record = null;
  let status = 'Draft';

  const canManage = () => getPermSet().has('inspection-edit');

  const $form = $('#inschForm');
  const resultsBody = $('#inschResultsBody');

  const canEdit = () => getPermSet().has('inspection-edit');

  async function loadTeamLeaderOptions() {
    const res = await apiGet('teamleaders', 'roster').catch(() => null);
    const leaders = (res && res.success) ? (res.data || []) : [];
    const fill = (sel, teamNo) => {
      const el = $(sel);
      if (!el) return;
      const members = leaders.filter(l => parseInt(l.team_no, 10) === teamNo);
      const opts = members.length
        ? members.map(l => `<option value="${l.id}">${escapeHtml(l.full_name)}${l.position ? ' — ' + escapeHtml(l.position) : ''}</option>`).join('')
        : '';
      el.innerHTML = '<option value="">Select team leader</option>' + opts;
    };
    fill('#inschTeamLeader1', 1);
    fill('#inschTeamLeader2', 2);
  }

  function setStatusUI() {
    const pill = $('#inschStatusPill');
    if (pill) pill.innerHTML = statusBadge(status);

    const editable = canEdit();
    $form.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !editable);
    $form.querySelectorAll('button').forEach(el => el.disabled = !editable);
    $form.querySelectorAll('.insp-type-pills .insp-pill').forEach(el => el.style.pointerEvents = editable ? '' : 'none');
    resultsBody.querySelectorAll('input, textarea').forEach(el => el.disabled = !editable);
    const photoDrop = $('#inschPhotoDrop');
    if (photoDrop) photoDrop.style.pointerEvents = editable ? '' : 'none';
    const photoInput = $('#inschPhotoInput');
    if (photoInput) photoInput.disabled = !editable;

    $('#inschSaveBtn').style.display = editable ? '' : 'none';
    $('#inschSubmitBtn').style.display = (editable && recordId && status === 'Draft') ? '' : 'none';

    const reviewCard = $('#inschReviewCard');
    const approveBtn = $('#inschApproveBtn');
    const rejectBtn = $('#inschRejectBtn');
    const remarksWrap = $('#inschReviewRemarksWrap');
    if (canManage() && status === 'Under Review') {
      reviewCard.style.display = '';
      $('#inschReviewTitle').textContent = 'Review';
      approveBtn.style.display = '';
      approveBtn.textContent = 'Approve';
      rejectBtn.style.display = '';
      if (remarksWrap) remarksWrap.style.display = 'none';
    } else {
      reviewCard.style.display = 'none';
      approveBtn.style.display = 'none';
      rejectBtn.style.display = 'none';
      if (remarksWrap) remarksWrap.style.display = 'none';
    }
  }

  function renderResults(rec) {
    const template = rec.template;
    const existing = rec.results || [];
    const byTpl = {};
    existing.forEach(r => { byTpl[r.template_item_id] = r; });
    const xf = rec.extra_fields || {};
    const sb = xf.setbacks || {};
    const pct = xf.pct || {};
    const remarks = xf.remarks || {};

    const cats = rec.categories || [];
    resultsBody.innerHTML = cats.map(cat => {
      const items = (template[cat] || []).slice().sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
      if (!items.length) return '';

      let rows = '';

      if (cat === 'Architectural Works') {
        const sbFields = ['Front', 'Rear', 'Right Side', 'Left Side'];
        const sbCells = sbFields.map(k =>
          `<div class="sb-text-field"><label>${escapeHtml(k)}</label>
             <input type="text" class="form-control form-control-sm" data-sb-text="${escapeHtml(k)}" placeholder="(m)" value="${escapeHtml(sb[k] || '')}">
           </div>`).join('');
        rows += `<tr class="sb-text-row" data-cat="${escapeHtml(cat)}">
          <td colspan="3"><div class="sb-text-cells"><span class="sb-text-head"><strong>Setbacks</strong></span>${sbCells}</div></td>
        </tr>`;
      }

      if (cat === 'Civil / Structural Works') {
        rows += `<tr class="floor-level-row" data-cat="${escapeHtml(cat)}">
          <td colspan="3"><div class="sb-text-cells"><span class="sb-text-head"><strong>Completed Floor Level</strong></span>
            <input type="text" class="form-control form-control-sm" data-floor-level placeholder="e.g. 2nd Floor" value="${escapeHtml(xf.floorLevel || '')}">
          </div></td>
        </tr>`;
      }

      rows += items.map(it => {
        const prev = byTpl[it.id] || {};
        const isOthers = String(it.item_text) === 'Others';
        const othersInput = isOthers
          ? `<input type="text" class="form-control form-control-sm others-line" data-others placeholder="Specify" value="${escapeHtml(xf.others || '')}">`
          : '';
        return `<tr data-tpl="${it.id}" data-cat="${escapeHtml(cat)}" data-item="${escapeHtml(it.item_text)}" data-type="checkbox">
          <td>${escapeHtml(it.item_text)}${othersInput}</td>
          <td class="col-result col-check"><input type="checkbox" data-cb="${it.id}" ${(prev.result || 'N/A') === 'Pass' ? 'checked' : ''}></td>
          <td class="col-remarks"></td>
        </tr>`;
      }).join('');

      const pctDefault = cat === 'Mechanical Works'
        ? (pct[cat] != null ? pct[cat] : (rec.mech_accomplishment != null ? rec.mech_accomplishment : ''))
        : (pct[cat] || '');
      const pctInput = `<label>Percent (%)</label>
          <input type="number" class="form-control form-control-sm" data-cat-pct="${escapeHtml(cat)}" min="0" max="100" step="any" placeholder="%" value="${escapeHtml(String(pctDefault))}">`;

      return `<div class="checklist-cat">
        <div class="checklist-cat-head"><h4>${escapeHtml(cat)}</h4><span class="cat-pct">${pctInput}</span></div>
        <table class="checklist-table"><thead><tr><th>Inspection Item</th><th class="col-result">Compliance</th><th class="col-remarks"></th></tr></thead><tbody>${rows}</tbody></table>
        <div class="cat-remark-wrap">
          <label>AI Summary</label>
          <button type="button" class="icon-btn cat-ai-btn" data-ai-cat="${escapeHtml(cat)}" title="Generate remark with AI" style="display:none;"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="3.5"/></svg> AI</button>
          <textarea class="form-control cat-remark" data-cat-remarks="${escapeHtml(cat)}" rows="2" placeholder="Remark/s">${escapeHtml(remarks[cat] || '')}</textarea>
        </div>
      </div>`;
    }).join('') || '<div class="section-hint">No checklist template items configured.</div>';
  }

  function collectResults() {
    const arr = [];
    resultsBody.querySelectorAll('tr[data-tpl]').forEach(tr => {
      const cb = tr.querySelector('input[data-cb]');
      arr.push({
        template_item_id: parseInt(tr.dataset.tpl, 10),
        category: tr.dataset.cat,
        item_text: tr.dataset.item,
        item_type: 'checkbox',
        result: cb && cb.checked ? 'Pass' : 'N/A',
        remarks: ''
      });
    });
    return arr;
  }

  function collectExtraFields() {
    const xf = { setbacks: {}, pct: {}, remarks: {} };
    resultsBody.querySelectorAll('input[data-sb-text]').forEach(el => {
      const v = el.value.trim();
      if (v !== '') xf.setbacks[el.dataset.sbText] = v;
    });
    const fl = resultsBody.querySelector('[data-floor-level]');
    if (fl && fl.value.trim() !== '') xf.floorLevel = fl.value.trim();
    const oth = resultsBody.querySelector('[data-others]');
    if (oth && oth.value.trim() !== '') xf.others = oth.value.trim();
    resultsBody.querySelectorAll('input[data-cat-pct]').forEach(el => {
      const v = el.value.trim();
      if (v !== '') xf.pct[el.dataset.catPct] = v;
    });
    resultsBody.querySelectorAll('textarea[data-cat-remarks]').forEach(el => {
      const v = el.value.trim();
      if (v !== '') xf.remarks[el.dataset.catRemarks] = v;
    });
    return xf;
  }

  function updateSummary() {
    const el = $('#inschResultsSummary');
    if (!el) return;
    const arr = collectResults();
    const pass = arr.filter(r => r.result === 'Pass').length;
    const fail = arr.filter(r => r.result === 'Fail').length;
    const na = arr.filter(r => r.result === 'N/A').length;
    el.innerHTML = `${arr.length} items · <b>${pass}</b> Pass, <b>${fail}</b> Fail, <b>${na}</b> N/A`;
  }

  function fillForm(rec) {
    const set = (sel, val) => { const el = $(sel); if (el) el.value = val == null ? '' : val; };
    $('#inschId').value = rec.id || '';
    $('#inschScheduleId').value = rec.schedule_id || '';
    $('#inschInspectionNo').value = rec.inspection_no || '';
    set('#inschAppNo', rec.application_no);
    set('#inschPermitNo', rec.permit_no);
    set('#inschDateIssued', rec.permit_date_issued);
    set('#inschProjectTitle', rec.project_title);
    set('#inschLocation', rec.project_location);
    set('#inschOwner', rec.owner_representative);
    set('#inschContact', rec.contact_number);
    set('#inschContractor', rec.project_contractor);
    set('#inschEngineer', rec.project_engineer);
    set('#inschTeamLeader1', rec.team_leader_1 || '');
    set('#inschTeamLeader2', rec.team_leader_2 || '');
    set('#inschDate', rec.inspection_date);
    const inspType = (rec.inspection_type || '').trim();
    setInspCountUI(inspType);
    set('#inschTimeStart', rec.time_started);
    set('#inschTimeEnd', rec.time_finished);
    const resRadio = $('input[name="inschResult"]');
    if (resRadio) {
      resRadio.checked = resRadio.value === (rec.inspection_result || '');
    }
    set('#inschPhysical', rec.physical_accomplishment != null ? rec.physical_accomplishment : '');
    set('#inschFindings', rec.overall_findings);
    set('#inschRecommendations', rec.recommendations);
  }

  function setInspCountUI(value) {
    const v = (value || '').trim();
    const low = v.toLowerCase();
    const pill = $('input[name="inschInspCount"][value="' + (low.includes('1st') ? '1st Inspection' : low.includes('2nd') ? '2nd Inspection' : low.includes('3rd') ? '3rd Inspection' : 'others') + '"]');
    if (pill) {
      pill.checked = true;
      if (pill.value === 'others') {
        $('#inschInspectionType').value = v;
        $('#inschInspectionType').style.display = 'block';
      } else {
        $('#inschInspectionType').style.display = 'none';
      }
    }
  }

  function collectInspType() {
    const sel = $('input[name="inschInspCount"]:checked');
    if (!sel) return $('#inschInspectionType').value.trim() || null;
    if (sel.value === 'others') {
      const t = $('#inschInspectionType').value.trim();
      return t || null;
    }
    return sel.value;
  }

  function initInspCountUI() {
    document.querySelectorAll('input[name="inschInspCount"]').forEach(rb => {
      rb.addEventListener('change', () => {
        if (rb.value === 'others') {
          $('#inschInspectionType').style.display = 'block';
          $('#inschInspectionType').focus();
        } else {
          $('#inschInspectionType').style.display = 'none';
        }
      });
    });
  }

  async function initAiRemarkButtons() {
    try {
      const probe = await apiGet('inspection', 'ai-status').catch(() => ({ success: false }));
      const aiOn = probe && probe.success === true && probe.ai_enabled === true;
      resultsBody.querySelectorAll('.cat-ai-btn').forEach(btn => { btn.style.display = aiOn ? '' : 'none'; });
      if (!aiOn) return;
      resultsBody.addEventListener('click', async (e) => {
        const btn = e.target.closest('.cat-ai-btn');
        if (!btn) return;
        const cat = btn.dataset.aiCat;
        await generateAiRemark(cat, btn);
      });
    } catch (err) { /* AI not available */ }
  }

  async function generateAiRemark(cat, btn) {
    const catEl = resultsBody.querySelector(`.cat-remark[data-cat-remarks="${CSS.escape(cat)}"]`);
    if (!catEl) return;
    const items = [];
    resultsBody.querySelectorAll(`tr[data-cat="${CSS.escape(cat)}"]`).forEach(tr => {
      const text = tr.dataset.item || '';
      const cb = tr.querySelector('input[data-cb]');
      items.push({ item_text: text, result: cb && cb.checked ? 'Pass' : 'N/A' });
    });
    if (!items.length) { showToast({ title: 'Nothing to summarize', message: 'Check at least one item in this category.', type: 'warning' }); return; }
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '…';
    try {
      const res = await apiPost('inspection', 'remark-ai', { category: cat, items });
      if (!res.success) { showToast({ title: 'AI error', message: res.error || 'Failed to generate remark.', type: 'danger' }); return; }
      if (res.ai_enabled === false) { showToast({ title: 'AI not configured', message: 'Set an AI key in Settings to use auto-summary.', type: 'warning' }); return; }
      catEl.value = res.summary;
      showToast({ title: 'AI remark ready', message: `${cat} summary generated.`, type: 'success' });
    } catch (err) {
      showToast({ title: 'AI error', message: 'Could not reach the AI service.', type: 'danger' });
    } finally {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  }

  function loadPhotos() {
    const listEl = $('#inschPhotoList');
    if (!listEl) return;
    const photos = (record && record.photos) ? record.photos : [];
    const removable = canEdit();
    listEl.innerHTML = photos.map(p => `
      <div class="photo-thumb">
        <img src="${imgPath(p.file_path)}" alt="site photo">
        <div class="photo-thumb-meta">${escapeHtml(p.caption || '')}</div>
        ${removable ? `<button type="button" class="icon-btn photo-thumb-remove" title="Remove" onclick="removeInspectionPhoto(${p.id})">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>` : ''}
      </div>`).join('') || '<span class="text-xs text-muted">No site photos attached.</span>';
  }

  async function refreshRecord() {
    if (!recordId) return;
    const [recRes, tplRes] = await Promise.all([
      apiGet('inspection', 'checklist/get', { id: recordId }).catch(() => null),
      apiGet('inspection', 'template').catch(() => null)
    ]);
    if (recRes && recRes.success) {
      record = recRes.data;
      record.template = (tplRes && tplRes.success) ? tplRes.data : {};
      record.categories = (tplRes && tplRes.success) ? tplRes.categories : [];
      status = record.status;
      fillForm(record);
      renderResults(record);
      setStatusUI();
      loadPhotos();
      updateSummary();
      initAiRemarkButtons();
    }
  }

  $('#inschSaveBtn') && $('#inschSaveBtn').addEventListener('click', async () => {
    const title = $('#inschProjectTitle').value.trim();
    const date = $('#inschDate').value;
    if (!title || !date) {
      showToast({ title: 'Incomplete form', message: 'Project Title and Date Inspected are required.', type: 'warning' });
      return;
    }
    const mechEl = resultsBody.querySelector('input[data-cat-pct="Mechanical Works"]');
    const resRadioChecked = $('input[name="inschResult"]:checked');
    const payload = {
      application_no: ($('#inschAppNo')?.value || '').trim(),
      permit_no: $('#inschPermitNo').value.trim() || null,
      permit_date_issued: $('#inschDateIssued').value || null,
      project_title: title,
      project_location: $('#inschLocation').value.trim() || null,
      owner_representative: $('#inschOwner').value.trim() || null,
      contact_number: $('#inschContact').value.trim() || null,
      project_contractor: $('#inschContractor').value.trim() || null,
      project_engineer: $('#inschEngineer').value.trim() || null,
      team_leader_1: $('#inschTeamLeader1').value || null,
      team_leader_2: $('#inschTeamLeader2').value || null,
      inspection_date: date,
      inspection_type: collectInspType(),
      inspection_result: resRadioChecked ? resRadioChecked.value : null,
      time_started: $('#inschTimeStart').value || null,
      time_finished: $('#inschTimeEnd').value || null,
      physical_accomplishment: $('#inschPhysical').value || null,
      mech_accomplishment: (mechEl && mechEl.value !== '') ? mechEl.value : null,
      extra_fields: collectExtraFields(),
      overall_findings: $('#inschFindings').value.trim() || null,
      recommendations: $('#inschRecommendations').value.trim() || null,
      schedule_id: $('#inschScheduleId').value || null,
      results: collectResults()
    };
    const res = recordId
      ? await apiPost('inspection', 'checklist/update', { ...payload, id: recordId })
      : await apiPost('inspection', 'checklist/create', payload);
    if (res.success) {
      recordId = parseInt(res.id, 10);
      showToast({ title: 'Saved', message: res.message, type: 'success' });
      await refreshRecord();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });

  $('#inschSubmitBtn') && $('#inschSubmitBtn').addEventListener('click', async () => {
    if (!recordId) { showToast({ title: 'Save first', message: 'Save the draft before submitting.', type: 'warning' }); return; }
    const res = await apiPost('inspection', 'checklist/submit', { id: recordId });
    if (res.success) { showToast({ title: 'Submitted', message: res.message, type: 'success' }); await refreshRecord(); }
    else showToast({ title: 'Error', message: res.error, type: 'danger' });
  });

  $('#inschApproveBtn') && $('#inschApproveBtn').addEventListener('click', async () => {
    if (!recordId) return;
    const res = await apiPost('inspection', 'checklist/review', {
      id: recordId,
      remarks: '',
    });
    if (res.success) {
      showToast({ title: 'Done', message: res.message, type: 'success' });
      $('#inschReviewRemarks').value = '';
      await refreshRecord();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });

  $('#inschRejectBtn') && $('#inschRejectBtn').addEventListener('click', async () => {
    if (!recordId) return;
    const remarksEl = $('#inschReviewRemarks');
    const remarksWrap = $('#inschReviewRemarksWrap');
    const remarks = remarksEl ? remarksEl.value.trim() : '';
    if (!remarks) {
      if (remarksWrap) remarksWrap.style.display = '';
      if (remarksEl) remarksEl.focus();
      showToast({ title: 'Rejection reason required', message: 'Please enter a remark explaining the rejection.', type: 'warning' });
      return;
    }
    const res = await apiPost('inspection', 'checklist/review', { id: recordId, remarks });
    if (res.success) {
      showToast({ title: 'Rejected', message: res.message, type: 'success' });
      if (remarksEl) remarksEl.value = '';
      await refreshRecord();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });

  resultsBody.addEventListener('change', updateSummary);
  window.addEventListener('insch-photos-changed', () => { refreshRecord(); });

  const photoInput = $('#inschPhotoInput');
  const photoDrop = $('#inschPhotoDrop');
  if (photoDrop) photoDrop.addEventListener('click', () => photoInput && photoInput.click());
  if (photoInput) photoInput.addEventListener('change', async () => {
    const files = Array.from(photoInput.files || []);
    if (!files.length || !recordId) {
      if (!recordId) showToast({ title: 'Save first', message: 'Save the inspection record before attaching photos.', type: 'warning' });
      return;
    }
    photoInput.value = '';
    for (const file of files) {
      const fd = new FormData();
      fd.append('inspection_id', recordId);
      fd.append('caption', '');
      fd.append('photo', file);
      const res = await apiPost('inspection', 'photos/upload', fd, true).catch(() => ({ success: false, error: 'Upload failed.' }));
      if (!res.success) { showToast({ title: 'Upload error', message: res.error, type: 'danger' }); break; }
    }
    await refreshRecord();
  });

  await loadTeamLeaderOptions();
  initInspCountUI();

  if (editId) {
    await refreshRecord();
  } else {
    if (fromSchedule) {
      const res = await apiGet('inspection', 'schedules/list', { id: fromSchedule, per_page: 100 }).catch(() => null);
      const s = res && res.success ? (res.data || []).find(d => d.id == fromSchedule) : null;
      if (s) {
        fillForm({
          application_no: s.application_no, permit_no: s.permit_no,
          project_title: s.project_title, project_location: s.project_location,
          owner_representative: s.applicant_name, contact_number: s.contact_number,
          inspection_date: s.scheduled_date, time_started: s.scheduled_time,
          schedule_id: s.id
        });
      }
    }
    const tplRes = await apiGet('inspection', 'template').catch(() => null);
    record = {
      id: null,
      schedule_id: fromSchedule || null,
      template: (tplRes && tplRes.success) ? tplRes.data : {},
      categories: (tplRes && tplRes.success) ? tplRes.categories : [],
      results: [],
      photos: []
    };
    renderResults(record);
    setStatusUI();
    updateSummary();
    initAiRemarkButtons();
  }
}

async function removeInspectionPhoto(photoId) {
  const res = await apiPost('inspection', 'photos/remove', { id: photoId });
  if (res.success) {
    showToast({ title: 'Removed', message: 'Photo removed.', type: 'success' });
    window.dispatchEvent(new Event('insch-photos-changed'));
  } else {
    showToast({ title: 'Error', message: res.error, type: 'danger' });
  }
}

/* --------------------------------------------------------------------------
   INSPECTION REPORTS page
   -------------------------------------------------------------------------- */
function fitReportToPage() {
  const doc = $('#insrReportBody');
  if (!doc || !doc.children.length) return;
  const PRINT_W = 776;
  const PRINT_H = 1188;
  doc.style.zoom = '1';
  doc.style.width = PRINT_W + 'px';
  const naturalH = doc.scrollHeight;
  const Z = Math.min(1, PRINT_H / Math.max(1, naturalH));
  doc.style.zoom = Z.toFixed(4);
  doc.style.width = (PRINT_W / Z) + 'px';
}

function resetReportFit() {
  const doc = $('#insrReportBody');
  if (!doc) return;
  doc.style.zoom = '';
  doc.style.width = '';
}

async function initInspectionReportsPage() {
  const tbody = $('#insrTbody');
  if (!tbody) return;

  async function loadReports() {
    const params = { status: 'Completed,Approved' };
    const search = $('#insrSearch')?.value.trim() || '';
    if (search) params.search = search;
    const res = await apiGet('inspection', 'reports/list', params).catch(() => ({ data: [] }));
    const rows = res.data || [];
    const canEdit = getPermSet().has('inspection-edit');
    const canDelete = getPermSet().has('inspection-delete');
    const showActions = canEdit || canDelete;
    const colspan = tbody.dataset.colspan || '7';
    tbody.innerHTML = rows.map(r => `
       <tr onclick="viewInspectionReport(${r.id})" style="cursor:pointer">
         <td class="cell-mono">${escapeHtml(r.inspection_no)}</td>
         <td class="cell-mono">${escapeHtml(r.application_no)}</td>
         <td><strong>${escapeHtml(r.project_title)}</strong></td>
         <td>${r.inspection_date ? formatDate(r.inspection_date) : '—'}</td>
         <td>${escapeHtml(r.team_leader_1_name || '—')}${r.team_leader_2_name ? ', ' + escapeHtml(r.team_leader_2_name) : ', <span class="text-muted">No Team 2</span>'}</td>
         <td>${statusBadge(r.status)}</td>
         ${showActions ? `<td><div class="row-actions">
           ${canEdit ? `<button class="icon-btn" title="Open checklist" onclick="event.stopPropagation();location.href='inspection-checklist.php?id=${r.id}'">${userIcon('edit')}</button>` : ''}
           ${canDelete ? `<button class="icon-btn" title="Delete" onclick="event.stopPropagation();deleteInspectionRecord(${r.id})">${userIcon('trash')}</button>` : ''}
         </div></td>` : ''}
       </tr>`).join('') || `<tr><td colspan="${colspan}" style="text-align:center;padding:48px;color:var(--gray-400);">No inspection records found.</td></tr>`;
    const pi = $('#insrPageInfo');
    if (pi) pi.textContent = `${rows.length} record${rows.length === 1 ? '' : 's'}`;
  }

  $('#insrRefreshBtn')?.addEventListener('click', loadReports);
  $('#insrSearch')?.addEventListener('input', debounce(loadReports, 250));
  $('#insrPrintReportBtn')?.addEventListener('click', () => window.print());
  window.addEventListener('beforeprint', fitReportToPage);
  window.addEventListener('afterprint', resetReportFit);
  bindInlineModal('#insrReportModal');

  await loadReports();
}

/* --------------------------------------------------------------------------
   INSPECTION REVIEW page (Under Review queue + final approval)
   -------------------------------------------------------------------------- */
function closeInlineModal(modalId) {
  const w = $(modalId);
  if (w) {
    w.classList.remove('open');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
  }
}

async function initInspectionReviewPage() {
  const tbody = $('#insrqTbody');
  if (!tbody) return;
  const status = 'Under Review';
  let search = '';

  const title = $('#insrqModalTitle');
  const info = $('#insrqRecordInfo');
  const remarksEl = $('#insrqRemarks');
  const remarksWrap = $('#insrqRemarksWrap');
  const approveBtn = $('#insrqApproveBtn');
  const rejectBtn = $('#insrqRejectBtn');
  let currentId = null;

  async function loadQueue() {
    const res = await apiGet('inspection', 'reports/list', { status }).catch(() => ({ data: [] }));
    const rows = res.data || [];
    const canEdit = getPermSet().has('inspection-edit');
    const q = search.trim().toLowerCase();
    const filtered = q
      ? rows.filter(r =>
          [r.inspection_no, r.application_no, r.project_title, r.inspector_name, r.team_leader_1_name, r.team_leader_2_name]
            .some(v => v && String(v).toLowerCase().includes(q)),
        )
      : rows;
    const t = $('#insrqTitle');
    if (t) t.textContent = `${status} Queue (${filtered.length})`;
    tbody.innerHTML = filtered.map(r => `
      <tr>
        <td class="cell-mono">${escapeHtml(r.inspection_no)}</td>
        <td class="cell-mono">${escapeHtml(r.application_no)}</td>
        <td><strong>${escapeHtml(r.project_title)}</strong></td>
        <td>${r.inspection_date ? formatDate(r.inspection_date) : '—'}</td>
        <td>${escapeHtml(r.team_leader_1_name || '—')}${r.team_leader_2_name ? ', ' + escapeHtml(r.team_leader_2_name) : ', <span class="text-muted">No Team 2</span>'}</td>
        <td>${escapeHtml(r.inspector_name || '—')}</td>
        <td>${statusBadge(r.status)}</td>
        <td><div class="row-actions">
          <button class="icon-btn" title="View report" onclick="viewQueueReport(${r.id})">${userIcon('eye')}</button>
          ${canEdit && status === 'Under Review' ? `
            <button class="icon-btn" title="Approve" style="color:var(--success);" onclick="openQueueReview(${r.id}, 'approve')">${userIcon('check')}</button>
            <button class="icon-btn" title="Reject" style="color:var(--danger);" onclick="openQueueReview(${r.id}, 'reject')">${userIcon('x')}</button>` : ''}
        </div></td>
      </tr>`).join('') || `<tr><td colspan="8" style="text-align:center;padding:48px;color:var(--gray-400);">No ${status.toLowerCase()} inspections found.</td></tr>`;
    const count = $('#insrqCount');
    if (count) count.textContent = filtered.length;
    const pi = $('#insrqPageInfo');
    if (pi) pi.textContent = `${filtered.length} record${filtered.length === 1 ? '' : 's'}`;
  }

  window.viewQueueReport = async (id) => {
    const res = await apiGet('inspection', 'checklist/get', { id });
    if (!res.success) { showToast({ title: 'Error', message: res.error, type: 'danger' }); return; }
    const body = $('#insrqReportBody');
    if (body) body.innerHTML = renderReportDoc(res.data);
    bindInlineModal('#insrqReportModal');
    openInlineModal('#insrqReportModal');
  };

  window.openQueueReview = (id, mode) => {
    currentId = id;
    if (remarksEl) remarksEl.value = '';
    const isReject = mode === 'reject';
    if (title) title.textContent = isReject ? 'Reject Inspection' : 'Approve Inspection';
    if (info) info.textContent = isReject
      ? 'Add a remark explaining the rejection.'
      : 'Approve this inspection.';
    if (remarksWrap) remarksWrap.hidden = !isReject;
    if (approveBtn) approveBtn.style.display = isReject ? 'none' : '';
    if (rejectBtn) rejectBtn.style.display = isReject ? '' : 'none';
    bindInlineModal('#insrqModal');
    openInlineModal('#insrqModal');
  };

  const doAction = async (action, remarks) => {
    if (!currentId) return;
    if (action === 'reject' && !remarks.trim()) {
      showToast({ title: 'Rejection reason required', message: 'Please enter a remark explaining the rejection.', type: 'warning' });
      return;
    }
    const res = await apiPost('inspection', 'checklist/review', {
      id: currentId,
      remarks: action === 'reject' ? remarks : '',
    });
    closeInlineModal('#insrqModal');
    if (res.success) {
      showToast({ title: 'Done', message: res.message, type: 'success' });
      await loadQueue();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  };

  approveBtn?.addEventListener('click', () => doAction('approve', ''));
  rejectBtn?.addEventListener('click', () => doAction('reject', remarksEl ? remarksEl.value : ''));
  $('#insrqRefreshBtn')?.addEventListener('click', loadQueue);
  $('#insrqSearch')?.addEventListener('input', (e) => {
    search = e.target.value;
    loadQueue();
  });

  await loadQueue();
}

/* --------------------------------------------------------------------------
   TEAM LEADERS page (register / edit / delete team leaders)
   -------------------------------------------------------------------------- */
async function initTeamLeadersPage() {
  const tbody = $('#tlTbody');
  if (!tbody) return;
  let TEAM_LEADERS = [];
  let editingId = null;

  const nameEl = $('#tlName');
  const posEl = $('#tlPosition');

  window.tlPickTeam = (teamNo, el) => {
    el.closest('.tl-team-grid').querySelectorAll('.tl-team-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    const hidden = $('#tlTeam');
    if (hidden) hidden.value = String(teamNo);
  };

  async function loadLeaders() {
    const res = await apiGet('teamleaders', 'list').catch(() => null);
    if (res && res.success) {
      TEAM_LEADERS = res.data.map(l => ({ ...l, team_no: parseInt(l.team_no, 10) }));
    }
    tbody.innerHTML = TEAM_LEADERS.map(l => {
      const teamBadge = String(l.team_no) === '2'
        ? '<span class="badge badge-info">Team 2</span>'
        : '<span class="badge badge-success">Team 1</span>';
      const activeBadge = l.is_active == 1
        ? '<span class="badge badge-success">Active</span>'
        : '<span class="badge badge-neutral">Inactive</span>';
      return `<tr data-id="${l.id}">
        <td class="cell-user"><div class="avatar sm">${initials(l.full_name)}</div><div><strong>${escapeHtml(l.full_name)}</strong></div></td>
        <td>${escapeHtml(l.position || '—')}</td>
        <td>${teamBadge}</td>
        <td>${activeBadge}</td>
        <td><div class="row-actions">
          <button class="icon-btn tl-edit-btn" data-id="${l.id}" title="Edit">${userIcon('edit')}</button>
          <button class="icon-btn tl-del-btn" data-id="${l.id}" title="Delete" style="color:var(--danger);">${userIcon('trash')}</button>
        </div></td>
      </tr>`;
    }).join('') || '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--gray-400);">No team leaders registered yet.</td></tr>';

    const count = $('#tlCount');
    if (count) count.textContent = TEAM_LEADERS.length;
    const pi = $('#tlPageInfo');
    if (pi) pi.textContent = `${TEAM_LEADERS.length} team leader${TEAM_LEADERS.length === 1 ? '' : 's'}`;

    tbody.querySelectorAll('.tl-edit-btn').forEach(btn => btn.addEventListener('click', () => {
      const l = TEAM_LEADERS.find(x => x.id === parseInt(btn.dataset.id, 10));
      if (l) openForm(l);
    }));
    tbody.querySelectorAll('.tl-del-btn').forEach(btn => btn.addEventListener('click', () => {
      const l = TEAM_LEADERS.find(x => x.id === parseInt(btn.dataset.id, 10));
      if (l) confirmDelete(l);
    }));
  }

  function openForm(l) {
    editingId = l ? l.id : null;
    const t = $('#tlModalTitle');
    if (t) t.textContent = l ? 'Edit Team Leader' : 'Register Team Leader';
    if (nameEl) nameEl.value = l ? l.full_name : '';
    if (posEl) posEl.value = l ? (l.position || '') : '';
    document.querySelectorAll('#tlFormModal .tl-team-card').forEach(c => {
      const on = c.dataset.team === String(l ? l.team_no : 1);
      c.classList.toggle('selected', on);
    });
    const hidden = $('#tlTeam');
    if (hidden) hidden.value = String(l ? l.team_no : 1);
    bindInlineModal('#tlFormModal');
    openInlineModal('#tlFormModal');
  }

  function confirmDelete(l) {
    openConfirm({
      title: 'Delete team leader?',
      message: `Remove ${l.full_name} from the team leader registry? Existing reports keep their name.`,
      confirmLabel: 'Delete',
      onConfirm: async () => {
        const res = await apiPost('teamleaders', 'delete', { id: l.id });
        closeModal();
        if (res.success) {
          showToast({ title: 'Deleted', message: res.message, type: 'success' });
          await loadLeaders();
        } else {
          showToast({ title: 'Error', message: res.error, type: 'danger' });
        }
      }
    });
  }

  $('#tlCreateBtn')?.addEventListener('click', () => openForm(null));
  $('#tlRefreshBtn')?.addEventListener('click', loadLeaders);
  $('#tlSaveBtn')?.addEventListener('click', async () => {
    const fullName = nameEl ? nameEl.value.trim() : '';
    if (!fullName) { if (nameEl) nameEl.reportValidity(); return; }
    const payload = {
      full_name: fullName,
      position: (posEl ? posEl.value : '').trim(),
      team_no: ($('#tlTeam') ? $('#tlTeam').value : '1')
    };
    const res = editingId
      ? await apiPost('teamleaders', 'update', { ...payload, id: editingId })
      : await apiPost('teamleaders', 'create', payload);
    closeInlineModal('#tlFormModal');
    if (res.success) {
      showToast({ title: 'Team Leader ' + (editingId ? 'updated' : 'registered'), message: res.message, type: 'success' });
      await loadLeaders();
    } else {
      showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });

  await loadLeaders();
}

async function viewInspectionReport(id) {
  const res = await apiGet('inspection', 'checklist/get', { id });
  if (!res.success) { showToast({ title: 'Error', message: res.error, type: 'danger' }); return; }
  $('#insrReportBody').innerHTML = renderReportDoc(res.data);
  openInlineModal('#insrReportModal');
}

/* --------------------------------------------------------------------------
   INSPECTION HISTORY page
   -------------------------------------------------------------------------- */
async function initInspectionHistoryPage() {
  const tbody = $('#inshTbody');
  if (!tbody) return;
  let page = 1;
  const perPage = 10;

  async function loadHistory() {
    const params = { page, per_page: perPage };
    const search = $('#inshSearch')?.value.trim() || '';
    const status = $('#inshStatusFilter')?.value || '';
    if (search) params.search = search;
    if (status) params.status = status;
    const res = await apiGet('inspection', 'history/list', params).catch(() => ({ data: [], total: 0 }));
    const rows = res.data || [];
    const total = res.total || 0;
    tbody.innerHTML = rows.map(r => `
      <tr>
        <td class="cell-mono">${escapeHtml(r.inspection_no)}</td>
        <td class="cell-mono">${escapeHtml(r.application_no)}</td>
        <td><strong>${escapeHtml(r.project_title)}</strong></td>
        <td>${r.inspection_date ? formatDate(r.inspection_date) : '—'}</td>
        <td>${escapeHtml(r.inspector_name || '—')}</td>
        <td>${statusBadge(r.status)}</td>
        <td><div class="row-actions">
          <button class="icon-btn" title="View details" onclick="viewInspectionHistory(${r.id})">${userIcon('eye')}</button>
          <button class="icon-btn" title="Open checklist" onclick="location.href='inspection-checklist.php?id=${r.id}'">${userIcon('edit')}</button>
          ${getPermSet().has('inspection-delete') ? `<button class="icon-btn" title="Delete" onclick="deleteInspectionRecord(${r.id})">${userIcon('trash')}</button>` : ''}
        </div></td>
      </tr>`).join('') || '<tr><td colspan="7" style="text-align:center;padding:48px;color:var(--gray-400);">No inspection records found.</td></tr>';
    const pi = $('#inshPageInfo');
    if (pi) pi.textContent = total ? `Showing ${(page - 1) * perPage + 1}–${Math.min(page * perPage, total)} of ${total}` : 'No records';
    $('#inshPrev').disabled = page <= 1;
    $('#inshNext').disabled = page * perPage >= total;
  }

  $('#inshRefreshBtn')?.addEventListener('click', loadHistory);
  $('#inshSearch')?.addEventListener('input', debounce(() => { page = 1; loadHistory(); }, 250));
  $('#inshStatusFilter')?.addEventListener('change', () => { page = 1; loadHistory(); });
  $('#inshPrev')?.addEventListener('click', () => { if (page > 1) { page--; loadHistory(); } });
  $('#inshNext')?.addEventListener('click', () => { page++; loadHistory(); });
  bindInlineModal('#inshDetailModal');

  await loadHistory();
}

async function viewInspectionHistory(id) {
  const res = await apiGet('inspection', 'checklist/get', { id });
  if (!res.success) { showToast({ title: 'Error', message: res.error, type: 'danger' }); return; }
  $('#inshDetailBody').innerHTML = renderReportDoc(res.data);
  const openBtn = $('#inshOpenBtn');
  if (openBtn) {
    openBtn.style.display = '';
    openBtn.onclick = () => { location.href = 'inspection-checklist.php?id=' + id; };
  }
  openInlineModal('#inshDetailModal');
}

async function deleteInspectionRecord(id) {
  openConfirm({
    title: 'Delete inspection record?',
    message: 'The inspection checklist, results, and photos will be permanently removed.',
    confirmLabel: 'Delete',
    onConfirm: async () => {
      const res = await apiPost('inspection', 'checklist/delete', { id });
      closeModal();
      if (res.success) { showToast({ title: 'Deleted', message: 'Inspection record removed.', type: 'success' }); window.location.reload(); }
      else showToast({ title: 'Error', message: res.error, type: 'danger' });
    }
  });
}

/* ==========================================================================
   USER SETTINGS
   ========================================================================== */
function initUserSettingsPage() {
  const fullNameEl = $('#settingsFullName');
  const usernameEl = $('#settingsUsername');
  const emailEl = $('#settingsEmail');
  if (fullNameEl) fullNameEl.value = document.body.dataset.fullName || '';
  if (usernameEl) usernameEl.value = document.body.dataset.username || '';
  if (emailEl) emailEl.value = document.body.dataset.email || '';

  const avatarEl = $('#settingsAvatarInitials');
  if (avatarEl) avatarEl.textContent = initials(document.body.dataset.fullName || 'User');

  $('#settingsProfileForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await apiPost('profile', 'update', {
      full_name: $('#settingsFullName')?.value || '',
      email: $('#settingsEmail')?.value || ''
    });
    if (res.success) showToast({ title: 'Profile updated', message: 'Changes saved.', type: 'success' });
    else showToast({ title: 'Error', type: 'danger', message: res.error });
  });

  $('#settingsPasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const np = $('#settingsNewPassword');
    const cp = $('#settingsConfirmPassword');
    if (!np || !cp) return;
    clearFieldError(np); clearFieldError(cp);
    if (np.value.length < 6) { setFieldError(np, 'Minimum 6 characters.'); return; }
    if (np.value !== cp.value) { setFieldError(cp, 'Passwords do not match.'); return; }
    const res = await apiPost('profile', 'change-password', {
      current_password: $('#settingsCurrentPassword')?.value || '',
      new_password: np.value
    });
    if (res.success) { showToast({ title: 'Password changed', message: 'Use new password on next login.', type: 'success' }); np.value = ''; cp.value = ''; }
    else { showToast({ title: 'Error', type: 'danger', message: res.error }); }
  });

  const pwToggle1 = $('#settingsPwToggle1');
  const pwToggle2 = $('#settingsPwToggle2');
  if (pwToggle1) initPasswordToggle(pwToggle1, $('#settingsNewPassword'));
  if (pwToggle2) initPasswordToggle(pwToggle2, $('#settingsConfirmPassword'));
}

/* ==========================================================================
   USER PROFILE
   ========================================================================== */
function initUserProfilePage() {
  const fullName = document.body.dataset.fullName || 'User';
  const photoWrap = $('#profilePhotoWrap');
  const avatarEl = $('#profileHeroAvatar');
  if (avatarEl && !photoWrap.querySelector('img')) avatarEl.textContent = initials(fullName);

  initProfilePhotoUpload('profileCamBtn', 'profilePhotoInput', photoWrap, fullName);

  const nameEl = $('#profileHeroName');
  const usernameEl = $('#profileHeroUsername');
  const emailEl = $('#profileEmail');
  const lastLoginEl = $('#profileLastLogin');
  const statusEl = $('#profileStatus');

  if (nameEl) nameEl.textContent = fullName;
  if (usernameEl) usernameEl.textContent = '@' + (document.body.dataset.username || '');
  if (emailEl) emailEl.textContent = document.body.dataset.email || '—';
  if (lastLoginEl) lastLoginEl.textContent = document.body.dataset.lastLogin || '—';
  if (statusEl) statusEl.innerHTML = '<span class="badge badge-success">Active</span>';

  const roleEl = $('#profileRole');
  if (roleEl) {
    const role = document.body.dataset.role || 'inspector';
    const roleMap = { developer: 'System Administrator', admin: 'Administrator', admin_aid: 'Admin Aid', 'inspector-admin': 'Inspector Admin', inspector: 'Inspector' };
    roleEl.textContent = roleMap[role] || 'Inspector';
  }

  $('#profileEditBtn')?.addEventListener('click', () => { window.location.href = 'settings.php'; });
  $('#profileChangePwBtn')?.addEventListener('click', () => { window.location.href = 'settings.php#password'; });
}

/* ==========================================================================
   MODAL HELPERS
   ========================================================================== */
function openUserModal(html, opts = {}) {
  const root = $('#modalRoot');
  const box = $('#modalBox');
  if (!root || !box) return;
  box.className = 'modal-box' + (opts.size === 'wide' ? ' wide' : '');
  box.innerHTML = html;
  root.classList.add('open');
  root.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', closeUserModal));
  root.onclick = (e) => { if (e.target === root) closeUserModal(); };
}

function closeUserModal() {
  const root = $('#modalRoot');
  if (root) root.classList.remove('open');
}
