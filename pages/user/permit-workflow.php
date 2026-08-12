<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('permit-workflow');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Permit Workflow Â· PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260812c">
</head>
<body data-page="permit-workflow" data-is-admin="<?php echo !empty($_SESSION['is_admin']) ? '1' : '0'; ?>">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('permit-workflow'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Permit Workflow'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Permit Workflow</span></div>
        <div class="page-head">
          <div><h1>Permit Workflow</h1><p class="subtitle">Track and manage the lifecycle of each permit application from first-in to release.</p></div>
          <button class="btn btn-primary" id="createWorkflowBtn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> Create Workflow</button>
        </div>
        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input type="text" id="workflowSearch" placeholder="Search by permit number or applicantâ€¦"></div>
            <span class="text-xs text-muted" id="workflowRecordCount"></span>
            <button class="btn btn-secondary btn-sm" id="workflowRefresh">Refresh</button>
            <button class="btn btn-secondary btn-sm" id="workflowExport"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export in Excel</button>
          </div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>No.</th><th>Application No.</th><th>Applicant</th><th>Current Round</th><th>Last In Date</th><th>Last Out Date</th><th>Processing Days</th><th>Current Status</th><th>TAT</th><th>Action</th></tr></thead><tbody id="workflowTbody"></tbody></table>
          </div>
        </div>
      </main>
    </div>
    <div class="modal-wrap" id="modalRoot">
      <div class="backdrop"></div>
      <div class="modal-box" id="modalBox"></div>
    </div>
  </div>
  <script src="../../assets/js/utilities.js"></script>
  <script src="../../assets/js/api.js"></script>
  <script src="../../assets/js/validation.js"></script>
  <script src="../../assets/js/sidebar.js"></script>
  <script src="../../assets/js/dropdown.js"></script>
  <script src="../../assets/js/notification.js"></script>
  <script src="../../assets/js/modal.js"></script>
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260812d"></script>
</body>
</html>

