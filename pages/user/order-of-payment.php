<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('order-of-payment');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order of Payment Â· PAMS User</title>
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
<body data-page="order-of-payment">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('order-of-payment'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Order of Payment'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Order of Payment</span></div>
        <div class="page-head"><div><h1>Order of Payment Encoding</h1><p class="subtitle">Encode and track Order of Payment (OP) records.</p></div></div>
        <div class="section-card form-card">
          <div class="section-head"><h3>New Record</h3></div>
          <div class="section-body">
            <form id="opForm">
              <div class="form-grid">
                <div class="form-group"><label>Transaction No. <span class="req">*</span></label><input class="form-control form-mono" type="text" id="opTransactionNo" placeholder="e.g. OR-2026-001"></div>
                <div class="form-group"><label>Applicant Name <span class="req">*</span></label><input class="form-control" type="text" id="opApplicantName" placeholder="Full name of applicant"></div>
                <div class="form-group"><label>Permit Type <span class="req">*</span></label><select class="form-control" id="opPermitType"><option value="">â€” Select â€”</option><option>New Business Permit</option><option>Renewal â€” Food Services</option><option>Occupancy Permit</option><option>Zoning Clearance</option><option>Signage Permit</option><option>Ancillary/Accessory</option></select></div>
                <div class="form-group"><label>Amount <span class="req">*</span></label><input class="form-control form-mono" type="number" step="0.01" min="0" id="opAmount" placeholder="0.00"></div>
                <div class="form-group"><label>Payment Date</label><input class="form-control" type="date" id="opPaymentDate"></div>
                <div class="form-group"><label>OR No.</label><input class="form-control form-mono" type="text" id="opOfficialReceiptNo" placeholder="Optional"></div>
                <div class="form-group"><label>Time In</label><input class="form-control" type="time" id="opTimeIn" value="08:00"></div>
                <div class="form-group"><label>Time Out</label><input class="form-control" type="time" id="opTimeOut"></div>
                <div class="form-group full">
                  <label>Elapsed Time</label>
                  <div class="timer-display"><span class="timer-value" id="timerValue">--:--</span><span class="timer-status" id="timerStatus">Awaiting time in / time out</span></div>
                </div>
              </div>
              <div class="flex gap-sm" style="margin-top:8px;">
                <button type="button" class="btn btn-primary" id="opSaveBtn"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Save Record</button>
                <button type="button" class="btn btn-secondary" id="opClearBtn">Clear</button>
              </div>
            </form>
          </div>
        </div>
        <div class="section-card table-card">
          <div class="section-head"><h3>Recent Encoding</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" id="opRefreshBtn">Refresh</button></div>
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th>Transaction No.</th><th>Applicant</th><th>Permit Type</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody id="opRecentTbody"></tbody></table>
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

