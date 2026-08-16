<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('permit-approval-encoding');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Permit Approval Encoding Â· PAMS</title>
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
<body data-page="permit-approval-encoding">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('permit-approval-encoding'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Permit Approval Encoding'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Permit Approval Encoding</span></div>
        <div class="page-head"><div><h1>Permit Approval Encoding</h1><p class="subtitle">Select a permit type to encode, or view recently approved records below.</p></div></div>

        <div class="section-card">
          <div class="section-head"><h3>Select Permit Type</h3></div>
          <div class="section-body">
            <div class="permit-cards">
              <a class="permit-card" href="permit-encoding-form.php?type=building">
                <div class="permit-card-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M6 21V8l6-5 6 5v13"/><path d="M10 21v-5h4v5"/></svg></div>
                <div class="permit-card-title">Building Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=occupancy">
                <div class="permit-card-icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18"/><path d="M4 10v11h16V10"/><path d="M2 10l2.5-7h15L22 10"/><path d="M10 21v-6h4v6"/></svg></div>
                <div class="permit-card-title">Occupancy Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=sign">
                <div class="permit-card-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="7" rx="1"/><path d="M8 7V4h8v3M8 14v6M16 14v6"/></svg></div>
                <div class="permit-card-title">Sign Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=mechanical">
                <div class="permit-card-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
                <div class="permit-card-title">Mechanical Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=fencing">
                <div class="permit-card-icon icon-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18M9 3v18M15 3v18M21 3v18"/><path d="M3 9h18M3 15h18"/><path d="M5 21l4-6M11 21l4-6M17 21l4-6M3 9l2 6M9 9l2 6M15 9l2 6M21 9l-2 6"/></svg></div>
                <div class="permit-card-title">Fencing Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=plumbing">
                <div class="permit-card-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
                <div class="permit-card-title">Plumbing / Sanitary</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=coe">
                <div class="permit-card-icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <div class="permit-card-title">COE Certificate of Operation</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=cfei">
                <div class="permit-card-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8M9 3v3l-4 5a2 2 0 0 0 1.6 3.2H8v6h8v-6h1.4a2 2 0 0 0 1.6-3.2l-4-5V3"/></svg></div>
                <div class="permit-card-title">CFEI</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=electrical">
                <div class="permit-card-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
                <div class="permit-card-title">Electrical Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=electronics">
                <div class="permit-card-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="1"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/></svg></div>
                <div class="permit-card-title">Electronics Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=excavation">
                <div class="permit-card-icon icon-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22v-5l5-5 5 5-5 5z"/><path d="M9.5 14.5 16 8"/><path d="m17 2 5 5-.5.5a3.53 3.53 0 0 1-5 0s0 0 0 0a3.53 3.53 0 0 1 0-5L17 2z"/></svg></div>
                <div class="permit-card-title">Excavation Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=demolition">
                <div class="permit-card-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 12-8.5 8.5a2.12 2.12 0 0 1-3-3L12 9"/><path d="M17.64 15.64 21 12.28a2.1 2.1 0 0 0 0-2.97l-6.31-6.31a2.1 2.1 0 0 0-2.97 0L8.36 6.36l7.28 7.28z"/></svg></div>
                <div class="permit-card-title">Demolition Permit</div>
              </a>
              <a class="permit-card" href="permit-encoding-form.php?type=temporary-sidewalk">
                <div class="permit-card-icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10M7 3l-3 18h4M17 3l3 18h-4"/><path d="M9 9h6M10 14h4"/></svg></div>
                <div class="permit-card-title">Temporary Sidewalk Permit</div>
              </a>
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head"><h3>Recent Permit Encoded</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="loadApprovalTable()">Refresh</button></div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>App No.</th><th>Applicant</th><th>Permit Type</th><th>Date</th><th>TAT</th><th>Action</th></tr></thead><tbody id="approvedTbody"></tbody></table>
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
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

