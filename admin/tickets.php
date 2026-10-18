<?php
// ============================================================================
//  Admin — Ticket Management (Phase 5)
//  Complete list of feedback, filterable by status, category, department,
//  plus keyword search.
// ============================================================================
require_once __DIR__ . '/../config.php';
require_admin();

$base      = '../';
$pageTitle = 'Ticket Management';
$active    = 'admin';

// ---- Filters (all optional) ----
$status   = (string)($_GET['status'] ?? '');
$category = (string)($_GET['category'] ?? '');
$dept     = (string)($_GET['dept'] ?? '');
$q        = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

// ---- Scope: field admins only see their field's tickets ---------------------
$myField = admin_field($_SESSION['user']);
if ($myField !== null) {
    $where[] = 'f.category = ?';
    $params[] = $myField;
}

if (array_key_exists($status, STATUS_LABELS)) {
    $where[] = 'f.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if (valid_category($category)) {
    $where[] = 'f.category = ?';
    $params[] = $category;
} else {
    $category = '';
}
if (valid_department($dept)) {
    $where[] = 'f.assigned_dept = ?';
    $params[] = $dept;
} else {
    $dept = '';
}
if ($q !== '') {
    $where[] = '(f.ticket_no LIKE ? OR f.title LIKE ? OR u.name LIKE ? OR f.description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$sql = 'SELECT f.*, u.name
        FROM feedbacks f
        JOIN users u ON u.id = f.user_id' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' ORDER BY f.created_at DESC, f.id DESC
        LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$hasFilter = $status !== '' || $category !== '' || $dept !== '' || $q !== '';

require __DIR__ . '/../includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title"><?= $myField !== null ? e($myField) . ' Posts' : 'Post Management' ?></h1>
    <p class="page-sub"><?= count($tickets) ?> ticket(s)<?= $hasFilter ? ' matching your filters' : ' total' ?>.</p>
  </div>
</div>

<!-- Filters -->
<form method="get" action="tickets.php" class="card filter-bar">
  <div class="filter-search">
    <input type="search" name="q" class="input" placeholder="Search ticket no., subject, reporter…" value="<?= e($q) ?>">
  </div>
  <select name="status" class="input">
    <option value="">All statuses</option>
    <?php foreach (STATUS_LABELS as $key => $label): ?>
      <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="category" class="input">
    <option value="">All categories</option>
    <?php foreach (CATEGORIES as $cat): ?>
      <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="dept" class="input">
    <option value="">All offices</option>
    <?php foreach (DEPARTMENTS as $d): ?>
      <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
  <?php if ($hasFilter): ?>
    <a class="btn btn-ghost" href="tickets.php">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<?php if (empty($tickets)): ?>
  <div class="card empty-state">
    <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
    <h3>No tickets found</h3>
    <p>Try adjusting your search or filters.</p>
  </div>
<?php else: ?>
<div class="card panel">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Subject</th>
          <th>Reporter</th>
          <th>Status</th>
          <th>Assigned</th>
          <th>Public</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><span class="ticket-no"><?= e($t['ticket_no']) ?></span></td>
          <td class="cell-title">
            <?= e(truncate($t['title'], 45)) ?>
            <?php if ((int)$t['is_anonymous'] === 1): ?><?= anonymous_badge() ?><?php endif; ?>
          </td>
          <td><?= $t['is_anonymous'] ? '<span class="cell-muted">Anonymous</span>' : e($t['name']) ?></td>
          <td><?= status_badge($t['status']) ?></td>
          <td class="cell-muted"><?= $t['assigned_dept'] ? e($t['assigned_dept']) : '—' ?></td>
          <td><?= (int)$t['is_public'] === 1 ? '<span class="badge badge-public">Public</span>' : '<span class="badge badge-private">Private</span>' ?></td>
          <td class="cell-muted"><?= e(time_ago($t['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="ticket.php?id=<?= (int)$t['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
