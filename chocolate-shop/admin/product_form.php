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
$product = [
    'id' => '',
    'category_id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
    'image' => '',
    'is_featured' => 0,
    'is_active' => 'available'
];

// Check if we're editing an existing product
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = $_GET['id'];
    
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    
    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Product not found!";
    }
}

// Get all categories for the dropdown
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $description = trim($_POST['description']);
    $price = (float) $_POST['price'];
    $stock = (int) $_POST['stock'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = $_POST['is_active'];
    
    // Validate input
    if (empty($name) || $price <= 0) {
        $error = "Product name and price are required!";
    } else {
        // Handle image upload
        $image = $product['image']; // Keep existing image by default
        
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = "../uploads/products/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $image_name = time() . '_' . $_FILES['image']['name'];
            $target_file = $upload_dir . $image_name;
            $image_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            // Validate image
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($image_type, $allowed_types)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // Delete old image if exists and we're updating
                    if (!empty($product['id']) && !empty($product['image'])) {
                        $old_image_path = $upload_dir . $product['image'];
                        if (file_exists($old_image_path)) {
                            unlink($old_image_path);
                        }
                    }
                    $image = $image_name;
                } else {
                    $error = "Failed to upload image!";
                }
            } else if (!empty($_FILES['image']['name'])) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed!";
            }
        }
        
        if (empty($error)) {
            try {
                // Update or insert product
                if (!empty($product['id'])) {
                    // Update existing product
                    $query = "UPDATE products SET 
                            category_id = ?, name = ?, description = ?, 
                            price = ?, stock = ?, image = ?, 
                            is_featured = ?, is_active = ? 
                            WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $category_id, $name, $description,
                        $price, $stock, $image,
                        $is_featured, $is_active,
                        $product['id']
                    ]);
                    $message = "Product updated successfully!";
                } else {
                    // Insert new product
                    $query = "INSERT INTO products 
                            (category_id, name, description, price, stock, image, is_featured, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $category_id, $name, $description,
                        $price, $stock, $image,
                        $is_featured, $is_active
                    ]);
                    $message = "Product added successfully!";
                    // Reset form after successful addition
                    $product = [
                        'id' => '',
                        'category_id' => '',
                        'name' => '',
                        'description' => '',
                        'price' => '',
                        'stock' => '',
                        'image' => '',
                        'is_featured' => 0,
                        'is_active' => 'available'
                    ];
                }
            } catch (PDOException $e) {
                $error = "Error saving product: " . $e->getMessage();
            }
        }
    }
}
?>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0"><?= empty($product['id']) ? 'Add New' : 'Edit' ?> Product</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="products.php">Products</a></li>
          <li class="breadcrumb-item active"><?= empty($product['id']) ? 'Add New' : 'Edit' ?> Product</li>
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
    
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= empty($product['id']) ? 'Add New' : 'Edit' ?> Product</h3>
      </div>
      <div class="card-body">
        <form action="" method="post" enctype="multipart/form-data">
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="name">Product Name*</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
              </div>
              
              <div class="form-group">
                <label for="category_id">Category</label>
                <select class="form-control" id="category_id" name="category_id">
                  <option value="">-- Select Category --</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($category['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="5"><?= htmlspecialchars($product['description']) ?></textarea>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="form-group">
                <label for="price">Price ($)*</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required>
              </div>
              
              <div class="form-group">
                <label for="stock">Stock</label>
                <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?= $product['stock'] ?>">
              </div>
              
              <div class="form-group">
                <label for="image">Product Image</label>
                <input type="file" class="form-control-file" id="image" name="image">
                <?php if (!empty($product['image'])): ?>
                  <div class="mt-2">
                    <label>Current Image:</label>
                    <img src="../uploads/products/<?= $product['image'] ?>" alt="Product Image" class="img-fluid mt-2" style="max-height: 150px;">
                  </div>
                <?php endif; ?>
              </div>
              
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?= $product['is_featured'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_featured">Featured Product</label>
              </div>
              
              <div class="form-group">
                <label for="is_active">Product Status</label>
                <select class="form-control" id="is_active" name="is_active">
                  <option value="available" <?= $product['is_active'] == 'available' ? 'selected' : '' ?>>Available</option>
                  <option value="out_of_stock" <?= $product['is_active'] == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                  <option value="discontinued" <?= $product['is_active'] == 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
                  <option value="coming_soon" <?= $product['is_active'] == 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
                </select>
                <small class="form-text text-muted">Set the current availability status of this product</small>
              </div>
            </div>
          </div>
          
          <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="products.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/admin_footer.php'; ?>