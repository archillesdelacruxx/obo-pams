<?php
require_once __DIR__ . '/auth.php';

function getUserNavItems(): array {
    return [
        ['key' => 'dashboard',                'label' => 'Dashboard',                 'page' => 'dashboard.php',                'icon' => 'grid',           'section' => 'Main'],
        ['key' => 'order-of-payment',         'label' => 'Order of Payment',          'page' => 'order-of-payment.php',         'icon' => 'file-text',      'section' => 'Modules'],
        ['key' => 'op-records',               'label' => 'OP Records',                'page' => 'op-records.php',               'icon' => 'layers',         'section' => 'Modules'],
        ['key' => 'permit-workflow',          'label' => 'Permit Workflow',           'page' => 'permit-workflow.php',          'icon' => 'git-branch',     'section' => 'Modules'],
        ['key' => 'workflow-details',         'label' => 'Workflow Details',          'page' => 'workflow-details.php',         'icon' => 'git-branch',     'section' => 'Modules'],
        ['key' => 'permit-approval-encoding', 'label' => 'Permit Approval Encoding',  'page' => 'permit-approval-encoding.php', 'icon' => 'award',          'section' => 'Modules'],
        ['key' => 'permit-approval-records',  'label' => 'Permit Approval Records',   'page' => 'permit-approval-records.php',  'icon' => 'layers',         'section' => 'Modules'],
        ['key' => 'releasing',                'label' => 'Releasing Plans',           'page' => 'releasing.php',                'icon' => 'package',        'section' => 'Modules'],
        ['key' => 'releasing-records',        'label' => 'Releasing Records',         'page' => 'releasing-records.php',        'icon' => 'layers',         'section' => 'Modules'],
        ['key' => 'inspection-checklist',     'label' => 'Ocular Inspection Checklist','page' => 'inspection-checklist.php',   'icon' => 'clipboard',      'section' => 'Inspection Management'],
        ['key' => 'inspection-reports',       'label' => 'Monitoring Reports',        'page' => 'inspection-reports.php',       'icon' => 'activity',       'section' => 'Inspection Management'],
        ['key' => 'inspection-review',        'label' => 'Inspection Review',         'page' => 'inspection-review.php',        'icon' => 'checkmark-done', 'section' => 'Inspection Management'],
        ['key' => 'team-leaders',             'label' => 'Team Leaders',              'page' => 'team-leaders.php',             'icon' => 'users',          'section' => 'Inspection Management'],
        ['key' => 'activity-logs',            'label' => 'Activity Logs',             'page' => 'activity-logs.php',            'icon' => 'activity',       'section' => 'Administration'],
        ['key' => 'reports',                  'label' => 'Reports',                   'page' => 'reports.php',                  'icon' => 'bar-chart',      'section' => 'Administration'],
        ['key' => 'user-management',          'label' => 'User Management',           'page' => 'user-management.php',          'icon' => 'users',          'section' => 'Administration'],
        ['key' => 'module-access',            'label' => 'Module Access',             'page' => 'module-access.php',            'icon' => 'layers',         'section' => 'Administration'],
        ['key' => 'announcements',            'label' => 'Announcements',             'page' => 'announcements.php',            'icon' => 'megaphone',      'section' => 'Account'],
        ['key' => 'settings',                 'label' => 'Profile Settings',         'page' => 'settings.php',                 'icon' => 'settings',       'section' => 'Account'],
    ];
}

function renderPamsSidebar(string $activeKey, string $dir = ''): string {
    if ($activeKey === 'users') $activeKey = 'user-management';
    if ($activeKey === 'notifications') $activeKey = 'announcements';
    if ($activeKey === 'profile') $activeKey = 'settings';

    $role = $_SESSION['role'] ?? 'inspector';
    $isDev = $role === 'developer';
    $isAdmin = ($role === 'developer' || $role === 'admin') || (!empty($_SESSION['is_admin']) && $role !== 'admin_aid');

    $perms = getUserModulePermissions();
    $alwaysVisible = ['dashboard', 'announcements', 'settings'];

    $moduleStatuses = [];
    try {
        $pdo = getDB();
        $statusStmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'module_%'");
        $statusStmt->execute();
        while ($sr = $statusStmt->fetch()) {
            $moduleStatuses[str_replace('module_', '', $sr['setting_key'])] = $sr['setting_value'];
        }
    } catch (Exception $e) { /* ignore */ }

    $navAllowed = function (string $key) use ($role, $isDev, $isAdmin, $alwaysVisible, $perms): bool {
        if ($isAdmin) return true;
        if (in_array($key, $alwaysVisible, true)) return true;

        if ($key === 'activity-logs') return canViewActivityLogs();
        if ($key === 'reports') return isAdmin() || isDeveloper();
        if ($key === 'user-management') return canManageUsers() || $role === 'inspector-admin';
        if ($key === 'module-access') return isDeveloper();
        if ($key === 'team-leaders') return canManageTeamLeaders() || !empty($perms['team-leaders']);

        return !empty($perms[$key]);
    };

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    $inUserDir = str_contains($scriptName, '/pages/user/');

    $items = getUserNavItems();
    $sections = [];
    foreach ($items as $item) {
        $sections[$item['section']][] = $item;
    }

    $isRootDashboard = ($role === 'developer' || $role === 'admin') || (!empty($_SESSION['is_admin']) && $role !== 'admin_aid');

    $rootPages = [
        'dashboard.php' => $isRootDashboard,
        'activity-logs.php' => true,
        'reports.php' => true,
        'user-management.php' => true,
        'module-access.php' => true,
        'settings.php' => true,
        'profile.php' => true,
        'notifications.php' => true
    ];

    $navHtml = '';
    foreach ($sections as $section => $sectionItems) {
        $grantedItems = array_filter($sectionItems, fn($it) => $navAllowed($it['key']));
        $sectionVisible = $isAdmin || count($grantedItems) > 0;

        $navHtml .= '<div class="sidebar-section-label" data-nav-section="' . escape($section) . '"' . ($sectionVisible ? '' : ' hidden') . '>' . escape($section) . '</div>';

        foreach ($sectionItems as $item) {
            $granted = $navAllowed($item['key']);
            $active = ($item['key'] === $activeKey && $granted) ? ' active' : '';
            $isUnderDev = ($moduleStatuses[$item['key']] ?? 'active') === 'under_development';
            $icon = getNavIcon($item['icon']);

            $isRootFile = !empty($rootPages[$item['page']]);
            if ($inUserDir) {
                $linkPage = $isRootFile ? ('../' . $item['page']) : $item['page'];
            } else {
                $linkPage = $isRootFile ? $item['page'] : ('user/' . $item['page']);
            }

            $navHtml .= '<a class="nav-item' . $active . '" data-user-nav="' . escape($linkPage) . '" data-module-key="' . escape($item['key']) . '"' . ($isUnderDev ? ' data-under-dev="1"' : '') . ' href="' . escape($linkPage) . '" tabindex="0" aria-label="' . escape($item['label']) . '"' . ($granted ? '' : ' hidden') . '>'
                . $icon
                . '<span class="label">' . escape($item['label']) . '</span>'
                . ($isUnderDev ? '<span class="badge" style="font-size:9px;background:var(--warning,#eab308);color:#000;margin-left:auto;padding:1px 5px;border-radius:4px;">Dev</span>' : '')
                . '</a>';
        }
    }

    $basePath = $inUserDir ? '../../' : '../';
    $name = escape($_SESSION['full_name'] ?? 'User');
    $initials = implode('', array_map(fn($n) => strtoupper(substr($n, 0, 1)), array_filter(explode(' ', $name))));
    $initials = substr($initials, 0, 2) ?: 'U';
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : escape($initials);

    $posLabel = $_SESSION['position'] ?? '';
    if (!$posLabel) {
        $posLabel = roleDisplayName($role);
    }

    return '
    <aside class="sidebar" id="userSidebar">
        <div class="sidebar-brand">
            <div class="mark">P</div>
            <div class="name">PAMS<small>Permit Application Mgmt.</small></div>
        </div>
        <nav class="sidebar-nav">' . $navHtml . '</nav>
        <div class="sidebar-footer">
            <div class="sidebar-user" id="sidebarUserBtn">
                <div class="avatar sm">' . $avatarHtml . '</div>
                <div class="info">
                    <strong>' . escape($name) . '</strong>
                    <span>' . escape($posLabel) . '</span>
                </div>
            </div>
        </div>
    </aside>';
}

function renderUserSidebar(string $activeKey, string $dir = ''): string {
    return renderPamsSidebar($activeKey, $dir);
}

function renderPamsHeader(string $pageTitle, string $dir = ''): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
    $inUserDir = str_contains($scriptName, '/pages/user/');
    $basePath = $inUserDir ? '../../' : '../';
    $settingsPath = $inUserDir ? '../settings.php' : 'settings.php';
    $logoutPath = $basePath . 'logout.php';

    $name = escape($_SESSION['full_name'] ?? 'User');
    $first = explode(' ', $name)[0];
    $last = substr(strrchr($name, ' '), 1) ?: $first;
    $initials = implode('', array_map(fn($n) => strtoupper(substr($n, 0, 1)), array_filter(explode(' ', $name))));
    $initials = substr($initials, 0, 2) ?: 'U';
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : escape($initials);

    $role = $_SESSION['role'] ?? 'inspector';
    $posLabel = $_SESSION['position'] ?? '';
    if (!$posLabel) {
        $posLabel = roleDisplayName($role);
    }

    return '
    <header class="header">
        <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div class="header-search">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" placeholder="Search permits, records…" id="globalSearch">
            <kbd>/</kbd>
        </div>
        <div class="header-right">
            <div class="dropdown-wrap">
                <button class="icon-btn header-badge-btn" id="notifBtn" aria-label="Notifications">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span class="dot" style="display:none;"></span>
                </button>
                <div class="dropdown-panel" id="notifPanel"></div>
            </div>
            <div class="dropdown-wrap">
                <div class="profile-trigger" id="profileTrigger">
                    <div class="avatar sm">' . $avatarHtml . '</div>
                    <div class="info">
                        <strong>' . escape($first) . ' ' . escape($last) . '</strong>
                        <span>' . escape($posLabel) . '</span>
                    </div>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="dropdown-panel profile-menu" id="profilePanel">
                    <div class="profile-menu-item" data-user-nav="' . escape($settingsPath) . '"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Profile Settings</div>
                    <hr class="divider" style="margin:6px 0;">
                    <a class="profile-menu-item danger" id="userLogoutTrigger" href="' . escape($logoutPath) . '"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
                </div>
            </div>
        </div>
    </header>';
}

function renderUserHeader(string $pageTitle, string $dir = ''): string {
    return renderPamsHeader($pageTitle, $dir);
}
