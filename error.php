<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

$code = (int)($_SERVER['REDIRECT_STATUS'] ?? http_response_code());
if ($code < 400 || $code > 599) {
    $code = 404;
}

$pages = [
    403 => [
        'title'   => 'Access denied',
        'tag'     => 'ACCESS DENIED',
        'message' => 'You do not have permission to view this page. If you believe this is a mistake, please contact your administrator.',
        'icon'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ],
    404 => [
        'title'   => 'Page not found',
        'tag'     => 'PAGE NOT FOUND',
        'message' => 'The page you are looking for may have been moved, renamed, or never existed. Double-check the address and try again.',
        'icon'    => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
    ],
    500 => [
        'title'   => 'Something went wrong',
        'tag'     => 'SERVER ERROR',
        'message' => 'An unexpected error occurred on the server. Please try again in a moment, or contact your administrator.',
        'icon'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    ],
];
$info = $pages[$code] ?? $pages[404];

$isAuthed = isAuthenticated();
$dashUrl = BASE_PATH . (!empty($_SESSION['is_admin']) ? '/pages/dashboard.php' : '/pages/user/dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $code; ?> · PAMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/variables.css">
  <link rel="stylesheet" href="assets/css/buttons.css">
  <link rel="stylesheet" href="assets/css/error.css">
</head>
<body class="error-body" data-code="<?php echo $code; ?>">
  <div class="error-page">
    <div class="error-bg" aria-hidden="true">
      <span class="orb orb-1"></span>
      <span class="orb orb-2"></span>
      <span class="orb orb-3"></span>
      <div class="error-grid"></div>
    </div>

    <main class="error-content">
      <div class="error-brand">
        <div class="mark">P</div>
        <div class="name">PAMS<small>Permit Application Management System</small></div>
      </div>

      <div class="error-code-wrap">
        <div class="error-icon"><?php echo $info['icon']; ?></div>
        <div class="error-code"><?php echo $code; ?></div>
      </div>

      <span class="error-tag"><?php echo escape($info['tag']); ?></span>
      <h1 class="error-title"><?php echo escape($info['title']); ?></h1>
      <p class="error-message"><?php echo escape($info['message']); ?></p>

      <div class="error-actions">
        <?php if ($isAuthed): ?>
          <a class="btn btn-primary" href="<?php echo escape($dashUrl); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Back to Dashboard
          </a>
          <a class="btn btn-secondary" href="logout.php">Sign out</a>
        <?php else: ?>
          <a class="btn btn-primary" href="index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Go to Login
          </a>
          <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
        <?php endif; ?>
      </div>

      <p class="error-foot">&copy; 2026 Permit Application Management System &middot; Authorized personnel only</p>
    </main>
  </div>
</body>
</html>
