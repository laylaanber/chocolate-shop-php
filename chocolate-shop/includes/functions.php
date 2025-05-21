<?php
/**
 * Helper Functions
 */

// Only start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get the correct relative path to admin dashboard based on current file location
 */
function getAdminUrl() {
    $current_path = $_SERVER['PHP_SELF'];
    
    // Check if we're in a subdirectory
    if (strpos($current_path, '/auth/') !== false) {
        return '../admin/dashboard.php';
    } elseif (strpos($current_path, '/admin/') !== false) {
        return 'dashboard.php';
    } else {
        return 'admin/dashboard.php';
    }
}

/**
 * Format price with currency symbol
 */
function formatPrice($price, $symbol = '$') {
    return $symbol . number_format($price, 2);
}

/**
 * Generate a random string for tokens, etc.
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Get status badge class for order status
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'processing': return 'info';
        case 'shipped': return 'primary';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

/**
 * Check if a user is an admin
 */
function isAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return false;
    }
    
    return $_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'root_admin';
}

/**
 * Check if a user is a root admin
 */
function isRootAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return false;
    }
    
    return $_SESSION['user_role'] === 'root_admin';
}

/**
 * Truncate text to a certain length
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length) . $append;
    }
    return $text;
}
?>