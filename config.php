<?php
declare(strict_types=1);

// ============================================================================
//  OCT Feedback Board System — Configuration
//  Edit the values below to match your own environment (XAMPP defaults shown).
// ============================================================================

// ---- Database connection (XAMPP defaults: root with no password) ----------
// NOTE: you may override any of these with environment variables
// (e.g. DB_PORT=3307 php -S localhost:8000) — handy for local development.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'oct_feedback');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ---- Application ------------------------------------------------------------
define('APP_NAME', 'OCT Feedback Board');
define('APP_TAGLINE', 'Your voice. Our campus. One feedback board.');

// Public URL of the app. With XAMPP: http://localhost/<your-project-folder>/
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/oct-feedback-board');

// ---- School email verification ----------------------------------------------
// Comma-separated list of email domains allowed during registration.
// Leave '' to accept any valid email address.
// Example: define('SCHOOL_EMAIL_DOMAINS', 'oct.edu.ph,oct.edu');
define('SCHOOL_EMAIL_DOMAINS', '');

// ---- Options -----------------------------------------------------------------
define('CATEGORIES', [
    'Facility / Maintenance',
    'Academic',
    'Environment / Safety',
    'Social / Behavioral',
    'Others',
]);
define('DEPARTMENTS', [
    'OSA', 'HR', 'Maintenance', 'Guidance', 'Registrar', 'IT Office', "Dean's Office",
]);
define('STATUS_LABELS', [
    'pending'       => 'Pending',
    'under_review'  => 'Under Review',
    'resolved'      => 'Resolved',
]);

// ---- File uploads --------------------------------------------------------------
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_MB', 5);

// ---- Security -------------------------------------------------------------------
// DEMO_MODE = true  -> password-reset links are printed on screen (no mail server needed).
//             false -> reset links are sent by email via PHP mail() (needs XAMPP Mercury,
//                      or a configured SMTP server; set APP_URL to the live address).
define('DEMO_MODE', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

// ---- Session ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('OCTFBSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

// ---- Database connection (PDO) ----------------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('<p style="font-family:sans-serif;padding:2rem;max-width:40rem;margin:4rem auto;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#b91c1c;">'
        . '<strong>Database connection failed.</strong><br>'
        . 'Check that MySQL is running and that the credentials in <code>config.php</code> are correct.</p>');
}
