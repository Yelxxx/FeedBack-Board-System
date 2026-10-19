<?php
// ============================================================================
//  Ticket Details — User view (Phase 4: Tracking & Notifications)
//  Status timeline, two-way comment thread, reply form.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

$base      = '';
$pageTitle = 'Ticket Details';
$active    = 'dashboard';

$user = current_user();
$id   = (int)($_GET['id'] ?? 0);
$ticket = find_feedback($pdo, $id);

if ($ticket === null) {
    flash_set('error', 'Ticket not found.');
    redirect('dashboard.php');
}

// Access control:
// Can view if:
// 1. Author of the ticket
// 2. Super Admin
// 3. Department Admin whose field matches the ticket category
// 4. Staff / Maintenance whose department/field matches the ticket category
// 5. Ticket is public
$isAuthor = (int)$ticket['user_id'] === (int)$user['id'];
$isSuper  = is_super_admin();
$isAdminOfField = ($user['role'] === 'admin') && (admin_field($user) === null || admin_field($user) === $ticket['category']);
$isStaffOfField = ($user['role'] === 'staff') && (user_field($user) === null || user_field($user) === $ticket['category']);
$canComment = $isAuthor || $isSuper || $isAdminOfField || $isStaffOfField;
$canView = $canComment || ((int)$ticket['is_public'] === 1);

if (!$canView) {
    flash_set('error', 'You do not have permission to view this ticket.');
    redirect('dashboard.php');
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canComment) {
        flash_set('error', 'Only the reporter, assigned staff/maintenance, and department heads can reply to this ticket thread.');
        redirect('ticket.php?id=' . $ticket['id']);
    }

    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            flash_set('error', 'Please type a message first.');
        } elseif (strlen($message) > 2000) {
            flash_set('error', 'Messages must be 2000 characters or less.');
        } elseif (ProfanityFilter::containsBadWord($message)) {
            $badWords = ProfanityFilter::findBadWords($message);
            flash_set('error', 'Your comment contains inappropriate or explicit language (' . implode(', ', $badWords) . '). Please keep campus discussions respectful.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO comments (feedback_id, user_id, message, visibility) VALUES (?, ?, ?, 'public')"
            );
            $stmt->execute([(int)$ticket['id'], (int)$user['id'], $message]);

            // If the poster commented, notify staff & admins.
            // If staff/admin commented, notify the poster!
            if ($isAuthor) {
                notify_admins($pdo, 'New reply on ' . $ticket['ticket_no'] . ': "' . truncate($message, 60) . '"', (int)$ticket['id']);
            } else {
                notify_user($pdo, (int)$ticket['user_id'], 'New response on ' . $ticket['ticket_no'] . ' from ' . $user['name'] . ': "' . truncate($message, 60) . '"', (int)$ticket['id']);
            }

            flash_set('success', 'Your reply was posted.');
            redirect('ticket.php?id=' . $ticket['id']);
        }
    }
}

$comments = fetch_comments($pdo, (int)$ticket['id'], false);
$statusSteps = [
    'pending'      => ['label' => 'Pending', 'desc' => 'Ticket received & queued'],
    'under_review' => ['label' => 'Under Review', 'desc' => 'Assigned & being inspected'],
    'resolved'     => ['label' => 'Resolved', 'desc' => 'Issue completed & verified'],
];
$statusOrder = ['pending' => 0, 'under_review' => 1, 'resolved' => 2];
$isRejected = ($ticket['status'] === 'rejected');
$currentStep = $statusOrder[$ticket['status']] ?? 0;

require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="dashboard.php">&larr; Back to dashboard</a>

<div class="ticket-head">
  <div>
    <div class="ticket-head-badges">
      <span class="ticket-no ticket-no-lg"><?= e($ticket['ticket_no']) ?></span>
      <?= status_badge($ticket['status']) ?>
      <?= category_badge($ticket['category']) ?>
      <?php if ((int)$ticket['is_anonymous'] === 1): ?><?= anonymous_badge() ?><?php endif; ?>
    </div>
    <h1 class="page-title"><?= e($ticket['title']) ?></h1>
    <p class="page-sub">Submitted <?= e(time_ago($ticket['created_at'])) ?>
       <?php if ($ticket['updated_at'] !== $ticket['created_at']): ?>
         · Updated <?= e(time_ago($ticket['updated_at'])) ?>
       <?php endif; ?>
       <?php if ($ticket['assigned_dept']): ?>· Assigned to <strong><?= e($ticket['assigned_dept']) ?></strong><?php endif; ?>
    </p>
  </div>
</div>

<?php if ($isRejected): ?>
  <div class="alert alert-error" style="display:flex; align-items:center; gap:12px; margin-bottom:20px; border-radius:12px; padding:16px 20px;">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <div>
      <strong>Ticket Rejected by Moderation</strong>
      <div style="font-size:0.9rem; margin-top:2px;">This feedback concern was rejected or closed by the Super Administrator. It is hidden from public view.</div>
    </div>
  </div>
<?php endif; ?>

<!-- Modern Status Stepper Progress Bar -->
<div class="card timeline-card">
  <div class="stepper-wrap">
    <?php 
    $stepIdx = 0;
    foreach ($statusSteps as $key => $info):
        $stepNumber = $statusOrder[$key];
        $isDone = !$isRejected && ($stepNumber < $currentStep);
        $isCurrent = !$isRejected && ($stepNumber === $currentStep);
        $stepClass = $isDone ? 'step-done' : ($isCurrent ? 'step-current' : 'step-upcoming');
    ?>
    <div class="stepper-item <?= $stepClass ?>">
      <div class="stepper-indicator">
        <span class="stepper-circle">
          <?php if ($isDone): ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <?php else: ?>
            <?= ($stepIdx + 1) ?>
          <?php endif; ?>
        </span>
        <?php if ($stepIdx < 2): ?>
          <div class="stepper-line">
            <div class="stepper-line-fill" style="width: <?= ($isDone || ($isCurrent && $stepNumber > 0)) ? '100%' : '0%' ?>;"></div>
          </div>
        <?php endif; ?>
      </div>
      <div class="stepper-content">
        <div class="stepper-title"><?= e($info['label']) ?></div>
        <div class="stepper-desc">
          <?php if ($isCurrent): ?>
            <span class="stepper-badge-live">Current stage</span>
          <?php else: ?>
            <?= e($info['desc']) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php 
      $stepIdx++;
    endforeach; 
    ?>
  </div>
</div>

<div class="ticket-grid">
  <div class="ticket-col-main">
    <!-- Description -->
    <div class="card panel">
      <h2 class="panel-title">Description</h2>
      <p class="panel-text"><?= nl2br(e($ticket['description'])) ?></p>

      <?php foreach (fetch_attachments($pdo, (int)$ticket['id']) as $a): ?>
        <?= attachment_html($a) ?>
      <?php endforeach; ?>
    </div>

    <!-- Comments -->
    <div class="card panel">
      <h2 class="panel-title">Discussion (<?= count($comments) ?>)</h2>

      <?php if (empty($comments)): ?>
        <p class="panel-empty">No comments yet. The admin will reply here once your concern is reviewed.</p>
      <?php else: ?>
        <div class="comment-list">
          <?php foreach ($comments as $c):
              $isOfficial = in_array($c['role'], ['admin', 'super_admin', 'staff'], true);
              $isStaffResp = ($c['role'] === 'staff');
          ?>
          <div class="comment <?= $isOfficial ? 'comment-admin' : '' ?>">
            <span class="avatar"><?= e(initials($c['name'])) ?></span>
            <div class="comment-body">
              <div class="comment-meta">
                <strong><?= e($c['name']) ?></strong>
                <?php if ($c['role'] === 'super_admin'): ?><span class="badge badge-admin">Super Admin</span>
                <?php elseif ($c['role'] === 'admin'): ?><span class="badge badge-admin">Department Head</span>
                <?php elseif ($c['role'] === 'staff'): ?><span class="badge badge-admin" style="background:#0284c7;color:#fff;">Staff / Maintenance</span>
                <?php elseif ($c['role'] === 'faculty'): ?><span class="badge badge-neutral">Faculty</span>
                <?php else: ?><span class="badge badge-neutral">Student</span><?php endif; ?>
                <span class="comment-time"><?= e(time_ago($c['created_at'])) ?></span>
              </div>
              <p class="comment-text"><?= nl2br(e($c['message'])) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Reply -->
    <?php if ($canComment): ?>
    <div class="card panel">
      <h2 class="panel-title">Add a reply</h2>
      <p class="panel-sub" style="font-size:0.85rem;color:var(--text-muted, #64748b);margin-bottom:0.75rem;">
        <?= $isAuthor ? 'Direct communication channel between you and the assigned department/staff.' : 'Replying as official campus personnel (' . e(role_label($user['role'])) . ').' ?>
      </p>
      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <textarea name="message" class="input input-textarea" rows="3" maxlength="2000"
                  placeholder="Type your reply…" required></textarea>
        <div class="form-actions form-actions-left">
          <button type="submit" class="btn btn-primary">Post reply</button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="card panel">
      <p class="panel-empty" style="padding:1rem 0;">This discussion is restricted to the original poster and the assigned department personnel.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="ticket-col-side">
    <div class="card panel">
      <h2 class="panel-title">Ticket info</h2>
      <dl class="meta-list">
        <div><dt>Ticket no.</dt><dd><?= e($ticket['ticket_no']) ?></dd></div>
        <div><dt>Category</dt><dd><?= e($ticket['category']) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(STATUS_LABELS[$ticket['status']] ?? $ticket['status']) ?></dd></div>
        <div><dt>Assigned to</dt><dd><?= $ticket['assigned_dept'] ? e($ticket['assigned_dept']) : '—' ?></dd></div>
        <div><dt>Submitted</dt><dd><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></dd></div>
        <div><dt>Visibility</dt><dd><?= (int)$ticket['is_anonymous'] === 1 ? 'Anonymous' : 'Identified' ?></dd></div>
      </dl>
    </div>

    <div class="card panel tip-panel">
      <h2 class="panel-title">Good to know</h2>
      <ul class="tip-list">
        <li>Status changes and admin replies appear instantly in this thread.</li>
        <li>You'll see a notification whenever an admin updates your ticket.</li>
        <li>Need to add more details? Just reply here — the admin is notified.</li>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
