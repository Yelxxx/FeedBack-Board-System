<?php
// ============================================================================
//  Home / Landing page
//  Guests: hero + public board showcase (marketing + browse).
//  Logged-in users: clean hub with the three main destinations.
// ============================================================================
require_once __DIR__ . '/config.php';

$base      = '';
$active    = 'home';

if (current_user() === null) {
    // ------------------------- Guest view -------------------------
    $pageTitle = 'Public Feedback Board';
    $bodyClass = 'landing';

    $feed = $pdo->query(
        "SELECT f.*, u.name
         FROM feedbacks f
         JOIN users u ON u.id = f.user_id
         WHERE f.is_public = 1 AND f.status <> 'rejected'
         ORDER BY f.created_at DESC, f.id DESC
         LIMIT 12"
    )->fetchAll();

    require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-inner">
    <span class="hero-chip">A safe space to be heard</span>
    <h1 class="hero-title">Report campus concerns.<br><span class="hero-highlight">Watch them get fixed.</span></h1>
    <p class="hero-sub"><?= e(APP_TAGLINE) ?> — submit feedback anonymously or openly, track your ticket
       with a unique number, and talk directly with the office that handles it.</p>
    <div class="hero-actions">
      <a class="btn btn-light" href="auth/register.php">Get started</a>
      <a class="btn btn-light-outline" href="auth/login.php">Log in</a>
    </div>
    <div class="hero-features">
      <span>Anonymous reporting</span>
      <span>Unique ticket numbers</span>
      <span>Field-based routing</span>
    </div>
  </div>
</section>

<section class="landing-board">
  <div class="container">
    <div class="section-head">
      <h2>Public Board</h2>
      <p>Recent concerns shared by the community, approved for public viewing.</p>
    </div>

    <?php if (empty($feed)): ?>
      <div class="card empty-state">
        <div class="empty-emoji">
          <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>No public feedback yet</h3>
        <p>Be the first to share a concern. Create an account to get started.</p>
      </div>
    <?php else: ?>
      <div class="board-grid">
        <?php foreach ($feed as $item): ?>
        <article class="card board-card">
          <div class="board-card-top">
            <?= category_badge($item['category']) ?>
            <?= status_badge($item['status']) ?>
          </div>
          <h3 class="board-card-title"><?= e($item['title']) ?></h3>
          <p class="board-card-text"><?= e(truncate($item['description'], 160)) ?></p>
          <div class="board-card-meta">
            <span class="avatar avatar-sm" title="<?= $item['is_anonymous'] ? 'Anonymous' : e($item['name']) ?>">
              <?= $item['is_anonymous'] ? '?' : e(initials($item['name'])) ?>
            </span>
            <span><?= $item['is_anonymous'] ? 'Anonymous' : e($item['name']) ?></span>
            <span class="dot-sep">•</span>
            <span><?= e(time_ago($item['created_at'])) ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="how-section">
  <div class="container">
    <div class="section-head how-head">
      <h2>How it works</h2>
      <p>From report to resolution in three simple steps.</p>
    </div>
    <div class="how-grid">
      <div class="how-step">
        <span class="how-num">1</span>
        <h3>Submit</h3>
        <p>Fill in the category, subject and details. Add a photo, video or audio as evidence — or stay anonymous.</p>
      </div>
      <div class="how-step">
        <span class="how-num">2</span>
        <h3>Track</h3>
        <p>Get a unique ticket number and follow its status: Pending, Under Review, then Resolved.</p>
      </div>
      <div class="how-step">
        <span class="how-num">3</span>
        <h3>Resolved</h3>
        <p>Your concern is routed to the office that handles your field and kept updated via the discussion thread.</p>
      </div>
    </div>
  </div>
</section>

<section class="cta-banner">
  <div class="container">
    <h2>Have a concern to report?</h2>
    <p>Create an account in under a minute and let your voice be heard.</p>
    <a class="btn btn-light" href="auth/register.php">Create free account</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php';
    return;
}

// ------------------------- Logged-in hub -------------------------
$user      = current_user();
$pageTitle = 'Home';
$bodyClass = '';

$field  = admin_field($user) ?? user_field($user);
$notifCount = unread_count($pdo, (int)$user['id']);

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">Welcome, <?= e(first_name($user['name'])) ?> <?= $field !== null ? '· ' . e($field) : '' ?></h1>
    <p class="page-sub">Choose where to go — everything is one click away.</p>
  </div>
</div>

<div class="hub-grid">
  <?php if (is_super_admin()): ?>
  <a class="card hub-card" href="admin/tickets.php">
    <span class="hub-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
    <h3>Manage All Tickets</h3>
    <p>Overall campus oversight. Review tickets across all departments, resolve disputes, and maintain unbiased moderation.</p>
    <span class="hub-cta">Manage tickets ›</span>
  </a>
  <?php else: ?>
  <a class="card hub-card" href="my-field.php">
    <span class="hub-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></span>
    <h3><?= $field !== null ? e($field) : 'My Field Concerns' ?></h3>
    <p>Only posts related to your field — all filtered out for you. Reports about your area show up here the moment they're filed.</p>
    <span class="hub-cta">Open my field ›</span>
  </a>
  <?php endif; ?>

  <a class="card hub-card" href="dashboard.php#general">
    <span class="hub-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg></span>
    <h3>General Posts</h3>
    <p>Browse all approved posts from every field on campus — from broken ACs to exam schedules and more.</p>
    <span class="hub-cta">Browse all posts ›</span>
  </a>

  <?php if (is_super_admin()): ?>
  <a class="card hub-card" href="announcements.php">
    <span class="hub-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
    <h3>Campus Announcements</h3>
    <p>Broadcast official campus advisories, holiday notices, and facility maintenance news to all users.</p>
    <span class="hub-cta">Post announcement ›</span>
  </a>
  <?php else: ?>
  <a class="card hub-card" href="submit.php">
    <span class="hub-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg></span>
    <h3>Submit Feedback</h3>
    <p>Raise a new concern with an image, video or audio attachment. Anonymous posting is available.</p>
    <span class="hub-cta">Create a post ›</span>
  </a>
  <?php endif; ?>
</div>

<div class="hub-quicklinks">
  <a class="btn btn-outline" href="dashboard.php">My dashboard</a>
  <?php if (is_admin()): ?>
  <a class="btn btn-outline" href="admin/dashboard.php">Admin panel</a>
  <?php endif; ?>
  <?php if (is_super_admin()): ?>
  <a class="btn btn-outline" href="admin/users.php">User management</a>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
