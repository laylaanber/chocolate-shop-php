<?php
// Only start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include helper functions
require_once dirname(__FILE__) . '/functions.php';

// Calculate cart count if cart exists
$cart_count = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chocolate Shop - Premium Artisan Chocolates</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Cormorant+Garamond:wght@300;400;500;600&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* La Maison du Chocolat inspired variables */
        :root {
            --primary-color: #3F2113;      /* Dark chocolate */
            --secondary-color: #85634D;     /* Milk chocolate */
            --accent-color: #D1B78A;        /* Gold accent */
            --text-dark: #1E1E1E;
            --text-medium: #5A5A5A;
            --text-light: #888888;
            --background-light: #FFFFFF;    /* White background */
            --background-beige: #F9F4EF;    /* Light beige for sections */
            --background-dark: #1E1E1E;     /* Dark background (matching footer) */
            --transition: all 0.3s ease;
            --font-primary: 'Playfair Display', serif;
            --font-secondary: 'Poppins', sans-serif;
            --font-elegant: 'Cormorant Garamond', serif;
            --header-height: 145px;         /* Combined height of top bar and main header */
            --top-bar-height: 40px;         /* Height of top bar */
        }
        
        body {
            color: var(--text-dark);
            background-color: var(--background-light);
            font-family: var(--font-secondary);
            font-weight: 300;
            line-height: 1.7;
            margin: 0;
            padding: 0;
            /* Added padding to account for fixed header */
            padding-top: var(--header-height);
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-primary);
            font-weight: 500;
            line-height: 1.3;
        }
        
        a {
            text-decoration: none;
            color: var(--text-dark);
            transition: var(--transition);
        }
        
        .container {
            padding: 0 30px;
        }
        
        /* Fixed Header styling */
        .header-wrapper {
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .header-wrapper.scroll-down {
            transform: translateY(-40px); /* Hide top bar when scrolling down */
        }
        
        /* Top Bar - Matching footer dark background */
        .top-bar {
            background-color: var(--background-dark);
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            padding: 8px 0;
            position: relative;
            height: var(--top-bar-height);
        }
        
        .top-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.05;
            pointer-events: none;
        }
        
        .top-bar::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, rgba(209,183,138,0) 0%, rgba(209,183,138,0.5) 50%, rgba(209,183,138,0) 100%);
        }
        
        .top-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar-contact {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .top-bar-contact-item {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .top-bar-contact-item i {
            margin-right: 10px;
            color: var(--accent-color);
            font-size: 0.9rem;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .top-bar-link {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            position: relative;
            padding-left: 15px;
            transition: all 0.3s ease;
        }
        
        .top-bar-link i {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-color);
        }
        
        .top-bar-link:hover {
            color: var(--accent-color);
        }
        
        /* Admin indicator */
        .admin-indicator {
            background-color: var(--accent-color);
            color: var(--primary-color);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 3px;
            margin-right: 15px;
            display: inline-flex;
            align-items: center;
        }
        
        .admin-indicator i {
            margin-right: 6px;
            font-size: 0.8rem;
        }
        
        /* Site Header */
        .site-header {
            position: relative;
            border-bottom: 1px solid rgba(209, 183, 138, 0.2);
            background-color: var(--background-light);
            position: relative;
            z-index: 100;
            box-shadow: 0 5px 10px rgba(0,0,0,0.05);
        }
        
        /* Main Navigation */
        .main-header {
            background-color: var(--background-light);
            padding: 0;
            position: relative;
        }
        
        .nav-container {
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            padding-right: 40px;
            position: relative;
        }
        
        .logo a {
            font-family: var(--font-primary);
            font-size: 1.8rem;
            font-weight: 500;
            color: var(--primary-color);
            letter-spacing: 1px;
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 40px;
            margin-right: 15px;
            filter: brightness(0.9);
        }
        
        .logo::after {
            content: '';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 30px;
            background-color: rgba(209, 183, 138, 0.3);
        }
        
        .main-nav {
            flex-grow: 1;
        }
        
        .nav-list {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 40px;
        }
        
        .nav-item a {
            font-size: 0.9rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary-color);
            position: relative;
            padding: 25px 0;
            display: block;
        }
        
        .nav-item a::after {
            content: '';
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: var(--transition);
        }
        
        .nav-item a:hover,
        .nav-item a.active {
            color: var(--accent-color);
        }
        
        .nav-item a:hover::after,
        .nav-item a.active::after {
            width: 100%;
        }
        
        /* Dropdown Menu - Matching footer style */
        .nav-item.has-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background-color: white;
            min-width: 220px;
            padding: 15px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
            border-top: 2px solid var(--accent-color);
        }
        
        .nav-item.has-dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: block;
            padding: 10px 20px;
            font-size: 0.85rem;
            color: var(--text-medium);
            transition: all 0.2s ease;
            position: relative;
            padding-left: 35px;
        }
        
        .dropdown-item::before {
            content: '›';
            position: absolute;
            left: 20px;
            color: var(--accent-color);
            transition: transform 0.3s ease;
        }
        
        .dropdown-item:hover {
            color: var(--accent-color);
            background-color: var(--background-beige);
        }
        
        .dropdown-item:hover::before {
            transform: translateX(3px);
        }
        
        /* Header Actions - Matching footer social links */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-action {
            color: var(--primary-color);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(209, 183, 138, 0.1);
            border-radius: 50%;
            transition: var(--transition);
            font-size: 0.9rem;
            position: relative;
        }
        
        .header-action:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--primary-color);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            cursor: pointer;
            margin-left: auto;
            width: 40px;
            height: 40px;
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .menu-toggle:hover {
            background-color: rgba(209, 183, 138, 0.1);
        }
        
        /* Search Form - Matching footer newsletter */
        .search-form-container {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background-color: var(--background-beige);
            padding: 20px 0;
            box-shadow: 0 5px 10px rgba(0,0,0,0.05);
            display: none;
            z-index: 100;
            border-top: 1px solid rgba(209, 183, 138, 0.2);
            border-bottom: 1px solid rgba(209, 183, 138, 0.2);
        }
        
        .search-form {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            position: relative;
        }
        
        .search-input {
            flex: 1;
            height: 48px;
            padding: 10px 20px;
            border: 1px solid rgba(209, 183, 138, 0.3);
            background-color: white;
            color: var(--text-dark);
            border-radius: 4px 0 0 4px;
            font-family: var(--font-secondary);
            font-size: 0.9rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .search-btn {
            background-color: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0 25px;
            height: 48px;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background-color: #c2a677;
        }
        
        .search-form-active {
            display: block;
        }
        
        /* Responsive styles */
        @media (max-width: 991px) {
            :root {
                --header-height: 110px;
                --top-bar-height: 40px;
            }
            
            .header-wrapper.scroll-down {
                transform: translateY(-40px);
            }
            
            .nav-container {
                justify-content: space-between;
                padding: 15px 0;
            }
            
            .logo {
                padding-right: 0;
            }
            
            .logo::after {
                display: none;
            }
            
            .menu-toggle {
                display: flex;
                z-index: 1001;
            }
            
            .main-nav {
                position: fixed;
                top: 0;
                right: -300px;
                width: 300px;
                height: 100vh;
                background-color: white;
                padding: 80px 20px 30px;
                z-index: 1000;
                transition: right 0.3s ease;
                box-shadow: -10px 0 30px rgba(0,0,0,0.1);
                overflow-y: auto;
            }
            
            .main-nav.active {
                right: 0;
            }
            
            .nav-list {
                flex-direction: column;
                gap: 0;
            }
            
            .nav-item a {
                padding: 15px 0;
                border-bottom: 1px solid rgba(209, 183, 138, 0.2);
            }
            
            .nav-item a::after {
                display: none;
            }
            
            .dropdown-menu {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                box-shadow: none;
                border-top: none;
                padding: 0 0 0 15px;
                display: none;
            }
            
            .dropdown-menu.show {
                display: block;
            }
            
            .dropdown-item {
                padding: 12px 15px 12px 30px;
                border-bottom: 1px solid rgba(209, 183, 138, 0.1);
            }
            
            .dropdown-item::before {
                left: 15px;
            }
            
            .menu-toggle.active i::before {
                content: '\f00d'; /* Replace with X icon */
            }
            
            .mobile-menu-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                z-index: 999;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease;
            }
            
            .mobile-menu-backdrop.active {
                opacity: 1;
                visibility: visible;
            }
        }
        
        @media (max-width: 767px) {
            :root {
                --header-height: 150px;
                --top-bar-height: 80px;
            }
            
            .header-wrapper.scroll-down {
                transform: translateY(-80px);
            }
            
            .top-bar {
                height: auto;
                padding: 10px 0;
            }
            
            .top-bar-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .top-bar-contact {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            
            .top-bar-actions {
                justify-content: center;
            }
            
            .logo a {
                font-size: 1.5rem;
            }
            
            .logo img {
                height: 30px;
            }
            
            .header-actions {
                gap: 10px;
            }
        }
        
        @media (max-width: 575px) {
            :root {
                --header-height: 160px;
                --top-bar-height: 90px;
            }
            
            .header-wrapper.scroll-down {
                transform: translateY(-90px);
            }
        }

        /* No-scroll utility */
        .no-scroll {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Fixed Header Wrapper -->
    <div class="header-wrapper" id="headerWrapper">
        <!-- Top Bar - Now matching footer dark style -->
        <div class="top-bar">
            <div class="container">
                <div class="top-bar-content">
                    <div class="top-bar-contact">
                        <div class="top-bar-contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>123 Chocolate Avenue, Sweet City</span>
                        </div>
                        <div class="top-bar-contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>info@chocolateshop.com</span>
                        </div>
                        <div class="top-bar-contact-item">
                            <i class="fas fa-phone"></i>
                            <span>(123) 456-7890</span>
                        </div>
                    </div>
                    <div class="top-bar-actions">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <a href="/php/chocolate-shop/admin/dashboard.php" class="admin-indicator">
                                    <i class="fas fa-crown"></i> Admin Panel
                                </a>
                            <?php endif; ?>
                            <a href="/php/chocolate-shop/account.php" class="top-bar-link">
                                <i class="fas fa-user"></i> My Account
                            </a>
                            <a href="/php/chocolate-shop/auth/logout.php" class="top-bar-link">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        <?php else: ?>
                            <a href="/php/chocolate-shop/auth/login.php" class="top-bar-link">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            <a href="/php/chocolate-shop/auth/register.php" class="top-bar-link">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Header -->
        <header class="site-header">
            <div class="main-header">
                <div class="container">
                    <div class="nav-container">
                        <!-- Logo link -->
                        <div class="logo">
                            <a href="/php/chocolate-shop/index.php">
                                <img src="https://static.vecteezy.com/system/resources/previews/032/749/138/non_2x/organic-chocolate-or-cacao-fruit-logo-template-design-isolated-background-free-vector.jpg" alt="Chocolate Shop Logo">
                                <span>Chocolate Shop</span>
                            </a>
                        </div>
                        
                        <button id="menuToggle" class="menu-toggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <!-- Navigation links -->
                        <nav class="main-nav" id="mainNav">
                            <ul class="nav-list">
                                <li class="nav-item">
                                    <a href="/php/chocolate-shop/index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a>
                                </li>
                                <li class="nav-item">
                                    <a href="/php/chocolate-shop/products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">Shop</a>
                                </li>
                                
                                </li>
                                <li class="nav-item">
                                    <a href="/php/chocolate-shop/our-brand.php" class="<?= basename($_SERVER['PHP_SELF']) == 'our-brand.php' ? 'active' : '' ?>">Our Brand</a>
                                </li>
                                <li class="nav-item">
                                    <a href="/php/chocolate-shop/contact.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">Contact</a>
                                </li>
                            </ul>
                        </nav>
                        
                        <div class="header-actions">
                            <a href="javascript:void(0)" id="searchToggle" class="header-action">
                                <i class="fas fa-search"></i>
                            </a>
                            <a href="/php/chocolate-shop/cart.php" class="header-action">
                                <i class="fas fa-shopping-bag"></i>
                                <?php if ($cart_count > 0): ?>
                                    <span class="cart-count"><?= $cart_count ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="search-form-container" id="searchForm">
                <div class="container">
                    <form action="/php/chocolate-shop/products.php" method="get" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Search for products...">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mobile menu backdrop -->
            <div class="mobile-menu-backdrop" id="mobileMenuBackdrop"></div>
        </header>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle fixed header behavior on scroll
            const headerWrapper = document.getElementById('headerWrapper');
            let lastScrollTop = 0;
            
            window.addEventListener('scroll', function() {
                let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                
                // Only hide the top bar when scroll is past a certain point (e.g., 300px)
                if (currentScroll > 300) {
                    if (currentScroll > lastScrollTop) {
                        // Scrolling down - hide top bar
                        headerWrapper.classList.add('scroll-down');
                    } else {
                        // Scrolling up - show full header
                        headerWrapper.classList.remove('scroll-down');
                    }
                }
                
                lastScrollTop = currentScroll <= 0 ? 0 : currentScroll; // For Mobile or negative scrolling
            }, false);
            
            // Toggle mobile menu
            const menuToggle = document.getElementById('menuToggle');
            const mainNav = document.getElementById('mainNav');
            const menuBackdrop = document.getElementById('mobileMenuBackdrop');
            
            if (menuToggle && mainNav && menuBackdrop) {
                menuToggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                    mainNav.classList.toggle('active');
                    menuBackdrop.classList.toggle('active');
                    document.body.classList.toggle('no-scroll');
                });
                
                menuBackdrop.addEventListener('click', function() {
                    menuToggle.classList.remove('active');
                    mainNav.classList.remove('active');
                    this.classList.remove('active');
                    document.body.classList.remove('no-scroll');
                });
            }
            
            // Toggle dropdown menus on mobile
            const dropdownItems = document.querySelectorAll('.has-dropdown > a');
            
            dropdownItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (window.innerWidth <= 991) {
                        e.preventDefault();
                        const dropdown = this.nextElementSibling;
                        dropdown.classList.toggle('show');
                    }
                });
            });
            
            // Toggle search form
            const searchToggle = document.getElementById('searchToggle');
            const searchForm = document.getElementById('searchForm');
            
            if (searchToggle && searchForm) {
                searchToggle.addEventListener('click', function() {
                    searchForm.classList.toggle('search-form-active');
                    searchForm.querySelector('input').focus();
                });
            }
        });
    </script>
</body>
</html>