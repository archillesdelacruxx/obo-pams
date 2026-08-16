<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isAuthenticated()) {
    $redirect = !empty($_SESSION['is_admin']) ? 'pages/dashboard.php' : 'pages/user/dashboard.php';
    redirect($redirect);
}

$lockInfo = getSessionLockout();

$error = '';
$successRedirect = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validateCSRFToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $result = authenticate($username, $password);
        if ($result['success']) {
            $successRedirect = $result['redirect'];
        } else {
            $error = $result['error'];
        }
    }
    $lockInfo = getSessionLockout();

    if (isAjaxRequest()) {
        jsonResponse([
            'success' => !empty($successRedirect),
            'redirect' => $successRedirect,
            'error' => $error,
            'locked' => $lockInfo['locked'] ?? false,
            'locked_until' => $lockInfo['until'] ?? null,
            'locked_username' => $lockInfo['username'] ?? ''
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In · PAMS — Permit Application Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/variables.css">
  <link rel="stylesheet" href="assets/css/utilities.css">
  <link rel="stylesheet" href="assets/css/buttons.css">
  <link rel="stylesheet" href="assets/css/forms.css">
  <link rel="stylesheet" href="assets/css/modal.css">
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body class="login-body">
  <div class="login-shell fade-in">
    <div class="login-visual">
      <div class="gov-mark">
        <img src="assets/images/OBO LOGO.png" alt="OBO logo" class="gov-logo">
        <img src="assets/images/GENSAN LOGO.png" alt="Gensan logo" class="gov-logo">
        <span>Office of the Building Official</span>
      </div>
      <div class="login-illustration">
        <svg viewBox="0 0 320 260" width="100%" height="220" fill="none">
          <rect x="30" y="150" width="260" height="14" rx="4" fill="rgba(255,255,255,.14)" />
          <rect x="55" y="70" width="60" height="80" rx="8" fill="rgba(255,255,255,.10)" />
          <rect x="130" y="45" width="60" height="105" rx="8" fill="rgba(255,255,255,.16)" />
          <rect x="205" y="90" width="60" height="60" rx="8" fill="rgba(255,255,255,.10)" />
          <circle cx="160" cy="30" r="16" fill="rgba(255,255,255,.22)" />
          <path d="M144 30a16 16 0 0 1 32 0" stroke="rgba(255,255,255,.5)" stroke-width="2" />
          <rect x="70" y="90" width="30" height="8" rx="2" fill="rgba(255,255,255,.28)" />
          <rect x="145" y="65" width="30" height="8" rx="2" fill="rgba(255,255,255,.28)" />
          <rect x="220" y="110" width="30" height="8" rx="2" fill="rgba(255,255,255,.28)" />
          <path d="M40 150 L280 150" stroke="rgba(255,255,255,.3)" stroke-width="2" stroke-dasharray="4 5" />
        </svg>
      </div>
      <div>
        <h2>One system for every permit,<br>from encoding to release.</h2>
        <p>PAMS centralizes Order of Payment, Permit Workflow, Approval, and Releasing records for every office employee — in one secure, auditable platform.</p>
      </div>
      <p class="foot-note">© 2026 Permit Application Management System. Authorized personnel only.</p>
    </div>
    <div class="login-form-side">
      <div class="login-brand">
        <div class="mark">P</div>
        <div class="name">PAMS<small>Permit Application Management System</small></div>
      </div>
      <h1>Welcome back</h1>
      <p class="welcome">Sign in with your office credentials to continue.</p>

      <div class="login-error <?php echo $error ? 'show' : ''; ?>" id="loginError">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M12 8v5M12 16h.01" />
        </svg>
        <span id="loginErrorText"><?php echo escape($error); ?></span>
      </div>

      <form id="loginForm" method="POST" action="index.php" novalidate>
        <?php echo getCSRFField(); ?>
        <div class="form-group">
          <label for="username">Username</label>
          <input class="form-control" type="text" id="username" name="username" placeholder="e.g. msantiago.admin" autocomplete="username" value="<?php echo escape($_POST['username'] ?? ($lockInfo['locked'] ? $lockInfo['username'] : '')); ?>"<?php echo $lockInfo['locked'] ? ' disabled' : ''; ?>>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-affix">
            <input class="form-control" type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password"<?php echo $lockInfo['locked'] ? ' disabled' : ''; ?>>
            <button type="button" id="pwToggle" aria-label="Show password"<?php echo $lockInfo['locked'] ? ' disabled' : ''; ?>>
              <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
        </div>
        <div class="login-options">
          <label class="checkbox-row">
            <input type="checkbox" id="rememberMe"> Remember me
          </label>
          <a href="#" id="forgotLink">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="loginBtn"<?php echo $lockInfo['locked'] ? ' disabled' : ''; ?>>
          <span id="loginBtnText">Sign In</span>
        </button>
      </form>

    </div>
  </div>
  <div class="modal-wrap" id="forgotModal">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box sm confirm-modal">
      <div class="modal-head">
        <h3>Forgot password</h3>
        <button type="button" class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="c-icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <h3>Contact the admin</h3>
        <p>Please contact the admin to change or reset your password.</p>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" data-close-modal>Close</button>
      </div>
    </div>
  </div>
  <div class="modal-wrap" id="lockModal" data-locked="<?php echo $lockInfo['locked'] ? '1' : '0'; ?>" data-until="<?php echo (int)($lockInfo['until'] ?? 0); ?>">
    <div class="backdrop"></div>
    <div class="modal-box sm confirm-modal lock-modal">
      <div class="modal-head">
        <h3>Account temporarily locked</h3>
      </div>
      <div class="modal-body">
        <div class="c-icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
        </div>
        <h3>Too many failed attempts</h3>
        <p>Too many failed login attempts. Please wait <strong id="lockCountdown">05:00</strong> before trying again.</p>
      </div>
      <div class="modal-foot">
        <button class="btn btn-danger" disabled>Locked</button>
      </div>
    </div>
  </div>
  <div class="loading-overlay" id="pageLoader">
    <div class="spinner"></div>
  </div>
  <script src="assets/js/utilities.js"></script>
  <script src="assets/js/validation.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      attachRipple();
      const form = $('#loginForm');
      const usernameEl = $('#username');
      const passwordEl = $('#password');
      const errorBox = $('#loginError');
      const loader = $('#pageLoader');
      initPasswordToggle($('#pwToggle'), passwordEl);

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateLoginForm(usernameEl, passwordEl)) {
          return;
        }
        const btn = $('#loginBtn');
        btn.setAttribute('disabled', 'true');
        $('#loginBtnText').innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;border-color:rgba(255,255,255,.4);border-top-color:#fff;"></span>';
        loader.classList.add('show');

        const resetBtn = () => {
          btn.removeAttribute('disabled');
          $('#loginBtnText').textContent = 'Sign In';
          loader.classList.remove('show');
        };

        try {
          const res = await fetch('index.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
          });
          const data = await res.json();
          if (data.success) {
            window.location.href = data.redirect;
          } else {
            errorBox.classList.add('show');
            $('#loginErrorText').textContent = data.error || 'Invalid username or password.';
            resetBtn();
            if (data.locked && data.locked_until) {
              openLockModal(Number(data.locked_until));
            }
          }
        } catch (err) {
          errorBox.classList.add('show');
          $('#loginErrorText').textContent = 'Unable to sign in. Please try again.';
          resetBtn();
        }
      });

      [usernameEl, passwordEl].forEach(el => el.addEventListener('input', () => clearFieldError(el)));

      const forgotModal = $('#forgotModal');
      $('#forgotLink').addEventListener('click', (e) => {
        e.preventDefault();
        forgotModal.classList.add('open');
      });
      forgotModal.querySelectorAll('[data-close-modal]').forEach(el => {
        el.addEventListener('click', () => forgotModal.classList.remove('open'));
      });

      const lockModal = $('#lockModal');
      let lockTimer = null;

      const pad2 = (n) => String(n).padStart(2, '0');

      const renderLockCountdown = (remaining) => {
        const el = $('#lockCountdown');
        if (!el) return;
        el.textContent = `${pad2(Math.floor(remaining / 60))}:${pad2(remaining % 60)}`;
      };

      const setFormLocked = (locked) => {
        if (usernameEl) usernameEl.disabled = locked;
        if (passwordEl) passwordEl.disabled = locked;
        const btn = $('#loginBtn');
        if (btn) btn.disabled = locked;
      };

      const closeLockModal = () => {
        if (!lockModal) return;
        lockModal.classList.remove('open');
        if (lockTimer) { clearInterval(lockTimer); lockTimer = null; }
        setFormLocked(false);
      };

      const openLockModal = (until) => {
        if (!lockModal) return;
        lockModal.dataset.until = until;
        lockModal.classList.add('open');
        setFormLocked(true);
        if (lockTimer) clearInterval(lockTimer);
        const tick = () => {
          const remaining = Math.max(0, Math.ceil(until - Date.now() / 1000));
          renderLockCountdown(remaining);
          if (remaining <= 0) closeLockModal();
        };
        tick();
        lockTimer = setInterval(tick, 1000);
      };

      if (lockModal && Number(lockModal.dataset.locked) === 1) {
        const until = Number(lockModal.dataset.until || 0);
        if (until > Date.now() / 1000) openLockModal(until);
      }
    });
  </script>
</body>
</html>
