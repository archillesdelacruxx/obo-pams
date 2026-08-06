<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports Â· PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/tables.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="reports" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('reports'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Reports'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Reports</span></div>
        <div class="page-head">
          <div>
            <h1>Management Reports</h1>
            <p class="subtitle">Office-wide productivity summary across all encoding modules.</p>
          </div>
        </div>

        <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);">
          <div class="stat-card">
            <div class="top"><div class="icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></div></div>
            <div class="figure" id="totalOP">0</div>
            <div class="label">Order of Payment</div>
          </div>
          <div class="stat-card">
            <div class="top"><div class="icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div></div>
            <div class="figure" id="totalWorkflow">0</div>
            <div class="label">Permit Workflow</div>
          </div>
          <div class="stat-card">
            <div class="top"><div class="icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></div></div>
            <div class="figure" id="totalApproved">0</div>
            <div class="label">Permit Approved</div>
          </div>
          <div class="stat-card">
            <div class="top"><div class="icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 3v8M8 3v8M3 12h18"/></svg></div></div>
            <div class="figure" id="totalReleasing">0</div>
            <div class="label">Releasing</div>
          </div>
          <div class="stat-card" style="border-color:var(--color-primary-100); background:var(--color-primary-50);">
            <div class="top"><div class="icon icon-blue" style="background:#fff;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M12 20V4M20 20v-7"/></svg></div></div>
            <div class="figure" id="totalOverall">0</div>
            <div class="label">Overall Total Transactions</div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head"><h3>Staff Productivity</h3></div>
          <div class="table-toolbar">
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" id="reportsSearch" placeholder="Search by name or usernameâ€¦">
            </div>
            <select>
              <option>All Modules</option>
              <option>Order of Payment</option>
              <option>Permit Workflow</option>
              <option>Permit Approved</option>
              <option>Releasing</option>
            </select>
            <input type="date" id="reportsDateFrom" value="<?php echo date('Y-m-01'); ?>">
            <input type="date" id="reportsDateTo" value="<?php echo date('Y-m-d'); ?>">
            <div class="spacer"></div>
            <button class="btn btn-secondary btn-sm" id="printBtn">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
              Print
            </button>
            <button class="btn btn-primary btn-sm" id="exportBtn">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
              Export
            </button>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>User</th><th>Order of Payment</th><th>Permit Workflow</th><th>Permit Approved</th><th>Releasing</th><th>Total Transactions</th></tr>
              </thead>
              <tbody id="reportsTableBody"></tbody>
            </table>
          </div>
          <div class="table-footer">
            <span class="text-xs text-muted">Showing productivity for the selected date range</span>
            <div class="pagination">
              <button class="active">1</button>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/components.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script src="../assets/js/dropdown.js"></script>
  <script src="../assets/js/notification.js"></script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/search.js"></script>
  <script src="../assets/js/table.js"></script>
  <script src="../assets/js/validation.js"></script>
  <script src="../assets/js/realtime.js?v=20260803b"></script>
  <script src="../assets/js/app.js?v=20260803d"></script>
</body>
</html>

