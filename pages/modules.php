<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modules · PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:w500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260816c">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="modules" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('modules'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Modules'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Modules</span></div>
        <div class="page-head">
          <div>
            <h1>Modules</h1>
            <p class="subtitle">View available modules and their current status.</p>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head"><h3>System Modules</h3></div>
          <div class="section-body">
            <div class="module-switch-list" id="modulesList"></div>
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
  const MODULE_LABELS = {
    'order-of-payment': { label: 'Order of Payment', desc: 'Encode and track OP records.', icon: 'file-text' },
    'op-records': { label: 'OP Records', desc: 'View all encoded OP records.', icon: 'layers' },
    'permit-workflow': { label: 'Permit Workflow', desc: 'Track permit processing lifecycle.', icon: 'git-branch' },
    'workflow-details': { label: 'Workflow Details', desc: 'View detailed workflow rounds.', icon: 'git-branch' },
    'permit-approval-encoding': { label: 'Permit Approval Encoding', desc: 'Encode permit approvals.', icon: 'award' },
    'permit-approval-records': { label: 'Permit Approval Records', desc: 'View approval records.', icon: 'layers' },
    'releasing': { label: 'Releasing Plans', desc: 'Manage folder releasing.', icon: 'package' },
    'releasing-records': { label: 'Releasing Records', desc: 'View release records.', icon: 'layers' },
    'inspection-checklist': { label: 'Ocular Inspection Checklist', desc: 'Fill out and manage inspection checklists.', icon: 'clipboard' },
    'inspection-reports': { label: 'Monitoring Reports', desc: 'View and print monitoring reports.', icon: 'activity' }
  };

  (async function() {
    const list = $('#modulesList');
    try {
      const res = await apiGet('settings', 'modules');
      const modules = res.data || [];
      const active = modules.filter(m => m.status === 'active');
      const dev = modules.filter(m => m.status !== 'active');

      let html = '';
      if (active.length) {
        html += '<div style="margin-bottom:16px;"><strong style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);">Active Modules</strong></div>';
        html += active.map(m => {
          const info = MODULE_LABELS[m.key] || { label: m.label || m.key, desc: '' };
          return `<div class="module-switch-row">
            <div class="m-info">
              <strong>${escapeHtml(info.label)}</strong>
              <span>${escapeHtml(info.desc)}</span>
            </div>
            <span class="badge badge-success">Active</span>
          </div>`;
        }).join('');
      }
      if (dev.length) {
        html += '<div style="margin:20px 0 16px;"><strong style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gray-500);">Under Development</strong></div>';
        html += dev.map(m => {
          const info = MODULE_LABELS[m.key] || { label: m.label || m.key, desc: '' };
          return `<div class="module-switch-row" style="opacity:.7;">
            <div class="m-info">
              <strong>${escapeHtml(info.label)}</strong>
              <span>${escapeHtml(info.desc)}</span>
            </div>
            <span class="badge badge-neutral">Under Development</span>
          </div>`;
        }).join('');
      }
      list.innerHTML = html || '<div style="padding:24px;text-align:center;color:var(--gray-400);">No modules configured.</div>';
    } catch (e) {
      list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-400);">Failed to load modules.</div>';
    }
  })();
  </script>
</body>
</html>
