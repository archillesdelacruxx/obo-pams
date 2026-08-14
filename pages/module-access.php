<?php
require_once __DIR__ . '/../includes/dev-shell.php';
requireDeveloper();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Module Access · PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:w500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="module-access" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderDevSidebar('module-access'); ?>

    <div class="main-col">
      <?php echo renderDevHeader('Module Access'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Module Access</span></div>
        <div class="page-head">
          <div>
            <h1>Module Access</h1>
            <p class="subtitle">Turn modules ON or OFF. When OFF, users will see "Under Development" notice.</p>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head"><h3>System Modules</h3><span class="badge badge-info">Developer Control</span></div>
          <div class="section-body">
            <p class="text-sm text-muted" style="margin-bottom:14px;">
              Turning a module OFF shows an "Under Development" notice to all users who try to access it.
            </p>
            <div class="module-switch-list" id="devModuleList"></div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script src="../assets/js/dropdown.js"></script>
  <script src="../assets/js/app.js?v=20260816b"></script>
  <script src="../assets/js/modal.js"></script>
  <script>
  (async function() {
    const list = $('#devModuleList');
    async function loadModules() {
      try {
        const res = await apiGet('settings', 'modules');
        const modules = res.data || [];
        list.innerHTML = modules.map(m => `
          <div class="module-switch-row">
            <div class="m-info">
              <strong>${escapeHtml(m.label)}</strong>
              <span>${m.status === 'active' ? 'Operational' : '<span style="color:var(--warning,#eab308);font-weight:600;">Under Development</span>'}</span>
            </div>
            <label class="switch">
              <input type="checkbox" data-module="${escapeHtml(m.key)}" ${m.status === 'active' ? 'checked' : ''}>
              <span class="track"></span>
            </label>
          </div>`).join('');
      } catch (e) {
        list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-400);">Failed to load modules</div>';
      }
    }
    await loadModules();
    list.addEventListener('change', async (e) => {
      const input = e.target.closest('[data-module]');
      if (!input) return;
      const key = input.dataset.module;
      const status = input.checked ? 'active' : 'under_development';
      try {
        const res = await apiPost('settings', 'toggle-module', { key, status });
        if (res.success) {
          showToast({ title: input.checked ? 'Module enabled' : 'Module disabled', message: `${key} is now ${input.checked ? 'active' : 'under development'}.`, type: 'success' });
          await loadModules();
        } else {
          showToast({ title: 'Error', message: res.error || 'Failed to update.', type: 'error' });
          input.checked = !input.checked;
        }
      } catch (err) {
        showToast({ title: 'Error', message: 'Failed to update module status.', type: 'error' });
        input.checked = !input.checked;
      }
    });
  })();
  </script>
</body>
</html>
