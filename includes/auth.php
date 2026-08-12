<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

function authenticate(string $username, string $password): array {
    $pdo = getDB();

    $lockout = getLoginLockout($username);
    if ($lockout['locked_until']) {
        $mins = max(1, (int)ceil((strtotime($lockout['locked_until']) - time()) / 60));
        logActivity(null, 'login_blocked', "Login attempt blocked for locked username: $username");
        setSessionLockout($username, $lockout['locked_until']);
        return ['success' => false, 'error' => "Account temporarily locked. Too many failed attempts. Try again in $mins minute(s)."];
    }

    $stmt = $pdo->prepare('SELECT id, full_name, username, email, password_hash, profile_photo, is_active, is_admin FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginFailure($username);
        logActivity(null, 'login_failed', "Failed login attempt for username: $username");
        $lockout = getLoginLockout($username);
        if ($lockout['locked_until']) {
            $mins = max(1, (int)ceil((strtotime($lockout['locked_until']) - time()) / 60));
            logActivity(null, 'account_locked', "Account locked after repeated failures: $username");
            setSessionLockout($username, $lockout['locked_until']);
            return ['success' => false, 'error' => "Too many failed attempts. Account locked for $mins minute(s)."];
        }
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    if (!$user['is_active']) {
        logActivity($user['id'], 'login_blocked', 'Account is inactive');
        return ['success' => false, 'error' => 'Your account is inactive. Please contact the Administrator.'];
    }

    clearLoginFailures($username);
    unset($_SESSION['login_lock']);

    $permStmt = $pdo->prepare('SELECT module_key, is_granted FROM user_permissions WHERE user_id = ?');
    $permStmt->execute([$user['id']]);
    $permissions = [];
    while ($row = $permStmt->fetch()) {
        $permissions[$row['module_key']] = (bool)$row['is_granted'];
    }

    regenerateSession();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_pic'] = $user['profile_photo'];
    $_SESSION['is_admin'] = (bool)$user['is_admin'];
    $_SESSION['permissions'] = $permissions;
    $_SESSION['logged_in_at'] = time();

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    logActivity($user['id'], 'login', 'User logged in successfully');

    $redirect = $_SESSION['is_admin'] ? 'pages/dashboard.php' : 'pages/user/dashboard.php';
    return ['success' => true, 'redirect' => $redirect];
}

function getLoginLockout(string $username): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT failed_count, locked_until FROM login_attempts WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row) return ['failed_count' => 0, 'locked_until' => null];

    if ($row['locked_until'] && strtotime($row['locked_until']) <= time()) {
        $pdo->prepare('UPDATE login_attempts SET failed_count = 0, locked_until = NULL WHERE username = ?')->execute([$username]);
        return ['failed_count' => 0, 'locked_until' => null];
    }
    return ['failed_count' => (int)$row['failed_count'], 'locked_until' => $row['locked_until']];
}

function recordLoginFailure(string $username): void {
    $pdo = getDB();
    $count = getLoginLockout($username)['failed_count'] + 1;
    $lockedUntil = $count >= LOGIN_MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60) : null;
    $count = $lockedUntil ? 0 : $count;
    $pdo->prepare('INSERT INTO login_attempts (username, failed_count, locked_until) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE failed_count = ?, locked_until = ?')
        ->execute([$username, $count, $lockedUntil, $count, $lockedUntil]);
}

function clearLoginFailures(string $username): void {
    $pdo = getDB();
    $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$username]);
}

function setSessionLockout(string $username, string $lockedUntil): void {
    startSession();
    $_SESSION['login_lock'] = ['username' => $username, 'until' => strtotime($lockedUntil)];
}

function getSessionLockout(): array {
    startSession();
    if (empty($_SESSION['login_lock'])) return ['locked' => false];
    $lock = $_SESSION['login_lock'];
    $until = (int)($lock['until'] ?? 0);
    $remaining = $until - time();
    if ($remaining <= 0) {
        unset($_SESSION['login_lock']);
        return ['locked' => false];
    }
    return ['locked' => true, 'username' => $lock['username'] ?? '', 'remaining' => $remaining, 'until' => $until];
}

function refreshUserPermissions(): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT module_key, is_granted FROM user_permissions WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $permissions = [];
    while ($row = $stmt->fetch()) {
        $permissions[$row['module_key']] = (bool)$row['is_granted'];
    }
    $_SESSION['permissions'] = $permissions;
    return $permissions;
}

function getUserModulePermissions(): array {
    $permissions = refreshUserPermissions();
    if (!empty($_SESSION['is_admin'])) {
        $permissions = array_fill_keys(array_keys(MODULES), true);
    }
    return $permissions;
}

function isAuthenticated(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        startSession();
    }
    if (empty($_SESSION['user_id'])) return false;
    $elapsed = time() - ($_SESSION['logged_in_at'] ?? 0);
    if ($elapsed > SESSION_LIFETIME) {
        destroySession();
        return false;
    }
    $_SESSION['logged_in_at'] = time();
    return true;
}

function requireAuth(): void {
    if (!isAuthenticated()) {
        redirect(BASE_PATH . '/index.php');
    }
}

function requireAdmin(): void {
    requireAuth();
    if (empty($_SESSION['is_admin'])) {
        redirect(BASE_PATH . '/pages/user/dashboard.php');
    }
}

function logout(): void {
    if (!empty($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    destroySession();
}

function hasPermission(string $moduleKey): bool {
    if (!empty($_SESSION['is_admin'])) return true;
    $alwaysVisible = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
    if (in_array($moduleKey, $alwaysVisible, true)) return true;
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT 1 FROM user_permissions WHERE user_id = ? AND module_key = ? AND is_granted = 1 LIMIT 1');
        $stmt->execute([$_SESSION['user_id'], $moduleKey]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Permission check failed: ' . $e->getMessage());
        return false;
    }
}

function requirePermission(string $moduleKey): void {
    requireAuth();
    if (!hasPermission($moduleKey)) {
        http_response_code(403);
        require_once __DIR__ . '/../error.php';
        exit;
    }
}
