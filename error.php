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
        'message' => 'You do not have permission to view this page. If you believe this is a mistake, please contact your administrator.',
    ],
    404 => [
        'title'   => 'Page not found',
        'message' => 'The page you are looking for may have been moved, renamed, or never existed. Double-check the address and try again.',
    ],
    500 => [
        'title'   => 'Something went wrong',
        'message' => 'An unexpected error occurred on the server. Please try again in a moment, or contact your administrator.',
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
<body class="error-body">
  <div class="error-shell">
    <div class="error-main">
      <div class="error-brand">
        <div class="mark">P</div>
        <div class="name">PAMS<small>Permit Application Management System</small></div>
      </div>
      <div class="error-code"><?php echo $code; ?></div>
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
    </div>
    <div class="error-aside" aria-hidden="true">
      <svg viewBox="0 0 240 220" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="55" y="40" width="130" height="160" rx="10" fill="rgba(255,255,255,.10)" stroke="rgba(255,255,255,.45)" stroke-width="2"/>
        <path d="M55 40l42-22 88 22z" fill="rgba(255,255,255,.16)" stroke="rgba(255,255,255,.45)" stroke-width="2" stroke-linejoin="round"/>
        <path d="M97 18v22" stroke="rgba(255,255,255,.45)" stroke-width="2"/>
        <line x1="80" y1="78" x2="160" y2="78" stroke="rgba(255,255,255,.5)" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="80" y1="94" x2="160" y2="94" stroke="rgba(255,255,255,.28)" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="80" y1="110" x2="140" y2="110" stroke="rgba(255,255,255,.28)" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="172" cy="152" r="26" stroke="rgba(255,255,255,.6)" stroke-width="3"/>
        <path d="M191 171l18 18" stroke="rgba(255,255,255,.6)" stroke-width="3" stroke-linecap="round"/>
        <path d="M172 141v12m0 12h.01" stroke="rgba(255,255,255,.85)" stroke-width="3" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
</body>
</html>
