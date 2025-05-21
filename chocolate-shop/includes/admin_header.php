<?php
session_start();

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in and is an admin (either normal or root)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'root_admin')) {
    header("Location: ../auth/login.php");
    exit;
}

// Create database connection for use in this file
require_once dirname(__DIR__) . '/config/database.php';
$db = (new Database())->getConnection();

/**
 * Check if the current user is a root administrator
 * @return bool True if the user is a root admin, false otherwise
 */
function isCurrentUserRootAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        return false;
    }
    
    global $db;
    
    // If there's no database connection yet, create one
    if (!$db) {
        require_once dirname(__DIR__) . '/config/database.php';
        $database = new Database();
        $db = $database->getConnection();
    }
    
    try {
        // Explicitly check the is_root_admin column
        $query = "SELECT is_root_admin FROM users 
                 WHERE id = ? AND role = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug output (temporarily)
        error_log("Root admin check for user ID {$_SESSION['user_id']}: " . 
                 (isset($result['is_root_admin']) ? ($result['is_root_admin'] ? 'true' : 'false') : 'null'));
        
        return isset($result['is_root_admin']) && $result['is_root_admin'] == 1;
    } catch (Exception $e) {
        error_log("Error checking root admin status: " . $e->getMessage());
        return false;
    }
}

$is_root_admin = isCurrentUserRootAdmin();

// Get current page for sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chocolate Shop Admin</title>

  <!-- Google Font: Playfair Display & Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <!-- Custom Admin Styles -->
  <style>
    :root {
      --primary: #3F2113;      /* Dark chocolate */
      --secondary: #85634D;    /* Milk chocolate */
      --accent: #D1B78A;       /* Gold accent */
      --text-dark: #1E1E1E;
      --text-medium: #5A5A5A;
      --text-light: #888888;
      --bg-light: #FFFFFF;
      --bg-beige: #F9F4EF;
      --bg-dark: #1E1E1E;
      --transition: all 0.3s ease;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --info: #17a2b8;
    }
    
    /* Global typography and elements */
    body {
      font-family: 'Poppins', sans-serif;
      font-weight: 300;
      color: var(--text-medium);
      background-color: #f4f6f9;
    }
    
    h1, h2, h3, h4, h5, h6,
    .h1, .h2, .h3, .h4, .h5, .h6 {
      font-family: 'Playfair Display', serif;
      font-weight: 500;
    }
    
    /* Custom sidebar styling */
    .main-sidebar {
      background-color: var(--bg-dark);
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    
    .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
      background-color: var(--accent);
      color: var(--primary);
      font-weight: 500;
      box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    }
    
    .nav-sidebar .nav-link {
      color: rgba(255,255,255,0.8);
      border-radius: 6px;
    }
    
    .nav-sidebar .nav-link:hover {
      background-color: rgba(209,183,138,0.15);
      color: var(--accent);
    }
    
    .brand-link {
      border-bottom: 1px solid rgba(255,255,255,0.1) !important;
      font-family: 'Playfair Display', serif;
      color: white !important;
    }
    
    .brand-text {
      font-weight: 500 !important;
      letter-spacing: 0.5px;
    }
    
    .sidebar nav {
      padding: 0.5rem;
    }
    
    .nav-icon {
      color: var(--accent);
    }
    
    /* Navbar customization */
    .main-header {
      background-color: white;
      border: none;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .navbar-nav .nav-link {
      color: var(--text-dark);
      font-weight: 400;
    }
    
    /* Content wrapper */
    .content-wrapper {
      background-color: #f9f9f9;
    }
    
    /* Content header */
    .content-header {
      padding: 20px 0.5rem 10px;
    }
    
    .content-header h1 {
      font-size: 1.8rem;
      font-weight: 500;
      color: var(--primary);
    }
    
    /* Breadcrumb */
    .breadcrumb {
      background: none;
      padding: 0;
      font-size: 0.9rem;
    }
    
    .breadcrumb-item a {
      color: var(--accent);
    }
    
    .breadcrumb-item.active {
      color: var(--text-medium);
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
      color: #ccc;
    }
    
    /* Card styling */
    .card {
      border: none;
      border-radius: 10px;
      box-shadow: 0 0 20px rgba(0,0,0,0.05);
      transition: transform 0.2s, box-shadow 0.2s;
      margin-bottom: 20px;
    }
    
    .card:hover {
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    
    .card-header {
      background-color: transparent;
      border-bottom: 1px solid rgba(0,0,0,0.05);
      padding: 1.25rem 1.25rem;
    }
    
    .card-title {
      font-family: 'Playfair Display', serif;
      margin-bottom: 0;
      font-size: 1.2rem;
      color: var(--primary);
      font-weight: 500;
    }
    
    /* Table styling */
    .table {
      color: var(--text-dark);
    }
    
    .table td, .table th {
      padding: 0.75rem 1rem;
      vertical-align: middle;
      border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    .table thead th {
      border-bottom: 1px solid rgba(0,0,0,0.05);
      font-family: 'Poppins', sans-serif;
      font-weight: 500;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-medium);
      background-color: rgba(249, 244, 239, 0.5);
    }
    
    .table-hover tbody tr:hover {
      background-color: rgba(209,183,138,0.05);
    }
    
    /* Badge styling */
    .badge {
      font-weight: 500;
      font-size: 0.75rem;
      padding: 0.25em 0.75em;
      border-radius: 30px;
    }
    
    .badge-success {
      background-color: #d4edda;
      color: #155724;
    }
    
    .badge-warning {
      background-color: #fff3cd;
      color: #856404;
    }
    
    .badge-danger {
      background-color: #f8d7da;
      color: #721c24;
    }
    
    .badge-info {
      background-color: #d1ecf1;
      color: #0c5460;
    }
    
    .badge-primary {
      background-color: #cce5ff;
      color: #004085;
    }
    
    .badge-secondary {
      background-color: #e2e3e5;
      color: #383d41;
    }
    
    /* Button styling */
    .btn {
      font-weight: 500;
      border-radius: 6px;
      letter-spacing: 0.3px;
      font-size: 0.9rem;
      padding: 0.4rem 1rem;
    }
    
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }
    
    .btn-primary:hover {
      background-color: #301a0f;
      border-color: #301a0f;
    }
    
    .btn-info {
      background-color: var(--info);
      border-color: var(--info);
    }
    
    .btn-success {
      background-color: var(--success);
      border-color: var(--success);
    }
    
    .btn-warning {
      background-color: var(--warning);
      border-color: var(--warning);
    }
    
    .btn-danger {
      background-color: var(--danger);
      border-color: var(--danger);
    }
    
    .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }
    
    /* Form controls */
    .form-control {
      border: 1px solid rgba(0,0,0,0.15);
      border-radius: 6px;
      padding: 0.5rem 1rem;
      font-size: 0.95rem;
      color: var(--text-dark);
    }
    
    .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(209,183,138,0.25);
    }
    
    .input-group-text {
      background-color: #f8f9fa;
      border: 1px solid rgba(0,0,0,0.15);
    }
    
    /* Small info cards */
    .info-box {
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0,0,0,0.05);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .info-box:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .info-box-icon {
      border-radius: 10px 0 0 10px;
      opacity: 0.8;
    }
    
    .info-box-text {
      font-family: 'Poppins', sans-serif;
      text-transform: uppercase;
      font-weight: 500;
      font-size: 0.8rem;
      letter-spacing: 0.5px;
    }
    
    .info-box-number {
      font-size: 1.4rem;
      font-weight: 500;
      color: var(--text-dark);
    }
    
    /* Alert styling */
    .alert {
      border-radius: 8px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 20px;
    }
    
    .alert-success {
      color: #155724;
      background-color: #d4edda;
    }
    
    .alert-danger {
      color: #721c24;
      background-color: #f8d7da;
    }
    
    .alert-warning {
      color: #856404;
      background-color: #fff3cd;
    }
    
    .alert-info {
      color: #0c5460;
      background-color: #d1ecf1;
    }
    
    /* Footer styling */
    .main-footer {
      background-color: white;
      border-top: 1px solid rgba(0,0,0,0.05);
      color: var(--text-medium);
      font-size: 0.9rem;
      padding: 1rem;
    }
    
    .main-footer a {
      color: var(--primary);
      font-weight: 500;
    }
    
    /* Chart containers */
    .chart-container {
      position: relative;
      margin: 20px auto;
    }
    
    /* Empty state styling */
    .empty-state {
      text-align: center;
      padding: 30px;
      color: var(--text-light);
    }
    
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.3;
    }
    
    /* User info styling */
    .user-panel .info a {
      font-family: 'Playfair Display', serif;
      color: rgba(255,255,255,0.9);
      font-weight: 500;
    }
    
    /* Improve scrollbars */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--accent);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--secondary);
    }
    
    /* Responsive adjustments */
    @media (max-width: 991.98px) {
      .content-header h1 {
        font-size: 1.5rem;
      }
      
      .info-box-number {
        font-size: 1.2rem;
      }
    }
    
    @media (max-width: 767.98px) {
      .content-header {
        padding: 15px 0.5rem;
      }
      
      .breadcrumb {
        display: none;
      }
      
      .table-responsive {
        border-radius: 10px;
      }
      
      .content-header h1 {
        font-size: 1.3rem;
      }
    }
    
    @media (max-width: 575.98px) {
      .content-header h1 {
        font-size: 1.2rem;
      }
      
      .card-title {
        font-size: 1.1rem;
      }
      
      .info-box-text {
        font-size: 0.7rem;
      }
      
      .info-box-number {
        font-size: 1rem;
      }
      
      .btn {
        padding: 0.3rem 0.8rem;
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link">Home</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a class="nav-link" href="../auth/logout.php" role="button">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="../index.php" class="brand-link">
      <i class="fas fa-store-alt brand-image img-circle elevation-3" style="opacity: .8"></i>
      <span class="brand-text font-weight-light">Chocolate Shop</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <i class="fas fa-user-circle img-circle elevation-2" style="color: var(--accent); font-size: 2rem;"></i>
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= htmlspecialchars($_SESSION['username']) ?></a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <!-- Dashboard -->
          <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>
          
          <!-- Products -->
          <li class="nav-item">
            <a href="products.php" class="nav-link <?= ($current_page == 'products.php' || $current_page == 'product_form.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-box"></i>
              <p>Products</p>
            </a>
          </li>

          <!-- Categories -->
          <li class="nav-item">
            <a href="categories.php" class="nav-link <?= ($current_page == 'categories.php' || $current_page == 'category_form.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tags"></i>
              <p>Categories</p>
            </a>
          </li>
          
          <!-- Orders -->
          <li class="nav-item">
            <a href="orders.php" class="nav-link <?= ($current_page == 'orders.php' || $current_page == 'order_detail.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-shopping-cart"></i>
              <p>Orders</p>
            </a>
          </li>
          
          <!-- Users -->
          <li class="nav-item">
            <a href="manage_users.php" class="nav-link <?= ($current_page == 'manage_users.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Users</p>
            </a>
          </li>
          
         
          
          <!-- Return to Store -->
          <li class="nav-item">
            <a href="../index.php" class="nav-link">
              <i class="nav-icon fas fa-store"></i>
              <p>View Store</p>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">