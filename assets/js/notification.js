/* ==========================================================================
   PAMS — Notification panel content (fetched from API)
   ========================================================================== */
let _adminNotifCache = [];

async function renderNotificationPanel(){
  try {
    const res = await apiGet('notifications', 'list');
    _adminNotifCache = res.data || [];
  } catch (e) { _adminNotifCache = []; }

  const items = _adminNotifCache.slice(0, 5).map(n => {
    const typeMap = { announcement: 'blue', approved: 'green', record: 'orange' };
    const t = typeMap[n.type] || 'blue';
    return `<div class="notif-item ${n.is_read ? '' : 'unread'}">
      <div class="n-icon icon-${t}">${icon('bell')}</div>
      <div class="n-content">
        <strong>${escapeHtml(n.title)}</strong>
        <p>${escapeHtml((n.message || '').substring(0, 75))}</p>
        <time>${timeAgo(n.created_at)}</time>
      </div>
    </div>`;
  }).join('');

  return `
    <div class="notif-head">
      <strong>Notifications</strong>
      <a href="#" id="markAllRead" style="font-size:11.5px;font-weight:700;color:var(--color-primary);text-decoration:none;">Mark all read</a>
    </div>
    <div class="notif-list">${items || '<div style="padding:16px;text-align:center;color:var(--gray-400);">No notifications</div>'}</div>
    <div class="notif-footer"><a href="notifications.php" data-nav="notifications.php">View all notifications</a></div>`;
}

document.addEventListener('click', (e) => {
  if (e.target && e.target.id === 'markAllRead'){
    e.preventDefault();
    apiPost('notifications', 'mark-all-read').catch(() => {});
    $$('.notif-item').forEach(i => i.style.opacity = '.55');
    showToast({ title: 'All caught up', message: 'Notifications marked as read.', type: 'success' });
  }
});
