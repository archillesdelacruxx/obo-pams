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
    'notifications': initNotificationsPage,
    'announcements': initAnnouncementsPage,
    'permit-approval-encoding': initPermitApprovalEncodingPage,
    'permit-encoding-form': initPermitEncodingFormPage,
    'permit-approval-records': initPermitApprovalRecordsPage,
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

  let userPerms = [];
  try { userPerms = JSON.parse(document.body.dataset.permissions || '[]'); } catch (e) {}
  const permSet = new Set(userPerms);

  const hasPerm = key => permSet.has(key);

  try {
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
  } catch (e) {
    console.error('Dashboard load error:', e);
  }

  let notifData = [];
  if (hasPerm('notifications')) {
    const notifRes = await apiGet('notifications', 'list').catch(() => ({ data: [] }));
    notifData = notifRes.data || [];
  }

  const notifFeed = $('#userNotifFeed');
  if (notifFeed) {
    notifFeed.innerHTML = notifData.slice(0, 3).map((n, i) => `
      <div class="notif-item ${n.is_read ? '' : 'unread'}" data-notif-index="${i}" style="cursor:pointer;">
        <div class="ni-dot" ${n.is_read ? 'style="opacity:0"' : ''}></div>
        <div class="ni-body"><strong>${n.title}</strong><p>${(n.message || '').substring(0, 80)}</p><time>${timeAgo(n.created_at)}</time></div>
      </div>`).join('') || '<p style="text-align:center;padding:24px;color:var(--gray-400);font-size:13px;">No notifications yet.</p>';
    notifFeed.querySelectorAll('.notif-item').forEach(el => {
      el.addEventListener('click', () => {
        const idx = parseInt(el.dataset.notifIndex);
        const n = notifData[idx];
        if (!n) return;
        const label = MODULE_LABELS[n.module_name] || n.module_name || 'System';
        const color = MODULE_COLORS[n.module_name] || 'var(--gray-400)';
        openModal(`
          <div class="modal-head">
            <h3>${n.title}</h3>
            <button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button>
          </div>
          <div class="modal-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
              <div style="display:flex;gap:10px;align-items:center;">
                <span class="badge badge-neutral" style="font-size:11px;background:${color};color:#fff;">${label}</span>
                <span style="font-size:12px;color:var(--gray-400);">${n.created_at ? formatDate(n.created_at) : ''}</span>
                ${n.is_read ? '' : '<span style="font-size:11px;color:var(--color-primary);font-weight:600;">● Unread</span>'}
              </div>
              <p style="font-size:14px;line-height:1.6;color:var(--gray-700);">${n.message}</p>
              ${n.sender_name ? `<div style="font-size:12px;color:var(--gray-500);border-top:1px solid var(--gray-100);padding-top:12px;">From <strong>${n.sender_name}</strong></div>` : ''}
            </div>
          </div>
          <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button></div>`);
      });
    });
  }

  let annData = [];
  if (hasPerm('announcements')) {
    const annRes = await apiGet('announcements', 'list').catch(() => ({ data: [] }));
    annData = annRes.data || [];
  }

  const annFeed = $('#userAnnouncementsFeed');
  if (annFeed) {
    annFeed.innerHTML = annData.slice(0, 3).map(a => `
      <div class="announcement">
        <h5>${a.title}</h5>
        <p>${(a.content || '').substring(0, 100)}…</p>
      </div>`).join('');
  }
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
  const res = await apiGet('op', 'list', { id });
  const r = Array.isArray(res.data) ? res.data.find(d => d.id == id) : null;
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
  const res = await apiGet('op', 'list', { id });
  const r = Array.isArray(res.data) ? res.data.find(d => d.id == id) : null;
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
  if (searchInput) {
    const handler = debounce((q) => {
      $$('#opRecordsTbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
      });
    }, 220);
    searchInput.addEventListener('input', e => handler(e.target.value));
  }

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
}

async function loadWorkflowTable(search = '') {
  const tbody = $('#workflowTbody');
  if (!tbody) return;
  const res = await apiGet('workflow', 'list', search ? { search } : {}).catch(() => ({ data: [] }));
  const rows = res.data || [];
  const isAdmin = document.body.dataset.isAdmin === '1';

  tbody.innerHTML = rows.map(r => {
    const lastIn = r.latest_last_in ? formatDate(r.latest_last_in) : '—';
    const lastOutHtml = r.latest_last_out ? formatDate(r.latest_last_out) : (r.latest_last_in ? '<span style="color:var(--gray-400);font-style:italic;">In progress</span>' : '—');
    const procDays = r.latest_processing_days ?? 0;
    const tat = r.total_tat || 0;
    const actions = [];
    actions.push(`<button class="icon-btn" title="View" onclick="window.location.href='workflow-details.php?id=${r.id}'">${userIcon('eye')}</button>`);
    actions.push(`<button class="icon-btn" title="Edit" onclick="editWorkflow(${r.id})">${userIcon('edit')}</button>`);
    actions.push(`<button class="icon-btn" title="Print" onclick="printWorkflow(${r.id})">${userIcon('printer')}</button>`);
    if (isAdmin) {
      actions.push(`<button class="icon-btn" title="Delete" onclick="deleteWorkflow(${r.id})">${userIcon('trash')}</button>`);
    }
    return `<tr>
      <td class="cell-mono">${r.application_no}</td>
      <td>${r.applicant_name}</td>
      <td><span class="round-chip">Round ${r.current_round || 1}</span></td>
      <td>${lastIn}</td>
      <td>${lastOutHtml}</td>
      <td class="tat-days">${procDays} day${procDays !== 1 ? 's' : ''}</td>
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
          <div class="form-group"><label>Date Paid</label><input class="form-control" type="date" id="editDatePaid" value="${r.date_paid || ''}"></div>
          <div class="form-group"><label>Released</label><input class="form-control" type="date" id="editReleased" value="${r.released || ''}"></div>
          <div class="form-group"><label>First In Date</label><input class="form-control" type="date" id="editFirstIn" value="${r.first_in || ''}"></div>
          <div class="form-group"><label>Status</label><select class="form-control" id="editStatus"><option value="in-progress" ${r.status === 'in-progress' ? 'selected' : ''}>In Process</option><option value="completed" ${r.status === 'completed' ? 'selected' : ''}>Completed</option><option value="returned" ${r.status === 'returned' ? 'selected' : ''}>Returned</option><option value="approved" ${r.status === 'approved' ? 'selected' : ''}>Approved</option></select></div>
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
              <option value="pending">Pending</option>
              <option value="in-progress">Under Review</option>
              <option value="approved">Approved</option>
            </select>
          </div>
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
      const lastOutHtml = r.last_out ? formatDate(r.last_out) : '<span style="color:var(--gray-400);font-style:italic;">In progress</span>';
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
        <td class="tat-days">${r.processing_days} day${r.processing_days !== 1 ? 's' : ''}</td>
        <td class="remarks-cell">${r.remarks || ''}</td>
        <td><div class="row-actions">${actions.join('')}</div></td>
      </tr>`;
    }).join('');
  } else if (tbody) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--gray-400);font-style:italic;">No rounds yet. Click "Add Round" to start.</td></tr>';
  }

const totalTat = (data.rounds || []).reduce((s, r) => s + (r.processing_days || 0), 0);
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
          <div class="form-group full"><label>Remarks</label><textarea class="form-control" id="newRoundRemarks" rows="3" placeholder="Enter remarks…"></textarea></div>
        </div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" data-close-modal>Cancel</button>
      <button class="btn btn-primary" id="saveRoundBtn">${userIcon('plus')} Add Round</button>
    </div>`);

  $('#saveRoundBtn')?.addEventListener('click', async () => {
    const lastIn = $('#newRoundIn')?.value;
    const lastOut = $('#newRoundOut')?.value || null;
    const remarks = $('#newRoundRemarks')?.value.trim() || '';
    const res = await apiPost('workflow', 'add-round', { workflow_id: data.id, last_in: lastIn, last_out: lastOut, remarks });
    if (res.success) {
      closeUserModal();
      showToast({ title: 'Round added', message: `Round ${nextRound} added to ${data.application_no}.`, type: 'success' });
      setTimeout(() => window.location.reload(), 500);
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
          <div class="form-group"><label>Processing Days</label><input class="form-control" type="number" id="editRoundDays" value="${round.processing_days || 0}" min="0"></div>
          <div class="form-group full"><label>Remarks</label><textarea class="form-control" id="editRoundRemarks" rows="3" placeholder="Enter remarks…">${round.remarks || ''}</textarea></div>
        </div>
      </form>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Cancel</button><button class="btn btn-primary" id="saveEditRoundBtn">${userIcon('check')} Save</button></div>`);
  $('#saveEditRoundBtn')?.addEventListener('click', async () => {
    const lastIn = $('#editRoundIn')?.value || null;
    const lastOut = $('#editRoundOut')?.value || null;
    const processingDays = parseInt($('#editRoundDays')?.value) || 0;
    const remarks = $('#editRoundRemarks')?.value.trim() || '';
    const upd = await apiPost('workflow', 'update-round', { workflow_id: workflowId, round_number: roundNumber, last_in: lastIn, last_out: lastOut, processing_days: processingDays, remarks });
    if (upd.success) { closeUserModal(); showToast({ title: 'Updated', type: 'success', message: `Round ${roundNumber} updated.` }); setTimeout(() => window.location.reload(), 500); }
    else { showToast({ title: 'Error', type: 'danger', message: upd.error || 'Failed to update round.' }); }
  });
}

async function deleteRound(workflowId, roundNumber) {
  if (!confirm(`Delete Round ${roundNumber}? This cannot be undone.`)) return;
  const res = await apiPost('workflow', 'delete-round', { workflow_id: workflowId, round_number: roundNumber });
  if (res.success) { showToast({ title: 'Deleted', type: 'success', message: `Round ${roundNumber} deleted.` }); setTimeout(() => window.location.reload(), 500); }
  else { showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to delete round.' }); }
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

async function initNotificationsPage() {
  const tbody = $('#notifTbody');
  if (!tbody) return;

  const res = await apiGet('notifications', 'list').catch(() => ({ data: [] }));
  const notifs = res.data || [];

  tbody.innerHTML = notifs.map((n, i) => {
    const label = MODULE_LABELS[n.module_name] || n.module_name || 'System';
    const color = MODULE_COLORS[n.module_name] || 'var(--gray-400)';
    return `
    <tr class="${n.is_read ? '' : 'notif-unread'}" data-index="${i}" style="cursor:pointer;">
      <td><span class="${n.is_read ? '' : 'unread-dot'}" style="${n.is_read ? 'opacity:0.3;' : ''}">${n.is_read ? '○' : '●'}</span></td>
      <td><span class="badge" style="font-size:11px;background:${color};color:#fff;">${label}</span></td>
      <td><strong>${n.title}</strong></td>
      <td>${(n.message || '').substring(0, 80)}${(n.message || '').length > 80 ? '…' : ''}</td>
      <td style="white-space:nowrap;">${n.created_at ? formatDate(n.created_at) : '—'}</td>
    </tr>`;
  }).join('');

  tbody.querySelectorAll('tr').forEach((row, i) => {
    row.addEventListener('click', async () => {
      const n = notifs[i];
      if (!n) return;
      if (!n.is_read) {
        await apiGet('notifications', 'mark-read', { id: n.id });
      }
      const label = MODULE_LABELS[n.module_name] || n.module_name || 'System';
      const color = MODULE_COLORS[n.module_name] || 'var(--gray-400)';
      openModal(`
        <div class="modal-head">
          <h3>${n.title}</h3>
          <button class="icon-btn" data-close-modal aria-label="Close">${userIcon('x')}</button>
        </div>
        <div class="modal-body">
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;gap:10px;align-items:center;">
              <span class="badge badge-neutral" style="font-size:11px;background:${color};color:#fff;">${label}</span>
              <span style="font-size:12px;color:var(--gray-400);">${n.created_at ? formatDate(n.created_at) : ''}</span>
              ${n.is_read ? '' : '<span style="font-size:11px;color:var(--color-primary);font-weight:600;">● Unread</span>'}
            </div>
            <p style="font-size:14px;line-height:1.6;color:var(--gray-700);">${n.message}</p>
            ${n.sender_name ? `<div style="font-size:12px;color:var(--gray-500);border-top:1px solid var(--gray-100);padding-top:12px;">From <strong>${n.sender_name}</strong></div>` : ''}
          </div>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" data-close-modal>Close</button></div>`);
    });
  });
}

/* ==========================================================================
   ANNOUNCEMENTS
   ========================================================================== */
async function initAnnouncementsPage() {
  const tbody = $('#announcementsTbody');
  if (!tbody) return;

  const res = await apiGet('announcements', 'list').catch(() => ({ data: [] }));
  const announcements = res.data || [];

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
    });
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
  if (roleEl) roleEl.textContent = 'User';

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
