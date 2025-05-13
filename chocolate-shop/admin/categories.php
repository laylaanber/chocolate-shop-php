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

// Handle category deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    try {
        // Get category information for image deletion and logging
        $get_category = "SELECT name, image FROM categories WHERE id = ?";
        $stmt = $db->prepare($get_category);
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        $category_name = $category['name'];
        
        // Update products from this category to NULL category and discontinue them
        $update_query = "UPDATE products 
                        SET category_id = NULL, is_active = 'discontinued' 
                        WHERE category_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$category_id]);
        $affected_products = $update_stmt->rowCount();
        
        // Delete category
        $delete_query = "DELETE FROM categories WHERE id = ?";
        $stmt = $db->prepare($delete_query);
        $stmt->execute([$category_id]);
        
        // Delete category image if exists
        if ($category && !empty($category['image'])) {
            $image_path = "../uploads/categories/" . $category['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        if ($affected_products > 0) {
            $message = "Category '$category_name' deleted successfully! $affected_products products have been marked as discontinued.";
        } else {
            $message = "Category '$category_name' deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting category: " . $e->getMessage();
    }
}

// Get all categories with product counts
$query = "SELECT c.*, COUNT(p.id) as product_count 
         FROM categories c 
         LEFT JOIN products p ON c.id = p.category_id 
         GROUP BY c.id 
         ORDER BY c.name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Categories</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Categories</li>
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
    
    <div class="alert alert-info">
      <h5><i class="icon fas fa-info"></i> About Categories</h5>
      <p>When you delete a category, any products in that category will automatically be marked as discontinued and will no longer be associated with any category.</p>
    </div>
    
    <!-- Add Category Button -->
    <div class="mb-3">
      <a href="category_form.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Category
      </a>
    </div>
    
    <!-- Categories Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">All Categories</h3>
      </div>
      <div class="card-body">
        <table id="categories-table" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Image</th>
              <th>Name</th>
              <th>Description</th>
              <th>Products</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $category): ?>
              <tr <?= $category['name'] === 'Deleted Category' ? 'class="bg-light"' : '' ?>>
                <td><?= $category['id'] ?></td>
                <td>
                  <?php if (!empty($category['image'])): ?>
                    <img src="../uploads/categories/<?= $category['image'] ?>" alt="<?= htmlspecialchars($category['name']) ?>" height="50">
                  <?php else: ?>
                    <span class="badge bg-secondary">No image</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= htmlspecialchars($category['name']) ?>
                  <?php if ($category['name'] === 'Deleted Category'): ?>
                    <span class="badge badge-secondary">System Category</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php 
                    // Show truncated description
                    $desc = htmlspecialchars($category['description'] ?? '');
                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                  ?>
                </td>
                <td>
                  <span class="badge bg-info"><?= $category['product_count'] ?></span>
                </td>
                <td><?= $category['created_at'] ?></td>
                <td>
                  <?php if ($category['name'] === 'Deleted Category'): ?>
                    <button class="btn btn-sm btn-info" disabled>
                      <i class="fas fa-edit"></i> 
                    </button>
                    <button class="btn btn-sm btn-secondary" title="System categories cannot be deleted" disabled>
                      <i class="fas fa-trash"></i>
                    </button>
                  <?php else: ?>
                    <a href="category_form.php?id=<?= $category['id'] ?>" class="btn btn-sm btn-info">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="?delete=<?= $category['id'] ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this category? All products within this category will be marked as discontinued.')">
                      <i class="fas fa-trash"></i>
                    </a>
                  <?php endif; ?>
                  <a href="products.php?category=<?= $category['id'] ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-eye"></i> View Products
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
    $('#categories-table').DataTable();
  });
</script>

<?php require_once '../includes/admin_footer.php'; ?>