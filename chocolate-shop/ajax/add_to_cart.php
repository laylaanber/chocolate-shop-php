<?php
require_once '../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prepare response array
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'cart_count' => 0,
    'redirect' => false,
    'redirect_url' => ''
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, send redirect response
    $response['message'] = 'Please log in to add items to your cart';
    $response['redirect'] = true;
    
    // Use absolute URL instead of relative
    $response['redirect_url'] = '/php/chocolate-shop/auth/login.php?redirect=cart';
    
    // Return JSON response and exit
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If we get here, user is logged in so continue with cart functionality
try {
    // Get product ID and quantity
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if ($product_id && $quantity > 0) {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if product exists
        $product_query = "SELECT id, name, price, image FROM products WHERE id = ? AND is_active = 'available'";
        $product_stmt = $db->prepare($product_query);
        $product_stmt->execute([$product_id]);
        
        if ($product_stmt->rowCount() > 0) {
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Initialize cart if it doesn't exist
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Add or update product in cart
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image' => $product['image'],
                    'quantity' => $quantity
                ];
            }
            
            // Calculate total items in cart
            $cart_count = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cart_count += $item['quantity'];
            }
            
            $response['success'] = true;
            $response['message'] = 'Product added to cart';
            $response['cart_count'] = $cart_count;
        } else {
            $response['message'] = 'Product not found or unavailable';
        }
    } else {
        $response['message'] = 'Invalid product data';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>