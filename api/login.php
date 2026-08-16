<?php
/* ============================================================================
   PAMS — Mobile API Login (token-based)
   Returns a Bearer token na ginagamit ng React Native app.
   Ang web app ay nananatiling session-based; ito ay para sa mobile lang.
   ============================================================================ */
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$remember = !empty($data['remember']);

if ($username === '' || $password === '') {
    jsonResponse(['error' => 'Please enter both username and password.'], 422);
}

$result = authenticate($username, $password);
if (!$result['success']) {
    $lock = getSessionLockout();
    jsonResponse([
        'error' => $result['error'],
        'locked' => $lock['locked'] ?? false,
        'locked_until' => $lock['until'] ?? null,
    ], 401);
}

/* Mobile app is inspector-only: block admin/developer and any non-inspector role. */
if (($_SESSION['role'] ?? 'inspector') !== 'inspector' || !empty($_SESSION['is_admin'])) {
    logout();
    jsonResponse(['error' => 'Only inspector accounts can sign in to the mobile app.'], 403);
}

$pdo = getDB();
$userId = (int)$_SESSION['user_id'];

/* Create a fresh token. Optional cleanup: prune this user's old tokens. */
$pdo->prepare('DELETE FROM api_tokens WHERE user_id = ? AND expires_at <= NOW()')->execute([$userId]);

$token = bin2hex(random_bytes(32));
$ttlHours = $remember ? 24 * 30 : 12;
/* Compute expiry on the DB side (NOW()) so it matches the expiry check in api/index.php
   regardless of PHP vs MySQL timezone settings. */
$pdo->prepare('INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))')->execute([$userId, $token, $ttlHours]);

$expStmt = $pdo->prepare('SELECT expires_at FROM api_tokens WHERE token = ?');
$expStmt->execute([$token]);
$expires = $expStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT id, full_name, username, email, profile_photo, is_active, is_admin, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$perms = [];
if (!empty($user['is_admin']) && in_array($user['role'] ?? 'inspector', ['developer', 'admin'], true)) {
    $perms = array_keys(MODULES);
} else {
    $permStmt = $pdo->prepare('SELECT module_key FROM user_permissions WHERE user_id = ? AND is_granted = 1');
    $permStmt->execute([$userId]);
    $perms = $permStmt->fetchAll(PDO::FETCH_COLUMN);
}
$perms = array_values(array_unique(array_merge(['dashboard', 'notifications', 'announcements', 'profile', 'settings'], $perms)));

jsonResponse([
    'success' => true,
    'token' => $token,
    'expires_at' => $expires,
    'user' => [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'profile_photo' => $user['profile_photo'],
        'role' => $user['role'],
        'is_admin' => !empty($user['is_admin']),
    ],
    'permissions' => $perms,
]);
