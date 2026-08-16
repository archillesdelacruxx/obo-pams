<?php
require_once __DIR__ . '/auth.php';

function renderAdminSidebar(string $activeKey): string {
    $basePath = '../';
    $role = $_SESSION['role'] ?? 'inspector';

    if (in_array($role, ['admin_aid', 'inspector-admin'], true)) {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'page' => 'dashboard.php', 'icon' => 'grid'],
        ];

        require_once __DIR__ . '/user-shell.php';
        $userNavByKey = [];
        foreach (getUserNavItems() as $it) {
            $userNavByKey[$it['key']] = $it;
        }
        $granted = getUserModulePermissions();

        foreach ($userNavByKey as $key => $it) {
            if ($key === 'dashboard' || in_array($key, ['notifications', 'announcements', 'profile', 'settings'], true)) continue;
            if (empty($granted[$key])) continue;
            $items[] = ['key' => $key, 'label' => $it['label'], 'page' => 'user/' . $it['page'], 'icon' => $it['icon']];
        }

        if ($role === 'inspector-admin') {
            $items[] = ['key' => 'activity-logs', 'label' => 'Activity Logs', 'page' => 'activity-logs.php', 'icon' => 'activity'];
        }

        $items[] = ['key' => 'users', 'label' => 'User Management', 'page' => 'user-management.php', 'icon' => 'users'];
        $items[] = ['key' => 'settings', 'label' => ($role === 'developer' ? 'Settings' : 'Profile Settings'), 'page' => 'settings.php', 'icon' => 'settings'];
    } else {
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'page' => 'dashboard.php', 'icon' => 'grid'],
            ['key' => 'activity-logs', 'label' => 'Activity Logs', 'page' => 'activity-logs.php', 'icon' => 'activity'],
            ['key' => 'reports', 'label' => 'Reports', 'page' => 'reports.php', 'icon' => 'bar-chart'],
            ['key' => 'users', 'label' => 'User Management', 'page' => 'user-management.php', 'icon' => 'users'],
            ['key' => 'notifications', 'label' => 'Announcements', 'page' => 'notifications.php', 'icon' => 'megaphone'],
            ['key' => 'settings', 'label' => ($role === 'developer' ? 'Settings' : 'Profile Settings'), 'page' => 'settings.php', 'icon' => 'settings'],
        ];
    }

    if ($role === 'developer') {
        $items[] = ['key' => 'profile', 'label' => 'Profile', 'page' => 'profile.php', 'icon' => 'user'];
    }

    $navHtml = '';
    foreach ($items as $item) {
        $active = $item['key'] === $activeKey ? ' active' : '';
        $icon = getNavIcon($item['icon']);
        $navHtml .= '<a class="nav-item' . $active . '" data-nav="' . $item['page'] . '" href="' . $item['page'] . '" tabindex="0">'
            . $icon
            . '<span class="label">' . $item['label'] . '</span>'
            . '</a>';
    }

    $name = escape($_SESSION['full_name'] ?? 'Administrator');
    $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $name)));
    $initials = substr($initials, 0, 2);
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : $initials;

    return '
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="mark">P</div>
            <div class="name">PAMS<small>Permit Application Mgmt.</small></div>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Administration</div>
            ' . $navHtml . '
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user" id="sidebarUserBtn">
                <div class="avatar sm">' . $avatarHtml . '</div>
                <div class="info">
                    <strong>' . $name . '</strong>
                    <span>' . escape(roleDisplayName($role)) . '</span>
                </div>
            </div>
        </div>
    </aside>';
}

function renderAdminHeader(string $pageTitle): string {
    $basePath = '../';
    $name = escape($_SESSION['full_name'] ?? 'Administrator');
    $first = explode(' ', $name)[0];
    $last = substr(strrchr($name, ' '), 1) ?: $first;
    $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $name)));
    $initials = substr($initials, 0, 2);
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : $initials;

    return '
    <header class="header">
        <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div class="header-search">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" placeholder="Search permits, users, transactions…" id="globalSearch">
            <kbd>/</kbd>
        </div>
        <div class="header-right">
            <div class="dropdown-wrap">
                <div class="profile-trigger" id="profileTrigger">
                    <div class="avatar sm">' . $avatarHtml . '</div>
                    <div class="info">
                        <strong>' . $first . ' ' . $last . '</strong>
                        <span>' . escape(roleDisplayName($_SESSION['role'] ?? 'inspector')) . '</span>
                    </div>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="dropdown-panel profile-menu" id="profilePanel">
                    ' . ($_SESSION['role'] === 'developer' ? '<div class="profile-menu-item" data-nav="profile.php"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> My Profile</div>' : '') . '
                    <div class="profile-menu-item" data-nav="settings.php"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> ' . ($_SESSION['role'] === 'developer' ? 'Settings' : 'Profile Settings') . '</div>
                    <hr class="divider" style="margin:6px 0;">
                    <div class="profile-menu-item danger" id="logoutTrigger"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg> Logout</div>
                </div>
            </div>
        </div>
    </header>';
}
