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

// On public tickets, any logged-in user can join the discussion.
// On private tickets, only the author and assigned staff/admins have access.
$canView = ((int)$ticket['is_public'] === 1) || $isAuthor || $isSuper || $isAdminOfField || $isStaffOfField;

if (!$canView) {
    flash_set('error', 'You do not have permission to view this ticket.');
    redirect('dashboard.php');
}

$canComment = $canView; // Any user who can view can comment/reply

$canManageStatus = can_manage_ticket_status($user, $ticket);

// Handle status updates or comments/replies
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('ticket.php?id=' . $ticket['id']);
    }

    $action = (string)($_POST['action'] ?? 'comment');

    // 1. Target Field / Responder Status Management
    if ($action === 'update_status') {
        if (!$canManageStatus) {
            flash_set('error', 'You do not have permission to manage the status of this ticket.');
            redirect('ticket.php?id=' . $ticket['id']);
        }

        $newStatus = (string)($_POST['status'] ?? '');
        // Field responders can manage normal lifecycle: pending, under_review, resolved
        $allowedStatuses = ['pending', 'under_review', 'resolved'];
        if (is_super_admin()) {
            $allowedStatuses[] = 'rejected';
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash_set('error', 'Invalid status selection.');
            redirect('ticket.php?id=' . $ticket['id']);
        }

        if ($newStatus !== $ticket['status']) {
            $upd = $pdo->prepare('UPDATE feedbacks SET status = ? WHERE id = ?');
            $upd->execute([$newStatus, (int)$ticket['id']]);

            $deptName = $user['department'] ?: role_label($user['role']);
            notify_user(
                $pdo,
                (int)$ticket['user_id'],
                'Your ticket ' . $ticket['ticket_no'] . ' status was updated to "' . (STATUS_LABELS[$newStatus] ?? $newStatus) . '" by ' . $deptName . '.',
                (int)$ticket['id']
            );

            flash_set('success', 'Ticket status updated to "' . (STATUS_LABELS[$newStatus] ?? $newStatus) . '".');
        }
        redirect('ticket.php?id=' . $ticket['id']);
    }

    // 2. Comments / replies
    if (!$canComment) {
        flash_set('error', 'You do not have permission to reply on this ticket.');
        redirect('ticket.php?id=' . $ticket['id']);
    }

    $message = trim((string)($_POST['message'] ?? ''));
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    // Verify parent_id belongs to this feedback
    if ($parentId !== null) {
        $checkParent = $pdo->prepare('SELECT id, user_id FROM comments WHERE id = ? AND feedback_id = ?');
        $checkParent->execute([$parentId, (int)$ticket['id']]);
        $parentRow = $checkParent->fetch();
        if (!$parentRow) {
            $parentId = null;
        }
    }

    if ($message === '') {
        flash_set('error', 'Please type a message first.');
    } elseif (strlen($message) > 2000) {
        flash_set('error', 'Messages must be 2000 characters or less.');
    } elseif (ProfanityFilter::containsBadWord($message)) {
        $badWords = ProfanityFilter::findBadWords($message);
        flash_set('error', 'Your comment contains inappropriate or explicit language (' . implode(', ', $badWords) . '). Please keep campus discussions respectful.');
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO comments (feedback_id, parent_id, user_id, message, visibility) VALUES (?, ?, ?, ?, 'public')"
        );
        $stmt->execute([(int)$ticket['id'], $parentId, (int)$user['id'], $message]);

        // Notification dispatch:
        if ($parentId !== null && isset($parentRow['user_id']) && (int)$parentRow['user_id'] !== (int)$user['id']) {
            // User replied to a specific comment -> notify comment author
            notify_user(
                $pdo,
                (int)$parentRow['user_id'],
                $user['name'] . ' replied to your comment on ' . $ticket['ticket_no'] . ': "' . truncate($message, 50) . '"',
                (int)$ticket['id']
            );
        } elseif ($isAuthor) {
            notify_admins($pdo, 'New reply on ' . $ticket['ticket_no'] . ': "' . truncate($message, 60) . '"', (int)$ticket['id']);
        } elseif ((int)$ticket['user_id'] !== (int)$user['id']) {
            $roleBadge = ($isAdminOfField || $isStaffOfField || $isSuper) ? ' (Official Response)' : '';
            notify_user(
                $pdo,
                (int)$ticket['user_id'],
                'New reply on ' . $ticket['ticket_no'] . ' from ' . $user['name'] . $roleBadge . ': "' . truncate($message, 60) . '"',
                (int)$ticket['id']
            );
        }

        flash_set('success', $parentId !== null ? 'Your reply was posted.' : 'Your comment was posted.');
        redirect('ticket.php?id=' . $ticket['id'] . '#comments');
    }
}

$rawComments = fetch_comments($pdo, (int)$ticket['id'], false);
$commentTree = build_comment_tree($rawComments);
$totalCommentsCount = count($rawComments);

// Support status for current user & total count
$supportCount = get_support_count($pdo, (int)$ticket['id']);
$hasSupported = user_has_supported($pdo, (int)$ticket['id'], (int)$user['id']);
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

<div class="ticket-head" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
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

  <!-- Reddit-style Support Button -->
  <div class="ticket-actions-head">
    <button type="button" 
            class="btn-support <?= $hasSupported ? 'is-supported' : '' ?>" 
            id="supportBtn" 
            data-id="<?= (int)$ticket['id'] ?>"
            title="<?= $hasSupported ? 'You support this concern (click to withdraw)' : 'Click to support this concern' ?>">
      <svg class="support-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 19V5M5 12l7-7 7 7"/>
      </svg>
      <span class="support-label"><?= $hasSupported ? 'Supported' : 'Support' ?></span>
      <span class="support-count" id="supportCount"><?= $supportCount ?></span>
    </button>
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

<?php if ($canManageStatus && !$isRejected): ?>
  <!-- Field Responder Status Management Bar -->
  <div class="responder-action-bar">
    <div class="responder-action-info">
      <div class="responder-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
      </div>
      <div>
        <span class="responder-badge-pill"><?= e(role_label($user['role'])) ?> Control</span>
        <h3 class="responder-title">Department Status Action</h3>
        <p class="responder-desc">You have responder authority over this <strong><?= e($ticket['category']) ?></strong> concern. Advance its operational status below.</p>
      </div>
    </div>

    <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="responder-controls">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_status">
      <select name="status" class="responder-select" aria-label="Select ticket status">
        <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
        <option value="under_review" <?= $ticket['status'] === 'under_review' ? 'selected' : '' ?>>🔍 Under Review</option>
        <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>✅ Resolved</option>
        <?php if (is_super_admin()): ?>
          <option value="rejected" <?= $ticket['status'] === 'rejected' ? 'selected' : '' ?>>🚫 Rejected (Moderation)</option>
        <?php endif; ?>
      </select>
      <button type="submit" class="btn-responder-update">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Update Status
      </button>
    </form>
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

    <!-- Discussion / Comments Tree -->
    <div class="card panel" id="comments">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h2 class="panel-title" style="margin-bottom:0;">Discussion (<?= $totalCommentsCount ?>)</h2>
        <span style="font-size:0.85rem; color:var(--text-muted, #64748b);">
          <?= ((int)$ticket['is_public'] === 1) ? 'Open public discussion' : 'Private concern thread' ?>
        </span>
      </div>

      <?php if (empty($commentTree)): ?>
        <p class="panel-empty">No comments yet. Join the conversation below.</p>
      <?php else: ?>
        <?php
        // Recursive renderer for Reddit-style threaded comments
        function render_comment_tree(array $branch, array $ticket, array $currentUser, int $depth = 0) {
            $isAssignedDept = $ticket['assigned_dept'] ?? '';
            foreach ($branch as $c):
                $isSuperAdmin = ($c['role'] === 'super_admin');
                $isDeptHead   = ($c['role'] === 'admin');
                $isStaff      = ($c['role'] === 'staff');
                $isAuthor     = ((int)$c['user_id'] === (int)$ticket['user_id']);
                
                // Highlight assigned staff / dept head
                $isAssignedStaff = false;
                if ($isDeptHead || $isStaff) {
                    if (!empty($c['department']) && !empty($ticket['assigned_dept'])) {
                        if (stripos($c['department'], $ticket['assigned_dept']) !== false || stripos($ticket['assigned_dept'], $c['department']) !== false) {
                            $isAssignedStaff = true;
                        }
                    } else {
                        $isAssignedStaff = true;
                    }
                }
                
                $officialClass = '';
                if ($isSuperAdmin) {
                    $officialClass = 'comment-superadmin';
                } elseif ($isAssignedStaff) {
                    $officialClass = 'comment-assigned-staff';
                } elseif ($isDeptHead || $isStaff) {
                    $officialClass = 'comment-official';
                }
        ?>
          <div class="comment-thread <?= $depth > 0 ? 'comment-reply-level' : '' ?>" id="comment-<?= (int)$c['id'] ?>">
            <div class="comment <?= $officialClass ?>">
              <span class="avatar avatar-sm"><?= e(initials($c['name'])) ?></span>
              <div class="comment-body">
                <div class="comment-meta">
                  <strong><?= e($c['name']) ?></strong>

                  <?php if ($isSuperAdmin): ?>
                    <span class="badge badge-admin badge-glow">Super Admin</span>
                  <?php elseif ($isAssignedStaff): ?>
                    <span class="badge badge-staff-prio">Assigned Personnel</span>
                  <?php elseif ($isDeptHead): ?>
                    <span class="badge badge-admin">Department Head</span>
                  <?php elseif ($isStaff): ?>
                    <span class="badge badge-admin" style="background:#0284c7; color:#fff;">Staff</span>
                  <?php elseif ($c['role'] === 'faculty'): ?>
                    <span class="badge badge-neutral">Faculty</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Student</span>
                  <?php endif; ?>

                  <?php if ($isAuthor): ?>
                    <span class="badge badge-op">Author</span>
                  <?php endif; ?>

                  <span class="comment-time"><?= e(time_ago($c['created_at'])) ?></span>
                </div>

                <p class="comment-text"><?= nl2br(e($c['message'])) ?></p>

                <!-- Action row: Reply toggle -->
                <div class="comment-actions">
                  <button type="button" class="btn-reply-toggle" onclick="toggleReplyBox(<?= (int)$c['id'] ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                    Reply
                  </button>
                </div>

                <!-- Hidden inline reply box -->
                <div class="reply-form-wrap" id="reply-form-<?= (int)$c['id'] ?>" style="display:none; margin-top:10px;">
                  <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>">
                    <textarea name="message" class="input input-textarea" rows="2" maxlength="2000"
                              placeholder="Write a reply to <?= e(first_name($c['name'])) ?>…" required></textarea>
                    <div style="display:flex; gap:8px; margin-top:8px;">
                      <button type="submit" class="btn btn-primary btn-sm">Submit Reply</button>
                      <button type="button" class="btn btn-ghost btn-sm" onclick="toggleReplyBox(<?= (int)$c['id'] ?>)">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Nested child replies -->
            <?php if (!empty($c['replies'])): ?>
              <div class="comment-replies-wrap">
                <?php render_comment_tree($c['replies'], $ticket, $currentUser, $depth + 1); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php
            endforeach;
        }
        ?>

        <div class="comment-list comment-tree-root">
          <?php render_comment_tree($commentTree, $ticket, $user, 0); ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Main Thread Reply / Comment Box -->
    <div class="card panel">
      <h2 class="panel-title">Leave a comment</h2>
      <p class="panel-sub" style="font-size:0.85rem;color:var(--text-muted, #64748b);margin-bottom:0.75rem;">
        <?php if ($isAuthor): ?>
          Direct communication channel with assigned personnel and the school community.
        <?php elseif (in_array($user['role'], ['admin', 'super_admin', 'staff'], true)): ?>
          Replying as official campus personnel (<strong><?= e(role_label($user['role'])) ?></strong>).
        <?php else: ?>
          Commenting as <strong><?= e($user['name']) ?></strong> (<?= e(role_label($user['role'])) ?>).
        <?php endif; ?>
      </p>
      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <textarea name="message" class="input input-textarea" rows="3" maxlength="2000"
                  placeholder="Type your comment or update on this concern…" required></textarea>
        <div class="form-actions form-actions-left">
          <button type="submit" class="btn btn-primary">Post comment</button>
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

<script>
function toggleReplyBox(commentId) {
  const box = document.getElementById('reply-form-' + commentId);
  if (!box) return;
  if (box.style.display === 'none' || box.style.display === '') {
    box.style.display = 'block';
    const textarea = box.querySelector('textarea');
    if (textarea) textarea.focus();
  } else {
    box.style.display = 'none';
  }
}

// Support button AJAX toggle
document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('supportBtn');
  if (!btn) return;

  btn.addEventListener('click', function() {
    const feedbackId = this.dataset.id;
    const countEl = document.getElementById('supportCount');
    const labelEl = this.querySelector('.support-label');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '<?= e(csrf_token()) ?>';

    btn.disabled = true;
    btn.style.opacity = '0.7';

    fetch('support_toggle.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ feedback_id: feedbackId, csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.style.opacity = '1';
      if (data.success) {
        if (countEl) countEl.textContent = data.count;
        if (data.supported) {
          btn.classList.add('is-supported');
          if (labelEl) labelEl.textContent = 'Supported';
          btn.title = 'You support this concern (click to withdraw)';
        } else {
          btn.classList.remove('is-supported');
          if (labelEl) labelEl.textContent = 'Support';
          btn.title = 'Click to support this concern';
        }
      } else {
        alert(data.error || 'Failed to update support.');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.style.opacity = '1';
      console.error(err);
    });
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
