<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
$pdo = getDB();
$users = $pdo->query('SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
$modules = $pdo->query('SELECT DISTINCT module_name FROM activity_logs WHERE module_name IS NOT NULL AND module_name != "" ORDER BY module_name')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs &middot; PAMS</title>
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
<body data-page="activity-logs">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('dashboard'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Activity Logs'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Activity Logs</span></div>

        <div class="page-head">
          <div>
            <h1>Activity Logs</h1>
            <p class="subtitle">All activities performed by users across the system.</p>
          </div>
        </div>

        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" id="activitySearch" placeholder="Search by user or activity&hellip;">
            </div>
            <select id="activityUserFilter" class="form-control" style="width:auto;min-width:170px;">
              <option value="">All users</option>
              <?php foreach ($users as $u): ?>
              <option value="<?php echo (int)$u['id']; ?>"><?php echo escape($u['full_name']); ?></option>
              <?php endforeach; ?>
            </select>
            <select id="activityModuleFilter" class="form-control" style="width:auto;min-width:150px;">
              <option value="">All modules</option>
              <?php foreach ($modules as $m): ?>
              <option value="<?php echo escape($m); ?>"><?php echo escape($m); ?></option>
              <?php endforeach; ?>
            </select>
            <span class="text-xs text-muted" id="activityRecordCount"></span>
            <button class="btn btn-secondary btn-sm" id="activityRefresh">Refresh</button>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>User</th><th>Activity</th><th>Module</th><th>Date</th><th>IP Address</th></tr>
              </thead>
              <tbody id="activityTbody"></tbody>
            </table>
          </div>
          <div class="table-footer">
            <span class="text-xs text-muted" id="activityPageInfo"></span>
            <div class="pagination">
              <button id="activityPrev" class="btn btn-secondary btn-sm">&larr; Prev</button>
              <button id="activityNext" class="btn btn-secondary btn-sm">Next &rarr;</button>
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
