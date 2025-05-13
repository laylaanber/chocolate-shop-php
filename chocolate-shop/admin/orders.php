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

$message = '';
$error = '';

// Handle order status change
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Update order status
            $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_status, $order_id]);
            
            // Add tracking number if provided
            if ($new_status == 'shipped' && !empty($_POST['tracking_number'])) {
                $tracking_query = "UPDATE orders SET tracking_number = ? WHERE id = ?";
                $tracking_stmt = $db->prepare($tracking_query);
                $tracking_stmt->execute([$_POST['tracking_number'], $order_id]);
            }
            
            // Add a record to the order history table
            $notes = isset($_POST['notes']) ? $_POST['notes'] : "Status updated to " . ucfirst($new_status);
            $history_query = "INSERT INTO order_history (order_id, status, notes, created_by) 
                             VALUES (?, ?, ?, ?)";
            $history_stmt = $db->prepare($history_query);
            $history_stmt->execute([
                $order_id, 
                $new_status, 
                $notes, 
                $_SESSION['user_id']
            ]);
            
            // Commit transaction
            $db->commit();
            
            $message = "Order #$order_id status updated to " . ucfirst($new_status);
        } catch (PDOException $e) {
            // Rollback on error
            $db->rollback();
            $error = "Error updating order status: " . $e->getMessage();
        }
    } else {
        $error = "Invalid status selected.";
    }
}

// Get current page for pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Filter parameters
$status_filter = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;
$date_from = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : null;
$search = isset($_GET['search']) && !empty($_GET['search']) ? $_GET['search'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Allowed sort fields
$allowed_sorts = ['id', 'created_at', 'total_amount', 'status', 'username'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_at';
}

// Allowed order directions
$allowed_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($order), $allowed_orders)) {
    $order = 'DESC';
}

// Base query for orders
$query = "SELECT o.*, u.username, u.email
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];
$count_params = [];

// Apply filters
if ($status_filter) {
    $query .= " AND o.status = ?";
    $count_query .= " AND o.status = ?";
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

if ($date_from) {
    $query .= " AND DATE(o.created_at) >= ?";
    $count_query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $count_params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(o.created_at) <= ?";
    $count_query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $count_params[] = $date_to;
}

if ($search) {
    $query .= " AND (o.id LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR o.payment_method LIKE ?)";
    $count_query .= " AND (o.id LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR o.payment_method LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

// Add order by
$query .= " ORDER BY $sort $order";

// Add limit for pagination
$query .= " LIMIT $offset, $records_per_page";

// Execute queries
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total records for pagination
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Get order status counts
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

$count_by_status_query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$count_stmt = $db->query($count_by_status_query);
$status_results = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total count and add individual status counts
foreach ($status_results as $result) {
    $status_counts[$result['status']] = $result['count'];
    $status_counts['all'] += $result['count'];
}
?>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Orders Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Orders</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
  <div class="container-fluid">
    <?php if (!empty($message)): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?= $message ?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?= $error ?>
      </div>
    <?php endif; ?>
    
    <!-- Status Tabs -->
    <div class="card">
      <div class="card-header p-2">
        <ul class="nav nav-pills">
          <li class="nav-item">
            <a class="nav-link <?= !$status_filter ? 'active' : '' ?>" href="orders.php">
              All Orders <span class="badge bg-secondary ml-1"><?= $status_counts['all'] ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" href="?status=pending">
              <i class="fas fa-clock mr-1"></i> Pending <span class="badge bg-warning ml-1"><?= $status_counts['pending'] ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'processing' ? 'active' : '' ?>" href="?status=processing">
              <i class="fas fa-cogs mr-1"></i> Processing <span class="badge bg-info ml-1"><?= $status_counts['processing'] ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'shipped' ? 'active' : '' ?>" href="?status=shipped">
              <i class="fas fa-truck mr-1"></i> Shipped <span class="badge bg-primary ml-1"><?= $status_counts['shipped'] ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'delivered' ? 'active' : '' ?>" href="?status=delivered">
              <i class="fas fa-check-circle mr-1"></i> Delivered <span class="badge bg-success ml-1"><?= $status_counts['delivered'] ?></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">
              <i class="fas fa-times-circle mr-1"></i> Cancelled <span class="badge bg-danger ml-1"><?= $status_counts['cancelled'] ?></span>
            </a>
          </li>
        </ul>
      </div>
    </div>
    
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">
              <?php if ($status_filter): ?>
                <?= ucfirst($status_filter) ?> Orders
              <?php else: ?>
                All Orders
              <?php endif; ?>
            </h3>
            
            <div class="card-tools">
              <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-search"></i> Advanced Search
              </button>
            </div>
          </div>
          
          <div class="card-body collapse">
            <form method="get" class="mb-4">
              <div class="row">
                <?php if ($status_filter): ?>
                  <input type="hidden" name="status" value="<?= $status_filter ?>">
                <?php endif; ?>
                
                <div class="col-md-3 form-group">
                  <label>Order Date Range:</label>
                  <div class="input-group">
                    <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                    <div class="input-group-append">
                      <span class="input-group-text">to</span>
                    </div>
                    <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                  </div>
                </div>
                
                <div class="col-md-3 form-group">
                  <label>Search:</label>
                  <input type="text" class="form-control" name="search" placeholder="Order #, Customer, Email..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                
                <div class="col-md-3 form-group">
                  <label>Sort By:</label>
                  <select name="sort" class="form-control">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Order Date</option>
                    <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>Order ID</option>
                    <option value="total_amount" <?= $sort === 'total_amount' ? 'selected' : '' ?>>Total Amount</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                    <option value="username" <?= $sort === 'username' ? 'selected' : '' ?>>Customer</option>
                  </select>
                </div>
                
                <div class="col-md-3 form-group">
                  <label>Order:</label>
                  <select name="order" class="form-control">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                  </select>
                </div>
              </div>
              
              <div class="form-group">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-search"></i> Search
                </button>
                <a href="orders.php<?= $status_filter ? '?status=' . $status_filter : '' ?>" class="btn btn-default">
                  <i class="fas fa-sync"></i> Reset
                </a>
              </div>
            </form>
          </div>
          
          <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Date</th>
                  <th>Customer</th>
                  <th>Products</th>
                  <th>Total</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-4">
                      <div class="empty-state">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No orders found matching your criteria</p>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $order): ?>
                    <tr>
                      <td><strong>#<?= $order['id'] ?></strong></td>
                      <td><?= date('M j, Y', strtotime($order['created_at'])) ?><br>
                          <small class="text-muted"><?= date('g:i A', strtotime($order['created_at'])) ?></small>
                      </td>
                      <td>
                        <div class="user-info">
                          <?= htmlspecialchars($order['username'] ?? 'Guest') ?>
                          <?php if (!empty($order['email'])): ?>
                            <br><small><?= htmlspecialchars($order['email']) ?></small>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php 
                          // Get item count and names for this order
                          $items_query = "SELECT oi.quantity, p.name 
                                         FROM order_items oi
                                         LEFT JOIN products p ON oi.product_id = p.id
                                         WHERE oi.order_id = ?
                                         LIMIT 3";
                          $items_stmt = $db->prepare($items_query);
                          $items_stmt->execute([$order['id']]);
                          $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                          
                          $total_query = "SELECT COUNT(*) as total, SUM(quantity) as qty FROM order_items WHERE order_id = ?";
                          $total_stmt = $db->prepare($total_query);
                          $total_stmt->execute([$order['id']]);
                          $totals = $total_stmt->fetch(PDO::FETCH_ASSOC);
                          
                          foreach ($items as $item) {
                            echo "<div>" . $item['quantity'] . " × " . htmlspecialchars($item['name']) . "</div>";
                          }
                          
                          // If there are more items than shown
                          $remaining = $totals['total'] - count($items);
                          if ($remaining > 0) {
                            echo "<small class='text-muted'>+" . $remaining . " more item" . ($remaining > 1 ? 's' : '') . "</small>";
                          }
                        ?>
                      </td>
                      <td>
                        <strong>$<?= number_format($order['total_amount'], 2) ?></strong>
                      </td>
                      <td><?= htmlspecialchars($order['payment_method']) ?></td>
                      <td>
                        <?php
                          $status_class = [
                            'pending' => 'warning',
                            'processing' => 'info',
                            'shipped' => 'primary',
                            'delivered' => 'success',
                            'cancelled' => 'danger'
                          ];
                          $status = $order['status'];
                          $class = $status_class[$status] ?? 'secondary';
                          
                          $icons = [
                            'pending' => '<i class="fas fa-clock mr-1"></i>',
                            'processing' => '<i class="fas fa-cogs mr-1"></i>',
                            'shipped' => '<i class="fas fa-truck mr-1"></i>',
                            'delivered' => '<i class="fas fa-check-circle mr-1"></i>',
                            'cancelled' => '<i class="fas fa-times-circle mr-1"></i>'
                          ];
                          $icon = $icons[$status] ?? '';
                        ?>
                        <span class="badge badge-<?= $class ?>"><?= $icon ?><?= ucfirst($status) ?></span>
                        
                        <?php if ($status === 'shipped' && !empty($order['tracking_number'])): ?>
                          <div class="mt-1">
                            <small class="text-muted"><?= htmlspecialchars($order['tracking_number']) ?></small>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="btn-group">
                          <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-default" title="View Details">
                            <i class="fas fa-eye"></i>
                          </a>
                          <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" title="Update Status">
                            <i class="fas fa-exchange-alt"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-right">
                            <h6 class="dropdown-header">Update Status</h6>
                            
                            <!-- Quick update forms -->
                            <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status_option): ?>
                              <?php if ($status_option != $order['status']): ?>
                                <form method="post">
                                  <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                  <input type="hidden" name="new_status" value="<?= $status_option ?>">
                                  
                                  <?php if ($status_option === 'shipped'): ?>
                                    <a href="#" class="dropdown-item update-shipped" data-order-id="<?= $order['id'] ?>">
                                      <i class="fas fa-truck mr-1 text-primary"></i> Mark as Shipped
                                    </a>
                                  <?php else: ?>
                                    <button type="submit" name="update_status" class="dropdown-item">
                                      <i class="fas <?= getStatusIcon($status_option) ?> mr-1 text-<?= $status_class[$status_option] ?>"></i>
                                      Mark as <?= ucfirst($status_option) ?>
                                    </button>
                                  <?php endif; ?>
                                </form>
                              <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <div class="dropdown-divider"></div>
                            <a href="order_detail.php?id=<?= $order['id'] ?>" class="dropdown-item">
                              <i class="fas fa-edit mr-1"></i> Edit Full Details
                            </a>
                            <a href="print_invoice.php?id=<?= $order['id'] ?>" class="dropdown-item" target="_blank">
                              <i class="fas fa-print mr-1"></i> Print Invoice
                            </a>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <?php if ($total_pages > 1): ?>
            <div class="card-footer clearfix">
              <ul class="pagination pagination-sm m-0 float-right">
                <?php if ($current_page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?<?= buildPaginationQuery($current_page - 1) ?>">&laquo;</a>
                  </li>
                <?php endif; ?>
                
                <?php
                  $start_page = max(1, $current_page - 2);
                  $end_page = min($total_pages, $current_page + 2);
                  
                  // Always show first page
                  if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?' . buildPaginationQuery(1) . '">1</a></li>';
                    if ($start_page > 2) {
                      echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                    }
                  }
                  
                  // Pages
                  for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?' . buildPaginationQuery($i) . '">' . $i . '</a>';
                    echo '</li>';
                  }
                  
                  // Always show last page
                  if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                      echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?' . buildPaginationQuery($total_pages) . '">' . $total_pages . '</a></li>';
                  }
                ?>
                
                <?php if ($current_page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?<?= buildPaginationQuery($current_page + 1) ?>">&raquo;</a>
                  </li>
                <?php endif; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Modal for Tracking Number -->
<div class="modal fade" id="shipped-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Mark Order as Shipped</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="order_id" id="modal-order-id">
          <input type="hidden" name="new_status" value="shipped">
          
          <div class="form-group">
            <label for="tracking_number">Tracking Number</label>
            <input type="text" class="form-control" name="tracking_number" id="tracking_number" placeholder="Enter tracking number">
            <small class="form-text text-muted">Optional: Add a tracking number for shipment</small>
          </div>
          
          <div class="form-group">
            <label for="notes">Additional Notes</label>
            <textarea class="form-control" name="notes" id="notes" rows="3" placeholder="Enter additional shipping information"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="update_status" class="btn btn-primary">Mark as Shipped</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  // Handle shipped status updates
  $('.update-shipped').click(function(e) {
    e.preventDefault();
    const orderId = $(this).data('order-id');
    $('#modal-order-id').val(orderId);
    $('#shipped-modal').modal('show');
  });
});
</script>

<?php
// Helper functions
function buildPaginationQuery($page) {
    global $status_filter, $date_from, $date_to, $search, $sort, $order;
    
    $query = [];
    if ($status_filter) $query[] = "status=$status_filter";
    if ($date_from) $query[] = "date_from=$date_from";
    if ($date_to) $query[] = "date_to=$date_to";
    if ($search) $query[] = "search=" . urlencode($search);
    if ($sort) $query[] = "sort=$sort";
    if ($order) $query[] = "order=$order";
    
    $query[] = "page=$page";
    return implode("&", $query);
}

function getStatusIcon($status) {
    switch ($status) {
        case 'pending': return 'fa-clock';
        case 'processing': return 'fa-cogs';
        case 'shipped': return 'fa-truck';
        case 'delivered': return 'fa-check-circle';
        case 'cancelled': return 'fa-times-circle';
        default: return 'fa-question-circle';
    }
}
?>

<?php require_once '../includes/admin_footer.php'; ?>