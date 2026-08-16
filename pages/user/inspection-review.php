<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('inspection-review');
$perms = getUserModulePermissions();
$canReview = !empty($perms['inspection-edit']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspection Review · PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260816c">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260817d">
</head>
<body data-page="inspection-review" data-is-admin="<?php echo empty($_SESSION['is_admin']) ? '0' : '1'; ?>" data-permissions='<?php echo json_encode(array_keys(array_filter($perms))); ?>'>
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('inspection-review'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Inspection Review'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Inspection Management</span><span class="sep">/</span><span class="current">Inspection Review</span></div>
        <div class="page-head"><div><h1>Inspection Review</h1><p class="subtitle">Review, approve, or reject inspections submitted for review.</p></div></div>

        <div class="section-card table-card">
          <div class="section-head">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <h3 id="insrqTitle">Under Review Queue (<span id="insrqCount">0</span>)</h3>
              <input type="search" class="form-control form-control-sm" id="insrqSearch" placeholder="Search title, inspection no., inspector..." style="width:280px;padding:6px 12px;">
            </div>
            <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" id="insrqRefreshBtn">Refresh</button>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead><tr><th>Inspection No.</th><th>Application No.</th><th>Project Title</th><th>Inspection Date</th><th>Inspecting Team</th><th>Inspector</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody id="insrqTbody"></tbody>
            </table>
          </div>
          <div class="table-footer"><span class="text-xs text-muted" id="insrqPageInfo"></span></div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-wrap" id="insrqModal">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box confirm-modal">
      <div class="modal-head">
        <h3 id="insrqModalTitle">Review Inspection</h3>
        <button type="button" class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <p class="text-sm text-muted" id="insrqRecordInfo"></p>
        <div class="form-group full" style="margin-top:12px;" id="insrqRemarksWrap" hidden>
          <label>Remarks <span class="req">*</span></label>
          <textarea class="form-control" id="insrqRemarks" rows="2" placeholder="Explain why you are rejecting this inspection"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" data-close-modal>Cancel</button>
        <button class="btn btn-danger" id="insrqRejectBtn" style="display:none;">Reject</button>
        <button class="btn btn-success" id="insrqApproveBtn" style="display:none;">Approve</button>
      </div>
    </div>
  </div>

  <div class="modal-wrap" id="insrqReportModal">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box lg confirm-modal report-modal">
      <div class="modal-head">
        <h3>Monitoring Report</h3>
        <button type="button" class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="modal-body"><div id="insrqReportBody" class="report-doc"></div></div>
      <div class="modal-foot">
        <button class="btn btn-secondary" data-close-modal>Close</button>
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
  <script src="../../assets/js/user-app.js?v=20260817c"></script>
</body>
</html>
