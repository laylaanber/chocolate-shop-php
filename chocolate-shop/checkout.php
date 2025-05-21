<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Initialize variables
$message = '';
$error = '';
$user_addresses = [];
$default_address_id = null;
$user_info = [
    'email' => '',
    'phone' => ''
];

// If user is logged in, get their addresses and information
if ($is_logged_in) {
    // Get user information
    $user_query = "SELECT email, phone FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user addresses
    $address_query = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC";
    $address_stmt = $db->prepare($address_query);
    $address_stmt->execute([$_SESSION['user_id']]);
    $user_addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find default address
    foreach ($user_addresses as $address) {
        if ($address['is_default']) {
            $default_address_id = $address['id'];
            break;
        }
    }
}

// Calculate cart totals
$subtotal = 0;
$total_items = 0;

foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

// Apply promo code if exists
$discount = 0;
if (isset($_SESSION['promo_code'])) {
    $promo = $_SESSION['promo_code'];
    if ($promo['discount_type'] === 'percentage') {
        $discount = $subtotal * ($promo['discount_amount'] / 100);
    } else {
        $discount = $promo['discount_amount'];
        // Make sure discount doesn't exceed subtotal
        if ($discount > $subtotal) {
            $discount = $subtotal;
        }
    }
}

// Calculate tax and shipping
$tax_rate = 0.08; // 8% tax rate
$tax = $subtotal * $tax_rate;

// Free shipping over $50 (pre-tax)
$shipping = ($subtotal >= 50) ? 0 : 5.99;

// Calculate final total
$total = $subtotal - $discount + $tax + $shipping;

// Check if form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $shipping_notes = isset($_POST['shipping_notes']) ? $_POST['shipping_notes'] : '';
    
    // Different handling for guest vs. logged-in users
    if ($is_logged_in) {
        // Get selected address
        $address_id = isset($_POST['address_id']) ? $_POST['address_id'] : null;
        
        // Validate
        if (empty($payment_method)) {
            $error = "Please select a payment method.";
        } elseif (empty($address_id)) {
            $error = "Please select a shipping address.";
        } else {
            // Check if address belongs to user
            $check_query = "SELECT id FROM addresses WHERE id = ? AND user_id = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$address_id, $_SESSION['user_id']]);
            
            if ($check_stmt->rowCount() === 0) {
                $error = "Invalid address selected.";
            }
        }
    } else {
        // Guest checkout - collect address info
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $address_line1 = isset($_POST['address_line1']) ? trim($_POST['address_line1']) : '';
        $address_line2 = isset($_POST['address_line2']) ? trim($_POST['address_line2']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $state = isset($_POST['state']) ? trim($_POST['state']) : '';
        $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
        $country = isset($_POST['country']) ? trim($_POST['country']) : '';
        
        // Validate
        if (empty($first_name) || empty($last_name) || empty($email) || 
            empty($address_line1) || empty($city) || empty($state) || 
            empty($postal_code) || empty($country) || empty($payment_method)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        }
    }
    
    // Process order if no errors
    if (empty($error)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Create order record
            $order_query = "INSERT INTO orders (
                user_id, address_id, total_amount, subtotal, tax_amount, 
                shipping_cost, discount_amount, payment_method, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
            
            $order_params = [
                $is_logged_in ? $_SESSION['user_id'] : null,
                $is_logged_in ? $address_id : null,
                $total,
                $subtotal,
                $tax,
                $shipping,
                $discount,
                $payment_method,
                $shipping_notes
            ];
            
            $order_stmt = $db->prepare($order_query);
            $order_stmt->execute($order_params);
            $order_id = $db->lastInsertId();
            
            // If guest checkout, save the address with the order
            if (!$is_logged_in) {
                $guest_address_query = "INSERT INTO order_addresses (
                    order_id, first_name, last_name, email, phone,
                    address_line1, address_line2, city, state, postal_code, country
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $guest_address_params = [
                    $order_id, $first_name, $last_name, $email, $phone,
                    $address_line1, $address_line2, $city, $state, $postal_code, $country
                ];
                
                $guest_address_stmt = $db->prepare($guest_address_query);
                $guest_address_stmt->execute($guest_address_params);
            }
            
            // Add order items
            $items_query = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) 
                           VALUES (?, ?, ?, ?)";
            $items_stmt = $db->prepare($items_query);
            
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $items_stmt->execute([
                    $order_id,
                    $product_id,
                    $item['quantity'],
                    $item['price']
                ]);
                
                // Update product stock (optional)
                // $update_stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
                // $update_stock_stmt = $db->prepare($update_stock_query);
                // $update_stock_stmt->execute([$item['quantity'], $product_id]);
            }
            
            // Save promo code used (if any)
            if (isset($_SESSION['promo_code'])) {
                $promo_code = $_SESSION['promo_code']['code'];
                
                // Update usage count
                $update_promo_query = "UPDATE promo_codes SET usage_count = usage_count + 1 WHERE code = ?";
                $update_promo_stmt = $db->prepare($update_promo_query);
                $update_promo_stmt->execute([$promo_code]);
                
                // Link promo to order
                $order_promo_query = "UPDATE orders SET promo_code = ? WHERE id = ?";
                $order_promo_stmt = $db->prepare($order_promo_query);
                $order_promo_stmt->execute([$promo_code, $order_id]);
            }
            
            // Add order history entry
            $history_query = "INSERT INTO order_history (order_id, status, notes) 
                             VALUES (?, 'pending', 'Order placed')";
            $history_stmt = $db->prepare($history_query);
            $history_stmt->execute([$order_id]);
            
            // Commit transaction
            $db->commit();
            
            // Order successful - clear cart and promo code
            unset($_SESSION['cart']);
            unset($_SESSION['promo_code']);
            
            // Redirect to confirmation page
            $_SESSION['last_order_id'] = $order_id;
            header("Location: order-confirmation.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            $error = "Error processing your order: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <h1>Checkout</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="cart.php">Shopping Cart</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Checkout Section -->
<section class="checkout-section">
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form action="" method="post" id="checkout-form">
            <div class="row">
                <!-- Left Column: Customer Info & Shipping Details -->
                <div class="col-lg-8">
                    <div class="checkout-card">
                        <div class="checkout-steps">
                            <div class="step active">
                                <span class="step-number">1</span>
                                <span class="step-title">Customer Information</span>
                            </div>
                            <div class="step active">
                                <span class="step-number">2</span>
                                <span class="step-title">Shipping Details</span>
                            </div>
                            <div class="step active">
                                <span class="step-number">3</span>
                                <span class="step-title">Payment</span>
                            </div>
                        </div>

                        <?php if ($is_logged_in): ?>
                            <!-- Logged In User Checkout -->
                            <div class="section-title">
                                <h3>Shipping Address</h3>
                            </div>
                            
                            <?php if (empty($user_addresses)): ?>
                                <div class="alert alert-info">
                                    You don't have any saved addresses. Please add one to continue.
                                </div>
                                
                                <button type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal">
                                    <i class="fas fa-plus"></i> Add New Address
                                </button>
                            <?php else: ?>
                                <div class="saved-addresses">
                                    <?php foreach ($user_addresses as $address): ?>
                                        <div class="address-option">
                                            <input type="radio" name="address_id" id="address_<?= $address['id'] ?>" 
                                                   value="<?= $address['id'] ?>" <?= $address['id'] == $default_address_id ? 'checked' : '' ?>>
                                            <label for="address_<?= $address['id'] ?>" class="address-card">
                                                <?php if ($address['is_default']): ?>
                                                    <span class="default-badge">Default</span>
                                                <?php endif; ?>
                                                
                                                <div class="address-details">
                                                    <p class="address-line"><?= htmlspecialchars($address['address_line1']) ?></p>
                                                    <?php if (!empty($address['address_line2'])): ?>
                                                        <p class="address-line"><?= htmlspecialchars($address['address_line2']) ?></p>
                                                    <?php endif; ?>
                                                    <p class="address-line">
                                                        <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> <?= htmlspecialchars($address['postal_code']) ?>
                                                    </p>
                                                    <p class="address-line"><?= htmlspecialchars($address['country']) ?></p>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3 mb-4">
                                    <button type="button" class="btn-outline" data-bs-toggle="modal" data-bs-target="#addressModal">
                                        <i class="fas fa-plus"></i> Add New Address
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="section-title">
                                <h3>Contact Information</h3>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_info['email']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" readonly>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <!-- Guest Checkout -->
                            <div class="section-title">
                                <h3>Customer Information</h3>
                            </div>
                            
                            <div class="guest-checkout-notice mb-4">
                                <p>Already have an account? <a href="auth/login.php?redirect=checkout.php">Login here</a></p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                            
                            <div class="section-title">
                                <h3>Shipping Address</h3>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="address_line1" class="form-label">Address Line 1 *</label>
                                    <input type="text" class="form-control" id="address_line1" name="address_line1" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label for="address_line2" class="form-label">Address Line 2</label>
                                    <input type="text" class="form-control" id="address_line2" name="address_line2">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="state" class="form-label">State/Province *</label>
                                    <input type="text" class="form-control" id="state" name="state" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code *</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="country" class="form-label">Country *</label>
                                    <input type="text" class="form-control" id="country" name="country" value="United States" required>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="section-title">
                            <h3>Additional Information</h3>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_notes" class="form-label">Order Notes (Optional)</label>
                            <textarea class="form-control" id="shipping_notes" name="shipping_notes" rows="3" 
                                      placeholder="Special notes for delivery or gift message"></textarea>
                        </div>
                        
                        <div class="section-title">
                            <h3>Payment Method</h3>
                        </div>
                        
                        <div class="payment-methods mb-4">
                            <div class="payment-method">
                                <input type="radio" name="payment_method" id="payment_credit_card" value="credit_card" checked>
                                <label for="payment_credit_card">
                                    <span class="payment-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </span>
                                    <span class="payment-title">Credit Card</span>
                                    <div class="payment-cards">
                                        <i class="fab fa-cc-visa"></i>
                                        <i class="fab fa-cc-mastercard"></i>
                                        <i class="fab fa-cc-amex"></i>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-method">
                                <input type="radio" name="payment_method" id="payment_paypal" value="paypal">
                                <label for="payment_paypal">
                                    <span class="payment-icon">
                                        <i class="fab fa-paypal"></i>
                                    </span>
                                    <span class="payment-title">PayPal</span>
                                </label>
                            </div>
                            
                            <div class="payment-method">
                                <input type="radio" name="payment_method" id="payment_bank_transfer" value="bank_transfer">
                                <label for="payment_bank_transfer">
                                    <span class="payment-icon">
                                        <i class="fas fa-university"></i>
                                    </span>
                                    <span class="payment-title">Bank Transfer</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-info credit-card-info">
                            <!-- For a real site, you would integrate with a payment processor here -->
                            <p class="payment-note">In a production environment, this would be a secure payment form from a payment processor like Stripe or similar. For this demo, clicking "Place Order" will simulate a successful payment.</p>
                        </div>
                        
                        <div class="terms-agreement mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms_agree" required>
                                <label class="form-check-label" for="terms_agree">
                                    I agree to the <a href="terms.php" target="_blank">terms and conditions</a> and <a href="privacy.php" target="_blank">privacy policy</a>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Order Summary -->
                <div class="col-lg-4">
                    <div class="checkout-card order-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="order-items">
                            <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <img src="uploads/products/<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                                             onerror="this.src='https://via.placeholder.com/60x60'">
                                    </div>
                                    <div class="order-item-details">
                                        <h4><?= htmlspecialchars($item['name']) ?></h4>
                                        <p>Quantity: <?= $item['quantity'] ?></p>
                                    </div>
                                    <div class="order-item-price">
                                        $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-totals">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>$<?= number_format($subtotal, 2) ?></span>
                            </div>
                            
                            <?php if ($discount > 0): ?>
                                <div class="summary-row discount">
                                    <span>Discount</span>
                                    <span>-$<?= number_format($discount, 2) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="summary-row">
                                <span>Tax (8%)</span>
                                <span>$<?= number_format($tax, 2) ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping</span>
                                <?php if ($shipping === 0): ?>
                                    <span class="free-shipping">Free</span>
                                <?php else: ?>
                                    <span>$<?= number_format($shipping, 2) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($shipping === 0): ?>
                                <div class="free-shipping-message">
                                    <i class="fas fa-truck"></i> Free shipping on orders over $50
                                </div>
                            <?php else: ?>
                                <div class="free-shipping-message">
                                    <i class="fas fa-info-circle"></i> Spend $<?= number_format(50 - $subtotal, 2) ?> more for free shipping
                                </div>
                            <?php endif; ?>
                            
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>$<?= number_format($total, 2) ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-place-order">
                            Place Order <i class="fas fa-arrow-right"></i>
                        </button>
                        
                        <div class="secure-checkout">
                            <p><i class="fas fa-lock"></i> Secure Checkout</p>
                            <div class="payment-icons">
                                <i class="fab fa-cc-visa"></i>
                                <i class="fab fa-cc-mastercard"></i>
                                <i class="fab fa-cc-amex"></i>
                                <i class="fab fa-cc-paypal"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- Address Modal for Logged In Users -->
<?php if ($is_logged_in): ?>
<div class="modal fade" id="addressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="address-form" method="post" action="ajax/save_address.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_address_line1" class="form-label">Address Line 1 *</label>
                        <input type="text" class="form-control" id="modal_address_line1" name="address_line1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_address_line2" class="form-label">Address Line 2</label>
                        <input type="text" class="form-control" id="modal_address_line2" name="address_line2">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_city" class="form-label">City *</label>
                            <input type="text" class="form-control" id="modal_city" name="city" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_state" class="form-label">State/Province *</label>
                            <input type="text" class="form-control" id="modal_state" name="state" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_postal_code" class="form-label">Postal Code *</label>
                            <input type="text" class="form-control" id="modal_postal_code" name="postal_code" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_country" class="form-label">Country *</label>
                            <input type="text" class="form-control" id="modal_country" name="country" value="United States" required>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="modal_is_default" name="is_default" checked>
                        <label class="form-check-label" for="modal_is_default">
                            Set as default address
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    /* Checkout page styling inspired by La Maison du Chocolat */
    .checkout-section {
        padding-bottom: 80px;
    }
    
    .checkout-card {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 20px rgba(0,0,0,0.05);
        padding: 30px;
        margin-bottom: 30px;
    }
    
    /* Checkout steps */
    .checkout-steps {
        display: flex;
        margin-bottom: 30px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding-bottom: 20px;
    }
    
    .step {
        display: flex;
        align-items: center;
        flex: 1;
        opacity: 0.5;
    }
    
    .step.active {
        opacity: 1;
    }
    
    .step-number {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 10px;
    }
    
    .step-title {
        font-weight: 500;
        color: var(--primary-color);
    }
    
    /* Section titles */
    .section-title {
        margin: 30px 0 20px;
    }
    
    .section-title h3 {
        font-size: 1.3rem;
        color: var(--primary-color);
        margin-bottom: 0;
        padding-left: 10px;
        border-left: 3px solid var(--accent-color);
    }
    
    /* Saved addresses */
    .saved-addresses {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .address-option {
        position: relative;
    }
    
    .address-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .address-card {
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 5px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 100%;
        display: block;
        position: relative;
    }
    
    .address-option input[type="radio"]:checked + .address-card {
        border-color: var(--accent-color);
        background-color: var(--background-beige);
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    }
    
    .address-option input[type="radio"]:checked + .address-card::before {
        content: '\f058';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        top: 10px;
        right: 10px;
        color: var(--accent-color);
    }
    
    .default-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
    }
    
    .address-details {
        font-size: 0.9rem;
    }
    
    .address-line {
        margin-bottom: 3px;
    }
    
    /* Payment methods */
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .payment-method {
        position: relative;
    }
    
    .payment-method input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .payment-method label {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 15px;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.2s ease;
        height: 100%;
    }
    
    .payment-method input[type="radio"]:checked + label {
        border-color: var(--accent-color);
        background-color: var(--background-beige);
    }
    
    .payment-icon {
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 10px;
    }
    
    .payment-title {
        font-weight: 500;
        font-size: 0.95rem;
        margin-bottom: 10px;
    }
    
    .payment-cards {
        display: flex;
        gap: 5px;
        font-size: 1.2rem;
        color: var(--text-medium);
    }
    
    .payment-note {
        background-color: #f8f9fa;
        padding: 15px;
        font-size: 0.9rem;
        border-radius: 5px;
        color: var(--text-medium);
        margin-top: 15px;
    }
    
    /* Order summary */
    .order-summary {
        position: sticky;
        top: 20px;
    }
    
    .order-summary h3 {
        font-size: 1.3rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .order-items {
        margin-bottom: 20px;
        max-height: 300px;
        overflow-y: auto;
        padding-right: 5px;
    }
    
    .order-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .order-item:last-child {
        border-bottom: none;
    }
    
    .order-item-image {
        width: 60px;
        height: 60px;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .order-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 3px;
    }
    
    .order-item-details {
        flex: 1;
    }
    
    .order-item-details h4 {
        font-size: 0.95rem;
        margin-bottom: 3px;
    }
    
    .order-item-details p {
        font-size: 0.85rem;
        color: var(--text-medium);
        margin-bottom: 0;
    }
    
    .order-item-price {
        font-weight: 600;
        color: var(--primary-color);
        padding-left: 10px;
    }
    
    .summary-totals {
        margin-top: 20px;
        border-top: 1px solid rgba(0,0,0,0.05);
        padding-top: 20px;
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    
    .summary-row.discount {
        color: #28a745;
    }
    
    .summary-row.total {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.1);
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .free-shipping {
        color: #28a745;
    }
    
    .free-shipping-message {
        background-color: #f8f9fa;
        padding: 8px;
        font-size: 0.8rem;
        text-align: center;
        border-radius: 4px;
        margin: 10px 0;
        color: var(--text-medium);
    }
    
    .btn-place-order {
        background-color: var(--primary-color);
        color: white;
        border: none;
        width: 100%;
        padding: 15px;
        font-weight: 500;
        font-size: 1rem;
        border-radius: 5px;
        margin: 20px 0;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .btn-place-order:hover {
        background-color: #2a160d;
    }
    
    .secure-checkout {
        text-align: center;
        margin-top: 20px;
    }
    
    .secure-checkout p {
        font-size: 0.9rem;
        color: var(--text-medium);
        margin-bottom: 10px;
    }
    
    .secure-checkout i {
        color: var(--accent-color);
    }
    
    .payment-icons {
        display: flex;
        justify-content: center;
        gap: 10px;
        font-size: 2rem;
        color: var(--text-light);
    }
    
    /* Guest checkout notice */
    .guest-checkout-notice {
        padding: 10px;
        background-color: var(--background-beige);
        border-radius: 5px;
    }
    
    .guest-checkout-notice p {
        margin-bottom: 0;
        font-size: 0.9rem;
    }
    
    /* Form styling */
    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .form-control {
        border: 1px solid rgba(0,0,0,0.1);
        padding: 10px 15px;
        border-radius: 4px;
        height: auto;
    }
    
    .form-control:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 0.2rem rgba(209, 183, 138, 0.2);
    }
    
    /* Responsive adjustments */
    @media (max-width: 991px) {
        .order-summary {
            position: static;
        }
    }
    
    @media (max-width: 767px) {
        .checkout-steps {
            flex-direction: column;
            gap: 10px;
        }
        
        .payment-methods {
            grid-template-columns: 1fr;
        }
        
        .saved-addresses {
            grid-template-columns: 1fr;
        }
    }

    /* Scrollbar styling */
    .order-items::-webkit-scrollbar {
        width: 5px;
    }
    
    .order-items::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .order-items::-webkit-scrollbar-thumb {
        background: #bcbcbc;
        border-radius: 5px;
    }
    
    .order-items::-webkit-scrollbar-thumb:hover {
        background: #a0a0a0;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Payment method switching
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const paymentInfoDivs = document.querySelectorAll('.payment-info');
    
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Hide all payment info divs
            paymentInfoDivs.forEach(div => div.style.display = 'none');
            
            // Show the selected payment method's info
            const paymentMethod = this.value;
            const infoDiv = document.querySelector(`.${paymentMethod}-info`);
            if (infoDiv) {
                infoDiv.style.display = 'block';
            }
        });
    });
    
    // Show the initially selected payment method's info
    const checkedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (checkedPayment) {
        const initialPaymentMethod = checkedPayment.value;
        const initialInfoDiv = document.querySelector(`.${initialPaymentMethod}-info`);
        if (initialInfoDiv) {
            initialInfoDiv.style.display = 'block';
        }
    }
    
    // Handle address form submission via AJAX for logged in users
    const addressForm = document.getElementById('address-form');
    if (addressForm) {
        addressForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('ajax/save_address.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page to show the new address
                    window.location.reload();
                } else {
                    alert('Error saving address: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>