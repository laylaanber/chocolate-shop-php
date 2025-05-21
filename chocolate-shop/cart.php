<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle cart item updates and removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove item from cart
    if (isset($_POST['remove_item']) && isset($_POST['product_id'])) {
        $product_id = $_POST['product_id'];
        
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            $message = "Item removed from cart.";
        }
    }
    
    // Update item quantity
    if (isset($_POST['update_quantity']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
        $product_id = $_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if (isset($_SESSION['cart'][$product_id]) && $quantity > 0) {
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            $message = "Cart updated successfully.";
        } elseif ($quantity <= 0) {
            unset($_SESSION['cart'][$product_id]);
            $message = "Item removed from cart.";
        }
    }
    
    // Apply promo code
    if (isset($_POST['apply_promo']) && isset($_POST['promo_code'])) {
        $promo_code = trim($_POST['promo_code']);
        
        // Check if promo code exists and is valid
        $promo_query = "SELECT * FROM promo_codes WHERE code = ? AND is_active = 1 
                       AND (usage_limit > usage_count OR usage_limit = 0)
                       AND (expiry_date >= CURDATE() OR expiry_date IS NULL)";
        $promo_stmt = $db->prepare($promo_query);
        $promo_stmt->execute([$promo_code]);
        
        if ($promo_stmt->rowCount() > 0) {
            $promo = $promo_stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['promo_code'] = $promo;
            $message = "Promo code applied: " . $promo['discount_amount'] . ($promo['discount_type'] === 'percentage' ? '% discount' : '$ discount');
            
            // Increment usage count for the promo code
            $update_promo_query = "UPDATE promo_codes SET usage_count = usage_count + 1 WHERE id = ?";
            $update_promo_stmt = $db->prepare($update_promo_query);
            $update_promo_stmt->execute([$promo['id']]);
        } else {
            $error = "Invalid or expired promo code.";
            unset($_SESSION['promo_code']);
        }
    }
    
    // Clear promo code
    if (isset($_POST['clear_promo'])) {
        unset($_SESSION['promo_code']);
        $message = "Promo code removed.";
    }
    
    // Clear entire cart
    if (isset($_POST['clear_cart'])) {
        unset($_SESSION['cart']);
        unset($_SESSION['promo_code']);
        $message = "Your cart has been cleared.";
    }
}

// Calculate cart totals
$subtotal = 0;
$total_items = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $total_items += $item['quantity'];
    }
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
?>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <h1>Your Shopping Cart</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Cart</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Cart Section -->
<section class="cart-section">
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($_SESSION['cart'])): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="products.php" class="btn-primary">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="cart-table-wrapper">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                    <tr>
                                        <td class="product-cell">
                                            <div class="product-info">
                                                <a href="product.php?id=<?= $product_id ?>" class="product-image">
                                                    <img src="uploads/products/<?= $item['image'] ?>" 
                                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                                         onerror="this.src='https://via.placeholder.com/80x80?text=No+Image'">
                                                </a>
                                                <div class="product-details">
                                                    <a href="product.php?id=<?= $product_id ?>" class="product-title">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="price-cell">
                                            $<?= number_format($item['price'], 2) ?>
                                        </td>
                                        <td class="quantity-cell">
                                            <form method="post" class="quantity-form" id="form-<?= $product_id ?>">
                                                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                                <div class="quantity-controls">
                                                    <button type="button" class="quantity-btn minus" data-product-id="<?= $product_id ?>">-</button>
                                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="quantity-input" data-product-id="<?= $product_id ?>">
                                                    <button type="button" class="quantity-btn plus" data-product-id="<?= $product_id ?>">+</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="total-cell">
                                            $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                        </td>
                                        <td class="remove-cell">
                                            <form method="post">
                                                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                                                <button type="submit" name="remove_item" class="remove-btn">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="cart-actions">
                        <a href="products.php" class="btn-outline">Continue Shopping</a>
                        <form method="post" class="d-inline">
                            <button type="submit" name="clear_cart" class="btn-secondary" onclick="return confirm('Are you sure you want to clear your cart?')">Clear Cart</button>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?= $total_items ?> items)</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                            <div class="summary-row discount">
                                <span>
                                    Discount 
                                    <?php if (isset($_SESSION['promo_code'])): ?>
                                        (<?= htmlspecialchars($_SESSION['promo_code']['code']) ?>)
                                        <form method="post" class="d-inline">
                                            <button type="submit" name="clear_promo" class="clear-promo">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                                <span>-$<?= number_format($discount, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Tax (8%)</span>
                            <span>$<?= number_format($tax, 2) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span>
                                <?php if ($shipping > 0): ?>
                                    $<?= number_format($shipping, 2) ?>
                                <?php else: ?>
                                    <span class="free-shipping">FREE</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < 50 && $shipping > 0): ?>
                            <div class="free-shipping-message">
                                Add $<?= number_format(50 - $subtotal, 2) ?> more for FREE shipping!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>
                        
                        <!-- Promo Code Form -->
                        <?php if (!isset($_SESSION['promo_code'])): ?>
                            <form method="post" class="promo-form">
                                <div class="promo-input-group">
                                    <input type="text" name="promo_code" placeholder="Enter promo code" required>
                                    <button type="submit" name="apply_promo">Apply</button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Proceed to Checkout Button -->
                        <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
                        
                        <!-- Payment Icons -->
                        <div class="secure-checkout">
                            <p>Secure Checkout</p>
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
        <?php endif; ?>
    </div>
</section>

<!-- Cart Page Styling -->
<style>
    /* Cart page styling */
    .cart-section {
        padding-bottom: 80px;
    }
    
    /* Empty cart styling */
    .empty-cart {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-cart-icon {
        font-size: 5rem;
        color: var(--accent-color);
        margin-bottom: 20px;
    }
    
    .empty-cart h2 {
        font-size: 2rem;
        margin-bottom: 15px;
        color: var(--primary-color);
    }
    
    .empty-cart p {
        font-size: 1.1rem;
        color: var(--text-medium);
        margin-bottom: 30px;
    }
    
    /* Cart table styling */
    .cart-table-wrapper {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        overflow-x: auto;
        position: relative;
    }
    
    .cart-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .cart-table thead {
        background-color: var(--background-beige);
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .cart-table th {
        padding: 15px;
        text-align: left;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--primary-color);
    }
    
    .cart-table td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .cart-table tr:last-child td {
        border-bottom: none;
    }
    
    /* Product cell styling */
    .product-info {
        display: flex;
        align-items: center;
    }
    
    .product-image {
        width: 80px;
        height: 80px;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-details {
        flex-grow: 1;
    }
    
    .product-title {
        font-family: var(--font-primary);
        font-size: 1.1rem;
        color: var(--primary-color);
        font-weight: 500;
        display: block;
        margin-bottom: 5px;
    }
    
    .product-title:hover {
        color: var(--secondary-color);
    }
    
    /* Price cell styling */
    .price-cell {
        font-weight: 500;
        color: var(--text-dark);
    }
    
    /* Quantity cell styling */
    .quantity-form {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .quantity-controls {
        display: flex;
        align-items: center;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 4px;
    }
    
    .quantity-btn {
        background: none;
        border: none;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        color: var(--text-medium);
    }
    
    .quantity-input {
        width: 40px;
        text-align: center;
        border: none;
        outline: none;
        font-size: 0.9rem;
        -moz-appearance: textfield;
    }
    
    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .update-btn {
        background: none;
        border: none;
        color: var(--accent-color);
        font-size: 0.8rem;
        padding: 5px 0;
        cursor: pointer;
        transition: var(--transition);
        margin-top: 5px;
    }
    
    .update-btn:hover {
        color: var(--primary-color);
    }
    
    /* Total cell styling */
    .total-cell {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    /* Remove cell styling */
    .remove-btn {
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
        transition: var(--transition);
    }
    
    .remove-btn:hover {
        color: #dc3545;
    }
    
    /* Cart actions styling */
    .cart-actions {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
    }
    
    /* Cart summary styling */
    .cart-summary {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
        padding: 25px;
        position: relative;
    }
    
    .cart-summary h3 {
        font-size: 1.3rem;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        color: var(--primary-color);
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        color: var(--text-medium);
        font-size: 0.95rem;
    }
    
    .summary-row.discount {
        color: #28a745;
    }
    
    .summary-row.total {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.1);
        font-weight: 600;
        font-size: 1.2rem;
        color: var(--primary-color);
    }
    
    .free-shipping {
        color: #28a745;
        font-weight: 500;
    }
    
    .free-shipping-message {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        font-size: 0.9rem;
        margin: 10px 0;
        color: var(--text-medium);
        text-align: center;
    }
    
    /* Promo code styling */
    .promo-form {
        margin: 20px 0;
    }
    
    .promo-input-group {
        display: flex;
    }
    
    .promo-input-group input {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid rgba(0,0,0,0.1);
        border-right: none;
        border-radius: 4px 0 0 4px;
    }
    
    .promo-input-group button {
        background-color: var(--accent-color);
        color: var(--primary-color);
        border: none;
        padding: 10px 15px;
        font-weight: 500;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .promo-input-group button:hover {
        background-color: #c2a677;
    }
    
    .clear-promo {
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
        padding: 0;
        margin-left: 5px;
    }
    
    .clear-promo:hover {
        color: #dc3545;
    }
    
    /* Checkout button styling */
    .btn-checkout {
        display: block;
        width: 100%;
        background-color: var(--primary-color);
        color: white;
        text-align: center;
        padding: 15px;
        border-radius: 4px;
        font-weight: 500;
        margin: 20px 0;
        transition: var(--transition);
    }
    
    .btn-checkout:hover {
        background-color: #2a160d;
        color: white;
    }
    
    /* Secure checkout section */
    .secure-checkout {
        text-align: center;
        margin-top: 20px;
    }
    
    .secure-checkout p {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-bottom: 10px;
    }
    
    .payment-icons {
        display: flex;
        justify-content: center;
        gap: 10px;
        font-size: 1.7rem;
        color: var(--text-medium);
    }
    
    /* Additional styling for new functionality */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(209, 183, 138, 0.3);
        border-radius: 50%;
        border-top-color: var(--accent-color);
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .promo-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 5px;
        font-size: 0.95rem;
        font-weight: 500;
        z-index: 1000;
        animation: fadeInOut 3s ease;
    }
    
    .promo-notification.success {
        background-color: #dff2e1;
        color: #28a745;
        border-left: 4px solid #28a745;
    }
    
    .promo-notification.error {
        background-color: #f8d7da;
        color: #dc3545;
        border-left: 4px solid #dc3545;
    }
    
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(20px); }
        10% { opacity: 1; transform: translateY(0); }
        90% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
</style>

<!-- JavaScript for cart functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quantity increment/decrement with automatic update
    const minusButtons = document.querySelectorAll('.quantity-btn.minus');
    const plusButtons = document.querySelectorAll('.quantity-btn.plus');
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    // Helper function to update cart
    function updateCart(productId, quantity) {
        const form = document.getElementById('form-' + productId);
        const input = form.querySelector('.quantity-input');
        input.value = quantity;
        
        // Submit form via AJAX to avoid page reload
        const formData = new FormData(form);
        formData.append('update_quantity', 'true');
        
        // Show loading indicator
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="spinner"></div>';
        document.querySelector('.cart-table-wrapper').appendChild(loadingOverlay);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Extract and update only the cart content
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update cart table
            const newCartTable = doc.querySelector('.cart-table');
            if(newCartTable) {
                document.querySelector('.cart-table').innerHTML = newCartTable.innerHTML;
            }
            
            // Update cart summary
            const newCartSummary = doc.querySelector('.cart-summary');
            if(newCartSummary) {
                document.querySelector('.cart-summary').innerHTML = newCartSummary.innerHTML;
            }
            
            // Reinitialize event listeners
            initializeEventListeners();
            
            // Remove loading overlay
            document.querySelector('.loading-overlay').remove();
        })
        .catch(error => {
            console.error('Error updating cart:', error);
            document.querySelector('.loading-overlay').remove();
            alert('There was an error updating your cart. Please try again.');
        });
    }
    
    function initializeEventListeners() {
        // Minus button click
        document.querySelectorAll('.quantity-btn.minus').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                const currentValue = parseInt(input.value);
                if (currentValue > 1) {
                    updateCart(productId, currentValue - 1);
                }
            });
        });
        
        // Plus button click
        document.querySelectorAll('.quantity-btn.plus').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                const currentValue = parseInt(input.value);
                updateCart(productId, currentValue + 1);
            });
        });
        
        // Input change event
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const productId = this.getAttribute('data-product-id');
                let quantity = parseInt(this.value);
                if (quantity < 1) quantity = 1;
                updateCart(productId, quantity);
            });
        });
        
        // Promo code form submission
        const promoForm = document.querySelector('.promo-form');
        if (promoForm) {
            promoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading indicator
                const loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'loading-overlay';
                loadingOverlay.innerHTML = '<div class="spinner"></div>';
                document.querySelector('.cart-summary').appendChild(loadingOverlay);
                
                const formData = new FormData(this);
                formData.append('apply_promo', 'true');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Extract and update only the cart summary
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Remove loading overlay
                    document.querySelector('.loading-overlay').remove();
                    
                    // Check for error message
                    const errorMessage = doc.querySelector('.alert-danger');
                    if (errorMessage) {
                        // Create and show error notification
                        const notification = document.createElement('div');
                        notification.className = 'promo-notification error';
                        notification.textContent = errorMessage.textContent.trim();
                        document.querySelector('.cart-summary').appendChild(notification);
                        
                        setTimeout(() => {
                            notification.remove();
                        }, 3000);
                    } else {
                        // Update cart summary
                        const newCartSummary = doc.querySelector('.cart-summary');
                        if(newCartSummary) {
                            document.querySelector('.cart-summary').innerHTML = newCartSummary.innerHTML;
                            initializeEventListeners();
                            
                            // Show success notification
                            const notification = document.createElement('div');
                            notification.className = 'promo-notification success';
                            notification.textContent = 'Promo code applied successfully!';
                            document.querySelector('.cart-summary').appendChild(notification);
                            
                            setTimeout(() => {
                                notification.remove();
                            }, 3000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error applying promo code:', error);
                    document.querySelector('.loading-overlay').remove();
                    alert('There was an error applying the promo code. Please try again.');
                });
            });
        }
        
        // Clear promo code
        document.querySelectorAll('.clear-promo').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('clear_promo', 'true');
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Extract and update only the cart summary
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update cart summary
                    const newCartSummary = doc.querySelector('.cart-summary');
                    if(newCartSummary) {
                        document.querySelector('.cart-summary').innerHTML = newCartSummary.innerHTML;
                        initializeEventListeners();
                    }
                })
                .catch(error => {
                    console.error('Error clearing promo code:', error);
                    alert('There was an error clearing the promo code. Please try again.');
                });
            });
        });
    }
    
    // Initialize all event listeners
    initializeEventListeners();
});
</script>

<?php require_once 'includes/footer.php'; ?>