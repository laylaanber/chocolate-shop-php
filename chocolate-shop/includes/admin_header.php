<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Create database connection for use in this file
require_once dirname(__DIR__) . '/config/database.php';
$db = (new Database())->getConnection();

// Function to check if current user is root admin
function isCurrentUserRootAdmin() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) return false;
    
    $query = "SELECT is_root_admin FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && isset($result['is_root_admin']) && $result['is_root_admin'] == 1;
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

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
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
      <img src="https://static.vecteezy.com/system/resources/previews/032/749/138/non_2x/organic-chocolate-or-cacao-fruit-logo-template-design-isolated-background-free-vector.jpg" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">Chocolate Shop</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="https://static.vecteezy.com/system/resources/previews/007/319/933/non_2x/black-avatar-person-icons-user-profile-icon-vector.jpg" class="img-circle elevation-2" alt="User Image">
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
            <a href="orders.php" class="nav-link <?= ($current_page == 'orders.php') ? 'active' : '' ?>">
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
          
          <!-- Admin Management - only for root admin -->
          <?php if ($is_root_admin): ?>
          <li class="nav-item">
            <a href="manage_admins.php" class="nav-link <?= ($current_page == 'manage_admins.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-user-shield"></i>
              <p>Admin Management</p>
            </a>
          </li>
          <?php endif; ?>
          
          <!-- Settings -->
          <li class="nav-item">
            <a href="settings.php" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-cog"></i>
              <p>Settings</p>
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