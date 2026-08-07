<?php
// ============================================================================
//  Submit Feedback (Phase 3 — Core Feature)
//  Category, subject, description, optional photo, anonymous toggle.
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

$base      = '';
$pageTitle = 'Submit Feedback';
$active    = 'submit';
$user      = current_user();

$errors = [];
$old = ['category' => '', 'title' => '', 'description' => '', 'is_anonymous' => 0];

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
    }
    if ($old['description'] === '') {
        $errors[] = 'Please describe your concern in detail.';
    } elseif (strlen($old['description']) > 5000) {
        $errors[] = 'The description must be 5000 characters or less.';
    }

    // Optional photo upload
    $imagePath = null;
    $upload = handle_image_upload($_FILES['image'] ?? []);
    if (is_array($upload)) {
        $errors = array_merge($errors, $upload);
    } elseif ($upload !== null) {
        $imagePath = $upload;
    }

    if (empty($errors)) {
        $ticketNo = next_ticket_no($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO feedbacks (ticket_no, user_id, category, title, description, image_path, is_anonymous)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ticketNo,
            (int)$user['id'],
            $old['category'],
            $old['title'],
            $old['description'],
            $imagePath,
            $old['is_anonymous'],
        ]);
        $feedbackId = (int)$pdo->lastInsertId();

        notify_admins(
            $pdo,
            'New ' . ($old['is_anonymous'] ? 'anonymous ' : '') . 'feedback submitted: ' . $ticketNo,
            $feedbackId
        );

        flash_set('success', 'Feedback submitted! Your ticket number is ' . $ticketNo . '. You can track it from your dashboard.');
        redirect('ticket.php?id=' . $feedbackId);
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

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <span><strong>Please fix the following:</strong> <?= e(implode(' ', $errors)) ?></span>
    <button class="alert-close" aria-label="Dismiss">&times;</button>
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

    <!-- 4. Attachment -->
    <fieldset class="form-section">
        <legend>4 · Photo / File Attachment <span class="opt">(optional)</span></legend>
        <label class="file-input">
            <input type="file" id="imageInput" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
            <span class="file-input-inner">
                <span class="file-upload-icon"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
                <span class="file-upload-text">
                    <span id="fileName">Choose an image (max <?= MAX_UPLOAD_MB ?> MB)</span>
                    <span class="file-upload-sub">JPG, PNG, GIF or WEBP — e.g. a photo of the damaged facility</span>
                </span>
            </span>
        </label>
        <img id="imagePreview" class="image-preview" alt="Preview" hidden>
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
