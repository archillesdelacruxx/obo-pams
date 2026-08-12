<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('settings');

$pdo = getDB();
$stmt = $pdo->prepare('SELECT full_name, username, email FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings Â· PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260812c">
</head>
<body data-page="user-settings" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('settings'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Settings'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Settings</span></div>
        <div class="page-head"><div><h1>Account Settings</h1><p class="subtitle">Manage your profile information and security preferences.</p></div></div>
        <div class="two-col">
          <div class="section-card">
            <div class="section-head"><h3>Profile Information</h3></div>
            <div class="section-body">
              <div class="avatar-section"><div class="avatar lg" id="settingsAvatarInitials"></div></div>
              <form id="settingsProfileForm">
                <div class="form-grid">
                  <div class="form-group full"><label>Full Name</label><input class="form-control" id="settingsFullName" value="<?php echo escape($profile['full_name'] ?? ''); ?>"></div>
                  <div class="form-group"><label>Username</label><input class="form-control" id="settingsUsername" value="<?php echo escape($profile['username'] ?? ''); ?>" disabled></div>
                  <div class="form-group"><label>Email</label><input class="form-control" type="email" id="settingsEmail" value="<?php echo escape($profile['email'] ?? ''); ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </form>
            </div>
          </div>
          <div class="section-card">
            <div class="section-head"><h3>Change Password</h3></div>
            <div class="section-body">
              <form id="settingsPasswordForm">
                <div class="form-group"><label>Current Password</label><input class="form-control" type="password" name="current_password" placeholder="Enter current password"></div>
                <div class="form-group"><label>New Password</label><div class="input-affix"><input class="form-control" type="password" id="settingsNewPassword" placeholder="Min 6 characters"><button type="button" id="settingsPwToggle1"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                <div class="form-group"><label>Confirm Password</label><div class="input-affix"><input class="form-control" type="password" id="settingsConfirmPassword" placeholder="Re-enter new password"><button type="button" id="settingsPwToggle2"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                <button type="submit" class="btn btn-secondary btn-block">Update Password</button>
              </form>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="../../assets/js/utilities.js"></script>
  <script src="../../assets/js/api.js"></script>
  <script src="../../assets/js/validation.js"></script>
  <script src="../../assets/js/sidebar.js"></script>
  <script src="../../assets/js/dropdown.js"></script>
  <script src="../../assets/js/notification.js"></script>
  <script src="../../assets/js/modal.js"></script>
  <script src="../../assets/js/user-components.js?v=20260803e"></script>
  <script src="../../assets/js/realtime.js?v=20260803b"></script>
  <script src="../../assets/js/user-app.js?v=20260803e"></script>
</body>
</html>

