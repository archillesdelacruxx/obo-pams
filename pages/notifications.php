<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();
$pdo = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="csrf-token" content="<?php echo escape(generateCSRFToken()); ?>">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements &middot; PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260816c">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/tables.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
<style>
.post-card{max-width:760px;}
</style>
</head>
<body data-page="notifications">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('notifications'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('Announcements'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Announcements</span></div>

        <div class="page-head">
          <div>
            <h1>Announcements</h1>
            <p class="subtitle">Post announcements that notify all users automatically.</p>
          </div>
        </div>

        <div class="section-card post-card">
          <div class="section-head">
            <h3>Post Announcement</h3>
            <span class="badge badge-neutral">Notifies all users</span>
          </div>
          <form id="announcementForm">
            <div class="section-body">
              <div class="form-group">
                <label for="annTitle">Title</label>
                <input type="text" id="annTitle" class="form-control" placeholder="e.g. Office closure this Friday" maxlength="200">
              </div>
              <div class="form-group">
                <label for="annContent">Content</label>
                <textarea id="annContent" class="form-control" rows="4" placeholder="Announcement details&hellip;"></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Post Announcement</button>
            </div>
          </form>
        </div>

        <div class="section-card">
          <div class="section-head">
            <h3>Announcements</h3>
            <span class="badge badge-neutral" id="adminAnnouncementsCount">0</span>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>Title</th><th>Posted By</th><th>Date</th><th style="width:60px;"></th></tr>
              </thead>
              <tbody id="adminAnnouncementsTbody"></tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="loading-overlay" id="pageLoader">
    <div class="spinner"></div>
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
