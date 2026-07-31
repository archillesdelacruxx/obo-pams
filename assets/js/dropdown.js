/* ==========================================================================
   PAMS — Generic dropdown behavior (notifications + profile menu)
   ========================================================================== */
function closeAllDropdowns(except){
  $$('.dropdown-panel.open').forEach(p => { if (p !== except) p.classList.remove('open'); });
}

async function initHeaderDropdowns(){
  const notifBtn = $('#notifBtn');
  const notifPanel = $('#notifPanel');
  const profileTrigger = $('#profileTrigger');
  const profilePanel = $('#profilePanel');

  if (notifPanel) notifPanel.innerHTML = await renderNotificationPanel();

  notifBtn && notifBtn.addEventListener('click', async (e) => {
    e.stopPropagation();
    closeAllDropdowns(notifPanel);
    notifPanel.classList.toggle('open');
    if (notifPanel.classList.contains('open')) {
      notifPanel.innerHTML = await renderNotificationPanel();
    }
  });

  profileTrigger && profileTrigger.addEventListener('click', (e) => {
    e.stopPropagation();
    closeAllDropdowns(profilePanel);
    profilePanel.classList.toggle('open');
  });

  document.addEventListener('click', () => closeAllDropdowns());
  $$('.dropdown-panel').forEach(p => p.addEventListener('click', e => e.stopPropagation()));

  const logoutTrigger = $('#logoutTrigger');
  logoutTrigger && logoutTrigger.addEventListener('click', () => {
    closeAllDropdowns();
    openLogoutConfirm('../logout.php');
  });
}
