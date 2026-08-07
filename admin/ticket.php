<?php
// ============================================================================
//  Admin — Ticket Detail & Actions (Phase 5)
//  Change status, assign to a department, toggle public board visibility,
//  leave public/private comments, delete ticket.
// ============================================================================
require_once __DIR__ . '/../config.php';
require_admin();

$base      = '../';
$pageTitle = 'Manage Ticket';
$active    = 'admin';

$id     = (int)($_GET['id'] ?? 0);
$ticket = find_feedback($pdo, $id);

if ($ticket === null) {
    flash_set('error', 'Ticket not found.');
    redirect('tickets.php');
}

// ---- Handle actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = (string)($_POST['action'] ?? '');

        switch ($action) {
            case 'status':
                $newStatus = (string)($_POST['status'] ?? '');
                if (array_key_exists($newStatus, STATUS_LABELS) && $newStatus !== $ticket['status']) {
                    $upd = $pdo->prepare('UPDATE feedbacks SET status = ? WHERE id = ?');
                    $upd->execute([$newStatus, (int)$ticket['id']]);
                    notify_user(
                        $pdo,
                        (int)$ticket['user_id'],
                        'Your ticket ' . $ticket['ticket_no'] . ' is now "' . STATUS_LABELS[$newStatus] . '".',
                        (int)$ticket['id']
                    );
                    flash_set('success', 'Status updated to "' . STATUS_LABELS[$newStatus] . '".');
                }
                break;

            case 'assign':
                $newDept = trim((string)($_POST['assigned_dept'] ?? ''));
                if ($newDept === '') {
                    $upd = $pdo->prepare('UPDATE feedbacks SET assigned_dept = NULL WHERE id = ?');
                    $upd->execute([(int)$ticket['id']]);
                    flash_set('info', 'Ticket unassigned.');
                } elseif (valid_department($newDept)) {
                    $upd = $pdo->prepare('UPDATE feedbacks SET assigned_dept = ? WHERE id = ?');
                    $upd->execute([$newDept, (int)$ticket['id']]);
                    notify_user(
                        $pdo,
                        (int)$ticket['user_id'],
                        'Your ticket ' . $ticket['ticket_no'] . ' was assigned to ' . $newDept . '.',
                        (int)$ticket['id']
                    );
                    flash_set('success', 'Ticket assigned to ' . $newDept . '.');
                }
                break;

            case 'visibility':
                $newPublic = (int)$ticket['is_public'] === 1 ? 0 : 1;
                $upd = $pdo->prepare('UPDATE feedbacks SET is_public = ? WHERE id = ?');
                $upd->execute([$newPublic, (int)$ticket['id']]);
                flash_set('success', $newPublic ? 'Ticket is now visible on the Public Board.' : 'Ticket removed from the Public Board.');
                break;

            case 'comment':
                $message    = trim((string)($_POST['message'] ?? ''));
                $visibility = (string)($_POST['visibility'] ?? 'public');
                if ($message === '') {
                    flash_set('error', 'Please type a comment first.');
                } elseif (strlen($message) > 2000) {
                    flash_set('error', 'Comments must be 2000 characters or less.');
                } else {
                    $visibility = $visibility === 'private' ? 'private' : 'public';
                    $stmt = $pdo->prepare(
                        'INSERT INTO comments (feedback_id, user_id, message, visibility) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([(int)$ticket['id'], (int)$_SESSION['user']['id'], $message, $visibility]);

                    if ($visibility === 'public') {
                        notify_user(
                            $pdo,
                            (int)$ticket['user_id'],
                            'New reply from the admin on ' . $ticket['ticket_no'] . '.',
                            (int)$ticket['id']
                        );
                    }
                    flash_set('success', $visibility === 'private' ? 'Private note saved (only admins can see it).' : 'Public reply posted.');
                }
                break;

            case 'delete':
                // Remove the attached image file (if any) to avoid orphans.
                if (!empty($ticket['image_path']) && is_file(UPLOAD_DIR . '/' . $ticket['image_path'])) {
                    @unlink(UPLOAD_DIR . '/' . $ticket['image_path']);
                }
                $del = $pdo->prepare('DELETE FROM feedbacks WHERE id = ?');
                $del->execute([(int)$ticket['id']]);
                flash_set('success', 'Ticket ' . $ticket['ticket_no'] . ' deleted.');
                redirect('tickets.php');
                break;
        }
    }

    // Re-fetch and refresh
    $ticket = find_feedback($pdo, $id);
    if ($ticket !== null) {
        redirect('ticket.php?id=' . $ticket['id']);
    }
    redirect('tickets.php');
}

$comments = fetch_comments($pdo, (int)$ticket['id'], true);

require __DIR__ . '/../includes/header.php';
?>

<a class="back-link" href="tickets.php">Back to ticket list</a>

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
       · Updated <?= e(time_ago($ticket['updated_at'])) ?>
       <?php if ($ticket['assigned_dept']): ?>· Assigned to <strong><?= e($ticket['assigned_dept']) ?></strong><?php endif; ?>
    </p>
  </div>
</div>

<div class="ticket-grid">
  <div class="ticket-col-main">
    <!-- Reporter -->
    <div class="card panel">
      <h2 class="panel-title">Reporter</h2>
      <?php if ((int)$ticket['is_anonymous'] === 1): ?>
        <div class="alert alert-info">
          <span><strong>Anonymous submission.</strong> The reporter's identity is hidden per the anonymous reporting policy. The account is still securely linked for tracking.</span>
          <button class="alert-close" aria-label="Dismiss">&times;</button>
        </div>
      <?php else: ?>
        <div class="reporter">
          <span class="avatar avatar-lg"><?= e(initials($ticket['name'])) ?></span>
          <div>
            <div class="reporter-name"><?= e($ticket['name']) ?></div>
            <div class="reporter-meta"><?= e($ticket['email']) ?></div>
            <div class="reporter-meta"><?= e($ticket['user_department']) ?> · ID <?= e($ticket['id_number']) ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Description -->
    <div class="card panel">
      <h2 class="panel-title">Description</h2>
      <p class="panel-text"><?= nl2br(e($ticket['description'])) ?></p>
      <?php if ($ticket['image_path']): ?>
        <figure class="attachment">
          <img src="<?= e($base) ?>uploads/<?= e($ticket['image_path']) ?>" alt="Attachment" class="attachment-img"
               onclick="window.open(this.src,'_blank')">
          <figcaption>Attachment — click to enlarge</figcaption>
        </figure>
      <?php endif; ?>
    </div>

    <!-- Comments -->
    <div class="card panel">
      <h2 class="panel-title">Discussion (<?= count($comments) ?>)</h2>
      <?php if (empty($comments)): ?>
        <p class="panel-empty">No comments yet.</p>
      <?php else: ?>
        <div class="comment-list">
          <?php foreach ($comments as $c): ?>
          <div class="comment <?= $c['role'] === 'admin' ? 'comment-admin' : '' ?> <?= $c['visibility'] === 'private' ? 'comment-private' : '' ?>">
            <span class="avatar"><?= e(initials($c['name'])) ?></span>
            <div class="comment-body">
              <div class="comment-meta">
                <strong><?= e($c['name']) ?></strong>
                <?php if ($c['role'] === 'admin'): ?><span class="badge badge-admin">Admin</span><?php endif; ?>
                <?php if ($c['visibility'] === 'private'): ?><span class="badge badge-private">Private note</span><?php endif; ?>
                <span class="comment-time"><?= e(time_ago($c['created_at'])) ?></span>
              </div>
              <p class="comment-text"><?= nl2br(e($c['message'])) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Add comment -->
    <div class="card panel">
      <h2 class="panel-title">Add a comment</h2>
      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="comment">
        <textarea name="message" class="input input-textarea" rows="3" maxlength="2000"
                  placeholder="Reply to the reporter or leave a note…" required></textarea>
        <div class="form-row form-row-align">
          <div class="radio-inline">
            <label class="radio-inline-item">
              <input type="radio" name="visibility" value="public" checked> Public reply (user sees it)
            </label>
            <label class="radio-inline-item">
              <input type="radio" name="visibility" value="private"> Private note (admins only)
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Post</button>
        </div>
      </form>
    </div>
  </div>

  <div class="ticket-col-side">
    <!-- Actions -->
    <div class="card panel">
      <h2 class="panel-title">Actions</h2>

      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form form-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <label for="status" class="form-label-sm">Status</label>
        <div class="form-row">
          <select name="status" id="status" class="input">
            <?php foreach (STATUS_LABELS as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $ticket['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>

      <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>" class="form form-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <label for="assigned_dept" class="form-label-sm">Assign to office</label>
        <div class="form-row">
          <select name="assigned_dept" id="assigned_dept" class="input">
            <option value="">— Unassigned —</option>
            <?php foreach (DEPARTMENTS as $d): ?>
              <option value="<?= e($d) ?>" <?= $ticket['assigned_dept'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary">Assign</button>
        </div>
      </form>

      <div class="form-inline">
        <span class="form-label-sm">Public Board</span>
        <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="visibility">
          <button type="submit" class="btn <?= (int)$ticket['is_public'] === 1 ? 'btn-outline' : 'btn-primary' ?>">
            <?= (int)$ticket['is_public'] === 1 ? 'Remove from Public Board' : 'Make public' ?>
          </button>
        </form>
      </div>

      <div class="form-inline">
        <span class="form-label-sm">Danger zone</span>
        <form method="post" action="ticket.php?id=<?= (int)$ticket['id'] ?>"
              data-confirm="Delete this ticket permanently? This cannot be undone.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-danger">Delete ticket</button>
        </form>
      </div>
    </div>

    <!-- Meta -->
    <div class="card panel">
      <h2 class="panel-title">Ticket info</h2>
      <dl class="meta-list">
        <div><dt>Category</dt><dd><?= e($ticket['category']) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(STATUS_LABELS[$ticket['status']] ?? $ticket['status']) ?></dd></div>
        <div><dt>Assigned to</dt><dd><?= $ticket['assigned_dept'] ? e($ticket['assigned_dept']) : '—' ?></dd></div>
        <div><dt>Anonymous</dt><dd><?= (int)$ticket['is_anonymous'] === 1 ? 'Yes' : 'No' ?></dd></div>
        <div><dt>Public</dt><dd><?= (int)$ticket['is_public'] === 1 ? 'Yes' : 'No' ?></dd></div>
        <div><dt>Submitted</dt><dd><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></dd></div>
      </dl>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
