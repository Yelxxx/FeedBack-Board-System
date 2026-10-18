<?php
// ============================================================================
//  Shared page header / navigation.
//  Expects:  $base ('' for root pages, '../' for admin pages) to be set by
//            the including page BEFORE this file is required.
// ============================================================================

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Access denied.');
}

$base       = $base ?? '';
$pageTitle  = $pageTitle ?? APP_NAME;
$active     = $active ?? '';
$bodyClass = $bodyClass ?? '';
$user      = current_user();
$isLoggedIn = $user !== null;
$unread    = $isLoggedIn ? unread_count($pdo, (int)$user['id']) : 0;
$notifs    = $isLoggedIn ? unread_notifications($pdo, (int)$user['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css?v=5">
<link rel="icon" href="<?= e($base) ?>olivarez-college-tagaytay-logo.png">
</head>
<body data-base="<?= e($base) ?>" data-csrf="<?= e(csrf_token()) ?>" data-max-upload="<?= (int)MAX_UPLOAD_MB ?>"<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip-link" href="#main">Skip to content</a>

<nav class="navbar">
  <div class="container nav-inner">
    <a class="brand" href="<?= e($base) ?><?= $isLoggedIn ? 'dashboard.php' : 'index.php' ?>">
      <img class="brand-logo" src="<?= e($base) ?>olivarez-college-tagaytay-logo.png" alt="Olivarez College Tagaytay logo">
      <span class="brand-text"><?= e(APP_NAME) ?></span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <?php if ($isLoggedIn): ?>
    <div class="nav-links" id="navLinks">
      <a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e($base) ?>dashboard.php">Dashboard</a>
      <a class="nav-link <?= $active === 'submit' ? 'active' : '' ?>" href="<?= e($base) ?>submit.php">Submit Feedback</a>
      <?php if (is_admin()): ?>
      <a class="nav-link <?= $active === 'admin' ? 'active' : '' ?>" href="<?= e($base) ?>admin/dashboard.php">Admin Panel</a>
      <?php endif; ?>
      <?php if (is_super_admin()): ?>
      <a class="nav-link <?= $active === 'users' ? 'active' : '' ?>" href="<?= e($base) ?>admin/users.php">Users</a>
      <?php endif; ?>

      <div class="nav-right">
        <div class="notif" id="notifWrap">
          <button class="notif-btn" id="notifBtn" aria-label="Notifications">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($unread > 0): ?><span class="notif-dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-head">Notifications</div>
            <?php if (empty($notifs)): ?>
              <div class="notif-empty">You're all caught up.</div>
            <?php else: ?>
              <?php foreach ($notifs as $n): ?>
              <a class="notif-item" href="<?= e($base) . (is_admin() ? 'admin/ticket.php?id=' : 'ticket.php?id=') . (int)$n['feedback_id'] ?>">
                <div class="notif-msg"><?= e($n['message']) ?></div>
                <div class="notif-time"><?= e(time_ago($n['created_at'])) ?></div>
              </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="user-menu">
          <span class="avatar" title="<?= e($user['name']) ?>"><?= e(initials($user['name'])) ?></span>
          <span class="user-name"><?= e(first_name($user['name'])) ?></span>
          <a class="btn btn-sm btn-outline" href="<?= e($base) ?>auth/logout.php">Log out</a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="nav-links nav-links-guest" id="navLinks">
      <a class="nav-link <?= $active === 'board' ? 'active' : '' ?>" href="<?= e($base) ?>index.php">Public Board</a>
      <div class="nav-right">
        <a class="btn btn-sm btn-ghost" href="<?= e($base) ?>auth/login.php">Log in</a>
        <a class="btn btn-sm btn-primary" href="<?= e($base) ?>auth/register.php">Sign up</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</nav>

<main id="main" class="container page">
  <?php foreach (['success', 'error', 'info'] as $flashType):
      foreach (flash_get($flashType) as $flashMessage): ?>
      <div class="alert alert-<?= $flashType ?>">
        <span><?= e($flashMessage) ?></span>
        <button class="alert-close" aria-label="Dismiss">&times;</button>
      </div>
  <?php endforeach; endforeach; ?>
