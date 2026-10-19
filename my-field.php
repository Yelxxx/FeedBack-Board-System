<?php
// ============================================================================
//  My Field — field-filtered concerns
//  Shows ONLY posts relevant to the logged-in user's field (matched from
//  their department via FIELD_SCOPES). Staff and students see the posts about
//  their field; admins see their field's moderation queue here too.
//  Rejected posts are never shown; private posts are only visible to the
//  reporter and to admins.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

$base      = '';
$pageTitle = 'My Field Concerns';
$active    = 'field';
$user      = current_user();

if (is_super_admin()) {
    flash_set('info', 'Super Admin oversees all fields from the Ticket Management panel.');
    redirect('admin/tickets.php');
}

$isStaffLevel    = is_admin();
$field           = is_admin() ? admin_field($user) : user_field($user);

// Filters ---------------------------------------------------------------
$status   = (string)($_GET['status'] ?? '');
$category = (string)($_GET['category'] ?? '');
$q        = trim((string)($_GET['q'] ?? ''));

$where  = ['f.status <> ?'];
$params = ['rejected'];

// The core rule: this page is FIELD-SCOPED for everyone.
if ($field !== null) {
    $where[]  = 'f.category = ?';
    $params[] = $field;
} elseif ($category !== '' && valid_category($category)) {
    // Users without a typed field can still narrow by category, but this is
    // treated as a *general posts* browse rather than a field filter.
    $where[]  = 'f.category = ?';
    $params[] = $category;
}

// Visibility: end users only see public posts (or their own);
// admins/super see all non-rejected posts in their field.
if (!$isStaffLevel) {
    $where[]  = '(f.is_public = 1 OR f.user_id = ?)';
    $params[] = (int)$user['id'];
}

if (array_key_exists($status, STATUS_LABELS) && $status !== 'rejected') {
    $where[]  = 'f.status = ?';
    $params[] = $status;
} elseif ($status === 'rejected') {
    $status = ''; // filtered out above
}
if ($q !== '') {
    $where[] = '(f.title LIKE ? OR f.description LIKE ? OR f.ticket_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT f.*, u.name
        FROM feedbacks f
        JOIN users u ON u.id = f.user_id
        WHERE ' . implode(' AND ', $where) .
       ' ORDER BY f.created_at DESC, f.id DESC
         LIMIT 60';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title"><?= $field !== null ? e($field) : 'All Fields' ?></h1>
    <p class="page-sub">
      <?php if ($field !== null): ?>
        Filtered to your field — you see only posts related to <strong><?= e($field) ?></strong>.
        Other fields appear on the <a href="dashboard.php#general">General Posts</a> board.
      <?php else: ?>
        No matching field found from your department, so all non-rejected posts are shown.
      <?php endif; ?>
    </p>
  </div>
  <a class="btn btn-primary btn-lg" href="submit.php">+ Submit Feedback</a>
</div>

<form method="get" action="my-field.php" class="card filter-bar">
  <div class="filter-search">
    <input type="search" name="q" class="input" placeholder="Search posts in your field…" value="<?= e($q) ?>">
  </div>
  <select name="status" class="input">
    <option value="">All statuses</option>
    <?php foreach (STATUS_LABELS as $key => $label): ?>
      <?php if ($key === 'rejected') continue; ?>
      <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
  <?php if ($q !== '' || $status !== ''): ?>
    <a class="btn btn-ghost" href="my-field.php">Clear</a>
  <?php endif; ?>
</form>

<?php if (empty($posts)): ?>
  <div class="card empty-state">
    <div class="empty-emoji">
      <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>
    <h3>No posts in this field yet</h3>
    <p>When someone reports a concern related to <?= $field !== null ? '<strong>' . e($field) . '</strong>' : 'your field' ?>, it will appear here.</p>
    <a class="btn btn-primary" href="submit.php">Be the first to report</a>
  </div>
<?php else: ?>
<div class="ticket-list">
  <?php foreach ($posts as $t): ?>
  <a class="card ticket-row" href="<?= $isStaffLevel ? 'admin/ticket.php?id=' : 'ticket.php?id=' ?><?= (int)$t['id'] ?>">
    <span class="ticket-no"><?= e($t['ticket_no']) ?></span>
    <div class="ticket-main">
      <div class="ticket-title-line">
        <span class="ticket-title"><?= e($t['title']) ?></span>
        <?= status_badge($t['status']) ?>
        <?php if (in_array($t['status'], ADMIN_ONLY_STATUSES, true)): ?>
          <span class="badge badge-danger">Moderated</span><?php endif; ?>
      </div>
      <div class="ticket-meta">
        <?= category_badge($t['category']) ?>
        <span class="ticket-date">
          <?= $t['is_anonymous'] ? 'Anonymous' : e($t['name']) ?> · <?= e(time_ago($t['created_at'])) ?>
        </span>
      </div>
    </div>
    <span class="ticket-chevron">›</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
