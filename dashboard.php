<?php
// ============================================================================
//  User Dashboard (Phase 2)
//  Welcome screen: summary of the user's concerns + public board + CTA.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

$base      = '';
$pageTitle = 'Dashboard';
$active    = 'dashboard';
$user      = current_user();

// Status summary for the logged-in user
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS c FROM feedbacks WHERE user_id = ? GROUP BY status');
$stmt->execute([(int)$user['id']]);
$counts = ['pending' => 0, 'under_review' => 0, 'resolved' => 0];
foreach ($stmt as $row) {
    $counts[$row['status']] = (int)$row['c'];
}
$total = array_sum($counts);

// The user's own tickets
$stmt = $pdo->prepare(
    'SELECT * FROM feedbacks WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8'
);
$stmt->execute([(int)$user['id']]);
$myTickets = $stmt->fetchAll();

// Public board feed (everyone's approved feedback)
$publicFeed = $pdo->query(
    "SELECT f.*, u.name
     FROM feedbacks f
     JOIN users u ON u.id = f.user_id
     WHERE f.is_public = 1
     ORDER BY f.created_at DESC, f.id DESC
     LIMIT 6"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">Welcome back, <?= e(first_name($user['name'])) ?>!</h1>
    <p class="page-sub">Here's the status of your feedback.</p>
  </div>
  <a class="btn btn-primary btn-lg" href="submit.php">+ Submit Feedback</a>
</div>

<div class="stat-grid">
  <div class="card stat-card">
    <span class="stat-icon stat-icon-total"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg></span>
    <div>
      <div class="stat-number"><?= $total ?></div>
      <div class="stat-label">Total tickets</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-pending"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['pending'] ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-review"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['under_review'] ?></div>
      <div class="stat-label">Under review</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-resolved"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['resolved'] ?></div>
      <div class="stat-label">Resolved</div>
    </div>
  </div>
</div>

<div class="dash-grid">
  <div class="dash-col">
    <div class="section-head">
      <h2>My Tickets</h2>
      <p>Track the status of your submitted concerns.</p>
    </div>

    <?php if (empty($myTickets)): ?>
      <div class="card empty-state">
        <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></div>
        <h3>You haven't submitted any feedback yet</h3>
        <p>Spotted a broken facility or a safety issue? Let the school know.</p>
        <a class="btn btn-primary" href="submit.php">Submit your first feedback</a>
      </div>
    <?php else: ?>
      <div class="ticket-list">
        <?php foreach ($myTickets as $t): ?>
        <a class="card ticket-row" href="ticket.php?id=<?= (int)$t['id'] ?>">
          <span class="ticket-no"><?= e($t['ticket_no']) ?></span>
          <div class="ticket-main">
            <div class="ticket-title-line">
              <span class="ticket-title"><?= e($t['title']) ?></span>
              <?= status_badge($t['status']) ?>
            </div>
            <div class="ticket-meta">
              <?= category_badge($t['category']) ?>
              <?php if ((int)$t['is_anonymous'] === 1): ?><?= anonymous_badge() ?><?php endif; ?>
              <span class="ticket-date"><?= e(time_ago($t['created_at'])) ?></span>
            </div>
          </div>
          <span class="ticket-chevron">›</span>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="dash-col">
    <div class="section-head">
      <h2>Public Board</h2>
      <p>What others are reporting (approved for public viewing).</p>
    </div>

    <?php if (empty($publicFeed)): ?>
      <div class="card empty-state small">
        <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
        <p>No public feedback yet.</p>
      </div>
    <?php else: ?>
      <div class="feed-list">
        <?php foreach ($publicFeed as $item): ?>
        <article class="card feed-item">
          <div class="feed-top">
            <?= category_badge($item['category']) ?>
            <?= status_badge($item['status']) ?>
          </div>
          <h3 class="feed-title"><?= e($item['title']) ?></h3>
          <p class="feed-text"><?= e(truncate($item['description'], 110)) ?></p>
          <div class="board-card-meta">
            <span class="avatar avatar-sm"><?= $item['is_anonymous'] ? '?' : e(initials($item['name'])) ?></span>
            <span><?= $item['is_anonymous'] ? 'Anonymous' : e($item['name']) ?></span>
            <span class="dot-sep">•</span>
            <span><?= e(time_ago($item['created_at'])) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
