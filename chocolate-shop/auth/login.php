<?php
require_once '../config/database.php';

// Start the session if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$error = "";
$username = "";
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Process form data BEFORE including header
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Both username and password are required";
    } else {
        // Check if user exists
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $username]); // Allow login with either username or email
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect based on role or specified redirect
                if ($user['role'] === 'admin' || $user['role'] === 'root_admin') {
                    header("Location: ../admin/dashboard.php");
                } else if ($redirect === 'cart') {
                    header("Location: ../cart.php");
                } else {
                    header("Location: ../index.php");
                }
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}

// Include header AFTER processing form and potential redirects
require_once '../includes/header.php';
?>

<!-- Add a login message for users who were redirected from cart -->
<?php if ($redirect === 'cart'): ?>
    <div class="login-redirect-message">
        <div class="container">
            <div class="message-content">
                <i class="fas fa-info-circle"></i>
                <p>Please log in or create an account to add items to your shopping cart</p>
            </div>
        </div>
    </div>
    
    <style>
        .login-redirect-message {
            background-color: var(--background-beige);
            padding: 15px 0;
            border-bottom: 1px solid rgba(209, 183, 138, 0.3);
        }
        
        .message-content {
            display: flex;
            align-items: center;
            color: var(--primary-color);
        }
        
        .message-content i {
            margin-right: 10px;
            color: var(--accent-color);
            font-size: 1.2rem;
        }
        
        .message-content p {
            margin: 0;
        }
    </style>
<?php endif; ?>

<!-- Page Banner -->
<div class="page-banner auth-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1>Welcome Back</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active">Login</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Login Form Section -->
<section class="auth-section">
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-content">
                <div class="auth-header">
                    <div class="auth-logo">
                        <img src="https://static.vecteezy.com/system/resources/previews/032/749/138/non_2x/organic-chocolate-or-cacao-fruit-logo-template-design-isolated-background-free-vector.jpg" alt="Chocolate Shop Logo">
                    </div>
                    <h2>Sign In</h2>
                    <p>Please enter your credentials to access your account</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="auth-alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="auth-form">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="password-toggle">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn-auth-submit">Sign In</button>
                </form>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Create Account</a></p>
                </div>
            </div>
            
            <div class="auth-image">
                <img src="https://st3.depositphotos.com/1055085/18194/i/450/depositphotos_181943772-stock-photo-handmade-dark-chocolates-iii.jpg" alt="Luxury Chocolate">
                <div class="image-overlay"></div>
                <div class="auth-quote">
                    <blockquote>
                        "Life is like a box of chocolates. You never know what you're going to get."
                    </blockquote>
                    <cite>- Forrest Gump</cite>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Luxury Auth Pages Styling */
    .auth-banner {
        background: url('https://images.unsplash.com/photo-1511381939415-e44015466834?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') center/cover no-repeat fixed;
        height: 300px;
        position: relative;
        background-attachment: fixed;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-align: center;
    }
    
    .auth-banner .banner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to bottom, 
                    rgba(45, 25, 15, 0.85), 
                    rgba(70, 35, 20, 0.9));
    }
    
    .auth-banner h1 {
        font-size: 3rem;
        font-weight: 300;
        margin-bottom: 1rem;
        position: relative;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    
    .auth-section {
        padding: 80px 0;
        background-color: var(--background-beige);
        min-height: 70vh;
        display: flex;
        align-items: center;
    }
    
    .auth-wrapper {
        background-color: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        display: flex;
        margin: 0 auto;
        max-width: 1000px;
    }
    
    .auth-content {
        flex: 1;
        padding: 50px;
    }
    
    .auth-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .auth-logo {
        display: inline-block;
        margin-bottom: 20px;
    }
    
    .auth-logo img {
        height: 60px;
    }
    
    .auth-header h2 {
        font-family: var(--font-primary);
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .auth-header p {
        color: var(--text-medium);
        font-size: 0.95rem;
    }
    
    .auth-alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        font-size: 0.95rem;
    }
    
    .auth-alert.error {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border-left: 4px solid #dc3545;
    }
    
    .auth-alert.success {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border-left: 4px solid #28a745;
    }
    
    .auth-alert i {
        margin-right: 10px;
        font-size: 1.1rem;
    }
    
    .auth-form .form-group {
        margin-bottom: 25px;
    }
    
    .auth-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--primary-color);
        font-size: 0.95rem;
    }
    
    .input-icon-wrapper {
        position: relative;
    }
    
    .input-icon-wrapper i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }
    
    .input-icon-wrapper input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 1px solid rgba(209, 183, 138, 0.3);
        border-radius: 5px;
        font-family: inherit;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    
    .input-icon-wrapper input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(209, 183, 138, 0.2);
    }
    
    .password-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-light);
        transition: color 0.3s ease;
    }
    
    .password-toggle:hover {
        color: var(--accent-color);
    }
    
    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        font-size: 0.9rem;
    }
    
    .remember-me {
        display: flex;
        align-items: center;
    }
    
    .remember-me input {
        margin-right: 8px;
    }
    
    .forgot-password {
        color: var(--accent-color);
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .forgot-password:hover {
        color: var(--primary-color);
        text-decoration: underline;
    }
    
    .btn-auth-submit {
        width: 100%;
        padding: 14px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        border: none;
        border-radius: 5px;
        font-family: var(--font-secondary);
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn-auth-submit:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .auth-footer {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid rgba(209, 183, 138, 0.2);
        font-size: 0.95rem;
        color: var(--text-medium);
    }
    
    .auth-footer a {
        color: var(--accent-color);
        font-weight: 500;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .auth-footer a:hover {
        color: var(--primary-color);
        text-decoration: underline;
    }
    
    .auth-image {
        width: 450px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .auth-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to right, 
                    rgba(45, 25, 15, 0.8), 
                    rgba(70, 35, 20, 0.7));
    }
    
    .auth-quote {
        position: absolute;
        color: white;
        text-align: center;
        padding: 0 40px;
        z-index: 2;
    }
    
    .auth-quote blockquote {
        font-family: var(--font-primary);
        font-size: 1.7rem;
        font-style: italic;
        line-height: 1.5;
        margin-bottom: 15px;
    }
    
    .auth-quote cite {
        font-style: normal;
        font-size: 1rem;
        color: var(--accent-color);
    }
    
    /* Responsive adjustments */
    @media (max-width: 991px) {
        .auth-wrapper {
            flex-direction: column;
            max-width: 600px;
        }
        
        .auth-content {
            width: 100%;
            padding: 40px 30px;
        }
        
        .auth-image {
            width: 100%;
            height: 200px;
            order: -1;
        }
        
        .auth-quote blockquote {
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 576px) {
        .auth-banner h1 {
            font-size: 2.2rem;
        }
        
        .auth-content {
            padding: 30px 20px;
        }
        
        .auth-header h2 {
            font-size: 1.8rem;
        }
        
        .auth-section {
            padding: 50px 0;
        }
        
        .form-options {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const passwordToggle = document.querySelector('.password-toggle');
    const passwordInput = document.getElementById('password');
    
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Change icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>