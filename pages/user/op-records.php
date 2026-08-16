<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('op-records');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OP Records Â· PAMS User</title>
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
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260812c">
</head>
<body data-page="op-records">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('op-records'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('OP Records'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">OP Records</span></div>
        <div class="page-head"><div><h1>Order of Payment Records</h1><p class="subtitle">View all encoded OP records.</p></div></div>
        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input type="text" id="opRecordsSearch" placeholder="Search by OR or permit numberâ€¦"></div>
            <button class="btn btn-secondary btn-sm" id="opRecordsRefresh">Refresh</button>
            <button class="btn btn-primary btn-sm" id="opRecordsExport">Export</button>
          </div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>Transaction No.</th><th>Applicant</th><th>Permit Type</th><th>Amount</th><th>Status</th><th>OR No.</th><th>Date</th><th>Action</th></tr></thead><tbody id="opRecordsTbody"></tbody></table>
          </div>
          <div class="table-footer"><div class="pagination" id="opRecordsPagination"></div></div>
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
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

