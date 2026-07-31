<?php
require_once __DIR__ . '/../../includes/user-shell.php';
requireAuth();
requirePermission('notifications');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications · PAMS User</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/variables.css">
  <link rel="stylesheet" href="../../assets/css/utilities.css">
  <link rel="stylesheet" href="../../assets/css/buttons.css">
  <link rel="stylesheet" href="../../assets/css/layout.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/header.css">
  <link rel="stylesheet" href="../../assets/css/cards.css">
  <link rel="stylesheet" href="../../assets/css/forms.css">
  <link rel="stylesheet" href="../../assets/css/modal.css">
  <link rel="stylesheet" href="../../assets/css/tables.css">
  <link rel="stylesheet" href="../../assets/css/responsive.css">
  <link rel="stylesheet" href="../../assets/css/user.css">
</head>
<body data-page="notifications">
  <div class="app-shell" id="appShell">
    <?php echo renderUserSidebar('notifications'); ?>
    <div class="main-col">
      <?php echo renderUserHeader('Notifications'); ?>
      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">Notifications</span></div>
        <div class="page-head"><div><h1>Notifications</h1><p class="subtitle">All system notifications and alerts.</p></div></div>
        <div class="section-card">
          <div class="scroll-x">
            <table class="data-table"><thead><tr><th style="width:24px;"></th><th>Type</th><th>Title</th><th>Message</th><th>Date</th></tr></thead><tbody id="notifTbody"></tbody></table>
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
  <script src="../../assets/js/user-components.js"></script>
  <script src="../../assets/js/user-app.js?v=20260802"></script>
</body>
</html>
