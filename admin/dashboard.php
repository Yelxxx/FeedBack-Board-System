<?php
// ============================================================================
//  Admin Dashboard (Phase 5)
//  Category analytics graph + department tally + recent tickets.
// ============================================================================
require_once __DIR__ . '/../config.php';
require_admin();

$base      = '../';
$pageTitle = 'Admin Dashboard';
$active    = 'admin';

// ---- Scope: field admins see ONLY their own field; super admin sees everything
$myField = admin_field($_SESSION['user']);
$scopeTitle = $myField !== null
    ? 'Overview of "' . $myField . '" field feedback.'
    : 'Overview of all feedback across the campus.';

// ---- Stats ----
$scopeStmt = $pdo->prepare('SELECT COUNT(*) FROM feedbacks' . ($myField !== null ? ' WHERE category = ?' : ''));
if ($myField !== null) { $scopeStmt->execute([$myField]); }

$stats = [
    'total'         => $myField !== null ? (int)$scopeStmt->fetchColumn() : (int)$pdo->query('SELECT COUNT(*) FROM feedbacks')->fetchColumn(),
];
$statQueries = [
    'pending'       => "SELECT COUNT(*) FROM feedbacks WHERE status = 'pending'" . ($myField !== null ? ' AND category = ?' : ''),
    'under_review'  => "SELECT COUNT(*) FROM feedbacks WHERE status = 'under_review'" . ($myField !== null ? ' AND category = ?' : ''),
    'resolved'      => "SELECT COUNT(*) FROM feedbacks WHERE status = 'resolved'" . ($myField !== null ? ' AND category = ?' : ''),
    'anonymous'     => 'SELECT COUNT(*) FROM feedbacks WHERE is_anonymous = 1' . ($myField !== null ? ' AND category = ?' : ''),
];
foreach ($statQueries as $key => $sql) {
    $st = $pdo->prepare($sql);
    if ($myField !== null) { $st->execute([$myField]); } else { $st->execute(); }
    $stats[$key] = (int)$st->fetchColumn();
}
$stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin', 'super_admin')")->fetchColumn();

// ---- Charts: complaints by category (scoped) ----
$st = $pdo->prepare(
    'SELECT category, COUNT(*) AS c FROM feedbacks' . ($myField !== null ? ' WHERE category = ?' : '') . ' GROUP BY category ORDER BY c DESC'
);
if ($myField !== null) { $st->execute([$myField]); } else { $st->execute(); }
$byCategory = $st->fetchAll();

// ---- Charts: complaints by reporter department (scoped) ----
$st = $pdo->prepare(
    'SELECT u.department AS dept, COUNT(*) AS c
     FROM feedbacks f
     JOIN users u ON u.id = f.user_id' .
     ($myField !== null ? ' WHERE f.category = ?' : '') .
    ' GROUP BY u.department
     ORDER BY c DESC
     LIMIT 6'
);
if ($myField !== null) { $st->execute([$myField]); } else { $st->execute(); }
$byDept = $st->fetchAll();

// ---- Charts: workload by assigned office (scoped) ----
$st = $pdo->prepare(
    'SELECT assigned_dept AS dept, COUNT(*) AS c
     FROM feedbacks
     WHERE assigned_dept IS NOT NULL' .
     ($myField !== null ? ' AND category = ?' : '') .
    ' GROUP BY assigned_dept
     ORDER BY c DESC
     LIMIT 6'
);
if ($myField !== null) { $st->execute([$myField]); } else { $st->execute(); }
$byAssigned = $st->fetchAll();

$categoryCounts = array_column($byCategory, 'c');
$maxCategory = $categoryCounts ? max($categoryCounts) : 1;

$deptCounts = array_column($byDept, 'c');
$maxDept     = $deptCounts ? max($deptCounts) : 1;

$assignedCounts = array_column($byAssigned, 'c');
$maxAssigned = $assignedCounts ? max($assignedCounts) : 1;

// ---- Recent tickets (scoped) ----
$st = $pdo->prepare(
    "SELECT f.*, u.name
     FROM feedbacks f
     JOIN users u ON u.id = f.user_id" .
     ($myField !== null ? ' WHERE f.category = ?' : '') .
    " ORDER BY f.created_at DESC, f.id DESC
     LIMIT 7"
);
if ($myField !== null) { $st->execute([$myField]); } else { $st->execute(); }
$recent = $st->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title"><?= $myField !== null ? e($myField) . ' Admin' : 'Super Admin Dashboard' ?></h1>
    <p class="page-sub"><?= e($scopeTitle) ?></p>
  </div>
  <a class="btn btn-primary btn-lg" href="tickets.php">Manage posts</a>
</div>

<div class="stat-grid">
  <div class="card stat-card">
    <span class="stat-icon stat-icon-total"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg></span>
    <div><div class="stat-number"><?= $stats['total'] ?></div><div class="stat-label">Total feedback</div></div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-pending"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
    <div><div class="stat-number"><?= $stats['pending'] ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-review"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
    <div><div class="stat-number"><?= $stats['under_review'] ?></div><div class="stat-label">Under review</div></div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-resolved"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg></span>
    <div><div class="stat-number"><?= $stats['resolved'] ?></div><div class="stat-label">Resolved</div></div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-anon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/></svg></span>
    <div><div class="stat-number"><?= $stats['anonymous'] ?></div><div class="stat-label">Anonymous reports</div></div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-users"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
    <div><div class="stat-number"><?= $stats['users'] ?></div><div class="stat-label">Registered users</div></div>
  </div>
</div>

<div class="chart-grid">
  <div class="card panel">
    <h2 class="panel-title">Complaints by category</h2>
    <p class="panel-sub">Which category receives the highest volume of reports.</p>
    <div class="chart">
      <?php foreach ($byCategory as $row): ?>
      <div class="chart-row">
        <span class="chart-label" title="<?= e($row['category']) ?>"><?= e(truncate($row['category'], 20)) ?></span>
        <div class="chart-track">
          <div class="chart-fill" data-w="<?= (int)round($row['c'] / $maxCategory * 100) ?>"></div>
        </div>
        <span class="chart-count"><?= (int)$row['c'] ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byCategory)): ?><p class="panel-empty">No data yet.</p><?php endif; ?>
    </div>
  </div>

  <div class="card panel">
    <h2 class="panel-title">Complaints by department</h2>
    <p class="panel-sub">Which course / department reports the most issues.</p>
    <div class="chart">
      <?php foreach ($byDept as $row): ?>
      <div class="chart-row">
        <span class="chart-label" title="<?= e($row['dept']) ?>"><?= e(truncate($row['dept'], 18)) ?></span>
        <div class="chart-track chart-track-alt">
          <div class="chart-fill chart-fill-alt" data-w="<?= (int)round($row['c'] / $maxDept * 100) ?>"></div>
        </div>
        <span class="chart-count"><?= (int)$row['c'] ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byDept)): ?><p class="panel-empty">No data yet.</p><?php endif; ?>
    </div>
  </div>

  <div class="card panel">
    <h2 class="panel-title">Workload by assigned office</h2>
    <p class="panel-sub">How tickets are distributed across the offices.</p>
    <div class="chart">
      <?php foreach ($byAssigned as $row): ?>
      <div class="chart-row">
        <span class="chart-label" title="<?= e($row['dept']) ?>"><?= e(truncate($row['dept'], 18)) ?></span>
        <div class="chart-track chart-track-teal">
          <div class="chart-fill chart-fill-teal" data-w="<?= (int)round($row['c'] / $maxAssigned * 100) ?>"></div>
        </div>
        <span class="chart-count"><?= (int)$row['c'] ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($byAssigned)): ?><p class="panel-empty">Nothing assigned yet.</p><?php endif; ?>
    </div>
  </div>
</div>

<div class="card panel">
  <h2 class="panel-title">Recent feedback</h2>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Subject</th>
          <th>Reporter</th>
          <th>Status</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><span class="ticket-no"><?= e($r['ticket_no']) ?></span></td>
          <td class="cell-title"><?= e(truncate($r['title'], 40)) ?></td>
          <td><?= $r['is_anonymous'] ? '<span class="cell-muted">Anonymous</span>' : e($r['name']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="cell-muted"><?= e(time_ago($r['created_at'])) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="ticket.php?id=<?= (int)$r['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
        <tr><td colspan="6" class="cell-muted">No feedback yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
