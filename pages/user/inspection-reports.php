<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('inspection-reports');
$perms = getUserModulePermissions();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspection Monitoring Reports · PAMS User</title>
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
  <style>
    @media print {
      @page { size: 8.5in 13in; margin: 0.4in; }
    }
  </style>
</head>
<body data-page="inspection-reports" data-is-admin="<?php echo empty($_SESSION['is_admin']) ? '0' : '1'; ?>" data-permissions='<?php echo json_encode(array_keys(array_filter($perms))); ?>'>
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('inspection-reports'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Inspection Monitoring Reports'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Inspection Management</span><span class="sep">/</span><span class="current">Monitoring Reports</span></div>
        <div class="page-head"><div><h1>Inspection Monitoring Reports</h1><p class="subtitle">Auto-generated monitoring reports from completed on-site ocular inspections.</p></div></div>

        <div class="section-card table-card">
          <div class="section-head"><h3>Inspection Records</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" id="insrRefreshBtn">Refresh</button></div>
          <div class="table-toolbar" style="padding:0 20px 14px;border:none;">
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" id="insrSearch" placeholder="Search inspections…">
            </div>
            <select id="insrStatusFilter" class="form-control" style="width:auto;min-width:150px;">
              <option value="">All statuses</option>
              <option>Completed</option>
              <option>Approved</option>
              <option>Under Review</option>
              <option>Draft</option>
              <option>Rejected</option>
            </select>
          </div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>Inspection No.</th><th>Application No.</th><th>Project Title</th><th>Inspection Date</th><th>Inspector</th><th>Status</th></tr></thead><tbody id="insrTbody"></tbody></table>
          </div>
          <div class="table-footer">
            <span class="text-xs text-muted" id="insrPageInfo"></span>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-wrap" id="insrReportModal">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box lg confirm-modal report-modal">
      <div class="modal-head">
        <h3>Monitoring Report</h3>
        <button type="button" class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div id="insrReportBody" class="report-doc"></div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" data-close-modal>Close</button>
        <button class="btn btn-primary" id="insrPrintReportBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print Report</button>
      </div>
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
  <script src="../../assets/js/user-app.js?v=20260804f"></script>
</body>
</html>
