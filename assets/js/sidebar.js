/* ==========================================================================
   PAMS — Sidebar behavior: collapse (desktop) + slide-in (mobile)
   The hamburger/burger button (#mobileMenuBtn) is the single control for both.
   ========================================================================== */
function initSidebar(){
  const shell = $('#appShell');
  const burgerBtn = $('#mobileMenuBtn');

  // One burger button — collapses on desktop, slides in on mobile
  burgerBtn && burgerBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (window.innerWidth <= 960) {
      shell.classList.toggle('mobile-open');
    } else {
      shell.classList.toggle('collapsed');
    }
  });

  // Close mobile overlay when clicking outside sidebar
  document.addEventListener('click', (e) => {
    if (shell.classList.contains('mobile-open') &&
        !e.target.closest('.sidebar') && !e.target.closest('#mobileMenuBtn')){
      shell.classList.remove('mobile-open');
    }
  });
}

/* Any element carrying data-nav or data-user-nav becomes a navigation trigger.
   Capture phase is used so navigation still fires even when a descendant (e.g.
   .dropdown-panel) stops propagation during the bubble phase. */
function initNavRouting(){
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-nav], [data-user-nav]');
    if (!trigger) return;
    const target = trigger.getAttribute('data-nav') || trigger.getAttribute('data-user-nav');
    if (!target) return;
    window.location.href = target;
  }, true);
}
