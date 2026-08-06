<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('profile');

$pdo = getDB();
$stmt = $pdo->prepare('SELECT full_name, username, email, last_login, profile_photo FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

$permissions = getUserModulePermissions();
$activeModules = array_keys(array_filter($permissions));
$moduleLabels = [
    'dashboard' => 'Dashboard', 'order-of-payment' => 'Order of Payment',
    'op-records' => 'OP Records', 'permit-workflow' => 'Permit Workflow',
    'workflow-details' => 'Workflow Details', 'permit-approval-encoding' => 'Permit Approval Encoding',
    'permit-approval-records' => 'Permit Approval Records',
    'releasing' => 'Releasing Plans', 'releasing-records' => 'Releasing Records',
    'notifications' => 'Notifications', 'announcements' => 'Announcements',
    'settings' => 'Settings', 'profile' => 'Profile',
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile Â· PAMS User</title>
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
  <link rel="stylesheet" href="../../assets/css/user.css">
  <style>.module-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;background:var(--color-primary-50);color:var(--color-primary);border:1px solid var(--color-primary-100)}</style>
</head>
<body data-page="user-profile" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>" data-last-login="<?php echo escape($_SESSION['logged_in_at'] ?? ''); ?>" data-profile-photo="<?php echo escape($profile['profile_photo'] ?? ''); ?>">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('profile'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Profile'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Profile</span></div>
        <div class="page-head"><div><h1>My Profile</h1><p class="subtitle">Your account information and assigned modules.</p></div></div>
        <div class="section-card">
          <div class="profile-hero">
            <div class="avatar lg" id="profilePhotoWrap">
              <?php if ($profile['profile_photo']): ?>
              <img src="../../<?php echo escape($profile['profile_photo']); ?>" alt="Profile photo" class="avatar-img">
              <?php else: ?>
              <span id="profileHeroAvatar"></span>
              <?php endif; ?>
              <div class="cam-btn" id="profileCamBtn" title="Change profile picture">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg>
              </div>
              <input type="file" id="profilePhotoInput" accept="image/*" hidden>
            </div>
            <div class="p-info">
              <h2 id="profileHeroName"><?php echo escape($profile['full_name'] ?? 'User'); ?></h2>
              <p id="profileHeroUsername">@<?php echo escape($profile['username'] ?? ''); ?></p>
              <div class="flex gap-sm" style="margin-top:10px;">
                <span class="badge badge-success">Active</span>
                <span id="profileLastLogin" style="font-size:12px;color:var(--gray-400);">Last login: <?php echo $profile['last_login'] ? formatDate($profile['last_login']) : 'Never'; ?></span>
              </div>
            </div>
          </div>
        </div>
        <div class="section-card">
          <div class="section-head"><h3>Assigned Modules</h3></div>
          <div class="section-body" id="profileModules">
            <?php foreach ($activeModules as $key): ?>
            <span class="module-badge"><?php echo escape($moduleLabels[$key] ?? $key); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <div class="section-head"><h3>Account Details</h3></div>
            <div class="section-body" style="display:flex;flex-direction:column;gap:8px;">
              <div><span class="text-muted">Full Name:</span> <strong><?php echo escape($profile['full_name'] ?? ''); ?></strong></div>
              <div><span class="text-muted">Username:</span> <strong><?php echo escape($profile['username'] ?? ''); ?></strong></div>
              <div><span class="text-muted">Email:</span> <strong><?php echo escape($profile['email'] ?? 'â€”'); ?></strong></div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:16px;">
            <button class="btn btn-primary" id="profileEditBtn">Edit Profile</button>
            <button class="btn btn-secondary" id="profileChangePwBtn">Change Password</button>
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

