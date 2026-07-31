<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('releasing');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Releasing Plans · PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css">
</head>
<body data-page="releasing">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('releasing'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Releasing Plans'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Releasing Plans</span></div>
        <div class="page-head"><div><h1>Releasing Plans</h1><p class="subtitle">Encode and manage folder releasing records.</p></div></div>
        <div class="two-col">
          <div class="section-card">
            <div class="section-head"><h3>New Releasing Record</h3></div>
            <div class="section-body">
              <form id="relForm">
                <div class="form-grid">
                  <div class="form-group"><label>Date <span class="req">*</span></label><input class="form-control" type="date" id="relDate"></div>
                  <div class="form-group"><label>Permit Number <span class="req">*</span></label><input class="form-control form-mono" type="text" id="relPermitNumber" placeholder="e.g. BPLO-2026-0950"></div>
                  <div class="form-group"><label>Applicant <span class="req">*</span></label><input class="form-control" type="text" id="relApplicant" placeholder="Full name of applicant"></div>
                  <div class="form-group"><label>Claimed By</label><input class="form-control" type="text" id="relClaimedBy" placeholder="Name of person claiming"></div>
                  <div class="form-group"><label>Time Released</label><input class="form-control" type="time" id="relTimeReleased"></div>
                </div>
                <div class="flex gap-sm" style="margin-top:8px;">
                  <button type="button" class="btn btn-primary" id="relSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Record</button>
                  <button type="button" class="btn btn-secondary" id="relClearBtn">Clear</button>
                </div>
              </form>
            </div>
          </div>
          <div>
            <div class="section-card">
              <div class="section-head"><h3>Today's Releases</h3></div>
              <div class="scroll-x">
                <table class="data-table"><thead><tr><th>Permit App No.</th><th>Applicant</th><th>Claimed By</th><th>Time Released</th></tr></thead><tbody id="relTodayTbody"></tbody></table>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../../assets/js/utilities.js"></script>
  <script src="../../assets/js/api.js"></script>
  <script src="../../assets/js/validation.js"></script>
  <script src="../../assets/js/sidebar.js"></script>
  <script src="../../assets/js/dropdown.js"></script>
  <script src="../../assets/js/notification.js"></script>
  <script src="../../assets/js/modal.js"></script>
  <script src="../../assets/js/user-components.js"></script>
  <script src="../../assets/js/user-app.js?v=20260802"></script>
</body>
</html>
