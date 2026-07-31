/* ==========================================================================
   PAMS — Modal system: generic open/close + confirmation dialog factory
   ========================================================================== */
function openModal(html, opts = {}){
  let root = $('#modalRoot');
  let box = $('#modalBox');
  if (!root) {
    root = document.createElement('div');
    root.className = 'modal-wrap';
    root.id = 'modalRoot';
    root.innerHTML = '<div class="backdrop"></div><div class="modal-box" id="modalBox"></div>';
    document.body.appendChild(root);
    box = $('#modalBox', root);
  }
  box.className = `modal-box ${opts.size || ''} ${opts.className || ''}`.trim();
  box.innerHTML = html;
  root.classList.add('open');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';
}

function closeModal(){
  const root = $('#modalRoot');
  if (!root) return;
  root.classList.remove('open');
  document.body.classList.remove('modal-open');
  document.body.style.overflow = '';
  setTimeout(() => { const box = $('#modalBox'); if (box) box.innerHTML = ''; }, 220);
}

document.addEventListener('click', (e) => {
  if (e.target.matches('[data-close-modal]') || e.target.closest('[data-close-modal]')) closeModal();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
});

/* Reusable confirmation dialog. onConfirm receives no args; caller closes
   modal itself only on success paths that need to stay open on error. */
function confirmIcon(name){
  const icons = {
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    trash:  '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/>',
    bell:   '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'
  };
  return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">' + (icons[name] || icons.bell) + '</svg>';
}
function openConfirm({ title, message, confirmLabel = 'Confirm', tone = 'danger', icon: iconName, onConfirm }){
  const toneIcon = iconName || (tone === 'danger' ? 'trash' : 'bell');
  const iconBg = tone === 'danger' ? 'icon-red' : 'icon-blue';
  openModal(`
    <div class="confirm-modal">
      <div class="modal-head" style="border-bottom:none; justify-content:flex-end; padding-bottom:0;">
        <button class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="c-icon ${iconBg}">${confirmIcon(toneIcon)}</div>
        <h3>${title}</h3>
        <p>${message}</p>
      </div>
      <div class="modal-foot" style="justify-content:center;">
        <button class="btn btn-secondary" data-close-modal>Cancel</button>
        <button class="btn ${tone === 'danger' ? 'btn-danger' : 'btn-primary'}" id="confirmActionBtn">${confirmLabel}</button>
      </div>
    </div>`, { size: 'sm' });

  $('#confirmActionBtn').addEventListener('click', () => {
    if (onConfirm) onConfirm();
  });
}

function openLogoutConfirm(redirect){
  openConfirm({
    title: 'Log out of PAMS?',
    message: 'You will need to sign in again to access the Permit Application Management System.',
    confirmLabel: 'Logout',
    tone: 'danger',
    icon: 'logout',
    onConfirm: () => {
      closeModal();
      const loader = showPageLoader();
      setTimeout(() => { window.location.href = redirect || '../../index.php'; }, 600);
    }
  });
}
