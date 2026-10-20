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

// General posts: public approved feedback from across all fields.
// Filters: search, category, sort (newest, most supported)
$q        = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$sort     = trim((string)($_GET['sort'] ?? 'newest'));

$where = ["f.is_public = 1 AND f.status <> 'rejected'"];
$params = [(int)$user['id']]; // for user_supported check

if ($category !== '' && valid_category($category)) {
    $where[] = 'f.category = ?';
    $params[] = $category;
}

if ($q !== '') {
    $where[] = '(f.title LIKE ? OR f.description LIKE ? OR f.ticket_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$orderBy = ($sort === 'supported')
    ? 'support_count DESC, f.created_at DESC'
    : 'f.created_at DESC, f.id DESC';

$sql = "SELECT f.*, u.name,
               (SELECT COUNT(*) FROM feedback_endorsements WHERE feedback_id = f.id) AS support_count,
               (SELECT COUNT(*) FROM comments WHERE feedback_id = f.id) AS comment_count,
               (SELECT 1 FROM feedback_endorsements WHERE feedback_id = f.id AND user_id = ?) AS user_supported
        FROM feedbacks f
        JOIN users u ON u.id = f.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
        LIMIT 40";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
    <h1 class="page-title">General Posts</h1>
    <p class="page-sub">Public concerns and suggestions shared across the campus community.</p>
  </div>
  <?php if (is_super_admin()): ?>
  <div style="display:flex; gap:0.6rem;">
    <a class="btn btn-primary btn-lg" href="announcements.php"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> + Post Announcement</a>
    <a class="btn btn-outline btn-lg" href="admin/dashboard.php">Admin Panel</a>
  </div>
  <?php else: ?>
  <a class="btn btn-primary btn-lg" href="my-activity.php">+ Submit Feedback</a>
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

<!-- Filter & Search Bar -->
<form method="get" action="dashboard.php" class="card filter-bar" style="margin-bottom: 20px;">
  <div class="filter-search">
    <input type="search" name="q" class="input" placeholder="Search general concerns by title or keyword…" value="<?= e($q) ?>">
  </div>
  <select name="category" class="input">
    <option value="">All categories</option>
    <?php foreach (CATEGORIES as $cat): ?>
      <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" class="input">
    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
    <option value="supported" <?= $sort === 'supported' ? 'selected' : '' ?>>Most supported</option>
  </select>
  <button type="submit" class="btn btn-primary">Filter</button>
  <?php if ($q !== '' || $category !== '' || $sort !== 'newest'): ?>
    <a class="btn btn-ghost" href="dashboard.php">Clear</a>
  <?php endif; ?>
</form>

<div class="general-feed-container">
  <?php if (empty($generalPosts)): ?>
    <div class="card empty-state">
      <div class="empty-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
      <h3>No posts match your filters</h3>
      <p>Try adjusting your search terms or category filter to view other public concerns.</p>
    </div>
  <?php else: ?>
    <div class="board-grid" style="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));">
      <?php foreach ($generalPosts as $item): ?>
      <a class="card board-card" href="ticket.php?id=<?= (int)$item['id'] ?>" style="text-decoration:none; color:inherit; display:flex; flex-direction:column;">
        <div class="board-card-top" style="display:flex; justify-content:space-between; align-items:center;">
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <?= category_badge($item['category']) ?>
            <?= status_badge($item['status']) ?>
          </div>
          <span class="ticket-no"><?= e($item['ticket_no']) ?></span>
        </div>
        <h3 class="board-card-title" style="margin-top:6px;"><?= e($item['title']) ?></h3>
        <p class="board-card-text"><?= e(truncate($item['description'], 130)) ?></p>

        <div class="board-card-meta" style="display:flex; justify-content:space-between; align-items:center; margin-top:auto; padding-top:10px; border-top:1px solid #f1f5f9;">
          <div style="display:flex; align-items:center; gap:6px;">
            <span class="avatar avatar-sm"><?= $item['is_anonymous'] ? '?' : e(initials($item['name'])) ?></span>
            <span style="font-size:13px; color:#475569;"><?= $item['is_anonymous'] ? 'Anonymous' : e($item['name']) ?></span>
            <span class="dot-sep">•</span>
            <span style="font-size:12.5px;"><?= e(time_ago($item['created_at'])) ?></span>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
