<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('dashboard');
$greeting = date('H') < 12 ? 'Good morning' : (date('H') < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $_SESSION['full_name'])[0];
$perms = getUserModulePermissions();
$pdo = getDB();
$moduleStatuses = [];
try {
    $statusStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'module_%'");
    $statusStmt->execute();
    while ($sr = $statusStmt->fetch()) {
        $moduleStatuses[str_replace('module_', '', $sr['setting_key'])] = $sr['setting_value'];
    }
} catch (Exception $e) { /* ignore */ }
$moduleActive = fn(string $key): bool => ($moduleStatuses[$key] ?? 'active') !== 'under_development';
$grantedKeys = array_keys(array_filter($perms));
$activePermKeys = array_values(array_filter($grantedKeys, fn($k) => $moduleActive($k)));

$quickCards = [
    'order-of-payment' => ['page' => 'order-of-payment.php', 'icon' => 'file-text', 'title' => 'Order of Payment', 'desc' => 'Encode new OP records', 'color' => 'blue'],
    'op-records' => ['page' => 'op-records.php', 'icon' => 'layers', 'title' => 'OP Records', 'desc' => 'View OP records', 'color' => 'green'],
    'permit-workflow' => ['page' => 'permit-workflow.php', 'icon' => 'git-branch', 'title' => 'Permit Workflow', 'desc' => 'Track permit processing', 'color' => 'blue'],
    'workflow-details' => ['page' => 'workflow-details.php', 'icon' => 'git-branch', 'title' => 'Workflow Details', 'desc' => 'View workflow records', 'color' => 'gray'],
    'permit-approval-encoding' => ['page' => 'permit-approval-encoding.php', 'icon' => 'award', 'title' => 'Permit Approval', 'desc' => 'Encode approvals', 'color' => 'orange'],
    'permit-approval-records' => ['page' => 'permit-approval-records.php', 'icon' => 'layers', 'title' => 'Approval Records', 'desc' => 'View approval records', 'color' => 'green'],
    'releasing' => ['page' => 'releasing.php', 'icon' => 'package', 'title' => 'Releasing Plans', 'desc' => 'Encode folder releases', 'color' => 'orange'],
    'releasing-records' => ['page' => 'releasing-records.php', 'icon' => 'layers', 'title' => 'Releasing Records', 'desc' => 'View released folders', 'color' => 'gray'],
    'inspection-checklist' => ['page' => 'inspection-checklist.php', 'icon' => 'clipboard', 'title' => 'Ocular Inspection', 'desc' => 'Record on-site checklists', 'color' => 'blue'],
    'inspection-reports' => ['page' => 'inspection-reports.php', 'icon' => 'activity', 'title' => 'Monitoring Reports', 'desc' => 'View inspection reports', 'color' => 'green'],
    'inspection-review' => ['page' => 'inspection-review.php', 'icon' => 'checkmark-done', 'title' => 'Inspection Review', 'desc' => 'Approve/reject submitted records', 'color' => 'orange'],
    'team-leaders' => ['page' => 'team-leaders.php', 'icon' => 'users', 'title' => 'Team Leaders', 'desc' => 'Manage inspection teams', 'color' => 'gray'],
];
$visibleCards = array_filter($quickCards, fn($card, $key) => !empty($perms[$key]) && $moduleActive($key), ARRAY_FILTER_USE_BOTH);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Â· PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260816c">
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
<body data-page="user-dashboard" data-first-name="<?php echo escape(explode(' ', $_SESSION['full_name'])[0]); ?>" data-permissions='<?php echo json_encode($activePermKeys); ?>'>
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
              <?php if (empty($visibleCards)): ?>
              <p style="grid-column:1 / -1;padding:14px;color:var(--gray-400);font-size:13px;">No modules are enabled for your account yet.</p>
              <?php else: ?>
              <?php foreach ($visibleCards as $key => $card): ?>
              <a href="<?php echo $card['page']; ?>" class="quick-card" data-user-nav="<?php echo $card['page']; ?>">
                <div class="qc-icon icon-<?php echo $card['color']; ?>"><?php echo getNavIcon($card['icon']); ?></div>
                <div class="qc-body"><strong><?php echo escape($card['title']); ?></strong><span><?php echo escape($card['desc']); ?></span></div>
                <div class="qc-arrow"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
              </a>
              <?php endforeach; ?>
              <?php endif; ?>
              <a href="announcements.php" class="quick-card" data-user-nav="announcements.php">
                <div class="qc-icon icon-gray"><?php echo getNavIcon('megaphone'); ?></div>
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

