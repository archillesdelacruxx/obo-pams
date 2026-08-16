<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$isDev = ($_SESSION['role'] ?? '') === 'developer';
if (!$isDev) {
    requirePermission('settings');
    header('Location: settings.php');
    exit;
}
require_once __DIR__ . '/../includes/admin-shell.php';

$pdo = getDB();
$stmt = $pdo->prepare('SELECT full_name, username, email, last_login, created_at, profile_photo FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile · PAMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/variables.css">
  <link rel="stylesheet" href="../assets/css/utilities.css">
  <link rel="stylesheet" href="../assets/css/buttons.css">
  <link rel="stylesheet" href="../assets/css/layout.css?v=20260816c">
  <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../assets/css/header.css">
  <link rel="stylesheet" href="../assets/css/cards.css">
  <link rel="stylesheet" href="../assets/css/forms.css">
  <link rel="stylesheet" href="../assets/css/modal.css">
  <link rel="stylesheet" href="../assets/css/tables.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body data-page="profile" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>" data-last-login="<?php echo escape($_SESSION['logged_in_at'] ?? ''); ?>" data-profile-photo="<?php echo escape($profile['profile_photo'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('profile'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Profile'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Profile</span></div>
        <div class="page-head">
          <div>
            <h1>My Profile</h1>
            <p class="subtitle">Manage your personal information and account security.</p>
          </div>
        </div>

        <div class="section-card">
          <div class="profile-hero">
            <div class="avatar lg" id="profilePhotoWrap">
              <?php if ($profile['profile_photo']): ?>
              <img src="../<?php echo escape($profile['profile_photo']); ?>" alt="Profile photo" class="avatar-img">
              <?php else: ?>
              <span id="profileAvatarInitials"></span>
              <?php endif; ?>
              <div class="cam-btn" id="profileCamBtn" title="Change profile picture">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg>
              </div>
              <input type="file" id="profilePhotoInput" accept="image/*" hidden>
            </div>
            <div class="p-info">
              <h2><?php echo escape($profile['full_name'] ?? 'Administrator'); ?></h2>
              <p><?php echo escape(roleDisplayName($_SESSION['role'] ?? 'inspector')); ?></p>
              <div class="flex gap-sm" style="margin-top:10px;">
                <span class="badge badge-success">Active</span>
              </div>
            </div>
          </div>
        </div>

        <div class="two-col">
          <div class="section-card" style="margin:0;">
            <div class="section-head"><h3>Personal Information</h3></div>
            <div class="section-body">
              <form id="profileForm" method="POST" action="#">
                <input type="hidden" name="_csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-grid">
                  <div class="form-group full">
                    <label>Full Name</label>
                    <input class="form-control" name="full_name" value="<?php echo escape($profile['full_name'] ?? ''); ?>">
                  </div>
                  <div class="form-group">
                    <label>Username</label>
                    <input class="form-control" value="<?php echo escape($profile['username'] ?? ''); ?>" disabled>
                  </div>
                  <div class="form-group">
                    <label>Email Address</label>
                    <input class="form-control" type="email" name="email" value="<?php echo escape($profile['email'] ?? ''); ?>">
                  </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </form>
            </div>
          </div>

          <div class="section-card" style="margin:0;">
            <div class="section-head"><h3>Change Password</h3></div>
            <div class="section-body">
              <form id="changePasswordForm" method="POST" action="#">
                <input type="hidden" name="_csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                  <label>Current Password</label>
                  <input class="form-control" type="password" name="current_password" placeholder="Enter current password">
                </div>
                <div class="form-group">
                  <label>New Password</label>
                  <input class="form-control" type="password" id="newPassword" name="new_password" placeholder="At least 6 characters">
                </div>
                <div class="form-group">
                  <label>Confirm New Password</label>
                  <input class="form-control" type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password">
                </div>
                <button type="submit" class="btn btn-secondary btn-block">Update Password</button>
              </form>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/components.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script src="../assets/js/dropdown.js"></script>
  <script src="../assets/js/notification.js"></script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/search.js"></script>
  <script src="../assets/js/table.js"></script>
  <script src="../assets/js/validation.js"></script>
  <script src="../assets/js/realtime.js?v=20260803b"></script>
  <script src="../assets/js/app.js?v=20260803d"></script>
</body>
</html>