<?php
// ============================================================================
//  Endpoint to Toggle Feedback Support ("Support" action)
//  Accepts POST requests with JSON payload: { feedback_id: 123 }
// ============================================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to support a feedback concern.']);
    exit;
}

// CSRF check: accepts both header X-CSRF-Token or POST body token
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired session token.']);
    exit;
}

$feedbackId = (int)($input['feedback_id'] ?? 0);
if ($feedbackId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid feedback ID.']);
    exit;
}

$ticket = find_feedback($pdo, $feedbackId);
if (!$ticket) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
    exit;
}

$result = toggle_support($pdo, $feedbackId, (int)$user['id']);

// If user just supported the feedback, notify the ticket author (if not self)
if ($result['supported'] && (int)$ticket['user_id'] !== (int)$user['id']) {
    notify_user(
        $pdo,
        (int)$ticket['user_id'],
        $user['name'] . ' supported your concern (' . $ticket['ticket_no'] . ')',
        $feedbackId
    );
}

echo json_encode([
    'success'   => true,
    'supported' => $result['supported'],
    'count'     => $result['count'],
]);
