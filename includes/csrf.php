<?php

function generateCSRFToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function getCSRFField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . generateCSRFToken() . '">';
}

function validateCSRFToken(?string $token): bool {
    if (empty($_SESSION['_csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function requireCSRF(): void {
    $token = $_POST['_csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        logActivity($_SESSION['user_id'] ?? null, 'csrf_validation_failed', 'Invalid CSRF token');
        jsonResponse(['error' => 'Invalid security token. Please refresh the page and try again.'], 403);
    }
}
