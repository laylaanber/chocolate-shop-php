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

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    try {
        // Get product information for image deletion
        $get_product = "SELECT image FROM products WHERE id = ?";
        $stmt = $db->prepare($get_product);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete product
        $delete_query = "DELETE FROM products WHERE id = ?";
        $stmt = $db->prepare($delete_query);
        $stmt->execute([$product_id]);
        
        // Delete product image if exists
        if ($product && !empty($product['image'])) {
            $image_path = "../uploads/products/" . $product['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $message = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}

// Filter by category if specified
$category_filter = '';
$params = [];

if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $category_id = $_GET['category'];
    $category_filter = "WHERE p.category_id = ?";
    $params = [$category_id];
    
    // Get category name for display
    $cat_name_query = "SELECT name FROM categories WHERE id = ?";
    $cat_name_stmt = $db->prepare($cat_name_query);
    $cat_name_stmt->execute([$category_id]);
    $category_name = $cat_name_stmt->fetchColumn();
}

// Get all products with optional category filter
$query = "SELECT p.*, c.name as category_name 
         FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id 
         $category_filter
         ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for the add/edit form
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">
          <?php if (isset($category_name)): ?>
            Products in "<?= htmlspecialchars($category_name) ?>"
          <?php else: ?>
            All Products
          <?php endif; ?>
        </h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Products</li>
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
      <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- Add Product Button -->
    <div class="mb-3">
      <?php if (isset($category_name)): ?>
        <a href="products.php" class="btn btn-secondary mr-2">
          <i class="fas fa-arrow-left"></i> All Products
        </a>
      <?php endif; ?>
      <a href="product_form.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Product
      </a>
    </div>
    
    <!-- Products Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">All Products</h3>
      </div>
      <div class="card-body">
        <table id="products-table" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Image</th>
              <th>Name</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Featured</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <tr>
                <td><?= $product['id'] ?></td>
                <td>
                  <?php if (!empty($product['image'])): ?>
                    <img src="../uploads/products/<?= $product['image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" height="50">
                  <?php else: ?>
                    <span class="badge bg-secondary">No image</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($product['name']) ?></td>
                <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                <td>$<?= number_format($product['price'], 2) ?></td>
                <td><?= $product['stock'] ?></td>
                <td>
                  <?php if ($product['is_featured']): ?>
                    <span class="badge bg-success">Yes</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php 
                  switch($product['is_active']) {
                    case 'available':
                      echo '<span class="badge bg-success">Available</span>';
                      break;
                    case 'out_of_stock':
                      echo '<span class="badge bg-warning">Out of Stock</span>';
                      break;
                    case 'discontinued':
                      echo '<span class="badge bg-danger">Discontinued</span>';
                      break;
                    case 'coming_soon':
                      echo '<span class="badge bg-info">Coming Soon</span>';
                      break;
                    default:
                      echo '<span class="badge bg-secondary">Unknown</span>';
                  }
                  ?>
                </td>
                <td>
                  <a href="product_form.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="?delete=<?= $product['id'] ?>" class="btn btn-sm btn-danger" 
                     onclick="return confirm('Are you sure you want to delete this product?')">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<script>
  $(document).ready(function() {
    $('#products-table').DataTable();
  });
</script>

<?php require_once '../includes/admin_footer.php'; ?>