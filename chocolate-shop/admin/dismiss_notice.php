<?php
session_start();

// Simple CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Set the notice as dismissed in session
if (isset($_POST['notice_type'])) {
    $notice_type = $_POST['notice_type'];
    
    // Validate notice_type to prevent session pollution
    $allowed_notices = ['security_notice_dismissed', 'root_notice_shown'];
    if (in_array($notice_type, $allowed_notices)) {
        $_SESSION[$notice_type] = true;
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid notice type']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
}
?>