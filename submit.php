<?php
// ============================================================================
//  Submit Feedback (Phase 3 — Core Feature)
//  Category, subject, description, optional photo, anonymous toggle.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

// Redirect to My Activity combined hub
header('Location: my-activity.php#submit-form');
exit;

// User's ticket counts for the summary stat cards
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS c FROM feedbacks WHERE user_id = ? GROUP BY status');
$stmt->execute([(int)$user['id']]);
$counts = ['pending' => 0, 'under_review' => 0, 'resolved' => 0];
foreach ($stmt as $row) {
    $counts[$row['status']] = (int)$row['c'];
}
$total = array_sum($counts);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'category'      => trim((string)($_POST['category'] ?? '')),
        'title'         => trim((string)($_POST['title'] ?? '')),
        'description'   => trim((string)($_POST['description'] ?? '')),
        'is_anonymous'  => isset($_POST['is_anonymous']) ? 1 : 0,
    ];

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!valid_category($old['category'])) {
        $errors[] = 'Please choose a category for your concern.';
    }
    if ($old['title'] === '') {
        $errors[] = 'Please provide a subject / title.';
    } elseif (strlen($old['title']) > 160) {
        $errors[] = 'The subject must be 160 characters or less.';
    } elseif (ProfanityFilter::containsBadWord($old['title'])) {
        $badWords = ProfanityFilter::findBadWords($old['title']);
        $errors[] = 'The title contains explicit or inappropriate language (' . implode(', ', $badWords) . '). Please keep campus feedback constructive and respectful.';
    }
    if ($old['description'] === '') {
        $errors[] = 'Please describe your concern in detail.';
    } elseif (strlen($old['description']) > 5000) {
        $errors[] = 'The description must be 5000 characters or less.';
    } elseif (ProfanityFilter::containsBadWord($old['description'])) {
        $badWords = ProfanityFilter::findBadWords($old['description']);
        $errors[] = 'The description contains explicit or inappropriate language (' . implode(', ', $badWords) . '). Please keep campus feedback constructive and respectful.';
    }

    // Optional media attachments (images / videos / audio, up to MAX_FILES_PER_POST)
    $attachments   = [];      // [ ['path'=>.., 'kind'=>..], .. ]
    $invalidUploads = $_FILES['attachments'] ?? [];
    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $fileCount = count($_FILES['attachments']['name']);
        if ($fileCount > MAX_FILES_PER_POST) {
            $errors[] = 'You can attach up to ' . MAX_FILES_PER_POST . ' files per post.';
        }
        for ($i = 0; $i < min($fileCount, MAX_FILES_PER_POST); $i++) {
            $one = [
                'name'     => $_FILES['attachments']['name'][$i],
                'type'     => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error'    => $_FILES['attachments']['error'][$i],
                'size'     => $_FILES['attachments']['size'][$i],
            ];
            $kind = null;
            $up  = handle_media_upload($one, $kind);
            if (is_array($up)) {
                $errors = array_merge($errors, $up);
            } elseif ($up !== null) {
                $attachments[] = ['path' => $up, 'kind' => $kind];
            }
        }
    }

    if (empty($errors)) {
        $ticketNo = next_ticket_no($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO feedbacks (ticket_no, user_id, category, title, description, is_anonymous)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $ticketNo,
                (int)$user['id'],
                $old['category'],
                $old['title'],
                $old['description'],
                $old['is_anonymous'],
            ]);
            $feedbackId = (int)$pdo->lastInsertId();

            $attStmt = $pdo->prepare(
                'INSERT INTO feedback_attachments (feedback_id, file_path, media_kind) VALUES (?, ?, ?)'
            );
            foreach ($attachments as $a) {
                $attStmt->execute([$feedbackId, $a['path'], $a['kind']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // Clean up any files saved during this request
            foreach ($attachments as $a) {
                @unlink(UPLOAD_DIR . '/' . $a['path']);
            }
            error_log('Feedback insert failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong saving your post: ' . $e->getMessage();
        }

        if (empty($errors)) {
            notify_admins(
                $pdo,
                'New ' . ($old['is_anonymous'] ? 'anonymous ' : '') . 'feedback submitted: ' . $ticketNo,
                $feedbackId
            );

            flash_set('success', 'Feedback submitted! Your ticket number is ' . $ticketNo . '. You can track it from your dashboard.');
            redirect('ticket.php?id=' . $feedbackId);
        }
    }
}

$svgIcon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%s</svg>';
$categoryIcons = [
    'Facility / Maintenance' => sprintf($svgIcon, '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'),
    'Academic'               => sprintf($svgIcon, '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'),
    'Environment / Safety'   => sprintf($svgIcon, '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'),
    'Social / Behavioral'    => sprintf($svgIcon, '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'),
    'Others'                 => sprintf($svgIcon, '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>'),
];

require __DIR__ . '/includes/header.php';
?>

<div class="dash-head">
  <div>
    <h1 class="page-title">Submit Feedback</h1>
    <p class="page-sub">Help us improve the campus. Every concern is read and tracked.</p>
  </div>
</div>

<!-- Dashboard Summary Cards -->
<div class="stat-grid" style="margin-bottom: 24px;">
  <div class="card stat-card">
    <span class="stat-icon stat-icon-total"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg></span>
    <div>
      <div class="stat-number"><?= $total ?></div>
      <div class="stat-label">Total submitted</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-pending"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['pending'] ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-review"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['under_review'] ?></div>
      <div class="stat-label">Under review</div>
    </div>
  </div>
  <div class="card stat-card">
    <span class="stat-icon stat-icon-resolved"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg></span>
    <div>
      <div class="stat-number"><?= $counts['resolved'] ?></div>
      <div class="stat-label">Resolved</div>
    </div>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error" style="display:block;">
    <strong>Please fix the following:</strong>
    <ul style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="submit.php" class="form form-card" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <!-- 1. Category -->
    <fieldset class="form-section">
        <legend>1 · Category <span class="req">*</span></legend>
        <p class="field-hint">What does your concern fall under?</p>
        <div class="pill-grid">
            <?php foreach (CATEGORIES as $cat): ?>
            <label class="pill-card">
                <input type="radio" name="category" value="<?= e($cat) ?>"
                       <?= $old['category'] === $cat ? 'checked' : '' ?>>
                <span class="pill-emoji"><?= $categoryIcons[$cat] ?? $categoryIcons['Others'] ?></span>
                <span class="pill-label"><?= e($cat) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <!-- 2. Subject -->
    <fieldset class="form-section">
        <legend>2 · Subject / Title <span class="req">*</span></legend>
        <input type="text" name="title" class="input" maxlength="160" data-count="titleCount"
               placeholder="e.g. Broken air conditioner in Room 204"
               value="<?= e($old['title']) ?>" required>
        <p class="field-hint counter"><span id="titleCount">0 / 160</span></p>
    </fieldset>

    <!-- 3. Description -->
    <fieldset class="form-section">
        <legend>3 · Description <span class="req">*</span></legend>
        <p class="field-hint">Give as much detail as possible — location, time, and what happened.</p>
        <textarea name="description" class="input input-textarea" rows="6" maxlength="5000"
                  data-count="descCount" placeholder="Describe the concern in detail…" required><?= e($old['description']) ?></textarea>
        <p class="field-hint counter"><span id="descCount">0 / 5000</span></p>
    </fieldset>

    <!-- 4. Attachments -->
    <fieldset class="form-section">
        <legend>4 · Attachments <span class="opt">(optional, up to <?= MAX_FILES_PER_POST ?> files)</span></legend>
        <label class="file-input">
            <input type="file" id="imageInput" name="attachments[]" multiple
                   accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.wav,.ogg,.m4a">
            <span class="file-input-inner">
                <span class="file-upload-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></span>
                <span class="file-upload-text">
                    <span id="fileName">Choose files (max <?= MAX_UPLOAD_MB ?> MB each, <?= MAX_FILES_PER_POST ?> max)</span>
                    <span class="file-upload-sub">Images (JPG, PNG, GIF, WEBP) · Videos (MP4, WEBM, MOV) · Audio (MP3, WAV, OGG, M4A)</span>
                </span>
            </span>
        </label>
        <div id="mediaPreview" class="media-preview"></div>
    </fieldset>

    <!-- 5. Anonymous -->
    <fieldset class="form-section">
        <legend>5 · Privacy</legend>
        <label class="switch-row">
            <span class="switch">
                <input type="checkbox" id="isAnonymous" name="is_anonymous" value="1"
                       <?= $old['is_anonymous'] ? 'checked' : '' ?>>
                <span class="switch-slider"></span>
            </span>
            <span>
                <strong>Submit as Anonymous</strong>
                <span class="field-hint">Admins will not see your identity, but you can still track your ticket.</span>
            </span>
        </label>
        <p class="anon-note" id="anonNote" <?= $old['is_anonymous'] ? '' : 'hidden' ?>>
            Your name and email will be hidden from the admins. Your report is still securely linked
            to your account so you can follow its status.
        </p>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg">Submit feedback</button>
        <a class="btn btn-ghost" href="dashboard.php">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
