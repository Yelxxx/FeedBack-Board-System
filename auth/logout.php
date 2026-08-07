<?php
// ============================================================================
//  Logout
// ============================================================================
require_once __DIR__ . '/../config.php';

// Clear all session data, then rotate to a fresh session ID.
$_SESSION = [];
session_regenerate_id(true);

flash_set('success', 'You have been logged out. See you soon.');
redirect('login.php');
