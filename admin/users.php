<?php
// ============================================================================
//  SUPER ADMIN — User & Permission Management
//  • Overall access: manage every account on the system
//  • Eliminate bias: assign each field admin the field they control
//  • Permission control: promote/demote, assign field scopes
//  Restricted to role = super_admin.
// ============================================================================
require_once __DIR__ . '/../config.php';
require_super_admin();

$base      = '../';
$pageTitle = 'User & Permission Management';
$active    = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('users.php');
    }

    $action = (string)($_POST['action'] ?? '');

    switch ($action) {
        case 'update_role':
            $userId   = (int)($_POST['user_id'] ?? 0);
            $newRole  = (string)($_POST['role'] ?? '');
            $newScope = (string)($_POST['field_scope'] ?? '');

            // Guard: the last super admin cannot demote themselves.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND id <> ?");
            $stmt->execute([$userId]);
            $otherSuperAdmins = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT role, name FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $target = $stmt->fetch();

            if ($target === false) {
                flash_set('error', 'User not found.');
            } elseif ($target['role'] === 'super_admin' && $otherSuperAdmins === 0 && $newRole !== 'super_admin') {
                flash_set('error', 'You are the only Super Admin — you cannot demote yourself. Promote another Super Admin first.');
            } elseif (!in_array($newRole, ROLES, true)) {
                flash_set('error', 'Invalid role selected.');
            } elseif ($newRole === 'admin' && $newScope !== '' && !array_key_exists($newScope, FIELD_SCOPES)) {
                flash_set('error', 'Invalid field selected.');
            } else {
                $upd = $pdo->prepare('UPDATE users SET role = ?, field_scope = ? WHERE id = ?');
                $upd->execute([$newRole, $newRole === 'admin' && $newScope !== '' ? $newScope : null, $userId]);
                flash_set('success', $target['name'] . ' is now a ' . role_label($newRole) .
                    ($newRole === 'admin' && $newScope !== '' ? ' for the "' . scope_category($newScope) . '" field' : '') . '.');
            }
            redirect('users.php');

        case 'create':
            $old = [
                'id_number'  => trim((string)($_POST['id_number'] ?? '')),
                'name'       => trim((string)($_POST['name'] ?? '')),
                'email'      => trim((string)($_POST['email'] ?? '')),
                'department' => trim((string)($_POST['department'] ?? '')),
                'role'       => (string)($_POST['role'] ?? 'student'),
                'field_scope'=> (string)($_POST['field_scope'] ?? ''),
            ];
            $password = (string)($_POST['password'] ?? '');

            $errors = [];
            if ($old['id_number'] === '' || strlen($old['id_number']) > 30)   $errors[] = 'ID number is required (max 30 chars).';
            if (strlen($old['name']) < 3 || strlen($old['name']) > 120)       $errors[] = 'Full name must be 3–120 characters.';
            if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))           $errors[] = 'A valid email address is required.';
            if (strlen($old['department']) < 2 || strlen($old['department']) > 100) $errors[] = 'Department / office is required (2–100 chars).';
            if (!in_array($old['role'], ROLES, true))                         $errors[] = 'Invalid role selected.';
            if ($old['role'] === 'admin' && !array_key_exists($old['field_scope'], FIELD_SCOPES)) $errors[] = 'A field admin needs a field scope.';
            if (strlen($password) < 8)                                       $errors[] = 'Password must be at least 8 characters.';

            if (!$errors) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id_number = ? OR email = ? LIMIT 1');
                $stmt->execute([$old['id_number'], $old['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'That ID number or email is already registered.';
                }
            }
            if (!$errors) {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (id_number, name, email, password, department, role, field_scope)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $old['id_number'], $old['name'], $old['email'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $old['department'], $old['role'],
                    $old['role'] === 'admin' ? $old['field_scope'] : null,
                ]);
                flash_set('success', $old['name'] . ' created as ' . role_label($old['role']) .
                    ($old['role'] === 'admin' ? ' — field: ' . scope_category($old['field_scope']) : '') . '.');
            } else {
                flash_set('error', implode(' ', $errors));
            }
            redirect('users.php');

        case 'delete':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)$_SESSION['user']['id']) {
                flash_set('error', 'You cannot delete your own account.');
            } else {
                $stmt = $pdo->prepare('SELECT name, role FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $target = $stmt->fetch();
                if ($target === false) {
                    flash_set('error', 'User not found.');
                } elseif ($target['role'] === 'super_admin') {
                    flash_set('error', 'Super Admin accounts cannot be deleted. Demote the user first.');
                } else {
                    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
                    $del->execute([$userId]);
                    flash_set('success', $target['name'] . ' and all their posts were deleted.');
                }
            }
            redirect('users.php');
    }
    redirect('users.php');
}

// ---- User list --------------------------------------------------------------
$users = $pdo->query(
    'SELECT id, id_number, name, email, department, role, field_scope, created_at
     FROM users ORDER BY
       CASE role WHEN "super_admin" THEN 0 WHEN "admin" THEN 1 ELSE 2 END,
       name ASC'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">User &amp; Permission Management</h1>
    <p class="page-sub">Super Admin only — overall access, permission control, and field assignments (bias-free).</p>
  </div>
  <a class="btn btn-primary btn-lg" href="#addUser">+ Add user</a>
</div>

<div class="card panel">
  <h2 class="panel-title">All accounts (<?= count($users) ?>)</h2>
  <p class="panel-sub">Promote, demote, assign fields, or remove accounts. Field Admins can only act inside their assigned field.</p>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>User</th>
          <th>ID number</th>
          <th>Role</th>
          <th>Field</th>
          <th>Department</th>
          <th>Change permissions</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="cell-title"><?= e($u['name']) ?></div>
            <div class="cell-muted"><?= e($u['email']) ?></div>
          </td>
          <td class="cell-muted"><?= e($u['id_number']) ?></td>
          <td>
            <span class="badge <?= in_array($u['role'], ['admin', 'super_admin'], true) ? 'badge-admin' : 'badge-neutral' ?>">
              <?= e(role_label($u['role'])) ?>
            </span>
          </td>
          <td><?= $u['field_scope'] ? e(scope_category($u['field_scope'])) : '<span class="cell-muted">—</span>' ?></td>
          <td class="cell-muted"><?= e($u['department']) ?></td>
          <td>
            <form method="post" action="users.php" class="user-role-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <select name="role" class="input input-sm">
                <?php foreach (ROLES as $r): ?>
                  <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="field_scope" class="input input-sm">
                <option value="">— No field —</option>
                <?php foreach (FIELD_SCOPES as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= $u['field_scope'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </form>
          </td>
          <td>
            <?php if ($u['role'] !== 'super_admin' && (int)$u['id'] !== (int)$_SESSION['user']['id']): ?>
            <form method="post" action="users.php" data-confirm="Delete <?= e($u['name']) ?> and all their posts? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" aria-label="Delete <?= e($u['name']) ?>">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card panel" id="addUser">
  <h2 class="panel-title">Add a new user</h2>
  <p class="panel-sub">Create staff / field admins directly — they skip registration.</p>
  <form method="post" action="users.php" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label for="nu_id">ID Number</label>
        <input type="text" id="nu_id" name="id_number" class="input" maxlength="30" placeholder="e.g. STA-0021" required>
      </div>
      <div class="form-group">
        <label for="nu_name">Full Name</label>
        <input type="text" id="nu_name" name="name" class="input" maxlength="120" placeholder="e.g. Jose Ramos" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="nu_email">School Email</label>
        <input type="email" id="nu_email" name="email" class="input" placeholder="e.g. jose.ramos@oct.edu.ph" required>
      </div>
      <div class="form-group">
        <label for="nu_dept">Department / Office</label>
        <input type="text" id="nu_dept" name="department" class="input" maxlength="100" placeholder="e.g. Maintenance Department" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="nu_role">Role</label>
        <select id="nu_role" name="role" class="input">
          <?php foreach (ROLES as $r): ?>
            <option value="<?= e($r) ?>"><?= e(role_label($r)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="nu_scope">Field (required for Field Admin)</label>
        <select id="nu_scope" name="field_scope" class="input">
          <option value="">— No field —</option>
          <?php foreach (FIELD_SCOPES as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="nu_pass">Temporary Password</label>
        <input type="text" id="nu_pass" name="password" class="input" minlength="8" placeholder="min. 8 characters" required>
      </div>
    </div>
    <div class="form-actions form-actions-left">
      <button type="submit" class="btn btn-primary">Create user</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
