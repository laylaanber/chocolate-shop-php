<?php
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Check if user is logged in, is an admin, and is active
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if admin account is active
$active_check_query = "SELECT is_active FROM users WHERE id = ? AND role = 'admin'";
$active_check_stmt = $db->prepare($active_check_query);
$active_check_stmt->execute([$_SESSION['user_id']]);
$is_admin_active = $active_check_stmt->fetchColumn();

// Use the function that's defined in admin_header.php
$is_root_admin = isCurrentUserRootAdmin();

// If admin is inactive, restrict their actions
$read_only_mode = !$is_admin_active;

$message = '';
$error = '';

// If admin is inactive, show warning
if ($read_only_mode) {
    $error = "Your admin account is currently inactive. You are in read-only mode until another admin activates your account.";
}

// Handle user deletion - with role-based restrictions
if (!$read_only_mode && isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $user_role = getUserRole($db, $user_id);
    
    // Only root admin can delete admins
    if ($user_role === 'admin' && !$is_root_admin) {
        $error = "Only root administrators can delete admin accounts!";
    }
    // Prevent deleting self
    else if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } 
    else {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Check if user has orders
            $order_check = "SELECT COUNT(*) FROM orders WHERE user_id = ?";
            $order_stmt = $db->prepare($order_check);
            $order_stmt->execute([$user_id]);
            $has_orders = $order_stmt->fetchColumn() > 0;
            
            if ($has_orders) {
                // Just anonymize the user if they have orders
                $anonymize_query = "UPDATE users SET 
                                  username = CONCAT('deleted_', id),
                                  email = CONCAT('deleted_', id, '@example.com'),
                                  password = NULL,
                                  phone = NULL,
                                  is_active = 0
                                  WHERE id = ?";
                $anonymize_stmt = $db->prepare($anonymize_query);
                $anonymize_stmt->execute([$user_id]);
                
                $message = "User has existing orders and has been anonymized instead of deleted.";
            } else {
                // Delete addresses associated with the user
                $delete_addresses = "DELETE FROM addresses WHERE user_id = ?";
                $addr_stmt = $db->prepare($delete_addresses);
                $addr_stmt->execute([$user_id]);
                
                // Delete the user
                $delete_user = "DELETE FROM users WHERE id = ?";
                $user_stmt = $db->prepare($delete_user);
                $user_stmt->execute([$user_id]);
                
                $message = "User has been permanently deleted.";
            }
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Deleted user ID: $user_id");
            
            // Commit transaction
            $db->commit();
        } catch (PDOException $e) {
            // Rollback on error
            $db->rollback();
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Handle user status toggle (active/inactive)
if (!$read_only_mode && isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'];
    $user_role = getUserRole($db, $user_id);
    
    // Only root admin can change admin status
    if ($user_role === 'admin' && !$is_root_admin) {
        $error = "Only root administrators can change admin account status!";
    }
    // Prevent changing own status
    else if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot change your own account status!";
    } else {
        try {
            $query = "UPDATE users SET is_active = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $user_id]);
            
            $status_text = $new_status ? 'activated' : 'deactivated';
            $message = "User has been $status_text successfully.";
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Changed user ID: $user_id status to $status_text");
        } catch (PDOException $e) {
            $error = "Error updating user status: " . $e->getMessage();
        }
    }
}

// Handle toggling root admin status
if (!$read_only_mode && isset($_POST['toggle_root_admin']) && isset($_POST['user_id']) && isset($_POST['is_root'])) {
    $user_id = $_POST['user_id'];
    $is_root = $_POST['is_root'];
    
    // Only root admins can change root status
    if (!$is_root_admin) {
        $error = "Only root administrators can change root admin privileges!";
    }
    // Prevent changing own root status (additional security)
    else if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot modify your own root status!";
    } 
    // Make sure there's always at least one root admin left
    else if ($is_root == 0) {
        // Count the number of root admins
        $root_count_query = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_root_admin = 1";
        $root_count_stmt = $db->query($root_count_query);
        $root_count = $root_count_stmt->fetchColumn();
        
        if ($root_count <= 1) {
            $error = "Cannot remove the last root admin. At least one root admin must exist.";
        }
    }
    
    if (empty($error)) {
        try {
            // Check if user is an admin (can't make regular users root)
            $user_role = getUserRole($db, $user_id);
            if ($user_role !== 'admin') {
                throw new PDOException("Only admin users can have root privileges!");
            }

            // Update user root status
            $query = "UPDATE users SET is_root_admin = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$is_root, $user_id]);
            
            $root_text = $is_root ? 'granted' : 'revoked';
            $message = "Root admin privileges $root_text successfully.";
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Changed user ID: $user_id root status to $is_root");
        } catch (PDOException $e) {
            $error = "Error updating root admin status: " . $e->getMessage();
        }
    }
}

// Handle quick role changes (promotion/demotion)
if (!$read_only_mode && isset($_POST['quick_role_change']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Only root admins can change roles
    if (!$is_root_admin) {
        $error = "Only root administrators can change user roles!";
    }
    // Prevent changing own role (though UI shouldn't allow this)
    else if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot change your own role!";
    } 
    // Make sure new_role is valid
    else if (!in_array($new_role, ['admin', 'user'])) {
        $error = "Invalid role specified!";
    } 
    else {
        try {
            // Get current role to log change properly
            $current_role_query = "SELECT role FROM users WHERE id = ?";
            $current_role_stmt = $db->prepare($current_role_query);
            $current_role_stmt->execute([$user_id]);
            $current_role = $current_role_stmt->fetchColumn();
            
            // Update the role
            $query = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_role, $user_id]);
            
            // If demoting from admin, ensure they no longer have root privileges
            if ($current_role === 'admin' && $new_role === 'user') {
                $query = "UPDATE users SET is_root_admin = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
            }
            
            $role_change_text = ($new_role === 'admin') ? 'promoted to admin' : 'demoted to regular user';
            $message = "User has been $role_change_text successfully.";
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Changed user ID: $user_id role from $current_role to $new_role");
        } catch (PDOException $e) {
            $error = "Error updating user role: " . $e->getMessage();
        }
    }
}

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Allowed sort fields
$allowed_sorts = ['id', 'username', 'email', 'role', 'created_at', 'last_login', 'is_active'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_at';
}

// Allowed order directions
$allowed_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($order), $allowed_orders)) {
    $order = 'DESC';
}

// Query to get all users based on admin role
$query = "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count 
          FROM users u WHERE 1=1";
$params = [];

// Non-root admins can't see other admins
if (!$is_root_admin) {
    $query .= " AND (u.role != 'admin' OR u.id = ?)";
    $params[] = $_SESSION['user_id'];
}

// Apply filters
if (!empty($role_filter)) {
    // Non-root admins can't filter to see admins
    if ($role_filter === 'admin' && !$is_root_admin) {
        $role_filter = '';
    } else {
        $query .= " AND u.role = ?";
        $params[] = $role_filter;
    }
}

if ($status_filter !== '') {
    $query .= " AND u.is_active = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
$query .= " ORDER BY $sort $order";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics for the sidebar
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_count,
                (SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_root_admin = 1) as root_admin_count,
                (SELECT COUNT(*) FROM users WHERE role = 'user') as user_count,
                (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_count,
                (SELECT COUNT(*) FROM users WHERE is_active = 0) as inactive_count,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as today_count,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as week_count,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as month_count";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get user for edit if ID is provided
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    // Check permissions - non-root admins cannot edit other admins
    $user_role = getUserRole($db, $edit_id);
    
    if ($user_role === 'admin' && !$is_root_admin && $edit_id != $_SESSION['user_id']) {
        $error = "You don't have permission to edit admin accounts.";
    } else {
        $edit_query = "SELECT * FROM users WHERE id = ?";
        $edit_stmt = $db->prepare($edit_query);
        $edit_stmt->execute([$edit_id]);
        $edit_user = $edit_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user addresses
        if ($edit_user) {
            $addr_query = "SELECT * FROM addresses WHERE user_id = ?";
            $addr_stmt = $db->prepare($addr_query);
            $addr_stmt->execute([$edit_id]);
            $edit_user['addresses'] = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get user's recent orders
            $orders_query = "SELECT o.*, COUNT(oi.id) as item_count
                            FROM orders o
                            LEFT JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.user_id = ?
                            GROUP BY o.id
                            ORDER BY o.created_at DESC
                            LIMIT 5";
            $orders_stmt = $db->prepare($orders_query);
            $orders_stmt->execute([$edit_id]);
            $edit_user['recent_orders'] = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Process user edit form
if (!$read_only_mode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    $current_user_role = getUserRole($db, $user_id);
    
    // Security checks
    $can_proceed = true;
    $error_message = "";
    
    // Check if trying to promote to admin
    if ($current_user_role !== 'admin' && $role === 'admin' && !$is_root_admin) {
        $can_proceed = false;
        $error_message = "Only root administrators can promote users to admin role.";
    }
    // Check if trying to demote admin
    else if ($current_user_role === 'admin' && $role !== 'admin' && !$is_root_admin) {
        $can_proceed = false;
        $error_message = "Only root administrators can change admin roles.";
    }
    // Don't allow changing own role
    else if ($user_id == $_SESSION['user_id'] && $role != $_SESSION['user_role']) {
        $can_proceed = false;
        $error_message = "You cannot change your own role!";
    }
    
    if (!$can_proceed) {
        $error = $error_message;
    } else {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Check if email already exists for another user
            $check_email = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
            $email_stmt = $db->prepare($check_email);
            $email_stmt->execute([$email, $user_id]);
            if ($email_stmt->fetchColumn() > 0) {
                throw new PDOException("Email already exists for another user!");
            }
            
            // Update user profile
            $update = "UPDATE users SET 
                     username = ?, 
                     email = ?, 
                     phone = ?,
                     role = ?, 
                     is_active = ?,
                     notes = ?
                     WHERE id = ?";
            $stmt = $db->prepare($update);
            $stmt->execute([$username, $email, $phone, $role, $is_active, $notes, $user_id]);
            
            // Handle password change if requested - but only for the user's own account
            if (!empty($_POST['password']) && $user_id == $_SESSION['user_id']) {
                $password = $_POST['password'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $pass_update = "UPDATE users SET password = ? WHERE id = ?";
                $pass_stmt = $db->prepare($pass_update);
                $pass_stmt->execute([$hashed_password, $user_id]);
                
                // Log successful password change
                logAdminAction($db, $_SESSION['user_id'], "Updated own password");
            } elseif (!empty($_POST['password']) && $user_id != $_SESSION['user_id']) {
                // If someone tries to change another user's password, log the attempt
                logAdminAction($db, $_SESSION['user_id'], "SECURITY: Attempted to change password for user ID: $user_id (blocked)");
                $error = "Security restriction: You can only change your own password.";
                // Roll back transaction
                $db->rollback();
                // Exit the function and don't proceed with other updates
                return;
            }
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Updated user ID: $user_id information");
            
            // Commit transaction
            $db->commit();
            $message = "User updated successfully!";
        } catch (PDOException $e) {
            // Rollback on error
            $db->rollback();
            $error = "Error updating user: " . $e->getMessage();
        }
    }
}

// Handle adding a new user
if (!$read_only_mode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Non-root admins can't add admin users
    if ($role === 'admin' && !$is_root_admin) {
        $error = "Only root administrators can create admin accounts.";
    } else {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Check if username or email already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$username, $email]);
            
            if ($check_stmt->fetchColumn() > 0) {
                throw new PDOException("Username or email already exists!");
            }
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $insert = "INSERT INTO users (username, email, password, phone, role, is_active) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insert);
            $stmt->execute([$username, $email, $hashed_password, $phone, $role, $is_active]);
            
            $new_user_id = $db->lastInsertId();
            
            // Log the action
            logAdminAction($db, $_SESSION['user_id'], "Created new user ID: $new_user_id with role: $role");
            
            // Commit transaction
            $db->commit();
            $message = "User created successfully!";
        } catch (PDOException $e) {
            // Rollback on error
            $db->rollback();
            $error = "Error creating user: " . $e->getMessage();
        }
    }
}

// Helper functions
function getUserRole($db, $user_id) {
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function logAdminAction($db, $admin_id, $action) {
    try {
        $query = "INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$admin_id, $action, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        // Silent fail if log table doesn't exist
    }
}

function buildSortingUrl($field) {
    global $sort, $order, $role_filter, $status_filter, $search;
    
    $params = [];
    
    if ($field === $sort) {
        $new_order = ($order === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $new_order = 'ASC';
    }
    
    $params[] = "sort=$field";
    $params[] = "order=$new_order";
    
    if (!empty($role_filter)) {
        $params[] = "role=$role_filter";
    }
    
    if ($status_filter !== '') {
        $params[] = "status=$status_filter";
    }
    
    if (!empty($search)) {
        $params[] = "search=" . urlencode($search);
    }
    
    return implode("&", $params);
}

function getSortIcon($field) {
    global $sort, $order;
    
    if ($field !== $sort) {
        return '<i class="fas fa-sort text-muted"></i>';
    }
    
    if ($order === 'ASC') {
        return '<i class="fas fa-sort-up"></i>';
    } else {
        return '<i class="fas fa-sort-down"></i>';
    }
}

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
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">
          User Management
          <?php if ($read_only_mode): ?>
            <span class="badge badge-warning ml-2">READ ONLY</span>
          <?php endif; ?>
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Users</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<!-- Add these styles for better visual design -->
<style>
/* Modern color scheme and visual enhancements */
:root {
  --primary-color: #3f6ad8;
  --secondary-color: #6c757d;
  --success-color: #3ac47d;
  --info-color: #16aaff;
  --warning-color: #f7b924;
  --danger-color: #d92550;
  --light-color: #f8f9fa;
  --dark-color: #343a40;
  --card-shadow: 0 0.46875rem 2.1875rem rgba(4,9,20,0.03), 
                0 0.9375rem 1.40625rem rgba(4,9,20,0.03), 
                0 0.25rem 0.53125rem rgba(4,9,20,0.05), 
                0 0.125rem 0.1875rem rgba(4,9,20,0.03);
}

/* Card improvements */
.card {
  box-shadow: var(--card-shadow);
  border-radius: 0.25rem;
  border: none;
  margin-bottom: 1.5rem;
}

.card-outline {
  border-top: 3px solid var(--primary-color);
}

.card-outline.card-primary {
  border-top-color: var(--primary-color);
}

.card-outline.card-secondary {
  border-top-color: var(--secondary-color);
}

/* Improve header appearance */
.card-header {
  background-color: #fff;
  border-bottom: 1px solid rgba(0,0,0,0.08);
  padding: 0.75rem 1.25rem;
}

/* Table improvements */
.table {
  margin-bottom: 0;
}

.table thead th {
  border-top: none;
  background-color: rgba(0,0,0,0.03);
  font-weight: 600;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table td {
  vertical-align: middle;
  padding: 0.75rem;
  border-top: 1px solid rgba(0,0,0,0.08);
}

/* Badge improvements */
.badge {
  font-weight: 600;
  padding: 0.4em 0.7em;
  font-size: 80%;
  letter-spacing: 0.5px;
}

/* Button improvements */
.btn {
  font-weight: 500;
  letter-spacing: 0.5px;
}

.btn-group .btn {
  border-radius: 0.25rem !important;
  margin: 0 3px;
  box-shadow: 0 2px 2px 0 rgba(0,0,0,0.14), 
              0 3px 1px -2px rgba(0,0,0,0.12), 
              0 1px 5px 0 rgba(0,0,0,0.2);
}

/* Form improvements */
.form-control {
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);
}

.form-control:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem rgba(63,106,216,0.25);
}

.input-group-text {
  background-color: #f8f9fa;
  border: 1px solid rgba(0,0,0,0.2);
}

/* Tab improvements */
.nav-tabs {
  border-bottom: 1px solid rgba(0,0,0,0.1);
}

.nav-tabs .nav-link.active {
  border-color: rgba(0,0,0,0.1) rgba(0,0,0,0.1) #fff;
  background-color: #fff;
  font-weight: 600;
}

.nav-pills .nav-link.active {
  background-color: var(--primary-color);
  box-shadow: 0 3px 5px rgba(63,106,216,0.2);
}

/* Alert improvements */
.alert-container {
  top: 20px;
  right: 20px;
  z-index: 9999;
  max-width: 350px;
}

.alert-container .alert {
  border: none;
  border-radius: 0.25rem;
  box-shadow: 0 0.46875rem 2.1875rem rgba(4,9,20,0.03),
              0 0.9375rem 1.40625rem rgba(4,9,20,0.03),
              0 0.25rem 0.53125rem rgba(4,9,20,0.05),
              0 0.125rem 0.1875rem rgba(4,9,20,0.03);
}

/* Read-only mode indicator */
.read-only-badge {
  position: fixed;
  top: 15px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1030;
  padding: 0.5rem 1rem;
  border-radius: 30px;
  box-shadow: 0 4px 20px 0 rgba(0,0,0,.14), 0 7px 10px -5px rgba(244,67,54,.4);
}

/* User avatar improvements */
.user-avatar {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  border: 2px solid rgba(0,0,0,0.1);
  box-shadow: 0 3px 5px rgba(0,0,0,0.1);
  object-fit: cover;
}

/* Status indicator improvements */
.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 5px;
}

.status-active {
  background-color: var(--success-color);
  box-shadow: 0 0 0 3px rgba(58, 196, 125, 0.2);
}

.status-inactive {
  background-color: var(--danger-color);
  box-shadow: 0 0 0 3px rgba(217, 37, 80, 0.2);
}

/* Animation for hover effects */
.table-hover tbody tr:hover {
  background-color: rgba(63,106,216,0.05);
  transition: all 0.2s ease;
}

.btn {
  transition: all 0.2s ease;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* List group improvements */
.list-group-item {
  border: 1px solid rgba(0,0,0,0.08);
}

/* Improved scrollbars */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Empty state improvements */
.empty-state {
  padding: 3rem 1.5rem;
  text-align: center;
}

.empty-state-icon {
  font-size: 4rem;
  color: rgba(0,0,0,0.15);
  margin-bottom: 1rem;
}

.empty-state-text {
  color: #6c757d;
  font-size: 1.1rem;
}

/* Animation for notifications */
@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.alert {
  animation: slideInRight 0.3s forwards;
}
</style>

<!-- Read-only mode indicator banner -->
<?php if ($read_only_mode): ?>
<div class="read-only-badge bg-danger text-white">
  <i class="fas fa-eye mr-2"></i> READ ONLY MODE - Your admin account is inactive
</div>
<?php endif; ?>

<div class="row">
  <?php if ($edit_user): ?>
  <div class="col-md-8">
  <?php else: ?>
  <div class="col-md-12">
  <?php endif; ?>
    <!-- User List Card -->
    <div class="card card-outline card-primary">
      <div class="card-header p-2">
        <ul class="nav nav-pills">
          <li class="nav-item">
            <a class="nav-link <?= empty($role_filter) && $status_filter === '' ? 'active' : '' ?>" href="manage_users.php">
              <i class="fas fa-users mr-1"></i> All Users
            </a>
          </li>
          <?php if ($is_root_admin): ?>
          <li class="nav-item">
            <a class="nav-link <?= $role_filter === 'admin' ? 'active' : '' ?>" href="?role=admin">
              <i class="fas fa-user-shield mr-1"></i> Admins
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link <?= $role_filter === 'user' ? 'active' : '' ?>" href="?role=user">
              <i class="fas fa-user mr-1"></i> Regular Users
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === '1' ? 'active' : '' ?>" href="?status=1">
              <i class="fas fa-user-check mr-1"></i> Active
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === '0' ? 'active' : '' ?>" href="?status=0">
              <i class="fas fa-user-slash mr-1"></i> Inactive
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <!-- Show root admin notice only once per session using session variable -->
        <?php 
        // Only show the root admin message if this is first visit or explicitly requested
        $show_root_notice = $is_root_admin && (!isset($_SESSION['root_notice_shown']) || isset($_GET['show_help']));
        if ($show_root_notice): 
          // Mark as shown in the session
          $_SESSION['root_notice_shown'] = true;
        ?>
          <div class="alert alert-primary alert-dismissible mb-3">
            <div class="d-flex align-items-center">
              <i class="fas fa-crown mr-3" style="font-size: 1.5rem;"></i>
              <div>
                <strong>Root Admin Privileges:</strong>
                <p class="mb-0">As a root administrator, you can view all admin accounts, upgrade users to admins, and demote admins to users. You also have exclusive access to grant or revoke root admin privileges.</p>
              </div>
            </div>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
          </div>
        <?php endif; ?>
        
        <!-- Security notice - show only when editing users and can be dismissed permanently -->
        <?php 
        // Only show security notice when actually editing a user or when explicitly requested
        $show_security_notice = (isset($_GET['edit']) || isset($_GET['show_help'])) && 
                                !isset($_SESSION['security_notice_dismissed']);
        
        if ($show_security_notice): 
        ?>
          <div class="alert alert-info alert-dismissible mb-3" id="security-notice">
            <div class="d-flex align-items-center">
              <i class="fas fa-shield-alt mr-3" style="font-size: 1.5rem;"></i>
              <div>
                <strong>Security Notice:</strong>
                <p class="mb-0">For security reasons, administrators can only change their own passwords. If a user forgets their password, you can direct them to the password reset feature.</p>
              </div>
            </div>
            <button type="button" class="close" id="dismiss-security-notice" data-dismiss="alert">&times;</button>
          </div>
        <?php endif; ?>
        
        <!-- Error/success messages - only shown when they exist -->
        <?php if (!empty($message) || !empty($error)): ?>
          <div class="alert-container">
            <?php if (!empty($message)): ?>
              <div class="alert alert-success alert-dismissible fade show">
                <div class="d-flex align-items-center">
                  <i class="fas fa-check-circle mr-2"></i>
                  <div><?= $message ?></div>
                  <button type="button" class="close ml-2" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
              <div class="alert alert-danger alert-dismissible fade show">
                <div class="d-flex align-items-center">
                  <i class="fas fa-exclamation-circle mr-2"></i>
                  <div><?= $error ?></div>
                  <button type="button" class="close ml-2" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <!-- Search & Filter -->
        <div class="row mb-3">
          <div class="col-md-8">
            <form action="" method="get" class="form-inline">
              <?php if (!empty($role_filter)): ?>
                <input type="hidden" name="role" value="<?= $role_filter ?>">
              <?php endif; ?>
              
              <?php if ($status_filter !== ''): ?>
                <input type="hidden" name="status" value="<?= $status_filter ?>">
              <?php endif; ?>
              
              <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by username or email..." value="<?= htmlspecialchars($search) ?>">
                <div class="input-group-append">
                  <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i>
                  </button>
                </div>
              </div>
            </form>
          </div>
          <div class="col-md-4 text-right">
            <?php if (!$read_only_mode): ?>
              <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#add-user-modal">
                <i class="fas fa-user-plus mr-1"></i> Add New User
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-secondary" disabled>
                <i class="fas fa-user-plus mr-1"></i> Add New User
              </button>
              <small class="d-block text-muted mt-1">Account inactive - Read only mode</small>
            <?php endif; ?>
          </div>
        </div>

        <!-- Users Table -->
        <div class="table-responsive">
          <?php if (empty($users)): ?>
            <div class="empty-state">
              <i class="fas fa-users empty-state-icon"></i>
              <h4 class="mb-3">No Users Found</h4>
              <p class="empty-state-text">
                <?php if (!empty($search)): ?>
                  No users match your search criteria. Try a different search term.
                <?php elseif (!empty($role_filter)): ?>
                  No <?= $role_filter ?> users found in the system.
                <?php elseif ($status_filter === '1'): ?>
                  No active users found in the system.
                <?php elseif ($status_filter === '0'): ?>
                  No inactive users found in the system.
                <?php else: ?>
                  No users found in the system. Add users to get started.
                <?php endif; ?>
              </p>
              <?php if (!$read_only_mode): ?>
                <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#add-user-modal">
                  <i class="fas fa-user-plus mr-1"></i> Add New User
                </button>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <table class="table table-hover">
              <thead class="thead-light">
                <tr>
                  <th>
                    <a href="?<?= buildSortingUrl('id') ?>" class="text-dark d-flex align-items-center">
                      ID <?= getSortIcon('id') ?>
                    </a>
                  </th>
                  <th>
                    <a href="?<?= buildSortingUrl('username') ?>" class="text-dark d-flex align-items-center">
                      Username <?= getSortIcon('username') ?>
                    </a>
                  </th>
                  <th>
                    <a href="?<?= buildSortingUrl('email') ?>" class="text-dark d-flex align-items-center">
                      Email <?= getSortIcon('email') ?>
                    </a>
                  </th>
                  <th>
                    <a href="?<?= buildSortingUrl('role') ?>" class="text-dark d-flex align-items-center">
                      Role <?= getSortIcon('role') ?>
                    </a>
                  </th>
                  <th>
                    <a href="?<?= buildSortingUrl('created_at') ?>" class="text-dark d-flex align-items-center">
                      Created <?= getSortIcon('created_at') ?>
                    </a>
                  </th>
                  <th class="text-center">Orders</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <?php 
                    $can_edit = true;
                    $can_toggle = true;
                    $can_delete = true;
                    
                    // For admins, only root admin can modify
                    if ($user['role'] === 'admin' && !$is_root_admin && $user['id'] != $_SESSION['user_id']) {
                        $can_edit = false;
                        $can_toggle = false;
                        $can_delete = false;
                    }
                    
                    // Can't modify self status or delete self
                    if ($user['id'] == $_SESSION['user_id']) {
                        $can_toggle = false;
                        $can_delete = false;
                    }
                  ?>
                  <!-- Enhanced user rows in the table -->
                  <tr class="<?= ($user['id'] == $_SESSION['user_id']) ? 'table-active' : '' ?> <?= ($user['role'] === 'admin' && isset($user['is_active']) && !$user['is_active']) ? 'table-danger' : '' ?>">
                    <td><?= $user['id'] ?></td>
                    <td>
                      <div class="d-flex align-items-center">
                        <img src="https://www.gravatar.com/avatar/<?= md5(strtolower(trim($user['email']))) ?>?s=35&d=mp" class="user-avatar mr-2" alt="Avatar">
                        <div>
                          <?= htmlspecialchars($user['username']) ?>
                          <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <span class="badge badge-info ml-1">You</span>
                          <?php endif; ?>
                          <?php if (isset($user['is_root_admin']) && $user['is_root_admin']): ?>
                            <span class="badge badge-danger ml-1">Root</span>
                          <?php endif; ?>
                          <?php if ($user['role'] === 'admin' && isset($user['is_active']) && !$user['is_active']): ?>
                            <span class="badge badge-dark ml-1">Inactive</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                      <?php if ($user['role'] === 'admin'): ?>
                        <?php if (isset($user['is_root_admin']) && $user['is_root_admin']): ?>
                          <span class="badge badge-danger">
                            <i class="fas fa-crown mr-1"></i> Root Admin
                          </span>
                        <?php else: ?>
                          <span class="badge badge-danger">
                            <i class="fas fa-user-shield mr-1"></i> Admin
                          </span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge badge-info">
                          <i class="fas fa-user mr-1"></i> User
                        </span>
                      <?php endif; ?>
                      
                      <?php if ($is_root_admin && !$read_only_mode && $user['id'] != $_SESSION['user_id']): ?>
                        <?php if ($user['role'] === 'user'): ?>
                          <a href="#" class="badge badge-light ml-1" data-toggle="tooltip" title="You can promote this user to admin">
                            <i class="fas fa-level-up-alt"></i>
                          </a>
                        <?php elseif ($user['role'] === 'admin' && (!isset($user['is_root_admin']) || !$user['is_root_admin'])): ?>
                          <a href="#" class="badge badge-light ml-1" data-toggle="tooltip" title="You can demote this admin to regular user">
                            <i class="fas fa-level-down-alt"></i>
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                    <td><span title="<?= date('F j, Y H:i', strtotime($user['created_at'])) ?>"><?= date('M j, Y', strtotime($user['created_at'])) ?></span></td>
                    <td class="text-center">
                      <?php if ($user['order_count'] > 0): ?>
                        <a href="orders.php?user_id=<?= $user['id'] ?>" class="badge badge-success"><?= $user['order_count'] ?></a>
                      <?php else: ?>
                        <span class="badge badge-secondary">0</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <?php if (isset($user['is_active'])): ?>
                        <?php if ($user['is_active']): ?>
                          <span class="badge badge-success">
                            <span class="status-indicator status-active"></span> Active
                          </span>
                        <?php else: ?>
                          <span class="badge badge-danger">
                            <span class="status-indicator status-inactive"></span> Inactive
                          </span>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge badge-secondary">Unknown</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="btn-group">
                        <?php if ($can_edit && !$read_only_mode): ?>
                          <a href="?edit=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Edit User">
                            <i class="fas fa-edit"></i>
                          </a>
                        <?php elseif (!$read_only_mode): ?>
                          <a href="#" class="btn btn-sm btn-secondary disabled" title="Cannot Edit">
                            <i class="fas fa-edit"></i>
                          </a>
                        <?php endif; ?>
                        
                        <?php if ($can_toggle && !$read_only_mode): ?>
                          <!-- Toggle active status -->
                          <form method="post" class="d-inline">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $user['is_active'] ? '0' : '1' ?>">
                            
                            <button type="submit" name="toggle_status" class="btn btn-sm <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                    onclick="return confirm('Are you sure you want to <?= $user['is_active'] ? 'deactivate' : 'activate' ?> this user?')" 
                                    title="<?= $user['is_active'] ? 'Deactivate User' : 'Activate User' ?>">
                              <i class="fas fa-<?= $user['is_active'] ? 'user-slash' : 'user-check' ?>"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                        
                        <?php if ($can_delete && !$read_only_mode): ?>
                          <!-- Delete user -->
                          <form method="post" class="d-inline">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            
                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger" title="Delete User"
                                    onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone!')">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                        
                        <?php if ($is_root_admin && $user['role'] === 'admin' && !$read_only_mode): ?>
                          <!-- For root admins managing other admins, show make/remove root button -->
                          <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                              
                              <?php if (isset($user['is_root_admin']) && $user['is_root_admin']): ?>
                                <input type="hidden" name="is_root" value="0">
                                <button type="submit" name="toggle_root_admin" class="btn btn-sm btn-secondary" title="Remove Root Access"
                                        onclick="return confirm('Are you sure you want to REMOVE ROOT privileges from this admin?')">
                                  <i class="fas fa-user-shield"></i>
                                </button>
                              <?php else: ?>
                                <input type="hidden" name="is_root" value="1">
                                <button type="submit" name="toggle_root_admin" class="btn btn-sm btn-warning" title="Grant Root Access"
                                        onclick="return confirm('Are you sure you want to grant ROOT privileges to this admin?')">
                                  <i class="fas fa-crown"></i>
                                </button>
                              <?php endif; ?>
                            </form>
                          <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($is_root_admin && !$read_only_mode && $user['id'] != $_SESSION['user_id']): ?>
                          <!-- Add quick role change buttons for root admins -->
                          <form method="post" class="d-inline">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <?php if ($user['role'] === 'user'): ?>
                              <input type="hidden" name="new_role" value="admin">
                              <button type="submit" name="quick_role_change" class="btn btn-sm btn-info" title="Promote to Admin"
                                      onclick="return confirm('Are you sure you want to promote this user to admin? They will have access to admin features.')">
                                <i class="fas fa-level-up-alt"></i>
                              </button>
                            <?php elseif ($user['role'] === 'admin' && !isset($user['is_root_admin'])): ?>
                              <input type="hidden" name="new_role" value="user">
                              <button type="submit" name="quick_role_change" class="btn btn-sm btn-warning" title="Demote to User"
                                      onclick="return confirm('Are you sure you want to demote this admin to regular user? They will lose all admin privileges.')">
                                <i class="fas fa-level-down-alt"></i>
                              </button>
                            <?php endif; ?>
                          </form>
                        <?php endif; ?>

                        <?php if ($read_only_mode): ?>
                          <a href="?view=<?= $user['id'] ?>" class="btn btn-sm btn-info" title="View User">
                            <i class="fas fa-eye"></i>
                          </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <?php if (!$edit_user): ?>
    <!-- Statistics Row (Only shown when not editing) -->
    <div class="row mt-4">
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-info">
          <span class="info-box-icon"><i class="fas fa-users"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Total Users</span>
            <span class="info-box-number"><?= ($stats['admin_count'] ?? 0) + ($stats['user_count'] ?? 0) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-danger">
          <span class="info-box-icon"><i class="fas fa-user-shield"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Administrators</span>
            <span class="info-box-number"><?= $stats['admin_count'] ?? 0 ?> (<?= $stats['root_admin_count'] ?? 0 ?> root)</span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-success">
          <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Active Users</span>
            <span class="info-box-number"><?= $stats['active_count'] ?? 0 ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="info-box bg-warning">
          <span class="info-box-icon"><i class="fas fa-calendar-plus"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">New This Month</span>
            <span class="info-box-number"><?= $stats['month_count'] ?? 0 ?></span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  
  <?php if ($edit_user): ?>
  <!-- User Edit Panel (Only shown when editing) -->
  <div class="col-md-4">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title d-flex align-items-center">
          <img src="https://www.gravatar.com/avatar/<?= md5(strtolower(trim($edit_user['email']))) ?>?s=30&d=mp" class="rounded-circle mr-2" alt="Avatar">
          Edit User: <?= htmlspecialchars($edit_user['username']) ?>
        </h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" onclick="window.location='manage_users.php'">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <ul class="nav nav-tabs" id="user-edit-tabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="true">
              <i class="fas fa-user-edit mr-1"></i> Profile
            </a>
          </li>
          <?php if (!empty($edit_user['addresses'])): ?>
          <li class="nav-item">
            <a class="nav-link" id="addresses-tab" data-toggle="tab" href="#addresses" role="tab" aria-controls="addresses" aria-selected="false">
              <i class="fas fa-map-marker-alt mr-1"></i> Addresses
            </a>
          </li>
          <?php endif; ?>
          <?php if (!empty($edit_user['recent_orders'])): ?>
          <li class="nav-item">
            <a class="nav-link" id="orders-tab" data-toggle="tab" href="#orders" role="tab" aria-controls="orders" aria-selected="false">
              <i class="fas fa-shopping-bag mr-1"></i> Orders
            </a>
          </li>
          <?php endif; ?>
        </ul>
        
        <div class="tab-content mt-3" id="user-edit-content">
          <!-- Profile Tab -->
          <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <form action="" method="post">
              <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
              
              <div class="form-group">
                <label for="username">Username</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" id="username" name="username" 
                         value="<?= htmlspecialchars($edit_user['username']) ?>" 
                         required <?= $read_only_mode ? 'readonly' : '' ?>>
                </div>
              </div>
              
              <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" id="email" name="email" 
                         value="<?= htmlspecialchars($edit_user['email']) ?>" required>
                </div>
              </div>
              
              <div class="form-group">
                <label for="phone">Phone</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                  </div>
                  <input type="text" class="form-control" id="phone" name="phone" 
                         value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>">
                </div>
              </div>
              
              <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                  </div>
                  <?php if ($edit_user['id'] == $_SESSION['user_id']): ?>
                    <input type="password" class="form-control" id="password" name="password">
                    <small class="form-text text-muted">Leave blank to keep current password</small>
                  <?php else: ?>
                    <input type="password" class="form-control" disabled placeholder="••••••••">
                    <div class="input-group-append">
                      <span class="input-group-text bg-warning text-dark"><i class="fas fa-lock mr-1"></i> Protected</span>
                    </div>
                    <small class="form-text text-warning">
                      <i class="fas fa-shield-alt mr-1"></i>
                      For security reasons, you cannot view or change another user's password
                    </small>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="form-group">
                <label for="role">Role</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                  </div>
                  <?php if ($is_root_admin && $edit_user['id'] != $_SESSION['user_id']): ?>
                    <!-- Root admins can change any user's role except their own -->
                    <select class="form-control" id="role" name="role">
                      <option value="user" <?= $edit_user['role'] === 'user' ? 'selected' : '' ?>>Regular User</option>
                      <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                    <?php if ($edit_user['role'] === 'user'): ?>
                      <div class="input-group-append">
                        <span class="input-group-text bg-info text-white"><i class="fas fa-level-up-alt mr-1"></i> Can Promote</span>
                      </div>
                    <?php elseif ($edit_user['role'] === 'admin'): ?>
                      <div class="input-group-append">
                        <span class="input-group-text bg-warning text-dark"><i class="fas fa-level-down-alt mr-1"></i> Can Demote</span>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($edit_user['id'] == $_SESSION['user_id']): ?>
                    <!-- Users cannot change their own role -->
                    <select class="form-control" id="role" name="role" disabled>
                      <option value="user" <?= $edit_user['role'] === 'user' ? 'selected' : '' ?>>Regular User</option>
                      <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                    <div class="input-group-append">
                      <span class="input-group-text bg-secondary text-white"><i class="fas fa-lock mr-1"></i> Cannot Change</span>
                    </div>
                  <?php else: ?>
                    <!-- Non-root admins can only see but not change role -->
                    <select class="form-control" id="role" name="role" disabled>
                      <option value="user" <?= $edit_user['role'] === 'user' ? 'selected' : '' ?>>Regular User</option>
                      <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                    <div class="input-group-append">
                      <span class="input-group-text bg-secondary text-white"><i class="fas fa-lock mr-1"></i> Root Only</span>
                    </div>
                  <?php endif; ?>
                </div>
                
                <?php if ($edit_user['id'] == $_SESSION['user_id']): ?>
                  <input type="hidden" name="role" value="<?= $edit_user['role'] ?>">
                  <small class="form-text text-warning">
                    <i class="fas fa-exclamation-triangle mr-1"></i> 
                    For security reasons, you cannot change your own role.
                  </small>
                <?php elseif (!$is_root_admin): ?>
                  <input type="hidden" name="role" value="<?= $edit_user['role'] ?>">
                  <small class="form-text text-warning">
                    <i class="fas fa-shield-alt mr-1"></i> 
                    Only root administrators can upgrade or downgrade user roles.
                  </small>
                <?php elseif ($is_root_admin && $edit_user['role'] === 'admin'): ?>
                  <small class="form-text text-warning">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Warning: Demoting an admin will remove all their administrative privileges.
                  </small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                         <?= isset($edit_user['is_active']) && $edit_user['is_active'] ? 'checked' : '' ?>
                         <?= $edit_user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                  <label class="custom-control-label" for="is_active">Active Account</label>
                </div>
                <?php if ($edit_user['id'] == $_SESSION['user_id']): ?>
                  <input type="hidden" name="is_active" value="1">
                  <small class="form-text text-warning"><i class="fas fa-exclamation-triangle mr-1"></i> You cannot deactivate your own account.</small>
                <?php endif; ?>
              </div>
              
              <div class="form-group">
                <label for="notes">Admin Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($edit_user['notes'] ?? '') ?></textarea>
                <small class="form-text text-muted">Private notes visible only to administrators</small>
              </div>
              
              <div class="mt-4 text-center">
                <?php if (!$read_only_mode): ?>
                  <button type="submit" name="update_user" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Save Changes
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-secondary" disabled>
                    <i class="fas fa-save mr-1"></i> Save Changes
                  </button>
                  <small class="d-block text-danger mt-1">You cannot edit users while your account is inactive</small>
                <?php endif; ?>
                <a href="manage_users.php" class="btn btn-default mt-2">
                  <i class="fas fa-times mr-1"></i> Back to List
                </a>
              </div>
            </form>
          </div>
          
          <!-- Addresses Tab -->
          <?php if (!empty($edit_user['addresses'])): ?>
          <div class="tab-pane fade" id="addresses" role="tabpanel" aria-labelledby="addresses-tab">
            <div class="addresses-list">
              <?php foreach ($edit_user['addresses'] as $addr): ?>
                <div class="card card-body mb-3 p-3 border">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">
                      <i class="fas fa-map-marker-alt mr-2"></i>
                      <?= htmlspecialchars($addr['name'] ?? 'Shipping Address') ?>
                    </h5>
                    <?php if (isset($addr['is_default']) && $addr['is_default']): ?>
                      <span class="badge badge-success">Default</span>
                    <?php endif; ?>
                  </div>
                  <hr class="mt-0 mb-2">
                  <address class="mb-0">
                    <?= htmlspecialchars($addr['address_line1']) ?><br>
                    <?php if (!empty($addr['address_line2'])): ?>
                      <?= htmlspecialchars($addr['address_line2']) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state'] ?? '') ?> <?= htmlspecialchars($addr['postal_code']) ?><br>
                    <?= htmlspecialchars($addr['country']) ?>
                  </address>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- Orders Tab -->
          <?php if (!empty($edit_user['recent_orders'])): ?>
          <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead class="thead-light">
                  <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($edit_user['recent_orders'] as $order): ?>
                    <tr>
                      <td><a href="order_detail.php?id=<?= $order['id'] ?>" class="font-weight-bold">#<?= $order['id'] ?></a></td>
                      <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                      <td>$<?= number_format($order['total_amount'], 2) ?></td>
                      <td>
                        <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
                          <?= ucfirst($order['status']) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="text-center mt-3">
              <a href="orders.php?user_id=<?= $edit_user['id'] ?>" class="btn btn-sm btn-info">
                <i class="fas fa-list mr-1"></i> View All Orders
              </a>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <!-- User Status Card -->
    <div class="card card-outline card-secondary mt-4">
      <div class="card-header">
        <h3 class="card-title">User Status</h3>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Member Since
            <span class="badge badge-primary"><?= date('M j, Y', strtotime($edit_user['created_at'])) ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Last Login
            <span class="badge badge-info"><?= isset($edit_user['last_login']) ? date('M j, Y', strtotime($edit_user['last_login'])) : 'Never' ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Total Orders
            <span class="badge badge-success"><?= count($edit_user['recent_orders'] ?? []) ?></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            Account Status
            <?php if (isset($edit_user['is_active']) && $edit_user['is_active']): ?>
              <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Active</span>
            <?php else: ?>
              <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Inactive</span>
            <?php endif; ?>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Add User Modal with improved styling -->
<div class="modal fade" id="add-user-modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h4 class="modal-title">
          <i class="fas fa-user-plus mr-2"></i> Add New User
        </h4>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="" method="post">
        <div class="modal-body">
          <div class="form-group">
            <label for="new_username">Username <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
              </div>
              <input type="text" class="form-control" id="new_username" name="username" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="new_email">Email Address <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" id="new_email" name="email" required>
            </div>
          </div>
          
          <div class="form-group">
            <label for="new_password">Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" id="new_password" name="password" required>
            </div>
            <small class="form-text text-muted">
              <i class="fas fa-info-circle mr-1"></i>
              You can set the initial password, but for security reasons, you cannot view or change it later
            </small>
          </div>
          
          <div class="form-group">
            <label for="new_phone">Phone (optional)</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
              </div>
              <input type="text" class="form-control" id="new_phone" name="phone">
            </div>
          </div>
          
          <div class="form-group">
            <label for="new_role">Role</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
              </div>
              <select class="form-control" id="new_role" name="role">
                <option value="user">Regular User</option>
                <?php if ($is_root_admin): ?>
                  <option value="admin">Administrator</option>
                <?php endif; ?>
              </select>
            </div>
          </div>
          
          <div class="form-group">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="new_is_active" name="is_active" checked>
              <label class="custom-control-label" for="new_is_active">Active Account</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
          </button>
          <button type="submit" name="add_user" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Create User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add custom CSS for better UI -->
<style>
.badge {
  padding: 0.4em 0.6em;
}
.table td, .table th {
  vertical-align: middle;
}
.list-group-item {
  padding: 0.75rem 1.25rem;
}
.alert-container .alert {
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  opacity: 0.95;
}
.nav-pills .nav-link.active {
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}
.card {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
.card-outline {
  border-top: 3px solid;
}
.btn-group .btn {
  border-radius: 0.25rem !important;
  margin: 0 2px;
}
</style>

<!-- Optional: Create admin_logs table SQL
CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action TEXT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);
-->

<script>
// Wait until document is ready
$(document).ready(function() {
  // Handle permanent dismissal of security notice
  $('#dismiss-security-notice').on('click', function() {
    // Set a flag in session via AJAX
    $.ajax({
      url: 'dismiss_notice.php',
      method: 'POST',
      data: { 
        notice_type: 'security_notice_dismissed',
        csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' 
      },
      success: function(response) {
        console.log('Notice dismissed');
      }
    });
  });
  
  // Auto-hide alerts after 5 seconds
  setTimeout(function() {
    $('.alert-success, .alert-danger').fadeOut('slow');
  }, 5000);
  
  // Initialize tooltips
  $('[data-toggle="tooltip"]').tooltip();
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>