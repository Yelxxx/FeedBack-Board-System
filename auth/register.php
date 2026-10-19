<?php
// ============================================================================
//  Registration (Phase 1: Authentication & Onboarding)
//  Requires: ID Number, Name, Department/Course, Role, Email, Password,
//  plus a Terms & Conditions agreement (Data Privacy Act compliance).
// ============================================================================
require_once __DIR__ . '/../config.php';

if (current_user() !== null) {
    redirect(is_admin() ? '../admin/dashboard.php' : '../dashboard.php');
}

$base      = '../';
$authTitle = 'Create account';
$errors    = [];

$old = [
    'id_number' => '', 'name' => '', 'email' => '', 'department' => '', 'role' => 'student',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'id_number'  => trim((string)($_POST['id_number'] ?? '')),
        'name'       => trim((string)($_POST['name'] ?? '')),
        'email'      => trim((string)($_POST['email'] ?? '')),
        'department' => trim((string)($_POST['department'] ?? '')),
        'role'       => (string)($_POST['role'] ?? 'student'),
    ];
    $password       = (string)($_POST['password'] ?? '');
    $passwordConf   = (string)($_POST['password_confirm'] ?? '');
    $acceptedTerms  = isset($_POST['terms']);

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($old['id_number'] === '' || strlen($old['id_number']) > 30) {
        $errors[] = 'Please enter your school ID number.';
    }
    if (strlen($old['name']) < 3 || strlen($old['name']) > 120) {
        $errors[] = 'Please enter your full name (3–120 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $allowed = array_filter(array_map('trim', explode(',', (string)SCHOOL_EMAIL_DOMAINS)));
        if ($allowed) {
            $domain = strtolower(substr(strrchr($old['email'], '@'), 1));
            if (!in_array($domain, array_map('strtolower', $allowed), true)) {
                $errors[] = 'Only school email addresses are allowed (' . implode(', ', $allowed) . ').';
            }
        }
    }
    if (!in_array($old['role'], ['student', 'faculty', 'staff'], true)) {
        $errors[] = 'Please choose a valid role.';
    }
    if ($old['department'] === '' || strlen($old['department']) > 100) {
        $errors[] = 'Please enter your department or course.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $passwordConf) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$acceptedTerms) {
        $errors[] = 'You must agree to the Terms & Conditions to continue.';
    }

    // Uniqueness checks
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id_number = ? LIMIT 1');
        $stmt->execute([$old['id_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'That ID number is already registered.';
        }
    }
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'That email address is already registered.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (id_number, name, email, password, department, role)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['id_number'], $old['name'], $old['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $old['department'], $old['role'],
        ]);
        flash_set('success', 'Account created! You can now log in.');
        redirect('login.php');
    }
}

$authSubtitle = 'Register with your school details';
require __DIR__ . '/../includes/auth_open.php';
?>

<form method="post" action="register.php" class="form" novalidate>
    <?= csrf_field() ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><span><?= e($error) ?></span><button class="alert-close" aria-label="Dismiss">&times;</button></div>
    <?php endforeach; ?>

    <div class="form-row">
        <div class="form-group">
            <label for="id_number">ID Number</label>
            <input type="text" id="id_number" name="id_number" class="input" placeholder="e.g. 2024-0001"
                   value="<?= e($old['id_number']) ?>" maxlength="30" required>
        </div>
        <div class="form-group">
            <label>I am a…</label>
            <div class="pill-grid role-grid">
                <label class="pill-card">
                    <input type="radio" name="role" value="student" <?= $old['role'] === 'student' ? 'checked' : '' ?> required>
                    <span class="pill-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/></svg></span>
                    <span class="pill-label">Student</span>
                </label>
                <label class="pill-card">
                    <input type="radio" name="role" value="faculty" <?= $old['role'] === 'faculty' ? 'checked' : '' ?>>
                    <span class="pill-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/></svg></span>
                    <span class="pill-label">Faculty</span>
                </label>
                <label class="pill-card">
                    <input type="radio" name="role" value="staff" <?= $old['role'] === 'staff' ? 'checked' : '' ?>>
                    <span class="pill-emoji"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                    <span class="pill-label">Staff / Maintenance</span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" class="input" placeholder="e.g. Juan M. Dela Cruz"
               value="<?= e($old['name']) ?>" maxlength="120" required>
    </div>

    <div class="form-group">
        <label for="department" id="deptLabel">Course / Program</label>
        <input type="text" id="department" name="department" class="input" placeholder="e.g. BS Computer Science"
               value="<?= e($old['department']) ?>" maxlength="100" required>
    </div>

    <div class="form-group">
        <label for="email">School Email</label>
        <input type="email" id="email" name="email" class="input" placeholder="you@oct.edu.ph"
               value="<?= e($old['email']) ?>" required>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="input" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" class="input" placeholder="Repeat password" required>
        </div>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
        <span>I agree to the <a href="#" onclick="return false;" title="Demo: full terms in README.md">Terms &amp; Conditions</a> and consent to the processing of my personal data in accordance with the Data Privacy Act (RA 10173). My feedback data is stored securely and anonymous reports protect my identity.</span>
    </label>

    <button type="submit" class="btn btn-primary btn-block">Create account</button>
</form>

<div class="auth-links">
    <span>Already have an account? <a href="login.php">Log in</a></span>
</div>

<?php require __DIR__ . '/../includes/auth_close.php'; ?>
