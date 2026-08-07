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

// Users may only view their own tickets
if ($ticket === null || (int)$ticket['user_id'] !== (int)$user['id']) {
    flash_set('error', 'Ticket not found.');
    redirect('dashboard.php');
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            flash_set('error', 'Please type a message first.');
        } elseif (strlen($message) > 2000) {
            flash_set('error', 'Messages must be 2000 characters or less.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO comments (feedback_id, user_id, message, visibility) VALUES (?, ?, ?, 'public')"
            );
            $stmt->execute([(int)$ticket['id'], (int)$user['id'], $message]);
            notify_admins($pdo, 'New reply on ' . $ticket['ticket_no'] . ': "' . truncate($message, 60) . '"', (int)$ticket['id']);
            flash_set('success', 'Your reply was posted.');
            redirect('ticket.php?id=' . $ticket['id']);
        }
    }
}

$comments = fetch_comments($pdo, (int)$ticket['id'], false);
$statusOrder = ['pending' => 0, 'under_review' => 1, 'resolved' => 2];
$currentStep = $statusOrder[$ticket['status']] ?? 0;

require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="dashboard.php">Back to dashboard</a>

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

<?php if ((int)$ticket['is_anonymous'] === 1): ?>
  <div class="alert alert-info">
    <span>This ticket was submitted anonymously. Admins cannot see your identity, but they can still reply here.</span>
    <button class="alert-close" aria-label="Dismiss">&times;</button>
  </div>
<?php endif; ?>

<!-- Status timeline -->
<div class="card timeline">
  <?php foreach (STATUS_LABELS as $key => $label):
      $step = $statusOrder[$key] ?? 0;
      $done = $step <= $currentStep;
      $current = $step === $currentStep; ?>
  <div class="timeline-step <?= $done ? 'done' : '' ?> <?= $current ? 'current' : '' ?>">
    <span class="timeline-dot"></span>
    <div>
      <div class="timeline-label"><?= e($label) ?></div>
      <?php if ($current && $ticket['status'] === 'pending'): ?>
        <div class="timeline-desc">Just submitted</div>
      <?php elseif ($current && $ticket['status'] === 'under_review'): ?>
        <div class="timeline-desc">Acknowledged by the admin</div>
      <?php elseif ($current && $ticket['status'] === 'resolved'): ?>
        <div class="timeline-desc">Issue has been addressed.</div>
      <?php else: ?>
        <div class="timeline-desc">&nbsp;</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="ticket-grid">
  <div class="ticket-col-main">
    <!-- Description -->
    <div class="card panel">
      <h2 class="panel-title">Description</h2>
      <p class="panel-text"><?= nl2br(e($ticket['description'])) ?></p>

      <?php if ($ticket['image_path']): ?>
        <figure class="attachment">
          <img src="uploads/<?= e($ticket['image_path']) ?>" alt="Attachment" class="attachment-img"
               onclick="window.open(this.src,'_blank')">
          <figcaption>Attachment — click to enlarge</figcaption>
        </figure>
      <?php endif; ?>
    </div>

    <!-- Comments -->
    <div class="card panel">
      <h2 class="panel-title">Discussion (<?= count($comments) ?>)</h2>

      <?php if (empty($comments)): ?>
        <p class="panel-empty">No comments yet. The admin will reply here once your concern is reviewed.</p>
      <?php else: ?>
        <div class="comment-list">
          <?php foreach ($comments as $c): ?>
          <div class="comment <?= $c['role'] === 'admin' ? 'comment-admin' : '' ?>">
            <span class="avatar"><?= e(initials($c['name'])) ?></span>
            <div class="comment-body">
              <div class="comment-meta">
                <strong><?= e($c['name']) ?></strong>
                <?php if ($c['role'] === 'admin'): ?><span class="badge badge-admin">Admin</span><?php endif; ?>
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
    <div class="card panel">
      <h2 class="panel-title">Add a reply</h2>
      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <textarea name="message" class="input input-textarea" rows="3" maxlength="2000"
                  placeholder="Type your reply…" required></textarea>
        <div class="form-actions form-actions-left">
          <button type="submit" class="btn btn-primary">Post reply</button>
        </div>
      </form>
    </div>
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
