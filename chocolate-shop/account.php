<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

require_once 'includes/header.php';
// header.php already includes functions.php, so getStatusBadgeClass() is available

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get user information - REMOVED last_login from query
$user_query = "SELECT id, username, email, phone, created_at FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user addresses
$address_query = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC";
$address_stmt = $db->prepare($address_query);
$address_stmt->execute([$_SESSION['user_id']]);
$addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders
$orders_query = "SELECT o.*, 
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                FROM orders o 
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT 5";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute([$_SESSION['user_id']]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($email)) {
        $error = "Username and email are required fields";
    } else {
        // Check if username or email already exists for other users
        $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email, $_SESSION['user_id']]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username or email already in use by another account";
        } else {
            // Start transaction
            $db->beginTransaction();
            try {
                // If changing password
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    $pwd_query = "SELECT password FROM users WHERE id = ?";
                    $pwd_stmt = $db->prepare($pwd_query);
                    $pwd_stmt->execute([$_SESSION['user_id']]);
                    $current_hash = $pwd_stmt->fetchColumn();
                    
                    if (!password_verify($current_password, $current_hash)) {
                        throw new Exception("Current password is incorrect");
                    }
                    
                    if ($new_password !== $confirm_password) {
                        throw new Exception("New passwords do not match");
                    }
                    
                    if (strlen($new_password) < 6) {
                        throw new Exception("New password must be at least 6 characters");
                    }
                    
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pwd_query = "UPDATE users SET password = ? WHERE id = ?";
                    $update_pwd_stmt = $db->prepare($update_pwd_query);
                    $update_pwd_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                }
                
                // Update profile
                $update_query = "UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$username, $email, $phone, $_SESSION['user_id']]);
                
                // Update session username
                $_SESSION['username'] = $username;
                
                $db->commit();
                $message = "Profile updated successfully";
                
                // Refresh user data
                $user_stmt->execute([$_SESSION['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Handle address operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_action'])) {
    $address_action = $_POST['address_action'];
    
    // Add or update address
    if ($address_action === 'save_address') {
        $address_id = isset($_POST['address_id']) ? $_POST['address_id'] : null;
        $address_line1 = trim($_POST['address_line1']);
        $address_line2 = trim($_POST['address_line2']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $postal_code = trim($_POST['postal_code']);
        $country = trim($_POST['country']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validate input
        if (empty($address_line1) || empty($city) || empty($state) || empty($postal_code) || empty($country)) {
            $error = "Please fill in all required address fields";
        } else {
            try {
                $db->beginTransaction();
                
                // If setting as default, unset other defaults
                if ($is_default) {
                    $unset_query = "UPDATE addresses SET is_default = 0 WHERE user_id = ?";
                    $unset_stmt = $db->prepare($unset_query);
                    $unset_stmt->execute([$_SESSION['user_id']]);
                }
                
                // If updating existing address
                if ($address_id) {
                    $update_query = "UPDATE addresses SET 
                                    address_line1 = ?, address_line2 = ?, city = ?, state = ?,
                                    postal_code = ?, country = ?, is_default = ?
                                    WHERE id = ? AND user_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([
                        $address_line1, $address_line2, $city, $state, 
                        $postal_code, $country, $is_default,
                        $address_id, $_SESSION['user_id']
                    ]);
                    $message = "Address updated successfully";
                } 
                // Adding new address
                else {
                    $insert_query = "INSERT INTO addresses 
                                    (user_id, address_line1, address_line2, city, state, postal_code, country, is_default)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->execute([
                        $_SESSION['user_id'], $address_line1, $address_line2, $city, $state, 
                        $postal_code, $country, $is_default
                    ]);
                    $message = "Address added successfully";
                }
                
                $db->commit();
                
                // Refresh addresses
                $address_stmt->execute([$_SESSION['user_id']]);
                $addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error saving address: " . $e->getMessage();
            }
        }
    }
    
    // Delete address
    if ($address_action === 'delete_address' && isset($_POST['address_id'])) {
        $address_id = $_POST['address_id'];
        
        try {
            $delete_query = "DELETE FROM addresses WHERE id = ? AND user_id = ?";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->execute([$address_id, $_SESSION['user_id']]);
            
            $message = "Address deleted successfully";
            
            // Refresh addresses
            $address_stmt->execute([$_SESSION['user_id']]);
            $addresses = $address_stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = "Error deleting address: " . $e->getMessage();
        }
    }
}
?>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <h1>My Account</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">My Account</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Account Section -->
<section class="account-section">
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
        
        <div class="row">
            <!-- Account Sidebar -->
            <div class="col-lg-3">
                <div class="account-sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <span><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                        </div>
                        <h3><?= htmlspecialchars($user['username']) ?></h3>
                        <p class="user-email"><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                    
                    <ul class="account-nav" id="account-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="dashboard-tab" data-bs-toggle="pill" href="#dashboard" role="tab">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="orders-tab" data-bs-toggle="pill" href="#orders" role="tab">
                                <i class="fas fa-shopping-bag"></i> Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="addresses-tab" data-bs-toggle="pill" href="#addresses" role="tab">
                                <i class="fas fa-map-marker-alt"></i> Addresses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="profile-tab" data-bs-toggle="pill" href="#profile" role="tab">
                                <i class="fas fa-user"></i> Account Details
                            </a>
                        </li>
                        <?php if(isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'root_admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/dashboard.php">
                                <i class="fas fa-user-shield"></i> Admin Dashboard
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Account Content -->
            <div class="col-lg-9">
                <div class="tab-content account-content">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                        <h2>Dashboard</h2>
                        
                        <div class="account-welcome">
                            <p>Hello <strong><?= htmlspecialchars($user['username']) ?></strong>, welcome back!</p>
                            <p>From your account dashboard you can view your recent orders, manage your shipping addresses, and edit your account details.</p>
                        </div>
                        
                        <div class="dashboard-stats">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-shopping-bag"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>Orders</h4>
                                            <span class="stat-count"><?= count($orders) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>Addresses</h4>
                                            <span class="stat-count"><?= count($addresses) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h4>Member Since</h4>
                                            <span class="stat-date"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($orders)): ?>
                            <div class="recent-orders-section">
                                <h3>Recent Orders</h3>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                                <tr onclick="window.location='order-detail.php?id=<?= $order['id'] ?>';" style="cursor: pointer;">
                                                    <td>#<?= $order['id'] ?></td>
                                                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?= $order['status'] ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="#orders" class="btn-secondary" data-bs-toggle="pill" role="tab">View All Orders</a>
                            </div>
                        <?php else: ?>
                            <div class="no-orders">
                                <p>You haven't placed any orders yet.</p>
                                <a href="products.php" class="btn-primary">Start Shopping</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Orders Tab -->
                    <div class="tab-pane fade" id="orders" role="tabpanel">
                        <h2>My Orders</h2>
                        
                        <?php if (empty($orders)): ?>
                            <div class="no-orders">
                                <p>You haven't placed any orders yet.</p>
                                <a href="products.php" class="btn-primary">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table orders-table">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr onclick="window.location='order-detail.php?id=<?= $order['id'] ?>';" style="cursor: pointer;">
                                                <td>#<?= $order['id'] ?></td>
                                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                                <td><?= $order['item_count'] ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $order['status'] ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Addresses Tab -->
                    <div class="tab-pane fade" id="addresses" role="tabpanel">
                        <h2>My Addresses</h2>
                        
                        <div class="row g-4 mb-4">
                            <?php if (empty($addresses)): ?>
                                <div class="col-12">
                                    <div class="empty-addresses">
                                        <p>You haven't saved any addresses yet.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6">
                                        <div class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                                            <?php if ($address['is_default']): ?>
                                                <div class="default-badge">Default</div>
                                            <?php endif; ?>
                                            
                                            <div class="address-content">
                                                <p><?= htmlspecialchars($address['address_line1']) ?></p>
                                                <?php if (!empty($address['address_line2'])): ?>
                                                    <p><?= htmlspecialchars($address['address_line2']) ?></p>
                                                <?php endif; ?>
                                                <p>
                                                    <?= htmlspecialchars($address['city']) ?>, 
                                                    <?= htmlspecialchars($address['state']) ?> 
                                                    <?= htmlspecialchars($address['postal_code']) ?>
                                                </p>
                                                <p><?= htmlspecialchars($address['country']) ?></p>
                                            </div>
                                            
                                            <div class="address-actions">
                                                <button type="button" class="btn-edit-address" data-bs-toggle="modal" data-bs-target="#addressModal" 
                                                        data-address='<?= json_encode($address) ?>'>
                                                    Edit
                                                </button>
                                                
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this address?')">
                                                    <input type="hidden" name="address_action" value="delete_address">
                                                    <input type="hidden" name="address_id" value="<?= $address['id'] ?>">
                                                    <button type="submit" class="btn-delete-address">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="col-12">
                                <button type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal">
                                    <i class="fas fa-plus"></i> Add New Address
                                </button>
                            </div>
                        </div>
                        
                        <!-- Address Modal -->
                        <div class="modal fade" id="addressModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addressModalLabel">Add New Address</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="post" id="addressForm">
                                            <input type="hidden" name="address_action" value="save_address">
                                            <input type="hidden" name="address_id" id="address_id">
                                            
                                            <div class="mb-3">
                                                <label for="address_line1" class="form-label">Address Line 1 *</label>
                                                <input type="text" class="form-control" id="address_line1" name="address_line1" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="address_line2" class="form-label">Address Line 2</label>
                                                <input type="text" class="form-control" id="address_line2" name="address_line2">
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
                                                    <input type="text" class="form-control" id="country" name="country" required>
                                                </div>
                                            </div>
                                            
                                            <div class="form-check mb-3">
                                                <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                                                <label class="form-check-label" for="is_default">Set as default address</label>
                                            </div>
                                            
                                            <div class="modal-footer">
                                                <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn-primary">Save Address</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Details Tab -->
                    <div class="tab-pane fade" id="profile" role="tabpanel">
                        <h2>Account Details</h2>
                        
                        <form method="post" class="profile-form">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                            
                            <h3>Password Change</h3>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="form-text text-muted">Leave blank to keep the same password</small>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Account Page Styling -->
<style>
    /* Account page styling */
    .account-section {
        padding-bottom: 80px;
    }
    
    /* User sidebar */
    .account-sidebar {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    .user-info {
        padding: 30px 20px;
        text-align: center;
        background-color: var(--background-beige);
    }
    
    .user-avatar {
        width: 80px;
        height: 80px;
        background-color: var(--primary-color);
        color: white;
        font-size: 2rem;
        font-weight: 600;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
    }
    
    .user-info h3 {
        font-size: 1.3rem;
        margin-bottom: 5px;
        color: var(--primary-color);
    }
    
    .user-email {
        font-size: 0.9rem;
        color: var(--text-medium);
        margin-bottom: 0;
    }
    
    .account-nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .account-nav li {
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .account-nav li:last-child {
        border-bottom: none;
    }
    
    .account-nav .nav-link {
        display: block;
        padding: 15px 20px;
        color: var(--text-dark);
        transition: var(--transition);
        border-left: 3px solid transparent;
    }
    
    .account-nav .nav-link i {
        margin-right: 10px;
        color: var(--text-medium);
        width: 20px;
        text-align: center;
    }
    
    .account-nav .nav-link:hover,
    .account-nav .nav-link.active {
        background-color: rgba(0,0,0,0.02);
        color: var(--primary-color);
        border-left-color: var(--accent-color);
    }
    
    .account-nav .nav-link:hover i,
    .account-nav .nav-link.active i {
        color: var(--accent-color);
    }
    
    /* Account content */
    .account-content {
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
        padding: 30px;
        min-height: 500px;
    }
    
    .account-content h2 {
        font-size: 1.8rem;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        color: var(--primary-color);
    }
    
    .account-content h3 {
        font-size: 1.3rem;
        margin: 30px 0 20px;
        color: var(--primary-color);
    }
    
    /* Dashboard */
    .account-welcome {
        margin-bottom: 30px;
    }
    
    .dashboard-stats {
        margin-bottom: 30px;
    }
    
    .stat-card {
        background-color: var(--background-beige);
        padding: 20px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        height: 100%;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        margin-right: 15px;
    }
    
    .stat-info h4 {
        font-size: 1rem;
        margin-bottom: 5px;
        color: var(--text-medium);
    }
    
    .stat-count {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .stat-date {
        font-size: 1.1rem;
        color: var(--primary-color);
    }
    
    /* Recent orders */
    .recent-orders-section {
        margin-top: 30px;
    }
    
    .table {
        margin-bottom: 30px;
        background-color: white;
    }
    
    .table thead {
        background-color: var(--background-beige);
    }
    
    .table th {
        font-weight: 600;
        color: var(--primary-color);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 15px;
    }
    
    .table td {
        padding: 15px;
        vertical-align: middle;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .status-processing {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .status-shipped {
        background-color: #cce5ff;
        color: #004085;
    }
    
    .status-delivered {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .no-orders {
        text-align: center;
        padding: 40px 0;
    }
    
    .no-orders p {
        margin-bottom: 20px;
        font-size: 1.1rem;
        color: var(--text-medium);
    }
    
    /* Addresses */
    .address-card {
        background-color: var(--background-beige);
        border-radius: 5px;
        padding: 20px;
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .address-card.default {
        border: 1px solid var(--accent-color);
    }
    
    .default-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        font-size: 0.75rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }
    
    .address-content {
        margin-bottom: 15px;
        flex-grow: 1;
    }
    
    .address-content p {
        margin-bottom: 5px;
    }
    
    .address-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: auto;
    }
    
    .btn-edit-address,
    .btn-delete-address {
        background: none;
        border: none;
        font-size: 0.9rem;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .btn-edit-address {
        color: var(--accent-color);
    }
    
    .btn-delete-address {
        color: #dc3545;
    }
    
    .btn-edit-address:hover {
        color: var(--primary-color);
    }
    
    .btn-delete-address:hover {
        color: #bd2130;
    }
    
    .empty-addresses {
        text-align: center;
        padding: 40px 0;
    }
    
    /* Forms */
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
    
    .form-text {
        font-size: 0.85rem;
    }
    
    /* Modal styling */
    .modal-content {
        border: none;
        border-radius: 5px;
    }
    
    .modal-header {
        background-color: var(--background-beige);
        border-bottom: none;
    }
    
    .modal-title {
        color: var(--primary-color);
        font-weight: 500;
    }
    
    .modal-footer {
        border-top: none;
    }
    
    /* Responsive adjustments */
    @media (max-width: 991px) {
        .account-content {
            margin-top: 30px;
        }
    }
    
    @media (max-width: 767px) {
        .account-content {
            padding: 20px;
        }
        
        .table th,
        .table td {
            padding: 10px;
        }
    }
</style>

<!-- JavaScript for address modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addressModal = document.getElementById('addressModal');
    const addressForm = document.getElementById('addressForm');
    
    // Reset form when modal is closed
    addressModal.addEventListener('hidden.bs.modal', function() {
        addressForm.reset();
        document.getElementById('address_id').value = '';
        document.getElementById('addressModalLabel').textContent = 'Add New Address';
    });
    
    // Fill form when editing an address
    const editButtons = document.querySelectorAll('.btn-edit-address');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const addressData = JSON.parse(this.getAttribute('data-address'));
            
            document.getElementById('address_id').value = addressData.id;
            document.getElementById('address_line1').value = addressData.address_line1;
            document.getElementById('address_line2').value = addressData.address_line2 || '';
            document.getElementById('city').value = addressData.city;
            document.getElementById('state').value = addressData.state;
            document.getElementById('postal_code').value = addressData.postal_code;
            document.getElementById('country').value = addressData.country;
            document.getElementById('is_default').checked = addressData.is_default === 1;
            
            document.getElementById('addressModalLabel').textContent = 'Edit Address';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

