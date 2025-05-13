<?php
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Use the function that's defined in admin_header.php
$is_root_admin = isCurrentUserRootAdmin();

// Get statistics
$users_query = "SELECT COUNT(*) as total FROM users";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users_count = $users_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get product count
$products_query = "SELECT COUNT(*) as total FROM products";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute();
$products_count = $products_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get order count
$orders_query = "SELECT COUNT(*) as total FROM orders";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute();
$orders_count = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get recent orders
$recent_orders_query = "SELECT o.*, u.username 
                       FROM orders o 
                       LEFT JOIN users u ON o.user_id = u.id 
                       ORDER BY o.created_at DESC 
                       LIMIT 5";
$recent_orders_stmt = $db->prepare($recent_orders_query);
$recent_orders_stmt->execute();
$recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order status breakdown for statistics
$status_query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$status_stmt = $db->prepare($status_query);
$status_stmt->execute();
$status_counts = [];
$status_labels = [];
$status_data = [];
$status_colors = [
    'pending' => '#ffc107',    // warning
    'processing' => '#17a2b8', // info
    'shipped' => '#007bff',    // primary
    'delivered' => '#28a745',  // success
    'cancelled' => '#dc3545'   // danger
];
$status_backgrounds = [];

while ($row = $status_stmt->fetch(PDO::FETCH_ASSOC)) {
    $status_counts[$row['status']] = $row['count'];
    $status_labels[] = ucfirst($row['status']);
    $status_data[] = $row['count'];
    $status_backgrounds[] = $status_colors[$row['status']] ?? '#6c757d'; // default to secondary
}

// Get total revenue
$revenue_query = "SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->execute();
$total_revenue = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get monthly revenue for the chart (last 6 months)
$monthly_revenue_query = "SELECT 
                           DATE_FORMAT(created_at, '%Y-%m') as month,
                           DATE_FORMAT(created_at, '%b %Y') as month_name,
                           SUM(total_amount) as revenue
                         FROM orders
                         WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         GROUP BY month
                         ORDER BY month ASC";
$monthly_revenue_stmt = $db->prepare($monthly_revenue_query);
$monthly_revenue_stmt->execute();
$monthly_revenue_data = $monthly_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

$revenue_labels = [];
$revenue_data = [];

foreach ($monthly_revenue_data as $month) {
    $revenue_labels[] = $month['month_name'];
    $revenue_data[] = $month['revenue'];
}

// Top selling products
$top_products_query = "SELECT 
                         p.id, p.name, p.image,
                         SUM(oi.quantity) as total_sold,
                         SUM(oi.quantity * oi.unit_price) as total_revenue
                       FROM order_items oi
                       JOIN products p ON oi.product_id = p.id
                       JOIN orders o ON oi.order_id = o.id
                       WHERE o.status != 'cancelled'
                       GROUP BY p.id
                       ORDER BY total_sold DESC
                       LIMIT 5";
$top_products_stmt = $db->prepare($top_products_query);
$top_products_stmt->execute();
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity history
$activity_query = "SELECT oh.*, o.id as order_id, u.username 
                  FROM order_history oh
                  JOIN orders o ON oh.order_id = o.id
                  LEFT JOIN users u ON oh.created_by = u.id
                  ORDER BY oh.created_at DESC
                  LIMIT 8";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->execute();
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status badge class helper function
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

// Get status icon
function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return 'clock';
        case 'processing': return 'cogs';
        case 'shipped': return 'truck';
        case 'delivered': return 'check-circle';
        case 'cancelled': return 'times-circle';
        default: return 'question-circle';
    }
}
?>

<!-- Custom CSS -->
<style>
  /* Modern dashboard styles */
  .card {
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: none;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  }
  
  /* Fixed sidebar */
  .main-sidebar {
    position: fixed;
    height: 100%;
  }
  
  /* Stat boxes */
  .stat-box {
    border-radius: 10px;
    padding: 20px;
    color: white;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  
  .stat-box h3 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
  }
  
  .stat-box p {
    font-size: 1rem;
    margin-bottom: 0;
    opacity: 0.9;
  }
  
  .stat-box .icon {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 3rem;
    opacity: 0.3;
  }
  
  .stat-box .action-link {
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px solid rgba(255,255,255,0.2);
    text-align: center;
    color: white;
    font-weight: 500;
    text-decoration: none;
  }
  
  .stat-box .action-link:hover {
    color: rgba(255,255,255,0.8);
  }
  
  /* Order status pills */
  .status-pill {
    border-radius: 50px;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
  }
  
  /* Recent orders table */
  .table-slim {
    margin-bottom: 0;
  }
  
  .table-slim td {
    padding: 0.5rem;
    vertical-align: middle;
  }
  
  /* Product list */
  .product-card {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
  }
  
  .product-card:last-child {
    border-bottom: none;
  }
  
  .product-card img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 5px;
  }
  
  .product-card .product-details {
    margin-left: 12px;
    flex-grow: 1;
  }
  
  .product-card .product-name {
    font-weight: 600;
    margin-bottom: 2px;
    font-size: 0.9rem;
  }
  
  .product-card .product-stats {
    font-size: 0.8rem;
    color: #6c757d;
  }
  
  .product-card .revenue {
    font-weight: 600;
    color: #28a745;
  }
  
  /* Chart containers */
  .chart-container {
    position: relative;
    height: 300px;
  }
  
  /* Activity timeline */
  .activity-timeline {
    position: relative;
    padding-left: 35px;
  }
  
  .activity-timeline::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 14px;
    width: 2px;
    background: rgba(0,0,0,0.1);
  }
  
  .timeline-item {
    position: relative;
    margin-bottom: 15px;
  }
  
  .timeline-item:last-child {
    margin-bottom: 0;
  }
  
  .timeline-item .timeline-icon {
    position: absolute;
    left: -35px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    box-shadow: 0 0 0 4px white;
    z-index: 1;
  }
  
  .timeline-item .timeline-content {
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 3px solid #007bff;
  }
  
  .timeline-item .timeline-date {
    color: #6c757d;
    font-size: 0.8rem;
    margin-bottom: 5px;
  }
  
  .timeline-item .timeline-title {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 0.9rem;
  }
  
  .timeline-item .timeline-body {
    color: #6c757d;
    font-size: 0.85rem;
  }
  
  /* Custom card header */
  .card-header {
    background-color: transparent;
    border-bottom: none;
    padding: 1.25rem 1.25rem 0.5rem;
  }
  
  .card-header .card-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0;
  }
  
  /* Quick action buttons */
  .quick-action {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    border-radius: 5px;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
    transition: background 0.2s;
    border: none;
  }
  
  .quick-action:hover {
    background: #e9ecef;
  }
  
  .quick-action i {
    margin-right: 10px;
    font-size: 1rem;
  }
  
  .quick-action span {
    font-weight: 500;
    font-size: 0.9rem;
  }
</style>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Dashboard</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
  <div class="container-fluid">
    <!-- Key Stats -->
    <div class="row mb-4">
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-box bg-gradient-info">
          <i class="fas fa-shopping-cart icon"></i>
          <div>
            <p>Total Orders</p>
            <h3><?= $orders_count ?></h3>
          </div>
          <a href="orders.php" class="action-link">
            View Orders <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-box bg-gradient-success">
          <i class="fas fa-dollar-sign icon"></i>
          <div>
            <p>Total Revenue</p>
            <h3>$<?= number_format($total_revenue, 2) ?></h3>
          </div>
          <a href="orders.php" class="action-link">
            View Details <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-box bg-gradient-warning">
          <i class="fas fa-box icon"></i>
          <div>
            <p>Total Products</p>
            <h3><?= $products_count ?></h3>
          </div>
          <a href="products.php" class="action-link">
            Manage Products <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
      
      <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-box bg-gradient-danger">
          <i class="fas fa-users icon"></i>
          <div>
            <p>Total Customers</p>
            <h3><?= $users_count ?></h3>
          </div>
          <a href="manage_users.php" class="action-link">
            Manage Users <i class="fas fa-arrow-right ml-1"></i>
          </a>
        </div>
      </div>
    </div>
    
    <!-- Order Status Cards -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-bar mr-1"></i>
              Order Status Overview
            </h3>
          </div>
          <div class="card-body pb-0">
            <div class="row">
              <?php
              $status_list = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
              foreach ($status_list as $status) {
                $count = $status_counts[$status] ?? 0;
                $percentage = ($orders_count > 0) ? ($count / $orders_count * 100) : 0;
                $badge_class = getStatusBadgeClass($status);
                $icon = getStatusIcon($status);
              ?>
                <div class="col">
                  <div class="info-box bg-light">
                    <div class="info-box-content">
                      <span class="info-box-text d-flex align-items-center">
                        <i class="fas fa-<?= $icon ?> text-<?= $badge_class ?> mr-2"></i>
                        <?= ucfirst($status) ?>
                      </span>
                      <span class="info-box-number">
                        <?= $count ?>
                        <small class="text-muted ml-2">(<?= number_format($percentage, 1) ?>%)</small>
                      </span>
                      
                      <div class="progress">
                        <div class="progress-bar bg-<?= $badge_class ?>" style="width: <?= $percentage ?>%"></div>
                      </div>
                      <a href="orders.php?status=<?= $status ?>" class="mt-2 d-block text-sm">
                        View <?= ucfirst($status) ?> Orders
                      </a>
                    </div>
                  </div>
                </div>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
      <!-- Revenue Chart -->
      <div class="col-md-8 mb-4">
        <div class="card h-100">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-line mr-1"></i>
              Revenue Trends
            </h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Order Status Pie Chart -->
      <div class="col-md-4 mb-4">
        <div class="card h-100">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-pie mr-1"></i>
              Order Distribution
            </h3>
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
              </button>
            </div>
          </div>
          <div class="card-body d-flex justify-content-center align-items-center">
            <div class="chart-container" style="height: 240px; width: 100%">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="row">
      <!-- Recent Orders -->
      <div class="col-md-8 mb-4">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <i class="fas fa-shopping-cart mr-1"></i>
              Recent Orders
            </h3>
            <div class="card-tools">
              <a href="orders.php" class="btn btn-sm btn-primary">
                View All
              </a>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recent_orders)): ?>
              <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">No orders yet</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover table-slim mb-0">
                  <thead>
                    <tr>
                      <th>Order #</th>
                      <th>Customer</th>
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                      <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td>
                          <?= htmlspecialchars($order['username'] ?? 'Guest') ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                        <td><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                        <td>
                          <span class="status-pill bg-<?= getStatusBadgeClass($order['status']) ?>">
                            <?= ucfirst($order['status']) ?>
                          </span>
                        </td>
                        <td>
                          <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-eye"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <!-- Top Selling Products -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
              <i class="fas fa-trophy mr-1"></i>
              Top Products
            </h3>
            <div class="card-tools">
              <a href="products.php" class="btn btn-sm btn-primary">
                All Products
              </a>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($top_products)): ?>
              <div class="text-center py-5">
                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                <p class="text-muted">No product data available</p>
              </div>
            <?php else: ?>
              <div class="product-list">
                <?php foreach ($top_products as $index => $product): ?>
                  <div class="product-card">
                    <?php if (!empty($product['image'])): ?>
                      <img src="../uploads/products/<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                      <div class="no-image d-flex align-items-center justify-content-center bg-light" style="width:40px;height:40px;border-radius:5px">
                        <i class="fas fa-box text-secondary"></i>
                      </div>
                    <?php endif; ?>
                    <div class="product-details">
                      <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                      <div class="product-stats">
                        <?= $product['total_sold'] ?> units sold
                      </div>
                    </div>
                    <div class="revenue">
                      $<?= number_format($product['total_revenue'], 2) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-bolt mr-1"></i>
              Quick Actions
            </h3>
          </div>
          <div class="card-body">
            <a href="product_form.php" class="btn btn-block quick-action">
              <i class="fas fa-plus text-primary"></i>
              <span>Add New Product</span>
            </a>
            <a href="category_form.php" class="btn btn-block quick-action">
              <i class="fas fa-folder-plus text-warning"></i>
              <span>Add New Category</span>
            </a>
            <a href="orders.php?status=pending" class="btn btn-block quick-action">
              <i class="fas fa-clock text-info"></i>
              <span>Pending Orders</span>
              <?php if (($status_counts['pending'] ?? 0) > 0): ?>
                <span class="badge badge-warning ml-auto"><?= $status_counts['pending'] ?></span>
              <?php endif; ?>
            </a>
            <?php if ($is_root_admin): ?>
              <a href="settings.php" class="btn btn-block quick-action">
                <i class="fas fa-cog text-secondary"></i>
                <span>Shop Settings</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Activity Timeline -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-history mr-1"></i>
              Recent Activity
            </h3>
          </div>
          <div class="card-body">
            <?php if (empty($activities)): ?>
              <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <p class="text-muted">No recent activity</p>
              </div>
            <?php else: ?>
              <div class="activity-timeline">
                <?php 
                $current_date = '';
                foreach ($activities as $activity): 
                  $history_date = date('Y-m-d', strtotime($activity['created_at']));
                  $show_date = ($current_date != $history_date);
                  $current_date = $history_date;
                  $badge_class = getStatusBadgeClass($activity['status']);
                  $icon = getStatusIcon($activity['status']);
                ?>
                  <div class="timeline-item">
                    <div class="timeline-icon bg-<?= $badge_class ?>">
                      <i class="fas fa-<?= $icon ?>"></i>
                    </div>
                    <div class="timeline-content">
                      <div class="timeline-date">
                        <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                      </div>
                      <div class="timeline-title">
                        <a href="order_detail.php?id=<?= $activity['order_id'] ?>">
                          Order #<?= $activity['order_id'] ?>
                        </a> 
                        was marked as <span class="font-weight-bold text-<?= $badge_class ?>"><?= ucfirst($activity['status']) ?></span>
                        by <?= htmlspecialchars($activity['username'] ?? 'System') ?>
                      </div>
                      <?php if (!empty($activity['notes'])): ?>
                        <div class="timeline-body">
                          "<?= htmlspecialchars(substr($activity['notes'], 0, 100)) ?><?= strlen($activity['notes']) > 100 ? '...' : '' ?>"
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div><!--/. container-fluid -->
</section>
<!-- /.content -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Fix sidebar
  document.body.classList.add('layout-fixed');
  
  // Revenue Chart
  const revenueChartCanvas = document.getElementById('revenueChart').getContext('2d');
  
  const revenueGradient = revenueChartCanvas.createLinearGradient(0, 0, 0, 250);
  revenueGradient.addColorStop(0, 'rgba(40,167,69,0.4)');
  revenueGradient.addColorStop(1, 'rgba(40,167,69,0.0)');
  
  new Chart(revenueChartCanvas, {
    type: 'line',
    data: {
      labels: <?= json_encode($revenue_labels) ?>,
      datasets: [{
        label: 'Revenue',
        data: <?= json_encode($revenue_data) ?>,
        backgroundColor: revenueGradient,
        borderColor: '#28a745',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: '#28a745',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointHoverRadius: 6,
        pointHoverBackgroundColor: '#28a745',
        pointHoverBorderColor: '#ffffff',
        pointHoverBorderWidth: 2,
        tension: 0.3,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.7)',
          titleFont: {
            size: 13
          },
          bodyFont: {
            size: 12
          },
          titleAlign: 'center',
          bodyAlign: 'center',
          displayColors: false,
          callbacks: {
            label: function(context) {
              return '$ ' + context.parsed.y.toFixed(2);
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            display: false
          },
          ticks: {
            font: {
              size: 10
            }
          }
        },
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(0,0,0,0.05)'
          },
          ticks: {
            font: {
              size: 10
            },
            callback: function(value) {
              return '$' + value;
            }
          }
        }
      }
    }
  });
  
  // Status Chart
  const statusChartCanvas = document.getElementById('statusChart').getContext('2d');
  
  new Chart(statusChartCanvas, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($status_labels) ?>,
      datasets: [{
        data: <?= json_encode($status_data) ?>,
        backgroundColor: <?= json_encode($status_backgrounds) ?>,
        borderWidth: 0,
        hoverOffset: 5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 15,
            boxWidth: 12,
            usePointStyle: true,
            pointStyle: 'circle',
            font: {
              size: 11
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.7)',
          titleFont: {
            size: 13
          },
          bodyFont: {
            size: 12
          },
          displayColors: false,
          callbacks: {
            label: function(context) {
              const value = context.parsed;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = Math.round((value / total) * 100);
              return context.label + ': ' + value + ' orders (' + percentage + '%)';
            }
          }
        }
      }
    }
  });
});
</script>

<?php 
// Fix for the sidebar - add to admin_footer.php if needed
echo '<script>document.body.classList.add("layout-fixed");</script>';
require_once '../includes/admin_footer.php'; 
?>