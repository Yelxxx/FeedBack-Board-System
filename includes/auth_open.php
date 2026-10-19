<?php
// ============================================================================
//  Shared shell for the standalone authentication pages (login / register /
//  forgot password / reset password). Expects $authTitle and $authSubtitle.
//  Set $authLayout = 'split' (login) for the 70/30 visual + form layout,
//  otherwise the centered card layout is used.
// ============================================================================
if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Access denied.');
}
$base         = $base ?? '../';
$authTitle    = $authTitle ?? APP_NAME;
$authSubtitle = $authSubtitle ?? '';
$authLayout   = $authLayout ?? 'center';
$isSplit      = $authLayout === 'split';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($authTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e($base) ?>assets/css/style.css?v=6">
<link rel="icon" href="<?= e($base) ?>olivarez-college-tagaytay-logo.png">
</head>
<body class="auth-page<?= $isSplit ? ' auth-page-split' : '' ?>" data-csrf="<?= e(csrf_token()) ?>">

<?php if ($isSplit): ?>
<div class="auth-split">
  <!-- 70%: visual brand panel -->
  <aside class="auth-visual">
    <div class="auth-visual-inner">
      <div class="auth-visual-brand">
        <img class="auth-logo-lg" src="<?= e($base) ?>olivarez-college-tagaytay-logo.png" alt="Olivarez College Tagaytay logo">
        <h1><?= e(APP_NAME) ?></h1>
        <p class="auth-visual-tagline"><?= e(APP_TAGLINE) ?></p>
      </div>

      <div class="auth-objectives">
        <h2>Our Objectives</h2>
        <ul>
          <li><span class="obj-ico"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg></span><span><strong>Voice your concerns</strong> — submit feedback about facilities, academics, safety and campus life.</span></li>
          <li><span class="obj-ico"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/></svg></span><span><strong>Report anonymously</strong> — share sensitive issues without revealing your identity.</span></li>
          <li><span class="obj-ico"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></span><span><strong>Track your tickets</strong> — follow every concern from Pending to Resolved in real time.</span></li>
          <li><span class="obj-ico"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg></span><span><strong>Reach the right office</strong> — concerns are routed to the department that can act on them.</span></li>
        </ul>
      </div>

      <p class="auth-visual-foot">Olivarez College Tagaytay · OCT Feedback Board System</p>
    </div>
  </aside>

  <!-- 30%: form panel -->
  <div class="auth-panel">
    <div class="auth-panel-card">
      <div class="auth-card-head">
        <img src="<?= e($base) ?>olivarez-college-tagaytay-logo.png" alt="">
        <div>
          <h2><?= e($authTitle) ?></h2>
          <?php if ($authSubtitle !== ''): ?><p><?= e($authSubtitle) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="auth-card-form">
<?php else: ?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-brand">
      <img class="auth-logo" src="<?= e($base) ?>olivarez-college-tagaytay-logo.png" alt="Olivarez College Tagaytay logo">
      <h1 class="auth-brand-name"><?= e(APP_NAME) ?></h1>
      <?php if ($authSubtitle !== ''): ?><p class="auth-subtitle"><?= e($authSubtitle) ?></p><?php endif; ?>
    </div>
<?php endif; ?>

    <?php foreach (['success', 'error', 'info'] as $flashType):
        foreach (flash_get($flashType) as $flashMessage): ?>
        <div class="alert alert-<?= $flashType ?>">
          <span><?= e($flashMessage) ?></span>
          <button class="alert-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; endforeach; ?>
