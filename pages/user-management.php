<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();

$pdo = getDB();
[$message, $messageType] = getFlashMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = '12345';
        $email = trim($_POST['email'] ?? '');
        $isAdmin = (($_POST['role'] ?? 'obo_user') === 'admin') ? 1 : 0;

        if ($fullName && $username) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                setFlashMessage('Username already exists.', 'error');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (full_name, username, email, password_hash, profile_photo, is_admin) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$fullName, $username, $email, $hash, 'assets/images/OBO LOGO.png', $isAdmin]);
                $userId = $pdo->lastInsertId();

                $modules = $_POST['modules'] ?? [];
                $fixedModules = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
                $grantedModules = array_values(array_unique(array_merge($fixedModules, $modules)));
                $stmt = $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, 1)');
                foreach ($grantedModules as $moduleKey) {
                    $stmt->execute([$userId, $moduleKey]);
                }

                logActivity($_SESSION['user_id'], 'user_created', "Created user: $username ($fullName)");
                setFlashMessage('User created successfully. Default password is 12345.', 'success');
            }
        } else {
            setFlashMessage('Full Name, Username, and Password are required.', 'error');
        }
    } elseif ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($userId && $fullName) {
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, is_active = ? WHERE id = ?')
                ->execute([$fullName, $email, $status === 'active' ? 1 : 0, $userId]);

            logActivity($_SESSION['user_id'], 'user_updated', "Updated user ID: $userId");
            setFlashMessage('User updated successfully.', 'success');
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['password'] ?? '';

        if ($userId && $newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            logActivity($_SESSION['user_id'], 'password_reset', "Password reset for user ID: $userId");
            setFlashMessage('Password reset successfully.', 'success');
        }
    } elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            setFlashMessage('You cannot delete your own account.', 'error');
        } elseif ($userId) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            logActivity($_SESSION['user_id'], 'user_deleted', "Deleted user ID: $userId");
            setFlashMessage('User deleted successfully.', 'success');
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'active';
        if ($userId === (int)$_SESSION['user_id']) {
            setFlashMessage('You cannot deactivate your own account.', 'error');
        } elseif ($userId && in_array($newStatus, ['active', 'inactive'])) {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus === 'active' ? 1 : 0, $userId]);
            logActivity($_SESSION['user_id'], 'user_status_changed', "User ID: $userId set to $newStatus");
            setFlashMessage('User status updated.', 'success');
        }
    }

    redirect('user-management.php');
}

$users = $pdo->query("SELECT id, full_name, username, email, profile_photo, is_admin, role, position, CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END AS status, last_login, created_at FROM users ORDER BY full_name")->fetchAll();
$admins = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'));
$oboUsers = array_values(array_filter($users, fn($u) => ($u['role'] ?? '') !== 'admin'));

$permStmt = $pdo->query('SELECT user_id, module_key FROM user_permissions WHERE is_granted = 1');
$allPerms = [];
while ($row = $permStmt->fetch()) {
    $allPerms[$row['user_id']][] = $row['module_key'];
}

function renderUserRows(array $rows, array $allPerms, bool $showModules = true, bool $canDelete = true): string {
    $html = '';
    foreach ($rows as $u) {
        $perms = $allPerms[$u['id']] ?? [];
        $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $u['full_name'])));
        $initials = substr($initials, 0, 2);
        $moduleLabels = array_map(fn($k) => MODULES[$k] ?? $k, $perms);
        $statusBadge = $u['status'] === 'active'
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-neutral">Inactive</span>';
        $role = $u['role'] ?? 'inspector';
        $roleBadge = $role === 'developer' ? '<span class="badge" style="background:#7c3aed;color:#fff;">Developer</span>'
            : ($role === 'admin' ? '<span class="badge badge-info">Admin</span>'
            : ($role === 'admin_aid' ? '<span class="badge" style="background:#0ea5e9;color:#fff;">Admin Aid</span>'
            : '<span class="badge badge-neutral">Inspector</span>'));
        $moduleCell = '';
        if ($showModules) {
            $moduleTags = implode('', array_map(fn($m) => '<span class="module-tag">' . escape($m) . '</span>', array_slice($moduleLabels, 0, 3)));
            if (count($moduleLabels) > 3) {
                $moduleTags .= '<span class="module-tag">+' . (count($moduleLabels) - 3) . '</span>';
            }
            $moduleCell = '<td><div class="module-tags">' . $moduleTags . '</div></td>';
        }
        $positionLabels = [
            'admin_aid_ii' => 'Admin Aid II',
            'pdo_ii' => 'Project Development Officer II',
            'head_admin' => 'Head Administrator',
            'engineer_i' => 'Engineer I',
            'architect_i' => 'Architect I',
            'evaluator' => 'Evaluator',
        ];
        $positionLabel = $positionLabels[$u['position'] ?? ''] ?? ($u['position'] ?? '—');
        $html .= '<tr data-id="' . $u['id'] . '">'
            . '<td class="cell-user">'
            . '<div class="avatar sm">' . escape($initials) . '</div>'
            . '<div><strong>' . escape($u['full_name']) . '</strong><span>@' . escape($u['username']) . '</span></div>'
            . '</td>'
            . '<td>' . escape($positionLabel) . '</td>'
            . '<td>' . $roleBadge . '</td>'
            . $moduleCell
            . '<td><div class="row-actions">'
            . '<button class="icon-btn view-user-btn" data-id="' . $u['id'] . '" aria-label="View">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
            . '</button>'
            . '<button class="icon-btn edit-user-btn" data-id="' . $u['id'] . '" aria-label="Edit">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>'
            . '</button>'
            . '<button class="icon-btn reset-pw-btn" data-id="' . $u['id'] . '" aria-label="Reset password">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>'
            . '</button>'
            . ($canDelete ? '<button class="icon-btn" data-action="delete" data-id="' . $u['id'] . '" aria-label="Delete" style="color:var(--danger);">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
            . '</button>' : '')
            . '</div></td></tr>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management Â· PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/sidebar.css?v=20260803b">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/tables.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
<style>
.user-detail-head{display:flex;align-items:center;gap:16px;margin-bottom:4px;}
.user-detail-photo{width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0;background:linear-gradient(135deg,var(--color-primary-400),var(--color-primary-700));color:#fff;font-size:24px;font-weight:700;font-family:var(--font-display);display:flex;align-items:center;justify-content:center;}
.assigned-modules{margin-top:4px;}
.tl-modal-hero{display:flex;align-items:center;gap:14px;padding:14px 16px;margin:-20px -24px 18px;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 100%);border-bottom:1px solid var(--gray-100);}
.tl-modal-icon{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,var(--color-primary-500),var(--color-primary-700));color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 14px rgba(37,99,235,.28);}
.tl-modal-hero h4{margin:0;font-size:14px;font-weight:700;color:var(--gray-800);}
.tl-modal-hero p{margin:2px 0 0;font-size:12px;color:var(--gray-500);}
.tl-team-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.tl-team-card{border:1.5px solid var(--gray-200);border-radius:var(--radius-lg);padding:14px;text-align:center;cursor:pointer;transition:all .15s ease;background:#fff;}
.tl-team-card:hover{border-color:var(--color-primary-300);background:#f8faff;}
.tl-team-card.selected{border-color:var(--color-primary-500);background:var(--color-primary-50,#eff6ff);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.tl-team-card .tl-team-dot{width:34px;height:34px;border-radius:10px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;background:linear-gradient(135deg,var(--color-primary-400),var(--color-primary-600));}
.tl-team-card.t2 .tl-team-dot{background:linear-gradient(135deg,var(--color-primary-500),var(--color-primary-700));}
.tl-team-card strong{display:block;font-size:13px;color:var(--gray-800);}
.tl-team-card small{font-size:11px;color:var(--gray-500);}
.tl-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;}
</style>
</head>
<body data-page="admin-users">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('users'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('User Management'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">User Management</span></div>

        <div class="page-head">
          <div>
            <h1>User Management</h1>
            <p class="subtitle"><span id="userCount"><?php echo count($users); ?></span> registered accounts Â· control module access instead of fixed roles.</p>
          </div>
          <div class="flex gap-sm" style="align-items:flex-end;">
            <button class="btn btn-primary" id="createUserBtn">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
              Create User
            </button>
            <button class="btn btn-primary" id="createTeamLeaderBtn">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Register Team Leader
            </button>
          </div>
        </div>

        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" id="usersSearch" placeholder="Search usersâ€¦">
            </div>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head">
            <h3>Administrators</h3>
            <span class="badge badge-neutral"><?php echo count($admins); ?></span>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>User</th><th>Position</th><th>Role</th><th>Action</th></tr>
              </thead>
              <tbody id="adminsTableBody" class="users-tbody">
                <?php echo renderUserRows($admins, $allPerms, false, count($admins) > 1); ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head">
            <h3>OBO Users</h3>
            <span class="badge badge-neutral"><?php echo count($oboUsers); ?></span>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>User</th><th>Position</th><th>Role</th><th>Assigned Modules</th><th>Action</th></tr>
              </thead>
              <tbody id="oboTableBody" class="users-tbody">
                <?php echo renderUserRows($oboUsers, $allPerms); ?>
              </tbody>
            </table>
          </div>
<div class="table-footer">
            <span class="text-xs text-muted">Showing all registered accounts</span>
          </div>
        </div>

        <div class="section-card">
          <div class="section-head">
            <h3>Team Leaders</h3>
            <span class="badge badge-neutral" id="teamLeaderCount">0</span>
          </div>
          <div class="scroll-x">
            <table class="data-table">
              <thead>
                <tr><th>Name</th><th>Position</th><th>Team</th><th>Status</th><th>Action</th></tr>
              </thead>
              <tbody id="teamLeaderTableBody"></tbody>
            </table>
          </div>
          <div class="table-footer">
            <span class="text-xs text-muted">Team leaders appear in the Inspection Checklist as INSPECTED BY members.</span>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal-wrap" id="modalRoot">
    <div class="backdrop" data-close-modal></div>
    <div class="modal-box" id="modalBox"></div>
  </div>

  <div class="loading-overlay" id="pageLoader">
    <div class="spinner"></div>
  </div>

  <script>
  const MODULES_LIST = <?php echo json_encode(array_map(fn($k, $v) => ['key' => $k, 'label' => $v], array_keys(MODULES), MODULES)); ?>;
  const USERS_DATA = <?php echo json_encode(array_map(function($u) use ($allPerms) {
    return ['id' => (int)$u['id'], 'full_name' => $u['full_name'], 'username' => $u['username'], 'email' => $u['email'], 'profile_photo' => $u['profile_photo'], 'status' => $u['status'], 'is_admin' => (int)$u['is_admin'], 'role' => $u['role'] ?? 'inspector', 'position' => $u['position'] ?? '', 'modules' => $allPerms[$u['id']] ?? []];
  }, $users)); ?>;
  const CSRF_TOKEN = '<?php echo generateCSRFToken(); ?>';
  const CURRENT_USER_ID = <?php echo (int)$_SESSION['user_id']; ?>;
  const FIXED_MODULES = ['dashboard', 'notifications', 'announcements', 'profile', 'settings'];
  const FLASH_MESSAGE = <?php echo json_encode($message); ?>;
  const FLASH_TYPE = <?php echo json_encode($messageType); ?>;
  </script>
  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script>
  function statusBadge(s) {
    return s === 'active' ? '<span class="badge badge-success">Active</span>'
      : '<span class="badge badge-neutral">Inactive</span>';
  }

  function buildModuleSwitchRows(activeKeys) {
    return MODULES_LIST
      .filter(m => !FIXED_MODULES.includes(m.key))
      .map(m => {
      const checked = activeKeys.includes(m.key) ? 'checked' : '';
      return '<div class="module-switch-row">'
        + '<div class="m-info"><strong>' + m.label + '</strong></div>'
        + '<label class="switch">'
        + '<input type="checkbox" name="modules[]" value="' + m.key + '" ' + checked + '>'
        + '<span class="track"></span></label></div>';
    }).join('');
  }

  function refreshRowModules(userId) {
    const row = document.querySelector('#usersTableBody tr[data-id="' + userId + '"]');
    const user = USERS_DATA.find(u => u.id === userId);
    if (!row || !user) return;
    const labels = user.modules.map(k => {
      const m = MODULES_LIST.find(x => x.key === k);
      return m ? m.label : k;
    });
    const cell = row.querySelector('.module-tags');
    if (!cell) return;
    const shown = labels.slice(0, 3).map(l => '<span class="module-tag">' + escapeHtml(l) + '</span>').join('');
    const extra = labels.length > 3 ? '<span class="module-tag">+' + (labels.length - 3) + '</span>' : '';
    cell.innerHTML = shown + extra;
  }

  function openUserModal(user, readOnly) {
    const isNew = !user;
    const title = isNew ? 'Create New User' : readOnly ? 'User Details' : 'Edit User';
    const action = isNew ? 'create' : readOnly ? '' : 'update';
    const userId = user ? user.id : 0;
    const modules = user ? (user.modules || []) : [];
    const statusOpts = ['active', 'inactive'];

    const html = '<div class="modal-head">'
      + '<h3>' + title + '</h3>'
      + '<button class="icon-btn" data-close-modal aria-label="Close">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
      + '</button></div>'
      + '<div class="modal-body">'
      + '<form id="userForm" method="POST" action="user-management.php">'
      + '<input type="hidden" name="_csrf_token" value="' + CSRF_TOKEN + '">'
      + '<input type="hidden" name="action" value="' + action + '">'
      + '<input type="hidden" name="user_id" value="' + userId + '">'
      + (readOnly ? '<div class="user-detail-head">'
        + (user.profile_photo
          ? '<img src="../' + escapeHtml(user.profile_photo) + '" alt="Profile photo" class="user-detail-photo" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">'
            + '<div class="user-detail-photo avatar" style="display:none;">' + initialsOf(user.full_name) + '</div>'
          : '<div class="user-detail-photo avatar">' + initialsOf(user.full_name) + '</div>')
        + '<div><h3 style="margin:0;">' + escapeHtml(user.full_name) + '</h3>'
        + '<span class="text-xs text-muted">@' + escapeHtml(user.username) + '</span>'
        + '<div style="margin-top:6px;">' + (user.status === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-neutral">Inactive</span>') + '</div>'
        + '</div></div>'
        + '<hr class="divider">' : '')
      + '<div class="form-grid">'
      + '<div class="form-group full">'
      + '<label>Full Name</label>'
      + '<input class="form-control" name="full_name" value="' + (user ? escapeHtml(user.full_name) : '') + '" ' + (readOnly ? 'disabled' : '') + ' required>'
      + '</div>'
      + '<div class="form-group">'
      + '<label>Username</label>'
      + '<input class="form-control" name="username" value="' + (user ? escapeHtml(user.username) : '') + '" ' + (readOnly || !isNew ? 'disabled' : '') + ' required>'
      + '</div>'
      + '<div class="form-group">'
      + '<label>Email</label>'
      + '<input class="form-control" name="email" value="' + (user ? escapeHtml(user.email || '') : '') + '" ' + (readOnly ? 'disabled' : '') + '>'
      + '</div>'
      + (isNew ? '<div class="form-group full">'
        + '<label>Default Password</label>'
        + '<input type="hidden" name="password" value="12345">'
        + '<input class="form-control" value="12345" disabled>'
        + '<p class="text-xs text-muted" style="margin-top:4px;">Default password is <strong>12345</strong>. The user can change it from their own profile.</p>'
        + '</div>' : '')
      + (isNew ? '<div class="form-group">'
        + '<label>Role</label>'
        + '<select class="form-control" name="role" id="userRoleSelect">'
        + '<option value="inspector" selected>Inspector</option>'
        + '<option value="admin_aid">Admin Aid</option>'
        + '<option value="admin">Admin</option>'
        + '<option value="developer">Developer</option>'
        + '</select></div>'
        + '<div class="form-group">'
        + '<label>Position</label>'
        + '<select class="form-control" name="position" id="userPositionSelect">'
        + '<option value="" selected>Select position</option>'
        + '<option value="admin_aid_ii">Admin Aid II</option>'
        + '<option value="pdo_ii">Project Development Officer II</option>'
        + '<option value="head_admin">Head Administrator</option>'
        + '<option value="engineer_i">Engineer I</option>'
        + '<option value="architect_i">Architect I</option>'
        + '<option value="evaluator">Evaluator</option>'
        + '</select></div>' : '')
      + (!isNew ? '<div class="form-group">'
        + '<label>Role</label>'
        + '<select class="form-control" name="role" id="userRoleSelect">'
        + '<option value="inspector"' + (user && user.role === 'inspector' ? ' selected' : '') + '>Inspector</option>'
        + '<option value="admin_aid"' + (user && user.role === 'admin_aid' ? ' selected' : '') + '>Admin Aid</option>'
        + '<option value="admin"' + (user && user.role === 'admin' ? ' selected' : '') + '>Admin</option>'
        + '<option value="developer"' + (user && user.role === 'developer' ? ' selected' : '') + '>Developer</option>'
        + '</select></div>'
        + '<div class="form-group">'
        + '<label>Position</label>'
        + '<select class="form-control" name="position" id="userPositionSelect">'
        + '<option value="">Select position</option>'
        + '<option value="admin_aid_ii"' + (user && user.position === 'admin_aid_ii' ? ' selected' : '') + '>Admin Aid II</option>'
        + '<option value="pdo_ii"' + (user && user.position === 'pdo_ii' ? ' selected' : '') + '>Project Development Officer II</option>'
        + '<option value="head_admin"' + (user && user.position === 'head_admin' ? ' selected' : '') + '>Head Administrator</option>'
        + '<option value="engineer_i"' + (user && user.position === 'engineer_i' ? ' selected' : '') + '>Engineer I</option>'
        + '<option value="architect_i"' + (user && user.position === 'architect_i' ? ' selected' : '') + '>Architect I</option>'
        + '<option value="evaluator"' + (user && user.position === 'evaluator' ? ' selected' : '') + '>Evaluator</option>'
        + '</select></div>' : '')
      + (!isNew && !readOnly ? '<div class="form-group">'
        + '<label>Status</label>'
        + '<select class="form-control" name="status">'
        + statusOpts.map(s => '<option value="' + s + '" ' + (user && user.status === s ? 'selected' : '') + '>' + s.charAt(0).toUpperCase() + s.slice(1) + '</option>').join('')
        + '</select></div>' : '')
      + '</div>'
      + (!readOnly ? '<div id="moduleAccessSection"><hr class="divider"><label style="font-size:12.5px;font-weight:700;color:var(--gray-700);">Module Access</label>'
        + '<p class="text-xs text-muted" style="margin:4px 0 8px;">Toggle ON to show a module in this user\'s sidebar. Changes are saved instantly.</p>'
        + '<p class="text-xs text-muted" style="margin:0 0 8px;">Dashboard, Notifications, Announcements, Profile, and Settings are always enabled.</p>'
        + '<div class="module-switch-list" id="modalModuleList">' + buildModuleSwitchRows(modules) + '</div></div>' : '')
      + (readOnly ? '<div class="assigned-modules"><hr class="divider"><label style="font-size:12.5px;font-weight:700;color:var(--gray-700);">Assigned Modules</label>'
        + '<div class="module-tags" style="margin-top:8px;">'
        + (modules.length ? modules.map(k => {
            const m = MODULES_LIST.find(x => x.key === k);
            return '<span class="module-tag">' + escapeHtml(m ? m.label : k) + '</span>';
          }).join('') : '<span class="text-xs text-muted">No modules assigned</span>')
        + '</div></div>' : '')
      + '</form></div>'
      + '<div class="modal-foot">'
      + '<button class="btn btn-secondary" data-close-modal>' + (readOnly ? 'Close' : 'Cancel') + '</button>'
      + (readOnly ? '' : '<button class="btn btn-primary" id="modalSaveBtn">' + (isNew ? 'Create User' : 'Save Changes') + '</button>')
      + '</div>';

    const root = document.getElementById('modalRoot');
    document.getElementById('modalBox').innerHTML = html;
    root.classList.add('open');

    root.querySelectorAll('[data-close-modal]').forEach(el => {
      el.addEventListener('click', () => root.classList.remove('open'));
    });

    const moduleList = document.getElementById('modalModuleList');
    const roleSelect = document.getElementById('userRoleSelect');
    const moduleSection = document.getElementById('moduleAccessSection');
    if (roleSelect && moduleSection) {
      const applyRole = () => {
        moduleSection.style.display = (roleSelect.value === 'admin' || roleSelect.value === 'developer') ? 'none' : '';
      };
      roleSelect.addEventListener('change', applyRole);
      applyRole();
    }
    if (moduleList && !isNew && !readOnly) {
      moduleList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', async function() {
          const moduleKey = this.value;
          const prevChecked = !this.checked;
          const label = (MODULES_LIST.find(m => m.key === moduleKey) || {}).label || moduleKey;
          this.disabled = true;
          const res = await apiPost('users', 'toggle-permission', {
            user_id: userId,
            module_key: moduleKey,
            is_granted: this.checked ? 1 : 0
          });
          this.disabled = false;
          if (res.success) {
            const u = USERS_DATA.find(x => x.id === userId);
            if (u) {
              if (this.checked) { if (!u.modules.includes(moduleKey)) u.modules.push(moduleKey); }
              else { u.modules = u.modules.filter(k => k !== moduleKey); }
            }
            showToast({
              title: this.checked ? 'Access granted' : 'Access revoked',
              message: label + ' is now ' + (this.checked ? 'ON' : 'OFF') + ' for this user.',
              type: 'success'
            });
            refreshRowModules(userId);
          } else {
            this.checked = prevChecked;
            showToast({ title: 'Error', type: 'danger', message: res.error || 'Failed to update module access.' });
          }
        });
      });
    }

    const saveBtn = document.getElementById('modalSaveBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', async function() {
        const form = document.getElementById('userForm');
        if (isNew && !form.checkValidity()) {
          form.reportValidity();
          return;
        }
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        const modCheckboxes = document.getElementById('modalModuleList') ? document.getElementById('modalModuleList').querySelectorAll('input[type="checkbox"]') : [];
        data.modules = Array.from(modCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        try {
          if (isNew) {
            const res = await apiPost('users', 'create', data);
            if (res.success) {
              showToast({ title: 'User created', message: 'New account is ready.', type: 'success' });
              document.getElementById('modalRoot').classList.remove('open');
              location.reload();
            } else {
              showToast({ title: 'Error', message: res.error || 'Failed.', type: 'error' });
            }
          } else {
            data.id = userId;
            const res = await apiPost('users', 'update', data);
            if (res.success) {
              showToast({ title: 'Changes saved', message: 'User updated.', type: 'success' });
              document.getElementById('modalRoot').classList.remove('open');
              location.reload();
            } else {
              showToast({ title: 'Error', message: res.error || 'Failed.', type: 'error' });
            }
          }
        } catch (err) {
          showToast({ title: 'Error', message: 'Request failed.', type: 'error' });
        }
      });
    }

    document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const input = this.closest('.input-affix').querySelector('input');
        input.type = input.type === 'password' ? 'text' : 'password';
      });
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function initialsOf(fullName) {
    return escapeHtml(fullName.split(' ').map(n => n[0] || '').join('').slice(0, 2).toUpperCase());
  }

  function rowHtmlFor(u, showModules, canDelete) {
    const modules = u.modules || [];
    let moduleCell = '';
    if (showModules) {
      const labels = modules.map(k => { const m = MODULES_LIST.find(x => x.key === k); return m ? m.label : k; });
      const shown = labels.slice(0, 3).map(l => '<span class="module-tag">' + escapeHtml(l) + '</span>').join('');
      const extra = labels.length > 3 ? '<span class="module-tag">+' + (labels.length - 3) + '</span>' : '';
      moduleCell = '<td><div class="module-tags">' + shown + extra + '</div></td>';
    }
    const positionLabels = {
      'admin_aid_ii': 'Admin Aid II',
      'pdo_ii': 'Project Development Officer II',
      'head_admin': 'Head Administrator',
      'engineer_i': 'Engineer I',
      'architect_i': 'Architect I',
      'evaluator': 'Evaluator'
    };
    const positionLabel = positionLabels[u.position] || '—';
    const role = (u.role || 'inspector').toLowerCase();
    const roleBadge = role === 'developer' ? '<span class="badge" style="background:#7c3aed;color:#fff;">Developer</span>'
      : role === 'admin' ? '<span class="badge badge-info">Admin</span>'
      : role === 'admin_aid' ? '<span class="badge" style="background:#0ea5e9;color:#fff;">Admin Aid</span>'
      : '<span class="badge badge-neutral">Inspector</span>';
    return '<tr data-id="' + u.id + '">'
      + '<td class="cell-user"><div class="avatar sm">' + initialsOf(u.full_name) + '</div><div><strong>' + escapeHtml(u.full_name) + '</strong><span>@' + escapeHtml(u.username) + '</span></div></td>'
      + '<td>' + escapeHtml(positionLabel) + '</td>'
      + '<td>' + roleBadge + '</td>'
      + moduleCell
      + '<td><div class="row-actions">'
      + '<button class="icon-btn view-user-btn" data-id="' + u.id + '" aria-label="View">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
      + '</button>'
      + '<button class="icon-btn edit-user-btn" data-id="' + u.id + '" aria-label="Edit">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>'
      + '</button>'
      + '<button class="icon-btn reset-pw-btn" data-id="' + u.id + '" aria-label="Reset password">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>'
      + '</button>'
      + (canDelete ? '<button class="icon-btn" data-action="delete" data-id="' + u.id + '" aria-label="Delete" style="color:var(--danger);">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
      + '</button>' : '')
      + '</div></td></tr>';
  }

  function bindUserRowActions() {
    // View
    document.querySelectorAll('.view-user-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id);
        const user = USERS_DATA.find(u => u.id === id);
        if (user) openUserModal(user, true);
      });
    });

    // Edit
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id);
        const user = USERS_DATA.find(u => u.id === id);
        if (user) openUserModal(user, false);
      });
    });

    // Reset password
    document.querySelectorAll('.reset-pw-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id);
        const user = USERS_DATA.find(u => u.id === id);
        if (!user) return;
        const html = '<div class="modal-head"><h3>Reset Password</h3>'
          + '<button class="icon-btn" data-close-modal aria-label="Close">'
          + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
          + '</button></div>'
          + '<div class="modal-body">'
          + '<form id="resetPwForm" method="POST" action="user-management.php">'
          + '<input type="hidden" name="_csrf_token" value="' + CSRF_TOKEN + '">'
          + '<input type="hidden" name="action" value="reset_password">'
          + '<input type="hidden" name="user_id" value="' + id + '">'
          + '<p style="margin-bottom:14px;color:var(--gray-600);">Set a new password for <strong>' + escapeHtml(user.full_name) + '</strong>.</p>'
          + '<div class="form-group">'
          + '<label>New Password</label>'
          + '<div class="input-affix">'
          + '<input class="form-control" type="password" name="password" required minlength="6" placeholder="Min 6 characters">'
          + '<button type="button" class="pw-toggle-btn">'
          + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
          + '</button></div></div></form></div>'
          + '<div class="modal-foot">'
          + '<button class="btn btn-secondary" data-close-modal>Cancel</button>'
          + '<button class="btn btn-primary" id="resetPwSaveBtn">Reset Password</button></div>';
        const root = document.getElementById('modalRoot');
        document.getElementById('modalBox').innerHTML = html;
        root.classList.add('open');
        root.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', () => root.classList.remove('open')));
        document.getElementById('resetPwSaveBtn').addEventListener('click', function() {
          const form = document.getElementById('resetPwForm');
          if (form.checkValidity()) form.submit();
          else form.reportValidity();
        });
        document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const input = this.closest('.input-affix').querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
          });
        });
      });
    });

    // Delete
    document.querySelectorAll('[data-action="delete"]').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id);
        if (id === CURRENT_USER_ID) {
          showToast({ title: 'Cannot delete yourself', message: 'You cannot delete your own account.', type: 'error' });
          return;
        }
        const user = USERS_DATA.find(u => u.id === id);
        if (!user) return;
        const html = '<div class="modal-head"><h3>Delete User</h3>'
          + '<button class="icon-btn" data-close-modal aria-label="Close">'
          + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
          + '</button></div>'
          + '<div class="modal-body">'
          + '<div style="display:flex;align-items:center;gap:14px;">'
          + '<div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;color:var(--danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
          + '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
          + '</div>'
          + '<div><strong>Delete ' + escapeHtml(user.full_name) + '?</strong>'
          + '<p class="text-sm text-muted" style="margin:4px 0 0;">This action cannot be undone. All data tied to this account will be removed.</p>'
          + '</div></div></div>'
          + '<div class="modal-foot">'
          + '<button class="btn btn-secondary" data-close-modal>Cancel</button>'
          + '<button class="btn btn-danger" id="deleteConfirmBtn">Delete</button></div>';
        const root = document.getElementById('modalRoot');
        document.getElementById('modalBox').innerHTML = html;
        root.classList.add('open');
        root.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', () => root.classList.remove('open')));
        document.getElementById('deleteConfirmBtn').addEventListener('click', async function() {
          const res = await apiPost('users', 'delete', { id: id });
          if (res.success) {
            showToast({ title: 'User deleted', message: 'Account removed.', type: 'success' });
            document.getElementById('modalRoot').classList.remove('open');
            location.reload();
          } else {
            showToast({ title: 'Error', message: res.error || 'Failed.', type: 'error' });
          }
        });
      });
    });
  }

  function applyUsersSearch() {
    const input = document.getElementById('usersSearch');
    const q = input ? input.value.toLowerCase() : '';
    document.querySelectorAll('.users-tbody tr').forEach(row => {
      row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }

  async function renderUserTables() {
    try {
      const res = await apiGet('users', 'list');
      const users = res.data || [];
      const fresh = users.map(u => ({
        id: parseInt(u.id),
        full_name: u.full_name,
        username: u.username,
        email: u.email || '',
        profile_photo: u.profile_photo || '',
        status: u.is_active == 1 ? 'active' : 'inactive',
        is_admin: u.is_admin ? 1 : 0,
        role: u.role || 'inspector',
        position: u.position || '',
        modules: u.granted_modules ? u.granted_modules.split(',').filter(Boolean) : []
      }));
      USERS_DATA.length = 0;
      USERS_DATA.push.apply(USERS_DATA, fresh);

      const admins = fresh.filter(u => (u.role || '') === 'admin');
      const obo = fresh.filter(u => (u.role || '') !== 'admin');
      const adminsBody = document.getElementById('adminsTableBody');
      const oboBody = document.getElementById('oboTableBody');
      if (adminsBody) adminsBody.innerHTML = admins.map(u => rowHtmlFor(u, false, admins.length > 1)).join('');
      if (oboBody) oboBody.innerHTML = obo.map(u => rowHtmlFor(u, true, true)).join('');

      const countEl = document.getElementById('userCount');
      if (countEl) countEl.textContent = fresh.length;

      applyUsersSearch();
      bindUserRowActions();
    } catch (e) {}
  }

let TEAM_LEADERS = [];

  function tlPickTeam(teamNo, el) {
    const hidden = document.getElementById('tlTeam');
    if (hidden) hidden.value = String(teamNo);
    el.closest('.tl-team-grid').querySelectorAll('.tl-team-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
  }

  function openTeamLeaderModal(leader) {
    const isNew = !leader;
    const title = isNew ? 'Register Team Leader' : 'Edit Team Leader';
    const team = leader ? String(leader.team_no) : '1';
    const html = '<div class="modal-head">'
      + '<h3>' + (isNew ? 'New Team Leader' : 'Edit Team Leader') + '</h3>'
      + '<button class="icon-btn" data-close-modal aria-label="Close">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
      + '</button></div>'
      + '<div class="modal-body">'
      + '<div class="tl-modal-hero">'
      + '<div class="tl-modal-icon">'
      + '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
      + '</div>'
      + '<div><h4>' + (isNew ? 'Add a leader to the inspection team registry' : 'Update this team leader\'s details') + '</h4>'
      + '<p>Team leaders appear as INSPECTED BY members on the checklist report.</p>'
      + '</div></div>'
      + '<div class="form-grid">'
      + '<div class="form-group full">'
      + '<label>Full Name <span class="req">*</span></label>'
      + '<div class="input-affix" style="position:relative;">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gray-400);pointer-events:none;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
      + '<input class="form-control" style="padding-left:36px;" id="tlName" value="' + (leader ? escapeHtml(leader.full_name) : '') + '" placeholder="e.g. Engr. Juan Dela Cruz" required>'
      + '</div></div>'
      + '<div class="form-group full">'
      + '<label>Position</label>'
      + '<div class="input-affix" style="position:relative;">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gray-400);pointer-events:none;"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59A2 2 0 0 0 3.41 11l9.58 9.59a2 2 0 0 0 2.83 0l4.77-4.78a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r=".5"/></svg>'
      + '<input class="form-control" style="padding-left:36px;" id="tlPosition" value="' + (leader ? escapeHtml(leader.position || '') : '') + '" placeholder="e.g. Chief Inspector">'
      + '</div></div>'
      + '</div>'
      + '<div style="margin-top:6px;">'
      + '<label style="display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:8px;">Assigned Team</label>'
      + '<div class="tl-team-grid">'
      + '<div class="tl-team-card' + (team === '1' ? ' selected' : '') + '" data-team="1" onclick="tlPickTeam(1, this)">'
      + '<div class="tl-team-dot">1</div><strong>Team 1</strong><small>Lead inspector</small>'
      + '</div>'
      + '<div class="tl-team-card t2' + (team === '2' ? ' selected' : '') + '" data-team="2" onclick="tlPickTeam(2, this)">'
      + '<div class="tl-team-dot">2</div><strong>Team 2</strong><small>Assistant inspector</small>'
      + '</div>'
      + '</div>'
      + '<input type="hidden" id="tlTeam" value="' + team + '">'
      + '</div></div>'
      + '<div class="modal-foot">'
      + '<button class="btn btn-secondary" data-close-modal>Cancel</button>'
      + '<button class="btn btn-primary" id="tlSaveBtn">' + (isNew ? 'Register Team Leader' : 'Save Changes') + '</button>'
      + '</div>';

    const root = document.getElementById('modalRoot');
    document.getElementById('modalBox').innerHTML = html;
    root.classList.add('open');
    if (leader) {
      document.getElementById('tlTeam').value = String(leader.team_no);
    }
    root.querySelectorAll('[data-close-modal]').forEach(el => {
      el.addEventListener('click', () => root.classList.remove('open'));
    });
    document.getElementById('tlSaveBtn').addEventListener('click', async function() {
      const fullName = document.getElementById('tlName').value.trim();
      if (!fullName) { document.getElementById('tlName').reportValidity(); return; }
      const payload = {
        full_name: fullName,
        position: document.getElementById('tlPosition').value.trim(),
        team_no: document.getElementById('tlTeam').value
      };
      const res = leader
        ? await apiPost('teamleaders', 'update', { ...payload, id: leader.id })
        : await apiPost('teamleaders', 'create', payload);
      if (res.success) {
        showToast({ title: 'Team Leader ' + (leader ? 'updated' : 'registered'), message: res.message, type: 'success' });
        root.classList.remove('open');
        renderTeamLeaders();
      } else {
        showToast({ title: 'Error', message: res.error, type: 'danger' });
      }
    });
  }

  function teamLeaderRowHtml(l) {
    const teamBadge = String(l.team_no) === '2'
      ? '<span class="badge badge-info">Team 2</span>'
      : '<span class="badge badge-success">Team 1</span>';
    const statusBadgeTl = l.is_active == 1
      ? '<span class="badge badge-success">Active</span>'
      : '<span class="badge badge-neutral">Inactive</span>';
    return '<tr data-id="' + l.id + '">'
      + '<td class="cell-user"><div class="avatar sm">' + initialsOf(l.full_name) + '</div><div><strong>' + escapeHtml(l.full_name) + '</strong></div></td>'
      + '<td>' + escapeHtml(l.position || '—') + '</td>'
      + '<td>' + teamBadge + '</td>'
      + '<td>' + statusBadgeTl + '</td>'
      + '<td><div class="row-actions">'
      + '<button class="icon-btn tl-edit-btn" data-id="' + l.id + '" aria-label="Edit">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>'
      + '</button>'
      + '<button class="icon-btn tl-delete-btn" data-id="' + l.id + '" aria-label="Delete" style="color:var(--danger);">'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
      + '</button>'
      + '</div></td></tr>';
  }

  function renderTeamLeaders() {
    const body = document.getElementById('teamLeaderTableBody');
    const count = document.getElementById('teamLeaderCount');
    if (body) {
      body.innerHTML = TEAM_LEADERS.map(teamLeaderRowHtml).join('') || '<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--gray-400);">No team leaders registered yet.</td></tr>';
    }
    if (count) count.textContent = TEAM_LEADERS.length;
    body && body.querySelectorAll('.tl-edit-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id, 10);
        const l = TEAM_LEADERS.find(x => x.id === id);
        if (l) openTeamLeaderModal(l);
      });
    });
    body && body.querySelectorAll('.tl-delete-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = parseInt(this.dataset.id, 10);
        const l = TEAM_LEADERS.find(x => x.id === id);
        if (!l) return;
        const html = '<div class="modal-head"><h3>Delete Team Leader</h3>'
          + '<button class="icon-btn" data-close-modal aria-label="Close">'
          + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>'
          + '</button></div>'
          + '<div class="modal-body">'
          + '<div style="display:flex;align-items:center;gap:14px;">'
          + '<div style="width:44px;height:44px;border-radius:12px;background:#fee2e2;color:var(--danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
          + '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
          + '</div>'
          + '<div><strong>Delete ' + escapeHtml(l.full_name) + '?</strong>'
          + '<p class="text-sm text-muted" style="margin:4px 0 0;">This removes them from the team leader registry. Existing reports keep their name.</p>'
          + '</div></div></div>'
          + '<div class="modal-foot">'
          + '<button class="btn btn-secondary" data-close-modal>Cancel</button>'
          + '<button class="btn btn-danger" id="tlDeleteConfirmBtn">Delete</button></div>';
        const root = document.getElementById('modalRoot');
        document.getElementById('modalBox').innerHTML = html;
        root.classList.add('open');
        root.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', () => root.classList.remove('open')));
        document.getElementById('tlDeleteConfirmBtn').addEventListener('click', async function() {
          const res = await apiPost('teamleaders', 'delete', { id });
          if (res.success) {
            showToast({ title: 'Deleted', message: res.message, type: 'success' });
            root.classList.remove('open');
            renderTeamLeaders();
          } else {
            showToast({ title: 'Error', message: res.error, type: 'danger' });
          }
        });
      });
    });
  }

  async function refreshTeamLeaders() {
    const res = await apiGet('teamleaders', 'list').catch(() => null);
    if (res && res.success) {
      TEAM_LEADERS = res.data.map(l => ({ ...l, team_no: parseInt(l.team_no, 10) }));
      renderTeamLeaders();
    }
  }

  function bindPageActions() {
    const createBtn = document.getElementById('createUserBtn');
    if (createBtn && !createBtn.dataset.umBound) {
      createBtn.dataset.umBound = '1';
      createBtn.addEventListener('click', function() { openUserModal(null, false); });
    }

    const tlCreateBtn = document.getElementById('createTeamLeaderBtn');
    if (tlCreateBtn && !tlCreateBtn.dataset.umBound) {
      tlCreateBtn.dataset.umBound = '1';
      tlCreateBtn.addEventListener('click', function() { openTeamLeaderModal(null); });
    }

    bindUserRowActions();

    const searchInput = document.getElementById('usersSearch');
    if (searchInput && !searchInput.dataset.umBound) {
      searchInput.dataset.umBound = '1';
      searchInput.addEventListener('input', applyUsersSearch);
    }

  }

document.addEventListener('DOMContentLoaded', function() {
    bindPageActions();
    refreshTeamLeaders();

    if (window.PAMS_REALTIME) {
      window.PAMS_REALTIME.register('user-management', renderUserTables, 15000);
      window.PAMS_REALTIME.register('team-leaders', refreshTeamLeaders, 15000);
    }
  });
  </script>
  <script src="../assets/js/validation.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script src="../assets/js/dropdown.js"></script>
  <script src="../assets/js/notification.js"></script>
  <script src="../assets/js/modal.js"></script>
  <script src="../assets/js/search.js"></script>
  <script src="../assets/js/table.js"></script>
  <script src="../assets/js/realtime.js?v=20260803b"></script>
  <script src="../assets/js/app.js?v=20260803d"></script>
  <script>
  if (FLASH_MESSAGE) {
    showToast({
      title: FLASH_TYPE === 'error' ? 'Error' : 'Success',
      message: FLASH_MESSAGE,
      type: FLASH_TYPE === 'error' ? 'danger' : 'success'
    });
  }
  </script>
</body>
</html>

