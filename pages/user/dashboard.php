<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('dashboard');
$greeting = date('H') < 12 ? 'Good morning' : (date('H') < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $_SESSION['full_name'])[0];
$perms = getUserModulePermissions();
$hasOP = !empty($perms['order-of-payment']);
$hasWorkflow = !empty($perms['permit-workflow']);
$hasReleasing = !empty($perms['releasing']);
$hasApproval = !empty($perms['permit-approval-encoding']) || !empty($perms['permit-approval-records']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Â· PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260812c">
</head>
<body data-page="user-dashboard" data-first-name="<?php echo escape(explode(' ', $_SESSION['full_name'])[0]); ?>" data-permissions='<?php echo json_encode(array_keys(array_filter($perms))); ?>'>
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('dashboard'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Dashboard'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb">
          <span>PAMS</span><span class="sep">/</span><span class="current">Dashboard</span>
        </div>
        <div class="page-head">
          <div>
            <h1 id="userGreeting"><?php echo $greeting; ?>, <?php echo escape($firstName); ?> ðŸ‘‹</h1>
            <p class="subtitle">Here's your workload summary for today, <strong><?php echo date('F j, Y'); ?></strong>.</p>
          </div>
          <div class="flex gap-sm">
            <button class="btn btn-secondary">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <?php echo date('M j, Y'); ?>
            </button>
          </div>
        </div>
        <div class="user-stat-grid" id="userStatGrid"></div>
        <div class="two-col">
          <div>
            <h3 style="font-size:14px;font-weight:700;color:var(--gray-700);margin-bottom:12px;">Quick Access</h3>
            <div class="quick-access-grid">
              <?php if ($hasOP): ?>
              <a href="order-of-payment.php" class="quick-card" data-user-nav="order-of-payment.php">
                <div class="qc-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                <div class="qc-body"><strong>Order of Payment</strong><span>Encode new OP records</span></div>
                <div class="qc-arrow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
              </a>
              <?php endif; ?>
              <?php if ($hasWorkflow): ?>
              <a href="permit-workflow.php" class="quick-card" data-user-nav="permit-workflow.php">
                <div class="qc-icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg></div>
                <div class="qc-body"><strong>Permit Workflow</strong><span>Track permit processing</span></div>
                <div class="qc-arrow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
              </a>
              <?php endif; ?>
              <?php if ($hasReleasing): ?>
              <a href="releasing.php" class="quick-card" data-user-nav="releasing.php">
                <div class="qc-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
                <div class="qc-body"><strong>Releasing Plans</strong><span>Encode folder releases</span></div>
                <div class="qc-arrow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
              </a>
              <?php endif; ?>
              <a href="announcements.php" class="quick-card" data-user-nav="announcements.php">
                <div class="qc-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg></div>
                <div class="qc-body"><strong>Announcements</strong><span>View admin announcements</span></div>
                <div class="qc-arrow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
              </a>
            </div>
          </div>
          <div>
            <div class="section-card">
              <div class="section-head"><h3>Admin Announcements</h3><a href="announcements.php" data-user-nav="announcements.php" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">View all</a></div>
              <div class="section-body" id="userAnnouncementsFeed" style="padding-top:4px;padding-bottom:4px;"></div>
            </div>
          </div>
        </div>
        <div class="two-col">
          <div>
            <div class="section-card" id="userAnalyticsCard">
              <div class="section-head">
                <h3>Usage Analytics</h3>
                <div class="seg-toggle" id="analyticsPeriodToggle">
                  <button class="seg-btn active" data-period="week">This Week</button>
                  <button class="seg-btn" data-period="month">This Month</button>
                </div>
              </div>
              <div class="section-body analytics-body" id="userAnalyticsBody">
                <div class="analytics-loading" style="text-align:center;padding:30px 0;color:var(--gray-400);font-size:13px;">Loading analytics…</div>
              </div>
            </div>
          </div>
          <div>
            <div class="section-card">
              <div class="section-head"><h3>Recent Activities</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">View all</button></div>
              <div class="section-body" id="userRecentActivities" style="padding-top:4px;padding-bottom:4px;"></div>
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
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

