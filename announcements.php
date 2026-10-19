<?php
// ============================================================================
//  Campus Announcements — Super Admin Broadcast
//  Super Admin can create, manage, and delete campus-wide announcements.
//  All users (students, faculty, staff, dept heads) see these announcements.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

$base      = '';
$pageTitle = 'Campus Announcements';
$active    = 'announcements';
$user      = current_user();
$isSuper   = is_super_admin();

$errors = [];
$old = ['title' => '', 'badge' => 'Campus Announcement', 'content' => ''];

// Handle new announcement or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isSuper) {
        flash_set('error', 'Only the Super Administrator can post or delete campus announcements.');
        redirect('announcements.php');
    }

    if (!csrf_verify()) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('announcements.php');
    }

    $action = (string)($_POST['action'] ?? 'create');

    if ($action === 'delete') {
        $annId = (int)($_POST['announcement_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
        $del->execute([$annId]);
        flash_set('success', 'Announcement deleted successfully.');
        redirect('announcements.php');
    }

    if ($action === 'create') {
        $old = [
            'title'   => trim((string)($_POST['title'] ?? '')),
            'badge'   => trim((string)($_POST['badge'] ?? 'Campus Announcement')),
            'content' => trim((string)($_POST['content'] ?? '')),
        ];

        if ($old['title'] === '') {
            $errors[] = 'Please provide an announcement headline / title.';
        } elseif (strlen($old['title']) > 180) {
            $errors[] = 'Title must be 180 characters or less.';
        } elseif (ProfanityFilter::containsBadWord($old['title'])) {
            $bad = ProfanityFilter::findBadWords($old['title']);
            $errors[] = 'The announcement title contains inappropriate words (' . implode(', ', $bad) . ').';
        }

        if ($old['content'] === '') {
            $errors[] = 'Please enter the announcement details.';
        } elseif (strlen($old['content']) > 5000) {
            $errors[] = 'Content must be 5000 characters or less.';
        } elseif (ProfanityFilter::containsBadWord($old['content'])) {
            $bad = ProfanityFilter::findBadWords($old['content']);
            $errors[] = 'The announcement content contains inappropriate words (' . implode(', ', $bad) . ').';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO announcements (user_id, title, badge, content) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([(int)$user['id'], $old['title'], $old['badge'], $old['content']]);

            // Notify all other users about this new campus announcement
            $notifMsg = 'New campus announcement: "' . truncate($old['title'], 50) . '"';
            foreach ($pdo->query('SELECT id FROM users WHERE id <> ' . (int)$user['id']) as $uRow) {
                notify_user($pdo, (int)$uRow['id'], $notifMsg, null);
            }

            flash_set('success', 'Campus announcement published to all users!');
            redirect('announcements.php');
        }
    }
}

// Fetch all announcements
$announcements = $pdo->query(
    'SELECT a.*, u.name AS author_name, u.role AS author_role
     FROM announcements a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC, a.id DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">Campus Announcements</h1>
    <p class="page-sub">
      <?= $isSuper ? 'Broadcast official advisories, policy updates, and notices to every student, faculty, and staff.' : 'Official notices and updates from the School Administration.' ?>
    </p>
  </div>
  <?php if ($isSuper): ?>
  <a class="btn btn-outline" href="admin/dashboard.php">Go to Admin Panel</a>
  <?php endif; ?>
</div>

<div class="ticket-grid">
  <div class="ticket-col-main">
    <?php if (empty($announcements)): ?>
      <div class="card empty-state">
        <div class="empty-emoji">
          <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </div>
        <h3>No announcements yet</h3>
        <p><?= $isSuper ? 'Use the form on the side to publish the first official campus announcement.' : 'Check back later for school advisories and official administrative announcements.' ?></p>
      </div>
    <?php else: ?>
      <div class="feed-list">
        <?php foreach ($announcements as $ann): ?>
        <article class="card feed-item" style="border-left: 4px solid var(--primary, #059669); margin-bottom: 1.25rem;">
          <div class="feed-top" style="display:flex; justify-content:space-between; align-items:center;">
            <span class="badge badge-admin" style="background:#059669; color:#fff; font-weight:600;">
              <?= e($ann['badge'] ?: 'Campus Announcement') ?>
            </span>
            <span style="font-size:0.8rem; color:var(--text-muted, #64748b);">
              <?= e(time_ago($ann['created_at'])) ?>
            </span>
          </div>

          <h2 style="font-size:1.25rem; font-weight:700; margin:0.6rem 0 0.5rem 0; color:var(--text-main, #0f172a);">
            <?= e($ann['title']) ?>
          </h2>

          <div class="feed-text" style="font-size:0.95rem; line-height:1.6; color:var(--text-muted, #334155); margin-bottom:1rem; white-space:pre-wrap;">
            <?= e($ann['content']) ?>
          </div>

          <div class="board-card-meta" style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border-light, #f1f5f9); padding-top:0.75rem;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
              <span class="avatar avatar-sm"><?= e(initials($ann['author_name'])) ?></span>
              <span style="font-size:0.85rem; font-weight:600;"><?= e($ann['author_name']) ?></span>
              <span class="badge badge-neutral" style="font-size:0.75rem;">Super Admin</span>
            </div>

            <?php if ($isSuper): ?>
            <form method="post" action="announcements.php" data-confirm="Delete this announcement? All users will no longer see it.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="announcement_id" value="<?= (int)$ann['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($isSuper): ?>
  <div class="ticket-col-side">
    <div class="card panel">
      <h2 class="panel-title">Post New Announcement</h2>
      <p class="panel-sub" style="font-size:0.85rem; color:var(--text-muted, #64748b); margin-bottom:1rem;">
        Publish an official announcement visible to all campus users on their dashboard and public boards.
      </p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <ul style="margin:0; padding-left:1.2rem;">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="announcements.php" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-group">
          <label for="ann_badge">Announcement Tag / Type</label>
          <select name="badge" id="ann_badge" class="input">
            <option value="Campus Advisory" <?= $old['badge'] === 'Campus Advisory' ? 'selected' : '' ?>>Campus Advisory</option>
            <option value="Campus Announcement" <?= $old['badge'] === 'Campus Announcement' ? 'selected' : '' ?>>Campus Announcement</option>
            <option value="System Maintenance" <?= $old['badge'] === 'System Maintenance' ? 'selected' : '' ?>>System Maintenance</option>
            <option value="Facility Advisory" <?= $old['badge'] === 'Facility Advisory' ? 'selected' : '' ?>>Facility Advisory</option>
            <option value="Holiday / Suspension" <?= $old['badge'] === 'Holiday / Suspension' ? 'selected' : '' ?>>Holiday / Suspension</option>
          </select>
        </div>

        <div class="form-group">
          <label for="ann_title">Headline / Title</label>
          <input type="text" name="title" id="ann_title" class="input"
                 placeholder="e.g. Campus Cafeteria Maintenance Schedule"
                 value="<?= e($old['title']) ?>" maxlength="180" required>
        </div>

        <div class="form-group">
          <label for="ann_content">Details / Body</label>
          <textarea name="content" id="ann_content" class="input input-textarea" rows="6"
                    placeholder="Provide full announcement details here for students, faculty, and staff…"
                    maxlength="5000" required><?= e($old['content']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-block">+ Broadcast to All Users</button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="ticket-col-side">
    <div class="card panel">
      <h2 class="panel-title">Official Board</h2>
      <p style="font-size:0.875rem; color:var(--text-muted, #64748b); line-height:1.5;">
        Announcements posted here are verified and released by the <strong>Super Administrator / School Executive Office</strong>.
      </p>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
