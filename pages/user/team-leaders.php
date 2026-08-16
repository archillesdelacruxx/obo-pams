<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('team-leaders');
$perms = getUserModulePermissions();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team Leaders · PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css?v=20260816c">
  <link rel="stylesheet" href="../../assets/css/sidebar.css?v=20260803b">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css?v=20260817d">
  <style>
    .tl-team-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .tl-team-card { border:1.5px solid var(--gray-200); border-radius:12px; padding:12px; text-align:center; cursor:pointer; transition:.15s; }
    .tl-team-card.selected { border-color:var(--primary); background:var(--primary-50,#F3F7FE); }
    .tl-team-card .tl-team-dot { width:26px; height:26px; border-radius:50%; background:var(--gray-100); color:var(--gray-600); display:inline-flex; align-items:center; justify-content:center; font-weight:700; margin-bottom:4px; }
    .tl-team-card.t2.selected { border-color:var(--info,#0ea5e9); }
    .tl-team-card.t2 .tl-team-dot { background:#e0f2fe; color:#0369a1; }
    .tl-team-card strong { display:block; font-size:13px; }
    .tl-team-card small { color:var(--gray-400); font-size:11px; }
  </style>
</head>
<body data-page="team-leaders" data-is-admin="<?php echo empty($_SESSION['is_admin']) ? '0' : '1'; ?>" data-permissions='<?php echo json_encode(array_keys(array_filter($perms))); ?>'>
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('team-leaders'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Team Leaders'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Inspection Management</span><span class="sep">/</span><span class="current">Team Leaders</span></div>
        <div class="page-head">
          <div><h1>Team Leaders</h1><p class="subtitle">Register and manage the inspection team leader registry.</p></div>
          <button class="btn btn-primary" id="tlCreateBtn" style="display:inline-flex;align-items:center;gap:6px;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg> Register Team Leader</button>
        </div>

        <div class="section-card table-card">
          <div class="section-head"><h3>Registered Team Leaders (<span id="tlCount">0</span>)</h3><button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" id="tlRefreshBtn">Refresh</button></div>
          <div class="scroll-x">
            <table class="data-table">
              <thead><tr><th>Full Name</th><th>Position</th><th>Team</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody id="tlTbody"></tbody>
            </table>
          </div>
          <div class="table-footer"><span class="text-xs text-muted" id="tlPageInfo"></span></div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-wrap" id="tlFormModal">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box confirm-modal">
      <div class="modal-head">
        <h3 id="tlModalTitle">Register Team Leader</h3>
        <button type="button" class="icon-btn" data-close-modal aria-label="Close">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group full">
            <label>Full Name <span class="req">*</span></label>
            <input class="form-control" id="tlName" placeholder="e.g. Engr. Juan Dela Cruz" required>
          </div>
          <div class="form-group full">
            <label>Position</label>
            <input class="form-control" id="tlPosition" placeholder="e.g. Chief Inspector">
          </div>
        </div>
        <div style="margin-top:6px;">
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:8px;">Assigned Team</label>
          <div class="tl-team-grid">
            <div class="tl-team-card selected" data-team="1" onclick="tlPickTeam(1, this)"><div class="tl-team-dot">1</div><strong>Team 1</strong><small>Lead inspector</small></div>
            <div class="tl-team-card t2" data-team="2" onclick="tlPickTeam(2, this)"><div class="tl-team-dot">2</div><strong>Team 2</strong><small>Assistant inspector</small></div>
          </div>
          <input type="hidden" id="tlTeam" value="1">
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" data-close-modal>Cancel</button>
        <button class="btn btn-primary" id="tlSaveBtn">Register Team Leader</button>
      </div>
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
  <script src="../../assets/js/user-app.js?v=20260817c"></script>
</body>
</html>
