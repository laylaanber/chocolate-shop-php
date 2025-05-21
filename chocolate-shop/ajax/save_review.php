<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => 'An error occurred'
];

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form data
    $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $response['message'] = 'Please provide a valid rating (1-5 stars)';
        echo json_encode($response);
        exit;
    }
    
    // Validate comment
    if (empty($comment)) {
        $response['message'] = 'Please provide a review comment';
        echo json_encode($response);
        exit;
    }
    
    // Determine user_id or get name/email for guest reviews
    $user_id = null;
    $user_name = null;
    $user_email = null;
    
    if (isset($_SESSION['user_id'])) {
        // Logged in user
        $user_id = $_SESSION['user_id'];
    } else {
        // Guest user - ensure name and email are provided
        $user_name = trim($_POST['name'] ?? '');
        $user_email = trim($_POST['email'] ?? '');
        
        if (empty($user_name) || empty($user_email)) {
            $response['message'] = 'Please provide your name and email';
            echo json_encode($response);
            exit;
        }
        
        // Validate email format
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please provide a valid email address';
            echo json_encode($response);
            exit;
        }
        
        // Check if we need to create a guest user account
        $user_check_query = "SELECT id FROM users WHERE email = ?";
        $user_check_stmt = $db->prepare($user_check_query);
        $user_check_stmt->execute([$user_email]);
        $existing_user = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            $user_id = $existing_user['id'];
        } else {
            // Create guest user
            $user_insert_query = "INSERT INTO users (name, email, role, created_at) VALUES (?, ?, 'customer', NOW())";
            $user_insert_stmt = $db->prepare($user_insert_query);
            $user_insert_stmt->execute([$user_name, $user_email]);
            $user_id = $db->lastInsertId();
        }
    }
    
    // Save the review
    $review_insert_query = "INSERT INTO reviews (product_id, user_id, rating, comment, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
    $review_insert_stmt = $db->prepare($review_insert_query);
    $review_insert_stmt->execute([
        $product_id,
        $user_id,
        $rating,
        $comment
    ]);
    
    $response['success'] = true;
    $response['message'] = 'Your review has been submitted successfully';
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>