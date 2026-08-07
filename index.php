<?php
// ============================================================================
//  Landing page + Public Board (Phase 2)
//  Guests see a hero and the approved public feedback feed.
//  Logged-in users are sent to their dashboard.
// ============================================================================
require_once __DIR__ . '/config.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$base      = '';
$pageTitle = 'Public Feedback Board';
$active    = 'board';
$bodyClass = 'landing';

$feed = $pdo->query(
    "SELECT f.*, u.name
     FROM feedbacks f
     JOIN users u ON u.id = f.user_id
     WHERE f.is_public = 1
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
      <span>Admin analytics</span>
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
        <p>Fill in the category, subject and details. Add a photo as evidence — or stay anonymous.</p>
      </div>
      <div class="how-step">
        <span class="how-num">2</span>
        <h3>Track</h3>
        <p>Get a unique ticket number (TKT-001) and follow its status: Pending, then Under Review, then Resolved.</p>
      </div>
      <div class="how-step">
        <span class="how-num">3</span>
        <h3>Resolved</h3>
        <p>Admins assign your concern to the right office and keep you updated through the comments thread.</p>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
