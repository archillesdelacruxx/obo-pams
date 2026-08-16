<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
$pdo = getDB();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

function monthlyTrend(PDO $pdo, string $table, string $dateCol): array {
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("first day of -$i months"));
        $months[$key] = ['label' => date('M', strtotime($key . '-01')), 'value' => 0];
    }
    $start = array_keys($months)[0];
    $rows = $pdo->query("SELECT DATE_FORMAT($dateCol, '%Y-%m') ym, COUNT(*) c FROM $table WHERE $dateCol >= '$start-01' GROUP BY ym")->fetchAll();
    foreach ($rows as $r) {
        if (isset($months[$r['ym']])) $months[$r['ym']]['value'] = (int)$r['c'];
    }
    return array_values($months);
}

$viewRole = $_SESSION['role'] ?? 'admin';
$userScopeWhere = $viewRole === 'admin' ? " WHERE role = 'admin_aid'" : '';
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users" . $userScopeWhere)->fetchColumn();
$opToday = (int)$pdo->query("SELECT COUNT(*) FROM order_of_payments WHERE payment_date = '$today'")->fetchColumn();
$activeWorkflows = (int)$pdo->query("SELECT COUNT(*) FROM permit_workflows WHERE status NOT IN ('Approved','Disapproved','Released')")->fetchColumn();
$approvalsMonth = (int)$pdo->query("SELECT COUNT(*) FROM permit_approvals WHERE approval_date >= '$monthStart'")->fetchColumn();
$releasingToday = (int)$pdo->query("SELECT COUNT(*) FROM releasing_plans WHERE date_released = '$today'")->fetchColumn();
$notifCount = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();

$opTrend = monthlyTrend($pdo, 'order_of_payments', 'payment_date');
$workflowTrend = monthlyTrend($pdo, 'permit_workflows', 'created_at');
$approvalTrend = monthlyTrend($pdo, 'permit_approvals', 'approval_date');
$releaseTrend = monthlyTrend($pdo, 'releasing_plans', 'date_released');
$monthlyLabels = array_column($opTrend, 'label');

$workflowStatus = ['Pending' => 0, 'Under Review' => 0, 'Approved' => 0, 'Disapproved' => 0, 'Released' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM permit_workflows GROUP BY status') as $r) {
    $workflowStatus[$r['status']] = (int)$r['c'];
}
$wfColors = ['Pending' => '#F0A93B', 'Under Review' => '#5B8DEF', 'Approved' => '#22A55A', 'Disapproved' => '#E5484D', 'Released' => '#8A94A6'];
$wfItems = [];
foreach ($workflowStatus as $k => $v) $wfItems[] = ['label' => $k, 'value' => $v, 'color' => $wfColors[$k]];

if ($viewRole === 'admin') {
    $userCounts = ['admin' => 0, 'obo' => $totalUsers];
} else {
    $userCounts = ['admin' => 0, 'obo' => 0];
    foreach ($pdo->query('SELECT is_admin, COUNT(*) c FROM users GROUP BY is_admin') as $r) {
        if ((int)$r['is_admin'] === 1) $userCounts['admin'] = (int)$r['c'];
        else $userCounts['obo'] = (int)$r['c'];
    }
}

$notifRows = $pdo->query('SELECT is_read, COUNT(*) c FROM notifications GROUP BY is_read')->fetchAll(PDO::FETCH_KEY_PAIR);
$unreadNotifs = (int)($notifRows[0] ?? 0);
$readNotifs = (int)($notifRows[1] ?? 0);

$yearRows = $pdo->query("SELECT MONTH(approval_date) m, COUNT(*) c FROM permit_approvals WHERE approval_date >= '" . date('Y-01-01') . "' GROUP BY MONTH(approval_date)")->fetchAll();
$yearMap = array_column($yearRows, 'c', 'm');
$yearly = [];
for ($i = 1; $i <= 12; $i++) $yearly[] = (int)($yearMap[$i] ?? 0);

$analytics = [
    'cards' => [
        ['key' => 'op', 'type' => 'bars', 'title' => 'Order of Payment volume', 'subtitle' => 'Last 6 months', 'months' => array_column($opTrend, 'label'), 'values' => array_column($opTrend, 'value'), 'total' => array_sum(array_column($opTrend, 'value'))],
        ['key' => 'workflow', 'type' => 'list', 'title' => 'Workflow records by status', 'subtitle' => 'Current records', 'items' => $wfItems, 'total' => array_sum($workflowStatus)],
        ['key' => 'approvals', 'type' => 'bars', 'title' => 'Permits approved', 'subtitle' => 'Last 6 months', 'months' => array_column($approvalTrend, 'label'), 'values' => array_column($approvalTrend, 'value'), 'total' => array_sum(array_column($approvalTrend, 'value'))],
        ['key' => 'releasing', 'type' => 'bars', 'title' => 'Releasing records', 'subtitle' => 'Last 6 months', 'months' => array_column($releaseTrend, 'label'), 'values' => array_column($releaseTrend, 'value'), 'total' => array_sum(array_column($releaseTrend, 'value'))],
        ['key' => 'users', 'type' => 'list', 'title' => 'Registered users', 'subtitle' => $viewRole === 'admin' ? 'Admin Aids' : 'By role', 'items' => $viewRole === 'admin' ? [['label' => 'Admin Aid', 'value' => $totalUsers, 'color' => '#22A55A']] : [['label' => 'Administrator', 'value' => $userCounts['admin'], 'color' => '#1D5FD6'], ['label' => 'OBO User', 'value' => $userCounts['obo'], 'color' => '#22A55A']], 'total' => $viewRole === 'admin' ? $totalUsers : ($userCounts['admin'] + $userCounts['obo'])],
        ['key' => 'notifications', 'type' => 'list', 'title' => 'Notifications', 'subtitle' => 'Read status', 'items' => [['label' => 'Unread', 'value' => $unreadNotifs, 'color' => '#E5484D'], ['label' => 'Read', 'value' => $readNotifs, 'color' => '#8A94A6']], 'total' => $unreadNotifs + $readNotifs],
    ],
    'monthly' => [
        'months' => $monthlyLabels,
        'series' => [
            ['key' => 'op',        'label' => 'Order of Payment', 'color' => '#5B8DEF', 'values' => array_column($opTrend, 'value')],
            ['key' => 'workflow',  'label' => 'Permit Workflow',  'color' => '#F0A93B', 'values' => array_column($workflowTrend, 'value')],
            ['key' => 'approvals', 'label' => 'Permits Approved', 'color' => '#22A55A', 'values' => array_column($approvalTrend, 'value')],
            ['key' => 'releasing', 'label' => 'Releasing',        'color' => '#8A94A6', 'values' => array_column($releaseTrend, 'value')],
        ],
    ],
    'yearly' => $yearly,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Â· PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260816c">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="dashboard" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('dashboard'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Dashboard'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb">
          <span>PAMS</span><span class="sep">/</span><span class="current">Dashboard</span>
        </div>
        <div class="page-head">
          <div>
            <h1>Good morning, <?php echo escape(explode(' ', $_SESSION['full_name'])[0]); ?> ðŸ‘‹</h1>
            <p class="subtitle">Here's the overall status of the Permit Application Management System today.</p>
          </div>
          <div class="flex gap-sm">
            <button class="btn btn-secondary">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <?php echo date('M j, Y'); ?>
            </button>
            <button class="btn btn-primary" id="generateReportBtn">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
              Generate Report
            </button>
          </div>
        </div>

        <div class="stat-grid">
          <div class="stat-card" data-card="op">
            <div class="top">
              <div class="icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($opToday); ?></div>
            <div class="label">Order of Payment Today</div>
            <a class="view-link" href="reports.php">View details <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
          <div class="stat-card" data-card="workflow">
            <div class="top">
              <div class="icon icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($activeWorkflows); ?></div>
            <div class="label">Active Workflows</div>
            <a class="view-link" href="reports.php">View details <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
          <div class="stat-card" data-card="approvals">
            <div class="top">
              <div class="icon icon-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($approvalsMonth); ?></div>
            <div class="label">Permits Approved This Month</div>
            <a class="view-link" href="reports.php">View details <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
          <div class="stat-card" data-card="releasing">
            <div class="top">
              <div class="icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 3v8M8 3v8M3 12h18"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($releasingToday); ?></div>
            <div class="label">Releasing Records Today</div>
            <a class="view-link" href="reports.php">View details <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
          <div class="stat-card" data-card="users">
            <div class="top">
              <div class="icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($totalUsers); ?></div>
            <div class="label">Total Registered Users</div>
            <a class="view-link" href="user-management.php">Manage users <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
          <div class="stat-card" data-card="notifications">
            <div class="top">
              <div class="icon icon-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
            </div>
            <div class="figure"><?php echo number_format($notifCount); ?></div>
            <div class="label">Unread Notifications</div>
            <a class="view-link" id="notifShortcut">View all <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          </div>
        </div>

        <div class="two-col">
          <div>
            <div class="chart-card">
              <div class="chart-head">
                <div>
                  <h3 style="font-size:15px;">6-Month Analytics</h3>
                  <p class="text-xs text-muted">All modules &middot; <?php echo $monthlyLabels[0] ?: ''; ?> â€“ <?php echo end($monthlyLabels) ?: ''; ?> <?php echo date('Y'); ?></p>
                </div>
                <div class="seg-tabs">
                  <button class="active">Monthly</button>
                  <button>Weekly</button>
                </div>
              </div>
              <div class="bar-chart" id="monthlyChart"></div>
              <div class="chart-legend" id="monthlyChartLegend" style="margin-top:14px;"></div>
            </div>
            <div class="chart-card" style="margin-top:22px;">
              <div class="chart-head">
                <div>
                  <h3 style="font-size:15px;">Yearly Analytics</h3>
                  <p class="text-xs text-muted">Total permit throughput, Jan â€“ Dec 2026</p>
                </div>
                <div class="chart-legend"><span><i style="background:var(--color-primary);"></i> Permits processed</span></div>
              </div>
              <svg class="line-chart-svg" id="yearlyChart"></svg>
            </div>
          </div>
          <div>
            <div class="section-card">
              <div class="section-head"><h3>Recent Activities</h3><a class="btn btn-secondary" href="activity-logs.php" data-nav="activity-logs.php" style="padding:6px 12px;font-size:12px;">View all</a></div>
              <div class="section-body" id="recentActivities" style="padding-top:4px;padding-bottom:4px;"></div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
  window.DASHBOARD_DATA = <?php echo json_encode($analytics); ?>;
  </script>
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

