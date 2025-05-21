<?php
session_start();
require_once '../config/database.php';

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred'
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to save an address.';
    echo json_encode($response);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $address_line1 = trim($_POST['address_line1']);
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $postal_code = trim($_POST['postal_code']);
    $country = trim($_POST['country']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validate input
    if (empty($address_line1) || empty($city) || empty($state) || empty($postal_code) || empty($country)) {
        $response['message'] = 'Please fill in all required address fields.';
        echo json_encode($response);
        exit;
    }
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // If setting as default, unset other defaults
        if ($is_default) {
            $unset_query = "UPDATE addresses SET is_default = 0 WHERE user_id = ?";
            $unset_stmt = $db->prepare($unset_query);
            $unset_stmt->execute([$_SESSION['user_id']]);
        }
        
        // Insert new address
        $insert_query = "INSERT INTO addresses 
                        (user_id, address_line1, address_line2, city, state, postal_code, country, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([
            $_SESSION['user_id'],
            $address_line1,
            $address_line2,
            $city,
            $state,
            $postal_code,
            $country,
            $is_default
        ]);
        
        // Commit transaction
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => 'Address saved successfully',
            'address_id' => $db->lastInsertId()
        ];
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollBack();
        $response['message'] = 'Error saving address: ' . $e->getMessage();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);