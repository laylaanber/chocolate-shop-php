<?php
require_once '../config/database.php';
require_once '../includes/admin_header.php';

// Check if user is logged in and is a root admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' || !isRootAdmin($db, $_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Function to check if user is root admin
function isRootAdmin($db, $user_id) {
    $query = "SELECT is_root_admin FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && isset($result['is_root_admin']) && $result['is_root_admin'] == 1;
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle admin promotion/demotion
if (isset($_POST['toggle_admin'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot change your own role!";
    } else {
        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        try {
            $stmt->execute([$new_role, $user_id]);
            $message = "User role updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating role: " . $e->getMessage();
        }
    }
}

// Handle admin deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete yourself!";
    } else {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        
        try {
            $stmt->execute([$user_id]);
            $message = "User deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users excluding the current root admin
$query = "SELECT * FROM users WHERE id != ? ORDER BY role DESC, username ASC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Content Header -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Admin Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Admin Management</li>
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
        <h3 class="card-title">Manage Administrator Access</h3>
      </div>
      <div class="card-body">
        <div class="alert alert-info">
          <h5><i class="icon fas fa-info"></i> Root Admin Access</h5>
          <p>As a root administrator, you can promote regular users to admin status or demote existing admins. 
          Use this power wisely as admins have full access to the store management system.</p>
        </div>
        
        <table id="users-table" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                  <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge badge-danger">Administrator</span>
                  <?php else: ?>
                    <span class="badge badge-info">Regular User</span>
                  <?php endif; ?>
                </td>
                <td><?= $user['created_at'] ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <?php if ($user['role'] === 'admin'): ?>
                      <input type="hidden" name="new_role" value="user">
                      <button type="submit" name="toggle_admin" class="btn btn-warning btn-sm" 
                              onclick="return confirm('Are you sure you want to demote this admin to regular user?')">
                        <i class="fas fa-arrow-down"></i> Demote to User
                      </button>
                    <?php else: ?>
                      <input type="hidden" name="new_role" value="admin">
                      <button type="submit" name="toggle_admin" class="btn btn-success btn-sm"
                              onclick="return confirm('Are you sure you want to promote this user to admin? This will give them full access to the admin panel.')">
                        <i class="fas fa-arrow-up"></i> Promote to Admin
                      </button>
                    <?php endif; ?>
                  </form>
                  
                  <form method="post" class="d-inline">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm"
                            onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                      <i class="fas fa-trash"></i> Delete
                    </button>
                  </form>
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
    $('#users-table').DataTable();
  });
</script>

<?php require_once '../includes/admin_footer.php'; ?>