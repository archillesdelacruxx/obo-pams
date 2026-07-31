<?php
require_once __DIR__ . '/auth.php';

function getUserNavItems(): array {
    return [
        ['key' => 'dashboard',  'label' => 'Dashboard',  'page' => 'dashboard.php',  'icon' => 'grid',       'section' => 'Main'],
        ['key' => 'order-of-payment', 'label' => 'Order of Payment', 'page' => 'order-of-payment.php', 'icon' => 'file-text', 'section' => 'Modules'],
        ['key' => 'op-records', 'label' => 'OP Records', 'page' => 'op-records.php', 'icon' => 'layers', 'section' => 'Modules'],
        ['key' => 'permit-workflow', 'label' => 'Permit Workflow', 'page' => 'permit-workflow.php', 'icon' => 'git-branch', 'section' => 'Modules'],
        ['key' => 'workflow-details', 'label' => 'Workflow Details', 'page' => 'workflow-details.php', 'icon' => 'git-branch', 'section' => 'Modules'],
        ['key' => 'permit-approval-encoding', 'label' => 'Permit Approval Encoding', 'page' => 'permit-approval-encoding.php', 'icon' => 'award', 'section' => 'Modules'],
        ['key' => 'permit-approval-records', 'label' => 'Permit Approval Records', 'page' => 'permit-approval-records.php', 'icon' => 'layers', 'section' => 'Modules'],
        ['key' => 'releasing', 'label' => 'Releasing Plans', 'page' => 'releasing.php', 'icon' => 'package', 'section' => 'Modules'],
        ['key' => 'releasing-records', 'label' => 'Releasing Records', 'page' => 'releasing-records.php', 'icon' => 'layers', 'section' => 'Modules'],
        ['key' => 'notifications', 'label' => 'Notifications', 'page' => 'notifications.php', 'icon' => 'bell', 'section' => 'Account'],
        ['key' => 'announcements', 'label' => 'Announcements', 'page' => 'announcements.php', 'icon' => 'megaphone', 'section' => 'Account'],
        ['key' => 'profile', 'label' => 'Profile', 'page' => 'profile.php', 'icon' => 'user', 'section' => 'Account'],
        ['key' => 'settings', 'label' => 'Settings', 'page' => 'settings.php', 'icon' => 'settings', 'section' => 'Account'],
    ];
}

function renderUserSidebar(string $activeKey): string {
    $items = getUserNavItems();
    $isAdmin = !empty($_SESSION['is_admin']);
    $perms = [];
    if (!$isAdmin) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT module_key FROM user_permissions WHERE user_id = ? AND is_granted = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $perms = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        } catch (Exception $e) {
            error_log('Permission load failed: ' . $e->getMessage());
        }
    }

    $alwaysVisible = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
    $sections = [];
    foreach ($items as $item) {
        if (!$isAdmin && !in_array($item['key'], $alwaysVisible, true) && empty($perms[$item['key']])) continue;
        $sections[$item['section']][] = $item;
    }

    $navHtml = '';
    foreach ($sections as $section => $sectionItems) {
        $navHtml .= '<div class="sidebar-section-label">' . escape($section) . '</div>';
        foreach ($sectionItems as $item) {
            $active = $item['key'] === $activeKey ? ' active' : '';
            $icon = getNavIcon($item['icon']);
            $navHtml .= '<a class="nav-item' . $active . '" data-user-nav="' . escape($item['page']) . '" href="' . escape($item['page']) . '" tabindex="0" aria-label="' . escape($item['label']) . '">'
                . $icon
                . '<span class="label">' . escape($item['label']) . '</span>'
                . '</a>';
        }
    }

    $basePath = '../../';
    $name = escape($_SESSION['full_name'] ?? 'User');
    $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $name)));
    $initials = substr($initials, 0, 2);
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : escape($initials);

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
                    <span>' . ($isAdmin ? 'Administrator' : 'User') . '</span>
                </div>
            </div>
        </div>
    </aside>';
}

function renderUserHeader(string $pageTitle): string {
    $basePath = '../../';
    $name = escape($_SESSION['full_name'] ?? 'User');
    $first = explode(' ', $name)[0];
    $last = substr(strrchr($name, ' '), 1) ?: $first;
    $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $name)));
    $initials = substr($initials, 0, 2);
    $profilePic = $_SESSION['profile_pic'] ?? '';
    $avatarHtml = $profilePic
        ? '<img src="' . $basePath . escape($profilePic) . '" alt="" class="avatar-img">'
        : escape($initials);

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
                </button>
                <div class="dropdown-panel" id="notifPanel"></div>
            </div>
            <div class="dropdown-wrap">
                <div class="profile-trigger" id="profileTrigger">
                    <div class="avatar sm">' . $avatarHtml . '</div>
                    <div class="info">
                        <strong>' . escape($first) . ' ' . escape($last) . '</strong>
                        <span>User</span>
                    </div>
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="dropdown-panel profile-menu" id="profilePanel">
                    <div class="profile-menu-item" data-user-nav="profile.php"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> My Profile</div>
                    <div class="profile-menu-item" data-user-nav="settings.php"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</div>
                    <hr class="divider" style="margin:6px 0;">
                    <a class="profile-menu-item danger" id="userLogoutTrigger" href="../../logout.php"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
                </div>
            </div>
        </div>
    </header>';
}
