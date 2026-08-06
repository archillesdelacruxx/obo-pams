<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('workflow-details');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Workflow Details Â· PAMS User</title>
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
  <link rel="stylesheet" href="../../assets/css/user.css">
</head>
<body data-page="workflow-details" data-is-admin="<?php echo !empty($_SESSION['is_admin']) ? '1' : '0'; ?>">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('workflow-details'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Workflow Details'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><a href="permit-workflow.php" style="color:var(--gray-500);">Permit Workflow</a><span class="sep">/</span><span class="current">Workflow Details</span></div>
        <div class="page-head"><div><h1>Workflow Details</h1><p class="subtitle">Review the complete processing timeline for a permit application.</p></div><a class="btn btn-ghost btn-sm" href="permit-workflow.php"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Back to Permit Workflow</a></div>
        <div id="wdLoading" style="text-align:center;padding:48px 24px;color:var(--gray-400);">Loading workflow detailsâ€¦</div>
        <div id="wdError" style="display:none;text-align:center;padding:48px 24px;color:var(--danger);">Failed to load workflow details.</div>
        <div id="wdEmpty" style="display:none;">
          <div class="section-card">
            <div class="empty-state">
              <div class="es-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg></div>
              <h4>No Workflow Details</h4>
              <p>No workflow data available for this application.</p>
            </div>
          </div>
        </div>
        <div id="wdContent" style="display:none;">
        <div class="permit-info-header" id="wdInfoHeader">
          <div class="pih-field"><label>Permit No.</label><span class="permit-number" id="wdPermitNumber">â€”</span></div>
          <div class="pih-field"><label>Applicant</label><span id="wdApplicant">â€”</span></div>
          <div class="pih-field"><label>App. No.</label><span id="wdAppNo">â€”</span></div>
          <div class="pih-field"><label>Application</label><span id="wdApplication">â€”</span></div>
          <div class="pih-field"><label>Permit Type</label><span id="wdType">â€”</span></div>
          <div class="pih-field"><label>Current Round</label><span id="wdCurrentRound">Round 1</span></div>
          <div class="pih-field"><label>Total TAT</label><span class="permit-number" id="wdTotalTat">0 days</span></div>
          <div class="pih-field"><label>Total Rounds</label><span id="wdTotalRounds">0</span></div>
          <div class="pih-field"><label>Last Updated</label><span id="wdLastUpdated">â€”</span></div>
          <div class="pih-field pih-status"><label>Status</label><span id="wdStatus"></span></div>
        </div>
        <div class="section-card" style="margin-top:16px;">
          <div class="section-head"><h3>Round Timeline</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" id="addRoundBtn"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> Add Round</button></div>
          <div class="section-body" style="padding:0;">
            <div class="scroll-x" style="max-height:55vh;overflow-y:auto;">
              <table class="data-table"><thead><tr><th>Round</th><th>Application No.</th><th>Last In Date</th><th>Last Out Date</th><th>TAT</th><th>Remarks</th><th>Action</th></tr></thead><tbody id="wdTimelineTbody"></tbody></table>
            </div>
          </div>
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
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

