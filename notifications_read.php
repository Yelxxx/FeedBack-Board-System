<?php
// ============================================================================
//  Mark all of the current user's notifications as read.
//  Called via fetch() whenever the notification dropdown is opened.
// ============================================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (current_user() === null || !csrf_verify()) {
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$stmt->execute([(int)current_user()['id']]);

echo json_encode(['ok' => true]);
