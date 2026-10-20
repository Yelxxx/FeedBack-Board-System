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

// The user's own posts (rejected posts still appear here so they can see the
// moderation decision on their own dashboard — shown with the badge).
$stmt = $pdo->prepare(
    'SELECT * FROM feedbacks WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8'
);
$stmt->execute([(int)$user['id']]);
$myTickets = $stmt->fetchAll();

// General posts: approved feedback from OTHER users, across all fields.
// Rejected (moderated) posts never appear in the general feed.
$stmt = $pdo->prepare(
    "SELECT f.*, u.name,
            (SELECT COUNT(*) FROM feedback_endorsements WHERE feedback_id = f.id) AS support_count,
            (SELECT COUNT(*) FROM comments WHERE feedback_id = f.id) AS comment_count,
            (SELECT 1 FROM feedback_endorsements WHERE feedback_id = f.id AND user_id = ?) AS user_supported
     FROM feedbacks f
     JOIN users u ON u.id = f.user_id
     WHERE f.is_public = 1 AND f.user_id <> ? AND f.status <> 'rejected'
     ORDER BY f.created_at DESC, f.id DESC
     LIMIT 8"
);
$stmt->execute([(int)$user['id'], (int)$user['id']]);
$generalPosts = $stmt->fetchAll();
// Campus Announcements (visible to all users)
$announcements = $pdo->query(
    'SELECT a.*, u.name AS author_name FROM announcements a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC LIMIT 3'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">Welcome back, <?= e(first_name($user['name'])) ?>!</h1>
    <p class="page-sub">
      <?= e(role_label($user['role'])) ?>
      <?php if (($field = admin_field($user)) !== null): ?> · Field: <strong><?= e($field) ?></strong><?php endif; ?>
    </p>
  </div>
  <?php if (is_super_admin()): ?>
  <div style="display:flex; gap:0.6rem;">
    <a class="btn btn-primary btn-lg" href="announcements.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> + Post Announcement</a>
    <a class="btn btn-outline btn-lg" href="admin/dashboard.php">Admin Panel</a>
  </div>
  <?php else: ?>
  <a class="btn btn-primary btn-lg" href="submit.php">+ Submit Feedback</a>
  <?php endif; ?>
</div>

<?php if (!empty($announcements)): ?>
<div class="card" style="background: linear-gradient(135deg, rgba(5,150,105,0.06), rgba(16,185,129,0.02)); border: 1px solid rgba(5,150,105,0.25); border-left: 5px solid #059669; margin-bottom: 2rem; padding: 1.25rem 1.5rem;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
    <div style="display:flex; align-items:center; gap:0.5rem;">
      <span class="badge" style="background:#059669; color:#fff; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700;">Official Campus Announcement</span>
      <span style="font-size:0.8rem; color:var(--text-muted, #64748b);">Latest Broadcast</span>
    </div>
    <a href="announcements.php" style="font-size:0.85rem; font-weight:600; color:#059669; text-decoration:none;">View all announcements &rarr;</a>
  </div>
  <?php $topAnn = $announcements[0]; ?>
  <h2 style="font-size:1.15rem; font-weight:700; margin:0 0 0.4rem 0; color:var(--text-main, #0f172a);"><?= e($topAnn['title']) ?></h2>
  <p style="font-size:0.9rem; line-height:1.55; color:var(--text-muted, #334155); margin:0 0 0.6rem 0;"><?= e(truncate($topAnn['content'], 180)) ?></p>
  <div style="font-size:0.8rem; color:var(--text-muted, #64748b);">
    Posted by <strong><?= e($topAnn['author_name']) ?></strong> (Super Admin) · <?= e(time_ago($topAnn['created_at'])) ?>
  </div>
</div>
<?php endif; ?>

<div class="dash-grid">
  <div class="dash-col" id="general-anchor">
    <div class="section-head">
      <h2>My Posts</h2>
      <p>Track the status of the posts you created.</p>
    </div>

    <?php if (empty($myTickets)): ?>
      <div class="card empty-state">
        <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></div>
        <?php if (is_super_admin()): ?>
        <h3>Super Administrator Overview</h3>
        <p>As Super Admin, you review campus concerns, assign field staff, and broadcast official announcements.</p>
        <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:0.5rem;">
          <a class="btn btn-primary" href="announcements.php">Post Announcement</a>
          <a class="btn btn-outline" href="admin/tickets.php">Manage All Tickets</a>
        </div>
        <?php else: ?>
        <h3>You haven't submitted any feedback yet</h3>
        <p>Spotted a broken facility or a safety issue? Let the school know.</p>
        <a class="btn btn-primary" href="submit.php">Submit your first feedback</a>
        <?php endif; ?>
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

  <div class="dash-col" id="general">
    <div class="section-head">
      <h2>General Posts</h2>
      <p>Feedback from other users across every field, approved for public viewing.</p>
    </div>

    <?php if (empty($generalPosts)): ?>
      <div class="card empty-state small">
        <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
        <p>No general posts yet.</p>
      </div>
    <?php else: ?>
      <div class="feed-list">
        <?php foreach ($generalPosts as $item): ?>
        <a class="card feed-item" href="ticket.php?id=<?= (int)$item['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
          <div class="feed-top">
            <?= category_badge($item['category']) ?>
            <?= status_badge($item['status']) ?>
          </div>
          <h3 class="feed-title"><?= e($item['title']) ?></h3>
          <p class="feed-text"><?= e(truncate($item['description'], 110)) ?></p>
          <div class="board-card-meta" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:6px;">
              <span class="avatar avatar-sm"><?= $item['is_anonymous'] ? '?' : e(initials($item['name'])) ?></span>
              <span><?= $item['is_anonymous'] ? 'Anonymous' : e($item['name']) ?></span>
              <span class="dot-sep">•</span>
              <span><?= e(time_ago($item['created_at'])) ?></span>
            </div>

            <div class="feed-stat-badges" style="display:flex; align-items:center; gap:8px;">
              <span class="badge-card-support <?= (int)$item['user_supported'] === 1 ? 'is-supported' : '' ?>" title="Community Support">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                <?= (int)$item['support_count'] ?> <?= (int)$item['support_count'] === 1 ? 'Support' : 'Supports' ?>
              </span>
              <span class="badge-card-comments" title="Discussion Comments">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <?= (int)$item['comment_count'] ?>
              </span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
