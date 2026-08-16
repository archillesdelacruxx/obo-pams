<?php
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$role = $_SESSION['role'] ?? 'inspector';
$isDev = $role === 'developer';
$useAdminShell = in_array($role, ['developer', 'admin', 'admin_aid', 'inspector-admin'], true);
if (!$isDev) requirePermission('settings');
if ($useAdminShell) {
    require_once __DIR__ . '/../includes/admin-shell.php';
} else {
    require_once __DIR__ . '/../includes/user-shell.php';
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT full_name, username, email, last_login, profile_photo FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$isDev) {
    $permissions = getUserModulePermissions();
    $activeModules = array_keys(array_filter($permissions));
    $moduleLabels = [
        'dashboard' => 'Dashboard', 'order-of-payment' => 'Order of Payment',
        'op-records' => 'OP Records', 'permit-workflow' => 'Permit Workflow',
        'workflow-details' => 'Workflow Details', 'permit-approval-encoding' => 'Permit Approval Encoding',
        'permit-approval-records' => 'Permit Approval Records',
        'releasing' => 'Releasing Plans', 'releasing-records' => 'Releasing Records',
        'inspection-checklist' => 'Ocular Inspection Checklist', 'inspection-reports' => 'Monitoring Reports',
        'inspection-review' => 'Inspection Review', 'inspection-edit' => 'Inspection — Edit Checklists',
        'inspection-delete' => 'Inspection — Delete Records', 'team-leaders' => 'Team Leaders',
        'notifications' => 'Notifications', 'announcements' => 'Announcements',
        'settings' => 'Profile Settings', 'profile' => 'Profile',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isDev ? 'Settings · PAMS' : 'Profile Settings · PAMS' . ($useAdminShell ? '' : ' User'); ?></title>
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
  <?php if (!$useAdminShell): ?><link rel="stylesheet" href="../assets/css/user.css?v=20260812c"><?php endif; ?>
  <?php if ($useAdminShell): ?><link rel="stylesheet" href="../assets/css/dashboard.css"><?php endif; ?>
  <?php if (!$isDev): ?><style>.module-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;background:var(--color-primary-50);color:var(--color-primary);border:1px solid var(--color-primary-100)}</style><?php endif; ?>
</head>
<body data-page="<?php echo $useAdminShell ? 'settings' : 'user-settings'; ?>" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>" data-last-login="<?php echo escape($_SESSION['logged_in_at'] ?? ''); ?>" data-profile-photo="<?php echo escape($profile['profile_photo'] ?? ''); ?>"<?php if (!$useAdminShell): ?> data-role="<?php echo escape($_SESSION['role'] ?? 'inspector'); ?>"<?php endif; ?>>

  <div class="app-shell" id="appShell">
    <?php echo $useAdminShell ? renderAdminSidebar('settings') : renderUserSidebar('settings', 'user/'); ?>

    <div class="main-col">
      <?php echo $useAdminShell ? renderAdminHeader('Settings') : renderUserHeader('Settings', 'user/'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current"><?php echo $isDev ? 'Settings' : 'Profile Settings'; ?></span></div>
        <?php if ($isDev): ?>
        <div class="page-head">
          <div>
            <h1>System Settings</h1>
            <p class="subtitle">Control module availability and general system information.</p>
          </div>
        </div>

        <div class="two-col">
          <div>
            <div class="section-card">
              <div class="section-head">
                <h3>Module Availability</h3>
                <span class="badge badge-info">Affects all users</span>
              </div>
              <div class="section-body">
                <p class="text-sm text-muted" style="margin-bottom:14px;">
                  Turning a module OFF immediately shows a maintenance placeholder to every user assigned to it.
                </p>
                <div class="module-switch-list" id="systemModuleList"></div>
              </div>
            </div>

            <div class="section-card">
              <div class="section-head"><h3>General Information</h3></div>
              <div class="section-body">
                <form id="generalSettingsForm">
                  <div class="form-grid">
                    <div class="form-group">
                      <label>System Name</label>
                      <input class="form-control" value="Permit Application Management System">
                    </div>
                    <div class="form-group">
                      <label>Office Name</label>
                      <input class="form-control" value="Office of the Building Official">
                    </div>
                    <div class="form-group full">
                      <label>Office Logo</label>
                      <div class="upload-drop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                        <span>Click to upload a new logo (PNG, SVG — max 2MB)</span>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Save General Settings</button>
                </form>
              </div>
            </div>
          </div>

          <div>
            <div class="section-card">
              <div class="section-head"><h3>AI Remark Assistant</h3><span class="badge badge-info">Inspection</span></div>
              <div class="section-body">
                <p class="text-sm text-muted" style="margin-bottom:14px;">
                  Add an AI API key so inspectors can auto-summarize each checklist category's compliance
                  into the Remark/s box. Leave blank to hide the AI button (manual typing still works).
                </p>
                <form id="aiSettingsForm">
                  <div class="form-group">
                    <label>API Key</label>
                    <input class="form-control form-mono" type="password" id="aiApiKey" placeholder="sk-…" autocomplete="off">
                  </div>
                  <div class="flex gap-sm" style="align-items:flex-end;">
                    <button type="submit" class="btn btn-primary">Save AI Settings</button>
                    <span class="text-xs text-muted" id="aiStatusText"></span>
                  </div>
                </form>
              </div>
            </div>

            <div class="section-card">
              <div class="section-head"><h3>Reserved for Future Settings</h3></div>
              <div class="section-body">
                <div class="empty-state" style="padding:36px 16px;">
                  <div class="empty-icon">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                  </div>
                  <h4>More controls are on the way</h4>
                  <p class="text-sm">Audit logs, backup schedules, and notification rules will appear here.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <div class="page-head"><div><h1>Profile Settings</h1><p class="subtitle">Your profile, assigned modules, and security preferences.</p></div></div>
        <div class="section-card">
          <div class="profile-hero">
            <div class="avatar lg" id="settingsPhotoWrap">
              <?php if ($profile['profile_photo']): ?>
              <img src="../<?php echo escape($profile['profile_photo']); ?>" alt="Profile photo" class="avatar-img">
              <?php else: ?>
              <span id="settingsHeroAvatar"></span>
              <?php endif; ?>
              <div class="cam-btn" id="settingsCamBtn" title="Change profile picture">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg>
              </div>
              <input type="file" id="settingsPhotoInput" accept="image/*" hidden>
            </div>
            <div class="p-info">
              <h2 id="settingsHeroName"><?php echo escape($profile['full_name'] ?? 'User'); ?></h2>
              <p id="settingsHeroUsername">@<?php echo escape($profile['username'] ?? ''); ?></p>
              <div class="flex gap-sm" style="margin-top:10px;">
                <span class="badge badge-success">Active</span>
                <span id="settingsLastLogin" style="font-size:12px;color:var(--gray-400);">Last login: <?php echo $profile['last_login'] ? formatDate($profile['last_login']) : 'Never'; ?></span>
              </div>
            </div>
          </div>
        </div>
        <div class="section-card">
          <div class="section-head"><h3>Assigned Modules</h3></div>
          <div class="section-body" id="settingsModules">
            <?php foreach ($activeModules as $key): ?>
            <span class="module-badge"><?php echo escape($moduleLabels[$key] ?? $key); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="two-col">
          <div class="section-card">
            <div class="section-head"><h3>Profile Information</h3></div>
            <div class="section-body">
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
                <div class="form-group"><label>Current Password</label><input class="form-control" type="password" name="current_password" id="settingsCurrentPassword" placeholder="Enter current password"></div>
                <div class="form-group"><label>New Password</label><div class="input-affix"><input class="form-control" type="password" id="settingsNewPassword" placeholder="Min 6 characters"><button type="button" id="settingsPwToggle1"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                <div class="form-group"><label>Confirm Password</label><div class="input-affix"><input class="form-control" type="password" id="settingsConfirmPassword" placeholder="Re-enter new password"><button type="button" id="settingsPwToggle2"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                <button type="submit" class="btn btn-secondary btn-block">Update Password</button>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </main>
    </div>
  </div>

  <?php if ($useAdminShell): ?>
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
  <script src="../assets/js/app.js?v=20260816b"></script>
  <?php else: ?>
  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/validation.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script src="../assets/js/dropdown.js"></script>
  <script src="../assets/js/notification.js"></script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/user-components.js?v=20260803e"></script>
  <script src="../assets/js/realtime.js?v=20260803b"></script>
  <script src="../assets/js/user-app.js?v=20260803e"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var heroAvatar = document.getElementById('settingsHeroAvatar');
    var photoWrap = document.getElementById('settingsPhotoWrap');
    if (heroAvatar && photoWrap && !photoWrap.querySelector('img')) {
      heroAvatar.textContent = initials(document.body.dataset.fullName || 'User');
    }
    var heroName = document.getElementById('settingsHeroName');
    var heroUser = document.getElementById('settingsHeroUsername');
    if (heroName) heroName.textContent = document.body.dataset.fullName || 'User';
    if (heroUser) heroUser.textContent = '@' + (document.body.dataset.username || '');
    if (photoWrap && window.initProfilePhotoUpload) {
      initProfilePhotoUpload('settingsCamBtn', 'settingsPhotoInput', photoWrap, (document.body.dataset.fullName || 'User').trim());
    }
  });
  </script>
  <?php endif; ?>
</body>
</html>