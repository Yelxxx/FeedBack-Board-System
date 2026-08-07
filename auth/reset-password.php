<?php
// ============================================================================
//  Reset Password (Phase 1)
//  Validates the one-time token from the email and saves the new password.
// ============================================================================
require_once __DIR__ . '/../config.php';

if (current_user() !== null) {
    redirect(is_admin() ? '../admin/dashboard.php' : '../dashboard.php');
}

$base      = '../';
$authTitle = 'Reset password';
$errors    = [];
$token     = (string)($_GET['token'] ?? '');
$valid     = false;

if ($token !== '') {
    // Tokens are stored hashed; only the plain token (from the email link) is accepted.
    $stmt = $pdo->prepare(
        'SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    $valid = $row !== false;
}

if (!$valid) {
    $authSubtitle = 'Password reset';
    require __DIR__ . '/../includes/auth_open.php';
    echo '<div class="alert alert-error"><span>This reset link is invalid or has already expired. Please request a new one.</span><button class="alert-close" aria-label="Dismiss">&times;</button></div>';
    echo '<div class="auth-links"><a href="forgot-password.php">Request a new link</a></div>';
    require __DIR__ . '/../includes/auth_close.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $password     = (string)($_POST['password'] ?? '');
        $passwordConf = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $passwordConf) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $row['email']]);

            $clean = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
            $clean->execute([$row['email']]);

            flash_set('success', 'Your password has been reset. You can now log in.');
            redirect('login.php');
        }
    }
}

$authSubtitle = 'Choose a new password for your account';
require __DIR__ . '/../includes/auth_open.php';
?>

<form method="post" action="reset-password.php?token=<?= e($token) ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><span><?= e($error) ?></span><button class="alert-close" aria-label="Dismiss">&times;</button></div>
    <?php endforeach; ?>

    <div class="form-group">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" class="input" placeholder="Min. 8 characters" required autofocus>
    </div>

    <div class="form-group">
        <label for="password_confirm">Confirm New Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="input" placeholder="Repeat password" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save new password</button>
</form>

<?php require __DIR__ . '/../includes/auth_close.php'; ?>
