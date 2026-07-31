<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('releasing-records');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Releasing Records · PAMS User</title>
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
<body data-page="releasing-records">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('releasing-records'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Releasing Records'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Releasing Records</span></div>
        <div class="page-head"><div><h1>Releasing Records</h1><p class="subtitle">Complete list of all releasing transactions.</p></div></div>
        <div class="stat-grid mini-stats">
          <div class="stat-card"><div class="figure" id="relRecToday">0</div><div class="label">Today</div></div>
          <div class="stat-card"><div class="figure" id="relRecWeek">0</div><div class="label">This Week</div></div>
          <div class="stat-card"><div class="figure" id="relRecMonth">0</div><div class="label">This Month</div></div>
          <div class="stat-card"><div class="figure" id="relRecYear">0</div><div class="label">This Year</div></div>
        </div>
        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input type="text" id="relRecordsSearch" placeholder="Search records…"></div>
            <div class="spacer"></div>
            <button class="btn btn-secondary btn-sm" id="relRecordsRefresh"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><polyline points="21 3 21 9 15 9"/></svg> Refresh</button>
            <button class="btn btn-secondary btn-sm" id="relRecordsPrint"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print</button>
            <button class="btn btn-primary btn-sm" id="relRecordsExport"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
          </div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>Release Date</th><th>Permit App No.</th><th>Applicant</th><th>Claimed By</th><th>Time Released</th><th>Action</th></tr></thead><tbody id="relRecordsTbody"></tbody></table>
          </div>
          <div class="table-footer"><div class="pagination" id="relRecordsPagination"></div></div>
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
