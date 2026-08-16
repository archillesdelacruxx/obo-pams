<?php
/* ============================================================================
   PAMS — Mobile API Logout (invalidates the Bearer token)
   ============================================================================ */
require_once __DIR__ . '/../includes/auth.php';
startSession();

header('Content-Type: application/json');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
    if ($token !== '') {
        $pdo = getDB();
        $pdo->prepare('DELETE FROM api_tokens WHERE token = ?')->execute([$token]);
    }
}
jsonResponse(['success' => true, 'message' => 'Signed out.']);
