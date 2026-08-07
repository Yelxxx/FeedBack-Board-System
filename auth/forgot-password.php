<?php
// ============================================================================
//  Forgot Password (Phase 1)
//  Issues a one-time reset token. In DEMO_MODE the reset link is printed on
//  screen; otherwise it is emailed via PHP mail() (requires a mail server).
// ============================================================================
require_once __DIR__ . '/../config.php';

if (current_user() !== null) {
    redirect(is_admin() ? '../admin/dashboard.php' : '../dashboard.php');
}

$base      = '../';
$authTitle = 'Forgot password';
$sent      = false;
$demoLink  = null;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Always show a friendly message (don't reveal whether the account exists)
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user !== false) {
                $token = bin2hex(random_bytes(32));

                $del = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
                $del->execute([$email]);

                // Expiry is computed on the MySQL side (DATE_ADD NOW) so it always
                // agrees with the NOW() used during token validation — no timezone issues.
                $ins = $pdo->prepare(
                    'INSERT INTO password_resets (email, token, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                );
                $ins->execute([$email, hash('sha256', $token)]);

                $resetUrl = url_for('auth/reset-password.php?token=' . $token);

                // Attempt to email the reset link (XAMPP Mercury / SMTP required).
                $subject = '[' . APP_NAME . '] Password Reset Request';
                $body    = "Hello,\n\nYou requested a password reset for your " . APP_NAME .
                           " account.\n\nOpen this link to choose a new password:\n" .
                           $resetUrl . "\n\nThis link expires in 1 hour.\n\nIf you did not request this, you can safely ignore this email.";
                $mailed  = @mail($email, $subject, $body);

                $sent = true;
                if (DEMO_MODE) {
                    $demoLink = $resetUrl; // show the link on screen for the demo/defense
                } elseif (!$mailed) {
                    $errors[] = 'The reset email could not be sent. Please contact the administrator.';
                }
            } else {
                $sent = true; // generic message
            }
        }
    }
}

$authSubtitle = 'We will send you a link to reset your password';
require __DIR__ . '/../includes/auth_open.php';
?>

<?php if ($sent): ?>
    <div class="alert alert-success">
        <span>If an account exists for that email, a password reset link has been sent. Please check your inbox.</span>
        <button class="alert-close" aria-label="Dismiss">&times;</button>
    </div>

    <?php if ($demoLink !== null): ?>
        <div class="demo-box">
            <p class="demo-title">Demo mode — your reset link</p>
            <p>No mail server is configured, so here is the link:</p>
            <p class="demo-link"><a href="<?= e($demoLink) ?>"><?= e($demoLink) ?></a></p>
        </div>
    <?php endif; ?>

    <div class="auth-links">
        <a href="login.php">Back to login</a>
    </div>
<?php else: ?>
    <form method="post" action="forgot-password.php" class="form" novalidate>
        <?= csrf_field() ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><span><?= e($error) ?></span><button class="alert-close" aria-label="Dismiss">&times;</button></div>
        <?php endforeach; ?>

        <div class="form-group">
            <label for="email">School Email</label>
            <input type="email" id="email" name="email" class="input" placeholder="you@oct.edu.ph" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>

    <div class="auth-links">
        <span>Remembered it? <a href="login.php">Log in</a></span>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/auth_close.php'; ?>
