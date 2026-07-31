<?php
require_once __DIR__ . '/../includes/admin-shell.php';
requireAdmin();

$pdo = getDB();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $isAdmin = (($_POST['role'] ?? 'obo_user') === 'admin') ? 1 : 0;

        if ($fullName && $username && $password) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $message = 'Username already exists.';
                $messageType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (full_name, username, email, password_hash, profile_photo, is_admin) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$fullName, $username, $email, $hash, 'assets/images/OBO LOGO.png', $isAdmin]);
                $userId = $pdo->lastInsertId();

                $modules = $_POST['modules'] ?? [];
                $stmt = $pdo->prepare('INSERT INTO user_permissions (user_id, module_key, is_granted) VALUES (?, ?, 1)');
                foreach ($modules as $moduleKey) {
                    $stmt->execute([$userId, $moduleKey]);
                }

                logActivity($_SESSION['user_id'], 'user_created', "Created user: $username ($fullName)");
                $message = 'User created successfully.';
                $messageType = 'success';
            }
        } else {
            $message = 'Full Name, Username, and Password are required.';
            $messageType = 'error';
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
            $message = 'User updated successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['password'] ?? '';

        if ($userId && $newPassword) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            logActivity($_SESSION['user_id'], 'password_reset', "Password reset for user ID: $userId");
            $message = 'Password reset successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            $message = 'You cannot delete your own account.';
            $messageType = 'error';
        } elseif ($userId) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            logActivity($_SESSION['user_id'], 'user_deleted', "Deleted user ID: $userId");
            $message = 'User deleted successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'active';
        if ($userId === (int)$_SESSION['user_id']) {
            $message = 'You cannot deactivate your own account.';
            $messageType = 'error';
        } elseif ($userId && in_array($newStatus, ['active', 'inactive'])) {
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newStatus === 'active' ? 1 : 0, $userId]);
            logActivity($_SESSION['user_id'], 'user_status_changed', "User ID: $userId set to $newStatus");
            $message = 'User status updated.';
            $messageType = 'success';
        }
    }
}

$users = $pdo->query("SELECT id, full_name, username, email, is_admin, CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END AS status, last_login, created_at FROM users ORDER BY full_name")->fetchAll();
$admins = array_values(array_filter($users, fn($u) => (int)$u['is_admin'] === 1));
$oboUsers = array_values(array_filter($users, fn($u) => (int)$u['is_admin'] !== 1));

$permStmt = $pdo->query('SELECT user_id, module_key FROM user_permissions WHERE is_granted = 1');
$allPerms = [];
while ($row = $permStmt->fetch()) {
    $allPerms[$row['user_id']][] = $row['module_key'];
}

function renderUserRows(array $rows, array $allPerms, bool $showModules = true): string {
    $html = '';
    foreach ($rows as $u) {
        $perms = $allPerms[$u['id']] ?? [];
        $initials = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $u['full_name'])));
        $initials = substr($initials, 0, 2);
        $moduleLabels = array_map(fn($k) => MODULES[$k] ?? $k, $perms);
        $statusBadge = $u['status'] === 'active'
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-neutral">Inactive</span>';
        $moduleCell = '';
        if ($showModules) {
            $moduleTags = implode('', array_map(fn($m) => '<span class="module-tag">' . escape($m) . '</span>', array_slice($moduleLabels, 0, 3)));
            if (count($moduleLabels) > 3) {
                $moduleTags .= '<span class="module-tag">+' . (count($moduleLabels) - 3) . '</span>';
            }
            $moduleCell = '<td><div class="module-tags">' . $moduleTags . '</div></td>';
        }
        $html .= '<tr data-id="' . $u['id'] . '">'
            . '<td class="cell-user">'
            . '<div class="avatar sm">' . escape($initials) . '</div>'
            . '<div><strong>' . escape($u['full_name']) . '</strong><span>@' . escape($u['username']) . '</span></div>'
            . '</td>'
            . '<td>' . $statusBadge . '</td>'
            . $moduleCell
            . '<td>' . ($u['last_login'] ? timeAgo($u['last_login']) : 'Never') . '</td>'
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
            . '<button class="icon-btn" data-action="delete" data-id="' . $u['id'] . '" aria-label="Delete" style="color:var(--danger);">'
            . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>'
            . '</button>'
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
<title>User Management · PAMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/variables.css">
<link rel="stylesheet" href="../assets/css/utilities.css">
<link rel="stylesheet" href="../assets/css/buttons.css">
<link rel="stylesheet" href="../assets/css/layout.css">
<link rel="stylesheet" href="../assets/css/sidebar.css">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/cards.css">
<link rel="stylesheet" href="../assets/css/tables.css">
<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body data-page="admin-users">

  <div class="app-shell" id="appShell">
    <?php echo renderAdminSidebar('users'); ?>

    <div class="main-col">
      <?php echo renderAdminHeader('User Management'); ?>

      <main class="page-content fade-in">
        <div class="breadcrumb"><span>PAMS</span><span class="sep">/</span><span class="current">User Management</span></div>

        <?php if ($message): ?>
        <div class="toast-notice toast-<?php echo $messageType; ?>">
          <span><?php echo escape($message); ?></span>
          <button onclick="this.parentElement.remove()">&times;</button>
        </div>
        <style>.toast-notice{padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:500}.toast-success{background:#e6f7e6;color:#166534;border:1px solid #bbf7bb}.toast-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.toast-notice button{background:none;border:none;font-size:18px;cursor:pointer;color:inherit;padding:0 4px}</style>
        <?php endif; ?>

        <div class="page-head">
          <div>
            <h1>User Management</h1>
            <p class="subtitle"><span id="userCount"><?php echo count($users); ?></span> registered accounts · control module access instead of fixed roles.</p>
          </div>
          <button class="btn btn-primary" id="createUserBtn">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
            Create User
          </button>
        </div>

        <div class="section-card">
          <div class="table-toolbar">
            <div class="search-box">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" id="usersSearch" placeholder="Search users…">
            </div>
            <select id="statusFilter">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
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
                <tr><th>User</th><th>Status</th><th>Last Login</th><th>Action</th></tr>
              </thead>
              <tbody id="adminsTableBody" class="users-tbody">
                <?php echo renderUserRows($admins, $allPerms, false); ?>
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
                <tr><th>User</th><th>Status</th><th>Assigned Modules</th><th>Last Login</th><th>Action</th></tr>
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
    return ['id' => (int)$u['id'], 'full_name' => $u['full_name'], 'username' => $u['username'], 'email' => $u['email'], 'status' => $u['status'], 'is_admin' => (int)$u['is_admin'], 'modules' => $allPerms[$u['id']] ?? []];
  }, $users)); ?>;
  const CSRF_TOKEN = '<?php echo generateCSRFToken(); ?>';
  const CURRENT_USER_ID = <?php echo (int)$_SESSION['user_id']; ?>;
  </script>
  <script src="../assets/js/utilities.js"></script>
  <script src="../assets/js/api.js"></script>
  <script>
  function statusBadge(s) {
    return s === 'active' ? '<span class="badge badge-success">Active</span>'
      : '<span class="badge badge-neutral">Inactive</span>';
  }

  function buildModuleSwitchRows(activeKeys) {
    return MODULES_LIST.map(m => {
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
      + (isNew ? '<div class="form-group">'
        + '<label>Password</label>'
        + '<div class="input-affix">'
        + '<input class="form-control" type="password" name="password" required placeholder="Min 6 characters">'
        + '<button type="button" class="pw-toggle-btn">'
        + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>'
        + '</button></div></div>' : '')
      + (isNew ? '<div class="form-group">'
        + '<label>Role</label>'
        + '<select class="form-control" name="role" id="userRoleSelect">'
        + '<option value="obo_user" selected>OBO User</option>'
        + '<option value="admin">Admin</option>'
        + '</select></div>' : '')
      + (!isNew ? '<div class="form-group">'
        + '<label>Role</label>'
        + '<input class="form-control" value="' + (user.is_admin ? 'Admin' : 'OBO User') + '" disabled>'
        + '</div>' : '')
      + (!isNew && !readOnly ? '<div class="form-group">'
        + '<label>Status</label>'
        + '<select class="form-control" name="status">'
        + statusOpts.map(s => '<option value="' + s + '" ' + (user && user.status === s ? 'selected' : '') + '>' + s.charAt(0).toUpperCase() + s.slice(1) + '</option>').join('')
        + '</select></div>' : '')
      + '</div>'
      + (!readOnly ? '<div id="moduleAccessSection"><hr class="divider"><label style="font-size:12.5px;font-weight:700;color:var(--gray-700);">Module Access</label>'
        + '<p class="text-xs text-muted" style="margin:4px 0 8px;">Toggle ON to show a module in this user\'s sidebar. Changes are saved instantly.</p>'
        + '<div class="module-switch-list" id="modalModuleList">' + buildModuleSwitchRows(modules) + '</div></div>' : '')
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
        moduleSection.style.display = roleSelect.value === 'admin' ? 'none' : '';
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
      saveBtn.addEventListener('click', function() {
        const form = document.getElementById('userForm');
        if (isNew && !form.checkValidity()) {
          form.reportValidity();
          return;
        }
        form.submit();
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

  document.addEventListener('DOMContentLoaded', function() {
    // Create user
    document.getElementById('createUserBtn').addEventListener('click', function() {
      openUserModal(null, false);
    });

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
        if (!confirm('Delete ' + user.full_name + '? This action cannot be undone.')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'user-management.php';
        form.innerHTML = '<input type="hidden" name="_csrf_token" value="' + CSRF_TOKEN + '">'
          + '<input type="hidden" name="action" value="delete">'
          + '<input type="hidden" name="user_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
      });
    });

    // Search
    const searchInput = document.getElementById('usersSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.users-tbody tr').forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      });
    }

    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
      statusFilter.addEventListener('change', function() {
        const val = this.value;
        document.querySelectorAll('.users-tbody tr').forEach(row => {
          if (!val) { row.style.display = ''; return; }
          const statusCell = row.querySelector('td:nth-child(2)');
          row.style.display = statusCell && statusCell.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
      });
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
  <script src="../assets/js/app.js"></script>
</body>
</html>
