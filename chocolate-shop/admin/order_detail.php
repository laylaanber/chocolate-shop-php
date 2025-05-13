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

// Check if we have an order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = $_GET['id'];

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['new_status'];
        $notes = $_POST['notes'] ?? '';
        $tracking_number = $_POST['tracking_number'] ?? null;
        
        $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (in_array($new_status, $allowed_statuses)) {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Update order status
                $update_query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$new_status, $order_id]);
                
                // Update tracking number if provided
                if ($new_status === 'shipped' && !empty($tracking_number)) {
                    $tracking_query = "UPDATE orders SET tracking_number = ? WHERE id = ?";
                    $tracking_stmt = $db->prepare($tracking_query);
                    $tracking_stmt->execute([$tracking_number, $order_id]);
                }
                
                // Add a record to the order history table
                $history_query = "INSERT INTO order_history (order_id, status, notes, created_by) 
                                VALUES (?, ?, ?, ?)";
                $history_stmt = $db->prepare($history_query);
                $history_stmt->execute([
                    $order_id, 
                    $new_status, 
                    !empty($notes) ? $notes : "Status updated to " . ucfirst($new_status), 
                    $_SESSION['user_id']
                ]);
                
                // Commit transaction
                $db->commit();
                
                $message = "Order status updated successfully to " . ucfirst($new_status);
            } catch (PDOException $e) {
                // Rollback transaction on error
                $db->rollback();
                $error = "Error updating order status: " . $e->getMessage();
            }
        } else {
            $error = "Invalid status selected.";
        }
    }
    
    // Handle customer info update
    if (isset($_POST['update_customer_info'])) {
        $phone = $_POST['phone'] ?? '';
        $notes = $_POST['customer_notes'] ?? '';
        
        try {
            // Update customer notes in the order
            $notes_query = "UPDATE orders SET notes = ? WHERE id = ?";
            $notes_stmt = $db->prepare($notes_query);
            $notes_stmt->execute([$notes, $order_id]);
            
            // Update phone if user exists
            if (!empty($phone) && !empty($_POST['user_id'])) {
                $user_query = "UPDATE users SET phone = ? WHERE id = ?";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->execute([$phone, $_POST['user_id']]);
            }
            
            $message = "Customer information updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating customer information: " . $e->getMessage();
        }
    }
}

// Get order details with joins for all related information
$order_query = "SELECT o.*, u.id as user_id, u.username, u.email, u.phone, 
                a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.country
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN addresses a ON o.address_id = a.id
                WHERE o.id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id]);

if ($order_stmt->rowCount() == 0) {
    $error = "Order not found!";
    $order = null;
} else {
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get order items with product details
    $items_query = "SELECT oi.*, p.name as product_name, p.image as product_image, p.id as product_id
                   FROM order_items oi
                   LEFT JOIN products p ON oi.product_id = p.id
                   WHERE oi.order_id = ?
                   ORDER BY oi.id";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order history with admin details
    $history_query = "SELECT oh.*, u.username as updated_by_username
                     FROM order_history oh
                     LEFT JOIN users u ON oh.created_by = u.id
                     WHERE oh.order_id = ?
                     ORDER BY oh.created_at DESC";
    $history_stmt = $db->prepare($history_query);
    $history_stmt->execute([$order_id]);
    $order_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get status badge class
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
        <h1 class="m-0">
          Order #<?= $order_id ?>
          <?php if ($order): ?>
            <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
              <?= ucfirst($order['status']) ?>
            </span>
          <?php endif; ?>
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
          <li class="breadcrumb-item active">Order #<?= $order_id ?></li>
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
      <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h5><i class="icon fas fa-check"></i> Success!</h5>
        <?= $message ?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h5><i class="icon fas fa-ban"></i> Error!</h5>
        <?= $error ?>
      </div>
    <?php endif; ?>
    
    <?php if ($order): ?>
      <!-- Quick Actions Bar -->
      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center p-3">
          <div>
            <span class="text-muted">Created</span>
            <strong class="d-block"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></strong>
          </div>
          
          <div>
            <span class="text-muted">Customer</span>
            <strong class="d-block"><?= htmlspecialchars($order['username'] ?? 'Guest') ?></strong>
          </div>
          
          <div>
            <span class="text-muted">Payment Method</span>
            <strong class="d-block"><?= htmlspecialchars($order['payment_method']) ?></strong>
          </div>
          
          <div>
            <span class="text-muted">Total</span>
            <strong class="d-block">$<?= number_format($order['total_amount'], 2) ?></strong>
          </div>
          
          <div>
            <a href="print_invoice.php?id=<?= $order_id ?>" class="btn btn-info" target="_blank">
              <i class="fas fa-print"></i> Print Invoice
            </a>
            <a href="orders.php" class="btn btn-secondary">
              <i class="fas fa-list"></i> Back to Orders
            </a>
          </div>
        </div>
      </div>
    
      <div class="row">
        <div class="col-md-8">
          <!-- Order Items Card -->
          <div class="card card-outline card-primary mb-4">
            <div class="card-header">
              <h3 class="card-title">Order Items</h3>
              <div class="card-tools">
                <span class="badge badge-<?= getStatusBadgeClass($order['status']) ?>">
                  <i class="fas fa-<?= getStatusIcon($order['status']) ?> mr-1"></i>
                  <?= ucfirst($order['status']) ?>
                </span>
              </div>
            </div>
            <div class="card-body p-0">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th style="width: 50%">Product</th>
                    <th class="text-center">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($order_items as $item): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <?php if (!empty($item['product_image'])): ?>
                            <img src="../uploads/products/<?= $item['product_image'] ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="mr-3" style="width: 50px; height: 50px; object-fit: cover;">
                          <?php else: ?>
                            <div class="mr-3 bg-secondary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                              <i class="fas fa-box"></i>
                            </div>
                          <?php endif; ?>
                          <div>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                            <div class="text-muted small">
                              ID: <?= $item['product_id'] ?>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td class="text-center">$<?= number_format($item['unit_price'], 2) ?></td>
                      <td class="text-center"><?= $item['quantity'] ?></td>
                      <td class="text-right">$<?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-right">Subtotal:</th>
                    <th class="text-right">$<?= number_format($order['total_amount'] - $order['shipping_cost'] - $order['tax_amount'], 2) ?></th>
                  </tr>
                  <?php if ($order['tax_amount'] > 0): ?>
                    <tr>
                      <th colspan="3" class="text-right">Tax:</th>
                      <td class="text-right">$<?= number_format($order['tax_amount'], 2) ?></td>
                    </tr>
                  <?php endif; ?>
                  <tr>
                    <th colspan="3" class="text-right">Shipping:</th>
                    <td class="text-right">$<?= number_format($order['shipping_cost'], 2) ?></td>
                  </tr>
                  <?php if ($order['discount_amount'] > 0): ?>
                    <tr>
                      <th colspan="3" class="text-right">Discount:</th>
                      <td class="text-right text-success">-$<?= number_format($order['discount_amount'], 2) ?></td>
                    </tr>
                  <?php endif; ?>
                  <tr>
                    <th colspan="3" class="text-right">Total:</th>
                    <th class="text-right h5">$<?= number_format($order['total_amount'], 2) ?></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          
          <!-- Order History Timeline -->
          <div class="card card-outline card-secondary">
            <div class="card-header">
              <h3 class="card-title">Order History</h3>
            </div>
            <div class="card-body">
              <?php if (empty($order_history)): ?>
                <div class="text-center text-muted py-3">
                  <i class="fas fa-history fa-3x mb-3"></i>
                  <p>No history records found</p>
                </div>
              <?php else: ?>
                <div class="timeline">
                  <?php 
                  $current_date = '';
                  foreach ($order_history as $history_item): 
                    $history_date = date('Y-m-d', strtotime($history_item['created_at']));
                    $show_date = ($current_date != $history_date);
                    $current_date = $history_date;
                  ?>
                    
                    <?php if ($show_date): ?>
                      <!-- timeline time label -->
                      <div class="time-label">
                        <span class="bg-gray"><?= date('F j, Y', strtotime($history_item['created_at'])) ?></span>
                      </div>
                    <?php endif; ?>
                    
                    <!-- timeline item -->
                    <div>
                      <i class="fas fa-<?= getStatusIcon($history_item['status']) ?> bg-<?= getStatusBadgeClass($history_item['status']) ?>"></i>
                      <div class="timeline-item">
                        <span class="time">
                          <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($history_item['created_at'])) ?>
                        </span>
                        <h3 class="timeline-header">
                          <strong><?= ucfirst($history_item['status']) ?></strong> 
                          by <?= htmlspecialchars($history_item['updated_by_username'] ?? 'System') ?>
                        </h3>
                        <?php if (!empty($history_item['notes'])): ?>
                          <div class="timeline-body">
                            <?= nl2br(htmlspecialchars($history_item['notes'])) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  
                  <div>
                    <i class="fas fa-clock bg-gray"></i>
                    <div class="timeline-item">
                      <div class="timeline-header">
                        <strong>Order Created</strong>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div class="col-md-4">
          <!-- Update Status Card -->
          <div class="card card-outline card-primary mb-4">
            <div class="card-header">
              <h3 class="card-title">Update Order Status</h3>
            </div>
            <div class="card-body">
              <form action="" method="post">
                <div class="form-group">
                  <label for="new_status">Status</label>
                  <select class="form-control" id="new_status" name="new_status">
                    <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                  </select>
                </div>
                
                <div id="tracking_container" class="form-group" style="<?= $order['status'] == 'shipped' ? '' : 'display:none;' ?>">
                  <label for="tracking_number">Tracking Number</label>
                  <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                         value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>"
                         placeholder="Enter tracking number">
                  <small class="form-text text-muted">Provide a tracking number when marking as shipped</small>
                </div>
                
                <div class="form-group">
                  <label for="notes">Notes</label>
                  <textarea class="form-control" id="notes" name="notes" rows="3" 
                            placeholder="Add notes about this status change"></textarea>
                </div>
                
                <button type="submit" name="update_status" class="btn btn-primary btn-block">
                  <i class="fas fa-save mr-1"></i> Update Status
                </button>
                
                <?php if ($order['status'] === 'cancelled'): ?>
                  <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    This order has been cancelled. Any further updates will be for record-keeping only.
                  </div>
                <?php endif; ?>
              </form>
            </div>
          </div>
          
          <!-- Customer Information Card -->
          <div class="card card-outline card-info mb-4">
            <div class="card-header">
              <h3 class="card-title">Customer Information</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-tool" data-toggle="modal" data-target="#edit-customer-modal">
                  <i class="fas fa-edit"></i>
                </button>
              </div>
            </div>
            <div class="card-body">
              <h5 class="mb-3"><?= htmlspecialchars($order['username'] ?? 'Guest') ?></h5>
              
              <div class="customer-info mb-4">
                <p class="mb-1">
                  <i class="fas fa-envelope mr-2 text-muted"></i> 
                  <?= htmlspecialchars($order['email']) ?>
                </p>
                
                <?php if (!empty($order['phone'])): ?>
                  <p class="mb-1">
                    <i class="fas fa-phone mr-2 text-muted"></i> 
                    <?= htmlspecialchars($order['phone']) ?>
                  </p>
                <?php endif; ?>
                
                <?php if (!empty($order['user_id'])): ?>
                  <p class="mb-1">
                    <i class="fas fa-user mr-2 text-muted"></i>
                    <a href="manage_users.php?id=<?= $order['user_id'] ?>">View Customer Profile</a>
                  </p>
                <?php endif; ?>
              </div>
              
              <h6 class="border-bottom pb-2 mb-3">Shipping Address</h6>
              <address>
                <?= htmlspecialchars($order['address_line1']) ?><br>
                <?php if (!empty($order['address_line2'])): ?>
                  <?= htmlspecialchars($order['address_line2']) ?><br>
                <?php endif; ?>
                <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['postal_code']) ?><br>
                <?= htmlspecialchars($order['country']) ?>
              </address>
              
              <?php if (!empty($order['notes'])): ?>
                <h6 class="border-bottom pb-2 mb-3 mt-4">Order Notes</h6>
                <div class="order-notes">
                  <?= nl2br(htmlspecialchars($order['notes'])) ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Payment Information Card -->
          <div class="card card-outline card-success">
            <div class="card-header">
              <h3 class="card-title">Payment Information</h3>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-6">
                  <p class="text-muted mb-1">Method</p>
                  <p class="font-weight-bold"><?= htmlspecialchars($order['payment_method']) ?></p>
                </div>
                <div class="col-6">
                  <p class="text-muted mb-1">Total</p>
                  <p class="font-weight-bold">$<?= number_format($order['total_amount'], 2) ?></p>
                </div>
              </div>
              
              <?php if ($order['status'] !== 'cancelled'): ?>
                <hr>
                <div class="text-center">
                  <a href="print_invoice.php?id=<?= $order_id ?>" class="btn btn-outline-success btn-sm" target="_blank">
                    <i class="fas fa-file-invoice-dollar mr-1"></i> View Invoice
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Edit Customer Information Modal -->
      <div class="modal fade" id="edit-customer-modal">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title">Edit Customer Information</h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form action="" method="post">
              <div class="modal-body">
                <?php if (!empty($order['user_id'])): ?>
                  <input type="hidden" name="user_id" value="<?= $order['user_id'] ?>">
                  
                  <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" 
                           value="<?= htmlspecialchars($order['phone'] ?? '') ?>">
                  </div>
                <?php endif; ?>
                
                <div class="form-group">
                  <label for="customer_notes">Order Notes</label>
                  <textarea class="form-control" id="customer_notes" name="customer_notes" rows="4"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                  <small class="form-text text-muted">Add any relevant notes about this order or customer.</small>
                </div>
              </div>
              <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" name="update_customer_info" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
    <?php else: ?>
      <div class="text-center py-5">
        <div class="error-page">
          <h2 class="headline text-warning">404</h2>
          <div class="error-content pt-4">
            <h3><i class="fas fa-exclamation-triangle text-warning"></i> Order not found!</h3>
            <p>
              We could not find the order you were looking for.<br>
              Meanwhile, you may <a href="orders.php">return to the orders list</a>.
            </p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('new_status');
    const trackingContainer = document.getElementById('tracking_container');
    
    // Show/hide tracking number field based on selected status
    statusSelect.addEventListener('change', function() {
      if (this.value === 'shipped') {
        trackingContainer.style.display = 'block';
      } else {
        trackingContainer.style.display = 'none';
      }
    });
  });
</script>

<?php require_once '../includes/admin_footer.php'; ?>