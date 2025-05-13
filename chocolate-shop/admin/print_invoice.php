<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Check if we have an order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = $_GET['id'];

// Get order details
$order_query = "SELECT o.*, u.username, u.email, u.phone, 
                a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.country
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN addresses a ON o.address_id = a.id
                WHERE o.id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id]);

if ($order_stmt->rowCount() == 0) {
    echo "Order not found!";
    exit;
}

$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

// Get order items
$items_query = "SELECT oi.*, p.name as product_name, p.image as product_image
               FROM order_items oi
               LEFT JOIN products p ON oi.product_id = p.id
               WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice #<?= $order_id ?> | Chocolate Shop</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <style>
    @media print {
      body {
        margin: 0;
        padding: 0;
      }
      .no-print {
        display: none;
      }
      .main-footer {
        display: none;
      }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="callout callout-info no-print">
            <h5><i class="fas fa-info"></i> Note:</h5>
            This page has been enhanced for printing. Click the print button at the bottom of the invoice to print.
          </div>

          <!-- Main content -->
          <div class="invoice p-3 mb-3">
            <!-- title row -->
            <div class="row">
              <div class="col-12">
                <h4>
                  <i class="fas fa-globe"></i> Chocolate Shop
                  <small class="float-right">Date: <?= date('M j, Y', strtotime($order['created_at'])) ?></small>
                </h4>
              </div>
              <!-- /.col -->
            </div>
            <!-- info row -->
            <div class="row invoice-info">
              <div class="col-sm-4 invoice-col">
                From
                <address>
                  <strong>Chocolate Shop, Inc.</strong><br>
                  123 Chocolate Ave<br>
                  Sweet City, CA 94107<br>
                  Phone: (555) 123-4567<br>
                  Email: info@chocolateshop.com
                </address>
              </div>
              <!-- /.col -->
              <div class="col-sm-4 invoice-col">
                To
                <address>
                  <strong><?= htmlspecialchars($order['username'] ?? 'Guest') ?></strong><br>
                  <?= htmlspecialchars($order['address_line1']) ?><br>
                  <?php if (!empty($order['address_line2'])): ?>
                    <?= htmlspecialchars($order['address_line2']) ?><br>
                  <?php endif; ?>
                  <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['postal_code']) ?><br>
                  <?= htmlspecialchars($order['country']) ?><br>
                  Phone: <?= htmlspecialchars($order['phone'] ?? 'N/A') ?><br>
                  Email: <?= htmlspecialchars($order['email']) ?>
                </address>
              </div>
              <!-- /.col -->
              <div class="col-sm-4 invoice-col">
                <b>Invoice #<?= $order_id ?></b><br>
                <br>
                <b>Order ID:</b> <?= $order_id ?><br>
                <b>Payment Method:</b> <?= htmlspecialchars($order['payment_method']) ?><br>
                <b>Order Date:</b> <?= date('M j, Y', strtotime($order['created_at'])) ?><br>
                <?php if (!empty($order['tracking_number'])): ?>
                  <b>Tracking #:</b> <?= htmlspecialchars($order['tracking_number']) ?>
                <?php endif; ?>
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <!-- Table row -->
            <div class="row">
              <div class="col-12 table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Qty</th>
                      <th>Product</th>
                      <th>Description</th>
                      <th>Price</th>
                      <th>Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($order_items as $item): ?>
                      <tr>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td>
                          <?php
                          // If you have a description column in your order_items or product table, use that here
                          echo "Premium chocolate product";
                          ?>
                        </td>
                        <td>$<?= number_format($item['unit_price'], 2) ?></td>
                        <td>$<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <div class="row">
              <!-- accepted payments column -->
              <div class="col-6">
                <p class="lead">Payment Method: <?= htmlspecialchars($order['payment_method']) ?></p>
                <p class="text-muted well well-sm shadow-none" style="margin-top: 10px;">
                  Thank you for your order with Chocolate Shop. We appreciate your business!
                </p>
              </div>
              <!-- /.col -->
              <div class="col-6">
                <div class="table-responsive">
                  <table class="table">
                    <tr>
                      <th style="width:50%">Subtotal:</th>
                      <td>$<?= number_format($order['total_amount'] - $order['shipping_cost'] - $order['tax_amount'], 2) ?></td>
                    </tr>
                    <?php if ($order['tax_amount'] > 0): ?>
                      <tr>
                        <th>Tax:</th>
                        <td>$<?= number_format($order['tax_amount'], 2) ?></td>
                      </tr>
                    <?php endif; ?>
                    <tr>
                      <th>Shipping:</th>
                      <td>$<?= number_format($order['shipping_cost'], 2) ?></td>
                    </tr>
                    <?php if ($order['discount_amount'] > 0): ?>
                      <tr>
                        <th>Discount:</th>
                        <td>-$<?= number_format($order['discount_amount'], 2) ?></td>
                      </tr>
                    <?php endif; ?>
                    <tr>
                      <th>Total:</th>
                      <td>$<?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                  </table>
                </div>
              </div>
              <!-- /.col -->
            </div>
            <!-- /.row -->

            <!-- this row will not appear when printing -->
            <div class="row no-print">
              <div class="col-12">
                <button onclick="window.print();" class="btn btn-default"><i class="fas fa-print"></i> Print</button>
                <a href="order_detail.php?id=<?= $order_id ?>" class="btn btn-secondary float-right">
                  <i class="fas fa-arrow-left"></i> Back to Order
                </a>
              </div>
            </div>
          </div>
          <!-- /.invoice -->
        </div><!-- /.col -->
      </div><!-- /.row -->
    </div><!-- /.container-fluid -->
  </section>
  <!-- /.content -->
</div>
<!-- ./wrapper -->
</body>
</html>