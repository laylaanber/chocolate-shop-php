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
$category = [
    'id' => '',
    'name' => '',
    'description' => '',
    'image' => ''
];

// Check if we're editing an existing category
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $category_id = $_GET['id'];
    
    $query = "SELECT * FROM categories WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$category_id]);
    
    if ($stmt->rowCount() > 0) {
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error = "Category not found!";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($name)) {
        $error = "Category name is required!";
    } else {
        // Handle image upload
        $image = $category['image']; // Keep existing image by default
        
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = "../uploads/categories/";
            
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
                    if (!empty($category['id']) && !empty($category['image'])) {
                        $old_image_path = $upload_dir . $category['image'];
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
                // Update or insert category
                if (!empty($category['id'])) {
                    // Update existing category
                    $query = "UPDATE categories SET 
                            name = ?, description = ?, image = ?
                            WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $name, $description, $image,
                        $category['id']
                    ]);
                    $message = "Category updated successfully!";
                } else {
                    // Insert new category
                    $query = "INSERT INTO categories 
                            (name, description, image) 
                            VALUES (?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $name, $description, $image
                    ]);
                    $message = "Category added successfully!";
                    
                    // Reset form after successful addition
                    $category = [
                        'id' => '',
                        'name' => '',
                        'description' => '',
                        'image' => ''
                    ];
                }
            } catch (PDOException $e) {
                $error = "Error saving category: " . $e->getMessage();
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
        <h1 class="m-0"><?= empty($category['id']) ? 'Add New' : 'Edit' ?> Category</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
          <li class="breadcrumb-item active"><?= empty($category['id']) ? 'Add New' : 'Edit' ?> Category</li>
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
        <h3 class="card-title"><?= empty($category['id']) ? 'Add New' : 'Edit' ?> Category</h3>
      </div>
      <div class="card-body">
        <form action="" method="post" enctype="multipart/form-data">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="name">Category Name*</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
              </div>
              
              <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                <small class="form-text text-muted">Provide a description of this category to help customers understand what products they'll find here.</small>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="form-group">
                <label for="image">Category Image</label>
                <input type="file" class="form-control-file" id="image" name="image">
                <small class="form-text text-muted">Recommended size: 300x300px</small>
                
                <?php if (!empty($category['image'])): ?>
                  <div class="mt-3">
                    <label>Current Image:</label>
                    <div class="mt-2">
                      <img src="../uploads/categories/<?= $category['image'] ?>" alt="Category Image" class="img-fluid" style="max-height: 200px;">
                    </div>
                  </div>
                <?php endif; ?>
              </div>
              
              <?php if (!empty($category['id'])): ?>
                <div class="form-group">
                  <label>Products in this category:</label>
                  <?php
                    // Get count of products in this category
                    $prod_query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
                    $prod_stmt = $db->prepare($prod_query);
                    $prod_stmt->execute([$category['id']]);
                    $product_count = $prod_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                  ?>
                  <div>
                    <span class="badge badge-info"><?= $product_count ?> products</span>
                    <?php if ($product_count > 0): ?>
                      <a href="products.php?category=<?= $category['id'] ?>" class="btn btn-sm btn-outline-secondary">View Products</a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Category</button>
            <a href="categories.php" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/admin_footer.php'; ?>