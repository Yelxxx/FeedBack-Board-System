<?php
// ============================================================================
//  Login (Phase 1: Authentication & Onboarding)
// ============================================================================
require_once __DIR__ . '/../config.php';

// Already logged in? Go straight to the right home page.
if (current_user() !== null) {
    redirect(is_admin() ? '../admin/dashboard.php' : '../dashboard.php');
}

$base        = '../';
$authTitle   = 'Log in';
$authLayout  = 'split'; // 70/30 visual + form layout
$errors      = [];
$email       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Simple brute-force protection
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
    $attempts    = (int)($_SESSION['login_attempts'] ?? 0);

    // If a previous lock has expired, give the user a fresh start.
    if ($lockedUntil > 0 && $lockedUntil <= time()) {
        $attempts = 0;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = 0;
    }

    if ($lockedUntil > time()) {
        $mins = (int)ceil(($lockedUntil - time()) / 60);
        $errors[] = 'Too many failed attempts. Please try again in ' . $mins . ' minute(s).';
    } elseif (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user !== false && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            flash_set('success', 'Welcome back, ' . first_name($user['name']) . '!');
            redirect($user['role'] === 'admin' ? '../admin/dashboard.php' : '../dashboard.php');
        }

        $attempts++;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_MINUTES * 60;
            $errors[] = 'Too many failed attempts. Your access is locked for ' . LOGIN_LOCK_MINUTES . ' minutes.';
        } else {
            $errors[] = 'Invalid email or password. ' . (MAX_LOGIN_ATTEMPTS - $attempts) . ' attempt(s) remaining.';
        }
    }
}

$authSubtitle = 'Log in to submit feedback and track your tickets';
require __DIR__ . '/../includes/auth_open.php';
?>

<form method="post" action="login.php" class="form" novalidate>
    <?= csrf_field() ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><span><?= e($error) ?></span><button class="alert-close" aria-label="Dismiss">&times;</button></div>
    <?php endforeach; ?>

    <div class="form-group">
        <label for="email">School Email</label>
        <input type="email" id="email" name="email" class="input" placeholder="you@oct.edu.ph"
               value="<?= e($email) ?>" required autofocus>
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="input" placeholder="••••••••" required>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Log in</button>
</form>

<div class="auth-links">
    <a href="forgot-password.php">Forgot password?</a>
    <span>Don't have an account? <a href="register.php">Sign up</a></span>
</div>

<div class="demo-box">
    <p class="demo-title">Demo accounts — tap to fill</p>
    <p>Try the system as any end user:</p>
    <div class="demo-btns">
        <button type="button" class="btn btn-demo" data-demo-email="admin@oct.edu.ph" data-demo-password="admin123">Admin</button>
        <button type="button" class="btn btn-demo" data-demo-email="juan.delacruz@oct.edu.ph" data-demo-password="student123">Student</button>
        <button type="button" class="btn btn-demo" data-demo-email="pedro.reyes@oct.edu.ph" data-demo-password="student123">Faculty</button>
        <button type="button" class="btn btn-demo" data-demo-email="ana.lopez@oct.edu.ph" data-demo-password="student123">Staff</button>
    </div>
</div>

<?php require __DIR__ . '/../includes/auth_close.php'; ?>
