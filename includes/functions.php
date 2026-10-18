<?php
declare(strict_types=1);

// ============================================================================
//  OCT Feedback Board System — Shared helper functions
// ============================================================================

// ---------------- Output helpers ----------------

/** Escape output for safe HTML rendering (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Absolute URL to a path inside the app. */
function url_for(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Redirect and stop execution. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Human-friendly relative time, e.g. "3d ago". */
function time_ago(string $datetime): string
{
    try {
        $then = new DateTime($datetime);
    } catch (Exception $e) {
        return '';
    }
    $diff = (new DateTime())->diff($then);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

/** Truncate text to $limit characters, appending an ellipsis. */
function truncate(string $text, int $limit = 120): string
{
    $text = trim($text);
    if (strlen($text) <= $limit) return $text;
    if (function_exists('mb_substr')) {
        $short = rtrim(mb_substr($text, 0, $limit - 1));
    } else {
        $short = rtrim(substr($text, 0, $limit - 1));
    }
    return $short . '…';
}

/** First-name greeting ("Juan Dela Cruz" -> "Juan"). */
function first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] ?? '';
}

/** Two-letter avatar initials from a name. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $init  = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== '') $init .= strtoupper(substr($part, 0, 1));
    }
    return $init === '' ? '?' : $init;
}

// ---------------- Flash messages ----------------

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/** Fetch (and clear) all flash messages of one type. */
function flash_get(string $type): array
{
    $messages = $_SESSION['flash'][$type] ?? [];
    unset($_SESSION['flash'][$type]);
    return $messages;
}

// ---------------- Authentication ----------------

/** Currently logged-in user row, or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        flash_set('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

/** For admin/* pages only (paths are relative to the admin/ folder). */
function require_admin(): void
{
    $user = current_user();
    if ($user === null) {
        flash_set('error', 'Please log in to continue.');
        redirect('../auth/login.php');
    }
    if (!in_array(($user['role'] ?? ''), ['admin', 'super_admin'], true)) {
        flash_set('error', 'Access denied. You do not have permission to view that page.');
        redirect('../dashboard.php');
    }
}

/** Super admin only (paths are relative to the admin/ folder). */
function require_super_admin(): void
{
    $user = current_user();
    if ($user === null) {
        flash_set('error', 'Please log in to continue.');
        redirect('../auth/login.php');
    }
    if (($user['role'] ?? '') !== 'super_admin') {
        flash_set('error', 'Access denied. This area is restricted to the Super Admin.');
        redirect('dashboard.php');
    }
}

function is_admin(): bool
{
    return in_array((current_user()['role'] ?? ''), ['admin', 'super_admin'], true);
}

function is_super_admin(): bool
{
    return (current_user()['role'] ?? '') === 'super_admin';
}

/** Pretty label for a role slug. */
function role_label(string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'admin'       => 'Field Admin',
        'staff'       => 'Staff',
        'faculty'     => 'Faculty',
        'student'     => 'Student',
    ];
    return $labels[$role] ?? $role;
}

/**
 * The field a departmental admin controls, as a human-readable label.
 * Reads the admin's stored field_scope key and maps it via FIELD_SCOPES.
 * Super admin (and everyone else) returns null = no scope bias.
 */
function admin_field(array $user): ?string
{
    if (($user['role'] ?? '') !== 'admin') return null;
    $scope = (string)($user['field_scope'] ?? '');
    return FIELD_SCOPES[$scope] ?? null;
}

/** Category a field-scope key controls ('' when unknown). */
function scope_category(string $scope): string
{
    return FIELD_SCOPES[$scope] ?? '';
}

/**
 * The "field" a logged-in user belongs to, as a category label — matched by
 * keyword from their department (e.g. "Maintenance Department" -> the
 * 'maintenance' scope). Falls back to null when no keyword matches.
 */
function user_field(array $user): ?string
{
    $dept = strtolower((string)($user['department'] ?? ''));
    if ($dept === '') return null;

    foreach (FIELD_SCOPES as $scope => $category) {
        foreach (preg_split('/[\s\/]+/', (string)$scope) as $word) {
            if ($word !== '' && strpos($dept, strtolower($word)) !== false) {
                return $category;
            }
        }
    }
    return null;
}

// ---------------- CSRF protection ----------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

// ---------------- Tickets / feedback ----------------

function next_ticket_no(PDO $pdo): string
{
    $next = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM feedbacks')->fetchColumn();
    return 'TKT-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

/** Fetch one feedback row joined with its owner's info, or null. */
function find_feedback(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT f.*, u.name, u.email, u.department AS user_department, u.id_number
         FROM feedbacks f
         JOIN users u ON u.id = f.user_id
         WHERE f.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function fetch_comments(PDO $pdo, int $feedbackId, bool $includePrivate): array
{
    $sql = 'SELECT c.*, u.name, u.role
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.feedback_id = ?' .
            ($includePrivate ? '' : " AND c.visibility = 'public'") . '
            ORDER BY c.created_at ASC, c.id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$feedbackId]);
    return $stmt->fetchAll();
}

function valid_category(string $category): bool
{
    return in_array($category, CATEGORIES, true);
}

function valid_department(string $dept): bool
{
    return in_array($dept, DEPARTMENTS, true);
}

// ---------------- Badges / UI ----------------

function status_badge(string $status): string
{
    $label = STATUS_LABELS[$status] ?? $status;
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

function category_badge(string $category): string
{
    return '<span class="badge badge-neutral">' . e($category) . '</span>';
}

function anonymous_badge(): string
{
    return '<span class="badge badge-anon" title="This submission is anonymous">Anonymous</span>';
}

// ---------------- Notifications ----------------

function notify_user(PDO $pdo, int $userId, string $message, ?int $feedbackId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, feedback_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $feedbackId, $message]);
}

function notify_admins(PDO $pdo, string $message, ?int $feedbackId = null): void
{
    foreach ($pdo->query("SELECT id, role, field_scope FROM users WHERE role IN ('admin', 'super_admin')") as $row) {
        // Field-bias elimination: departmental admins only get notified for
        // posts inside their own field. Super admin sees everything.
        if ($row['role'] === 'admin') {
            $fieldCategory = FIELD_SCOPES[(string)$row['field_scope']] ?? null;
            if ($fieldCategory !== null && $feedbackId !== null) {
                $cat = $pdo->prepare('SELECT category FROM feedbacks WHERE id = ?');
                $cat->execute([$feedbackId]);
                if ((string)$cat->fetchColumn() !== $fieldCategory) {
                    continue; // outside this admin's field — skip
                }
            }
        }
        notify_user($pdo, (int)$row['id'], $message, $feedbackId);
    }
}

function unread_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function unread_notifications(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, message, feedback_id, created_at
         FROM notifications
         WHERE user_id = ? AND is_read = 0
         ORDER BY created_at DESC, id DESC
         LIMIT 8'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ---------------- File uploads ----------------

/**
 * Validate and save an uploaded image.
 * Returns: null (no file chosen), string (saved filename),
 *          or array of error messages.
 */
function handle_image_upload(array $file)
{
    if (empty($file['name'])) {
        return null;
    }

    $errors  = [];
    $maxSize = MAX_UPLOAD_MB * 1024 * 1024;
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'The file could not be uploaded. Please try again.';
    }
    if ($file['size'] > $maxSize) {
        $errors[] = 'The image is too large. Maximum size is ' . MAX_UPLOAD_MB . ' MB.';
    }
    if (!in_array($ext, $allowed, true)) {
        $errors[] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
    }
    if (@getimagesize($file['tmp_name']) === false) {
        $errors[] = 'The uploaded file is not a valid image.';
    }
    if ($errors) {
        return $errors;
    }

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0777, true);
    }
    $filename = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        return ['Failed to save the uploaded file. Check that the uploads/ folder is writable.'];
    }
    return $filename;
}

// ---------------- Media attachments (img / video / audio) ----------------
/** Media kinds accepted as feedback attachments. */
function media_kind(string $ext): ?string
{
    static $map = [
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
        'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'm4a' => 'audio',
    ];
    return $map[$ext] ?? null;
}

/**
 * Validate and save one uploaded media file (image, video or audio).
 * Returns null when no file was chosen; a string filename on success;
 * or an array of error messages. On success also returns the media kind
 * via the $kind by-reference parameter.
 */
function handle_media_upload(array $file, ?string &$kind = null)
{
    if (empty($file['name'])) {
        return null;
    }

    $errors  = [];
    $maxSize = MAX_UPLOAD_MB * 1024 * 1024;
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'One of the files could not be uploaded. Please try again.';
    }
    if ($file['size'] > $maxSize) {
        $errors[] = 'Files must not exceed ' . MAX_UPLOAD_MB . ' MB each.';
    }
    $possibleKind = media_kind($ext);
    if ($possibleKind === null) {
        $errors[] = 'File type ".' . $ext . '" is not allowed. Use images (JPG/PNG/GIF/WEBP), videos (MP4/WEBM/MOV) or audio (MP3/WAV/OGG/M4A).';
    }

    // Content sniffing: images must really be images; do not trust the extension alone.
    if ($possibleKind === 'image' && @getimagesize($file['tmp_name']) === false) {
        $errors[] = 'One file claims to be an image but is not a valid image.';
    }

    if ($errors) {
        return $errors;
    }

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0777, true);
    }
    $filename = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        return ['Failed to save the uploaded file. Check that the uploads/ folder is writable.'];
    }
    $kind = $possibleKind;
    return $filename;
}

/** All attachment rows for one feedback, in upload order. */
function fetch_attachments(PDO $pdo, int $feedbackId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, file_path, media_kind FROM feedback_attachments WHERE feedback_id = ? ORDER BY id ASC'
    );
    $stmt->execute([$feedbackId]);
    return $stmt->fetchAll();
}

/** Delete attachment files + rows for a feedback (called before the row is deleted). */
function delete_attachments(PDO $pdo, int $feedbackId): void
{
    foreach (fetch_attachments($pdo, $feedbackId) as $a) {
        $path = UPLOAD_DIR . '/' . basename($a['file_path']);
        if (is_file($path)) @unlink($path);
    }
    $stmt = $pdo->prepare('DELETE FROM feedback_attachments WHERE feedback_id = ?');
    $stmt->execute([$feedbackId]);
}

/** Render one attachment for display (image / video / audio player). */
function attachment_html(array $a, string $base = ''): string
{
    $src = e($base . 'uploads/' . $a['file_path']);
    switch ($a['media_kind']) {
        case 'image':
            return '<figure class="attachment"><img src="' . $src . '" alt="Attachment" class="attachment-img" onclick="window.open(this.src,\'_blank\')"><figcaption>Image attachment — click to enlarge</figcaption></figure>';
        case 'video':
            return '<figure class="attachment attachment-video"><video controls preload="metadata" class="attachment-media"><source src="' . $src . '">Your browser cannot play this video.</video><figcaption>Video attachment</figcaption></figure>';
        case 'audio':
            return '<figure class="attachment attachment-audio"><audio controls preload="metadata" class="attachment-media" src="' . $src . '">Your browser cannot play this audio.</audio><figcaption>Audio attachment</figcaption></figure>';
    }
    return '';
}
