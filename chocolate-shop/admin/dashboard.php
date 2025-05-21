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

// Get daily revenue for the chart (last 15 days including today)
$daily_revenue_query = "SELECT 
                        DATE(created_at) as day,
                        DATE_FORMAT(created_at, '%b %d') as day_name,
                        SUM(total_amount) as revenue
                      FROM orders
                      WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
                      GROUP BY day
                      ORDER BY day ASC";
$daily_revenue_stmt = $db->prepare($daily_revenue_query);
$daily_revenue_stmt->execute();
$daily_revenue_data = $daily_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill in missing days with zero values - Make sure to include today
$revenue_labels = [];
$revenue_data = [];

// Create start and end dates for the range
$end_date = new DateTime(); // Today
// Set end date to end of the day to ensure today is included
$end_date->setTime(23, 59, 59); 

$start_date = new DateTime();
$start_date->modify('-14 days'); // Last 15 days (including today)
$start_date->setTime(0, 0, 0); // Start of day

// Create a lookup array for existing data
$revenue_lookup = [];
foreach ($daily_revenue_data as $day) {
    $revenue_lookup[$day['day']] = $day['revenue'];
    // Also store the formatted day name
    $revenue_lookup[$day['day'] . '_name'] = $day['day_name'];
}

// Fill array with all days in range
$current_date = clone $start_date;
while ($current_date <= $end_date) {
    $date_string = $current_date->format('Y-m-d');
    
    // Use existing data if available, otherwise zero
    $revenue_data[] = isset($revenue_lookup[$date_string]) ? $revenue_lookup[$date_string] : 0;
    
    // Use formatted name if available, otherwise format the current date
    $revenue_labels[] = isset($revenue_lookup[$date_string . '_name']) ? 
                         $revenue_lookup[$date_string . '_name'] : 
                         $current_date->format('M d');
    
    $current_date->modify('+1 day');
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

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Dashboard Overview</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
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
    <!-- Key Stats Cards -->
    <div class="row">
      <div class="col-lg-3 col-6">
        <!-- Orders card -->
        <div class="info-box">
          <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-shopping-cart"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Total Orders</span>
            <span class="info-box-number"><?= $orders_count ?></span>
            <a href="orders.php" class="text-primary">
              <small>View Orders <i class="fas fa-arrow-right ml-1"></i></small>
            </a>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <!-- Revenue card -->
        <div class="info-box">
          <span class="info-box-icon bg-success elevation-1"><i class="fas fa-dollar-sign"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Total Revenue</span>
            <span class="info-box-number">$<?= number_format($total_revenue, 2) ?></span>
            <a href="orders.php" class="text-success">
              <small>View Details <i class="fas fa-arrow-right ml-1"></i></small>
            </a>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <!-- Products card -->
        <div class="info-box">
          <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-box"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Products</span>
            <span class="info-box-number"><?= $products_count ?></span>
            <a href="products.php" class="text-warning">
              <small>Manage Products <i class="fas fa-arrow-right ml-1"></i></small>
            </a>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <!-- Users card -->
        <div class="info-box">
          <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-users"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">Customers</span>
            <span class="info-box-number"><?= $users_count ?></span>
            <a href="manage_users.php" class="text-danger">
              <small>Manage Users <i class="fas fa-arrow-right ml-1"></i></small>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Order Status Overview -->
    <div class="row">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-line mr-1"></i>
              Revenue (Last 15 Days)
            </h3>
          </div>
          <div class="card-body">
            <div class="chart-container" style="height: 300px;">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <i class="fas fa-chart-pie mr-1"></i>
              Order Status Distribution
            </h3>
          </div>
          <div class="card-body d-flex justify-content-center align-items-center">
            <div class="chart-container" style="height: 240px; width: 100%">
              <canvas id="statusChart"></canvas>
            </div>
          </div>
          <div class="card-footer">
            <div class="row text-center">
              <?php
              $status_list = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
              foreach ($status_list as $status) {
                $count = $status_counts[$status] ?? 0;
                $badge_class = getStatusBadgeClass($status);
              ?>
              <div class="col">
                <div class="description-block border-right">
                  <span class="badge badge-<?= $badge_class ?> mb-2">
                    <i class="fas fa-<?= getStatusIcon($status) ?>"></i>
                  </span>
                  <h5 class="description-header"><?= $count ?></h5>
                  <span class="description-text text-uppercase"><?= $status ?></span>
                </div>
              </div>
              <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Recent Orders -->
      <div class="col-md-8">
        <div class="card">
          <div class="card-header border-transparent">
            <h3 class="card-title">Recent Orders</h3>
            <div class="card-tools">
              <a href="orders.php" class="btn btn-sm btn-primary">
                View All
              </a>
            </div>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recent_orders)): ?>
              <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <p>No orders yet</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table m-0">
                  <thead>
                    <tr>
                      <th>Order #</th>
                      <th>Customer</th>
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Actions</th>
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
                          <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
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
        <!-- Top Products -->
        <div class="card">
          <div class="card-header">
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
              <div class="empty-state">
                <i class="fas fa-box"></i>
                <p>No product data available</p>
              </div>
            <?php else: ?>
              <ul class="products-list product-list-in-card pl-2 pr-2">
                <?php foreach ($top_products as $product): ?>
                  <li class="item">
                    <div class="product-img">
                      <?php if (!empty($product['image'])): ?>
                        <img src="../uploads/products/<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-size-50">
                      <?php else: ?>
                        <div class="img-size-50 d-flex align-items-center justify-content-center bg-light">
                          <i class="fas fa-box text-secondary"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="product-info">
                      <a href="product_form.php?id=<?= $product['id'] ?>" class="product-title">
                        <?= htmlspecialchars($product['name']) ?>
                        <span class="badge badge-success float-right">$<?= number_format($product['total_revenue'], 2) ?></span>
                      </a>
                      <span class="product-description">
                        <?= $product['total_sold'] ?> units sold
                      </span>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
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
            <div class="row">
              <div class="col-6 mb-3">
                <a href="product_form.php" class="btn btn-primary btn-block btn-sm">
                  <i class="fas fa-plus mr-1"></i> Add Product
                </a>
              </div>
              <div class="col-6 mb-3">
                <a href="category_form.php" class="btn btn-info btn-block btn-sm">
                  <i class="fas fa-folder-plus mr-1"></i> Add Category
                </a>
              </div>
              <div class="col-6">
                <a href="orders.php?status=pending" class="btn btn-warning btn-block btn-sm">
                  <i class="fas fa-clock mr-1"></i> Pending Orders
                  <?php if (isset($status_counts['pending']) && $status_counts['pending'] > 0): ?>
                    <span class="badge badge-light ml-1"><?= $status_counts['pending'] ?></span>
                  <?php endif; ?>
                </a>
              </div>
              <div class="col-6">
                <a href="orders.php" class="btn btn-success btn-block btn-sm">
                  <i class="fas fa-list mr-1"></i> All Orders
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Recent Activity -->
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
              <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No recent activity</p>
              </div>
            <?php else: ?>
              <div class="timeline">
                <?php 
                $current_date = '';
                foreach ($activities as $activity): 
                  $history_date = date('Y-m-d', strtotime($activity['created_at']));
                  $show_date = ($current_date != $history_date);
                  $current_date = $history_date;
                  $badge_class = getStatusBadgeClass($activity['status']);
                  $icon = getStatusIcon($activity['status']);
                ?>
                  
                  <?php if ($show_date): ?>
                    <!-- timeline time label -->
                    <div class="time-label">
                      <span class="bg-secondary"><?= date('F j, Y', strtotime($activity['created_at'])) ?></span>
                    </div>
                  <?php endif; ?>
                  
                  <!-- timeline item -->
                  <div>
                    <i class="fas fa-<?= $icon ?> bg-<?= $badge_class ?>"></i>
                    <div class="timeline-item">
                      <span class="time">
                        <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($activity['created_at'])) ?>
                      </span>
                      <h3 class="timeline-header">
                        <a href="order_detail.php?id=<?= $activity['order_id'] ?>">Order #<?= $activity['order_id'] ?></a> 
                        was marked as <span class="font-weight-bold text-<?= $badge_class ?>"><?= ucfirst($activity['status']) ?></span>
                        by <?= htmlspecialchars($activity['username'] ?? 'System') ?>
                      </h3>
                      <?php if (!empty($activity['notes'])): ?>
                        <div class="timeline-body">
                          <?= htmlspecialchars(substr($activity['notes'], 0, 100)) ?><?= strlen($activity['notes']) > 100 ? '...' : '' ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
                
                <div>
                  <i class="fas fa-clock bg-gray"></i>
                </div>
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
        label: 'Daily Revenue',
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
            },
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 15
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

<?php require_once '../includes/admin_footer.php'; ?>