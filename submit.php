<?php
// ============================================================================
//  Legacy Redirect: Submit Feedback -> My Activity Hub
// ============================================================================
require_once __DIR__ . '/config.php';
require_login();

header('Location: my-activity.php#submit-form');
exit;
