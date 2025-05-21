<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if there is a last order ID in session
if (!isset($_SESSION['last_order_id'])) {
    header('Location: index.php');
    exit;
}

$order_id = $_SESSION['last_order_id'];

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get order information
$order_query = "SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
               FROM orders o 
               WHERE o.id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id]);

if ($order_stmt->rowCount() === 0) {
    header('Location: index.php');
    exit;
}

$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

// Get order items
$items_query = "SELECT oi.*, p.name, p.image 
               FROM order_items oi
               JOIN products p ON oi.product_id = p.id
               WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Clear the last order ID from session
unset($_SESSION['last_order_id']);

require_once 'includes/header.php';
?>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <h1>Order Confirmation</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Order Confirmation</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Confirmation Section -->
<section class="confirmation-section">
    <div class="container">
        <div class="confirmation-box">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h2>Thank You for Your Order!</h2>
            <p class="confirmation-message">Your order #<?= $order_id ?> has been placed successfully and is being processed.</p>
            
            <div class="order-details">
                <div class="order-info">
                    <div class="info-item">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value">#<?= $order_id ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total:</span>
                        <span class="info-value">$<?= number_format($order['total_amount'], 2) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></span>
                    </div>
                </div>
                
                <div class="order-summary-table">
                    <h3>Order Summary</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td class="product-cell">
                                        <div class="product-info">
                                            <div class="product-image">
                                                <img src="uploads/products/<?= $item['image'] ?>" 
                                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                                     onerror="this.src='https://via.placeholder.com/50x50'">
                                            </div>
                                            <div class="product-name">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td>$<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end">Subtotal:</td>
                                <td>$<?= number_format($order['subtotal'], 2) ?></td>
                            </tr>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end">Discount:</td>
                                    <td>-$<?= number_format($order['discount_amount'], 2) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" class="text-end">Shipping:</td>
                                <td>$<?= number_format($order['shipping_cost'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end">Tax:</td>
                                <td>$<?= number_format($order['tax_amount'], 2) ?></td>
                            </tr>
                            <tr class="total-row">
                                <td colspan="3" class="text-end">Total:</td>
                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="next-steps">
                <p>A confirmation email has been sent to your email address. You can track the status of your order in your <a href="account.php">account dashboard</a>.</p>
                
                <div class="action-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="account.php#orders" class="btn-primary">View Your Orders</a>
                    <?php endif; ?>
                    <a href="products.php" class="btn-secondary">Continue Shopping</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Confirmation page styling */
    .confirmation-section {
        padding-bottom: 80px;
    }
    
    .confirmation-box {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 0 25px rgba(0,0,0,0.05);
        padding: 40px;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .confirmation-icon {
        text-align: center;
        margin-bottom: 20px;
        color: #28a745;
        font-size: 4rem;
    }
    
    .confirmation-box h2 {
        text-align: center;
        color: var(--primary-color);
        margin-bottom: 15px;
    }
    
    .confirmation-message {
        text-align: center;
        color: var(--text-medium);
        font-size: 1.1rem;
        margin-bottom: 30px;
    }
    
    .order-details {
        border-top: 1px solid rgba(0,0,0,0.1);
        padding-top: 30px;
    }
    
    .order-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .info-item {
        display: flex;
        align-items: baseline;
    }
    
    .info-label {
        font-weight: 500;
        color: var(--text-medium);
        width: 130px;
    }
    
    .info-value {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .order-summary-table h3 {
        font-size: 1.3rem;
        color: var(--primary-color);
        margin-bottom: 15px;
    }
    
    .table {
        border: 1px solid rgba(0,0,0,0.1);
    }
    
    .table thead {
        background-color: var(--background-beige);
    }
    
    .table th,
    .table td {
        padding: 12px 15px;
    }
    
    .product-cell {
        min-width: 250px;
    }
    
    .product-info {
        display: flex;
        align-items: center;
    }
    
    .product-image {
        width: 50px;
        height: 50px;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 3px;
    }
    
    .product-name {
        font-weight: 500;
    }
    
    .table tfoot tr {
        background-color: #f8f9fa;
    }
    
    .table tfoot td {
        padding: 10px 15px;
    }
    
    .total-row {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .next-steps {
        margin-top: 30px;
        text-align: center;
        border-top: 1px solid rgba(0,0,0,0.1);
        padding-top: 30px;
    }
    
    .next-steps p {
        margin-bottom: 20px;
        color: var(--text-medium);
    }
    
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    /* Responsive adjustments */
    @media (max-width: 767px) {
        .confirmation-box {
            padding: 20px;
        }
        
        .order-info {
            grid-template-columns: 1fr;
        }
        
        .table th, 
        .table td {
            padding: 8px 10px;
        }
        
        .product-cell {
            min-width: 180px;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>