<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings Â· PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="settings" data-full-name="<?php echo escape($_SESSION['full_name']); ?>" data-username="<?php echo escape($_SESSION['username']); ?>" data-email="<?php echo escape($_SESSION['email'] ?? ''); ?>">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('settings'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Settings'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Settings</span></div>
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
                        <span>Click to upload a new logo (PNG, SVG â€” max 2MB)</span>
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
  <script src="../assets/js/app.js?v=20260816b"></script>
</body>
</html>

