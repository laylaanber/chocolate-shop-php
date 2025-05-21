<?php
session_start();

// Check if this is a direct logout action
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to home page
    header("Location: ../index.php?logged_out=true");
    exit;
}

require_once '../includes/header.php';
?>

<!-- Page Banner -->
<div class="page-banner auth-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1>Sign Out</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active">Logout</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Logout Confirmation Section -->
<section class="auth-section">
    <div class="container">
        <div class="logout-container">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2>Are you sure you want to logout?</h2>
            <p>You will be securely signed out of your account</p>
            
            <div class="logout-actions">
                <a href="logout.php?confirm=yes" class="btn-logout-confirm">Yes, Sign Out</a>
                <a href="../index.php" class="btn-logout-cancel">No, Continue Shopping</a>
            </div>
        </div>
    </div>
</section>

<style>
    /* Logout page styling */
    .logout-container {
        max-width: 500px;
        background-color: white;
        padding: 60px 40px;
        text-align: center;
        border-radius: 10px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        margin: 0 auto;
    }
    
    .logout-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-color: rgba(209, 183, 138, 0.1);
        color: var(--accent-color);
        font-size: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
    }
    
    .logout-container h2 {
        font-family: var(--font-primary);
        font-size: 1.8rem;
        color: var(--primary-color);
        margin-bottom: 15px;
        font-weight: 500;
    }
    
    .logout-container p {
        color: var(--text-medium);
        margin-bottom: 30px;
    }
    
    .logout-actions {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .btn-logout-confirm, 
    .btn-logout-cancel {
        padding: 14px;
        border-radius: 5px;
        font-family: var(--font-secondary);
        font-size: 1rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-logout-confirm {
        background-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .btn-logout-confirm:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-logout-cancel {
        background-color: transparent;
        border: 1px solid rgba(209, 183, 138, 0.3);
        color: var(--text-medium);
    }
    
    .btn-logout-cancel:hover {
        background-color: #f9f9f9;
        color: var(--primary-color);
    }
    
    @media (max-width: 576px) {
        .logout-container {
            padding: 40px 25px;
        }
        
        .logout-icon {
            width: 70px;
            height: 70px;
            font-size: 1.6rem;
        }
        
        .logout-container h2 {
            font-size: 1.5rem;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>