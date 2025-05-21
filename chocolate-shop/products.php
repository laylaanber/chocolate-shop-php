<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle filtering and pagination
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_term = isset($_GET['search']) ? $_GET['search'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 9; // Changed to 9 for better grid layout (3x3)
$offset = ($page - 1) * $items_per_page;

// Base query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_active = 'available'";
$params = [];

// Add category filter
if ($category_id) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
    
    // Get category name for title
    $cat_query = "SELECT name, description FROM categories WHERE id = ?";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute([$category_id]);
    $category_info = $cat_stmt->fetch(PDO::FETCH_ASSOC);
    $category_name = $category_info['name'] ?? '';
    $category_description = $category_info['description'] ?? '';
}

// Add search filter
if ($search_term) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    case 'newest':
        $query .= " ORDER BY p.created_at DESC";
        break;
    case 'name_asc':
    default:
        $query .= " ORDER BY p.name ASC";
        break;
}

// Count total products for pagination
$count_query = "SELECT COUNT(*) FROM products p WHERE p.is_active = 'available'";
$count_params = [];

if ($category_id) {
    $count_query .= " AND p.category_id = ?";
    $count_params[] = $category_id;
}

if ($search_term) {
    $count_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_term%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $items_per_page);

// Add pagination limit
$query .= " LIMIT $offset, $items_per_page";

// Get products
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter sidebar
$categories_query = "SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 'available'
                    GROUP BY c.id 
                    ORDER BY c.name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$page_title = 'Our Collection';
if ($category_id && isset($category_name)) {
    $page_title = htmlspecialchars($category_name);
} elseif ($search_term) {
    $page_title = 'Search Results: ' . htmlspecialchars($search_term);
}
?>

<!-- Elegant Banner with Parallax Effect -->
<div class="page-banner products-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1><?= $page_title ?></h1>
        <?php if (isset($category_description) && !empty($category_description)): ?>
            <p class="category-description"><?= htmlspecialchars($category_description) ?></p>
        <?php elseif (!$category_id && !$search_term): ?>
            <p class="category-description">Discover our artisanal chocolate creations, crafted with the finest ingredients and meticulous attention to detail</p>
        <?php endif; ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Collection</a></li>
                <?php if ($category_id && isset($category_name)): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($category_name) ?></li>
                <?php elseif ($search_term): ?>
                    <li class="breadcrumb-item active" aria-current="page">Search</li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
</div>

<!-- Products Section -->
<section class="products-section">
    <div class="container">
        <div class="row">
            <!-- Fixed Filter Sidebar -->
            <div class="col-lg-3 sidebar-container">
                <div class="product-filters sticky-sidebar">
                    <?php if (!empty($search_term)): ?>
                        <div class="search-results-info mb-4">
                            <h3 class="filter-heading">Search Results</h3>
                            <p>Showing results for <strong>"<?= htmlspecialchars($search_term) ?>"</strong></p>
                            <p class="result-count"><?= $total_products ?> <?= $total_products === 1 ? 'product' : 'products' ?> found</p>
                            <a href="products.php" class="btn-clear-search">Clear Search</a>
                        </div>
                    <?php endif; ?>

                    <div class="filter-section">
                        <h3 class="filter-heading">Categories</h3>
                        <ul class="category-list">
                            <li>
                                <a href="products.php" class="<?= !$category_id ? 'active' : '' ?>">
                                    All Collections
                                    <span class="count"><?= $total_products ?></span>
                                </a>
                            </li>
                            <?php foreach($categories as $category): ?>
                                <?php if ($category['product_count'] > 0): ?>
                                    <li>
                                        <a href="products.php?category=<?= $category['id'] ?>" class="<?= $category_id == $category['id'] ? 'active' : '' ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                            <span class="count"><?= $category['product_count'] ?></span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="filter-section">
                        <h3 class="filter-heading">Sort By</h3>
                        <form id="sortForm">
                            <?php if ($category_id): ?>
                                <input type="hidden" name="category" value="<?= $category_id ?>">
                            <?php endif; ?>
                            <?php if ($search_term): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                            <?php endif; ?>
                            <select name="sort" id="sort" class="form-select" onchange="this.form.submit()">
                                <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                                <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                            </select>
                        </form>
                    </div>
                    
                    <div class="filter-section product-tags">
                        <h3 class="filter-heading">Popular Tags</h3>
                        <div class="tag-cloud">
                            <a href="products.php?search=dark" class="tag">Dark Chocolate</a>
                            <a href="products.php?search=milk" class="tag">Milk Chocolate</a>
                            <a href="products.php?search=truffle" class="tag">Truffles</a>
                            <a href="products.php?search=gift" class="tag">Gift Sets</a>
                            <a href="products.php?search=assorted" class="tag">Assorted</a>
                            <a href="products.php?search=praline" class="tag">Pralines</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Elegant Product Grid -->
            <div class="col-lg-9">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <div class="no-products-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3>No Products Found</h3>
                        <p>We couldn't find any products matching your criteria.</p>
                        <a href="products.php" class="btn-outline">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="products-header">
                        <p class="products-count">Showing <?= min($items_per_page, count($products)) ?> of <?= $total_products ?> products</p>
                        <div class="view-options">
                            <button class="view-option grid active" data-view="grid">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button class="view-option list" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                
                    <div class="product-grid view-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <a href="product-detail.php?id=<?= $product['id'] ?>">
                                        <img src="uploads/products/<?= $product['image'] ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.src='https://images.unsplash.com/photo-1549007994-cb92caebd54b?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'">
                                    </a>
                                    <div class="product-actions">
                                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="product-action view" title="Quick View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="product-action cart add-to-cart-btn" data-product-id="<?= $product['id'] ?>" title="Add to Cart">
                                            <i class="fas fa-shopping-bag"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-content">
                                    <span class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Artisanal') ?></span>
                                    <h3 class="product-title">
                                        <a href="product-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                                    </h3>
                                    <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                                    <div class="product-description">
                                        <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                                    </div>
                                    <div class="product-button">
                                        <button class="btn-add-to-cart add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Elegant Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="elegant-pagination">
                            <div class="pagination-info">
                                Page <?= $page ?> of <?= $total_pages ?>
                            </div>
                            
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-arrow prev">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="pagination-numbers">
                                    <?php
                                    // Display limited page numbers with ellipsis
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    // Always show first page
                                    if ($start_page > 1) {
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="page-number">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="ellipsis">...</span>';
                                        }
                                    }
                                    
                                    // Display page numbers
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $active_class = ($i == $page) ? 'active' : '';
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="page-number ' . $active_class . '">' . $i . '</a>';
                                    }
                                    
                                    // Always show last page
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="ellipsis">...</span>';
                                        }
                                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="page-number">' . $total_pages . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-arrow next">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Elegant Chocolate Quote Section -->
<section class="chocolate-quote">
    <div class="container">
        <div class="quote-container">
            <div class="quote-marks">"</div>
            <blockquote>Chocolate is happiness that you can eat. It's not just a flavor; it's an experience of pure indulgence.</blockquote>
            <div class="quote-author">— Pierre Laurent, Master Chocolatier</div>
        </div>
    </div>
</section>

<!-- Luxurious styling for products page -->
<style>
    /* Enhanced Products Page Styling */
    
    /* Banner styling with parallax - Updated to match exactly with "Our Brand" page */
    .products-banner {
        background: url('https://images.unsplash.com/photo-1511381939415-e44015466834?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') center/cover no-repeat fixed;
        height: 400px; /* Increased height to match our-brand.php */
        background-attachment: fixed;
        position: relative;
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-align: center;
        margin-bottom: 0;
    }
    
    .banner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        /* Exact gradient from our-brand.php */
        background: linear-gradient(to bottom, 
                    rgba(45, 25, 15, 0.85), 
                    rgba(70, 35, 20, 0.9));
    }
    
    .products-banner h1 {
        font-size: 4rem; /* Larger font size to match our-brand.php */
        font-weight: 300;
        margin-bottom: 1.2rem;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        color: #fff;
        /* Add subtle animation on page load */
        animation: fadeInDown 1.2s ease-out;
        position: relative;
    }
    
    .category-description {
        max-width: 700px;
        margin: 0 auto 30px;
        font-size: 1.4rem; /* Increased font size */
        font-style: italic;
        font-family: var(--font-elegant);
        position: relative;
        text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        color: var(--accent-color);
        animation: fadeInUp 1.2s ease-out 0.3s both;
    }
    
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Sticky sidebar container */
    .sidebar-container {
        position: relative;
    }
    
    .sticky-sidebar {
        position: sticky;
        top: calc(var(--header-height) + 20px);
        max-height: calc(100vh - var(--header-height) - 40px);
        overflow-y: auto;
        transition: top 0.2s ease;
        background-color: white;
        padding: 30px;
        margin-bottom: 30px;
        border: 1px solid rgba(209, 183, 138, 0.2);
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    }
    
    /* Custom scrollbar for the sidebar */
    .sticky-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sticky-sidebar::-webkit-scrollbar-track {
        background: rgba(209, 183, 138, 0.1);
        border-radius: 3px;
    }
    
    .sticky-sidebar::-webkit-scrollbar-thumb {
        background: var(--accent-color);
        border-radius: 3px;
    }
    
    .sticky-sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--primary-color);
    }
    
    /* Hide scrollbar for Firefox */
    .sticky-sidebar {
        scrollbar-width: thin;
        scrollbar-color: var(--accent-color) rgba(209, 183, 138, 0.1);
    }
    
    /* Add some padding at the bottom of the last filter section for better spacing */
    .filter-section:last-child {
        padding-bottom: 10px;
    }
    
    /* Ensure the sidebar doesn't get too tall */
    @media (min-height: 900px) {
        .sticky-sidebar {
            max-height: 800px;
        }
    }
    
    /* Responsive adjustments for the sticky sidebar */
    @media (max-width: 991px) {
        .sticky-sidebar {
            position: static;
            max-height: none;
            overflow-y: visible;
        }
    }
    
    /* Elegant Filter Sidebar */
    .product-filters {
        background-color: white;
        padding: 30px;
        margin-bottom: 30px;
        border: 1px solid rgba(209, 183, 138, 0.2);
        box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    }
    
    .filter-section {
        margin-bottom: 40px;
        position: relative;
    }
    
    .filter-section:last-child {
        margin-bottom: 0;
    }
    
    .filter-section::after {
        content: '';
        position: absolute;
        bottom: -20px;
        left: 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(to right, rgba(209,183,138,0.3) 0%, rgba(209,183,138,0) 100%);
    }
    
    .filter-section:last-child::after {
        display: none;
    }
    
    .filter-heading {
        font-size: 1.1rem;
        margin-bottom: 20px;
        color: var(--primary-color);
        position: relative;
        padding-bottom: 10px;
        font-family: var(--font-primary);
    }
    
    .filter-heading::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 30px;
        height: 2px;
        background-color: var(--accent-color);
    }
    
    .category-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .category-list li {
        margin-bottom: 12px;
    }
    
    .category-list a {
        color: var(--text-medium);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .category-list a:hover, 
    .category-list a.active {
        color: var(--accent-color);
        transform: translateX(5px);
    }
    
    .category-list a.active {
        font-weight: 500;
    }
    
    .category-list .count {
        background-color: rgba(209, 183, 138, 0.1);
        color: var(--primary-color);
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .category-list a:hover .count,
    .category-list a.active .count {
        background-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .form-select {
        border: 1px solid rgba(209, 183, 138, 0.3);
        padding: 10px 15px;
        color: var(--text-medium);
        font-size: 0.95rem;
        background-color: white;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23D1B78A' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
    }
    
    .form-select:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 0.25rem rgba(209, 183, 138, 0.25);
    }
    
    /* Tag cloud */
    .tag-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .tag {
        background-color: rgba(209, 183, 138, 0.1);
        color: var(--text-medium);
        font-size: 0.8rem;
        padding: 5px 12px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    
    .tag:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
        transform: translateY(-2px);
    }
    
    /* Search results */
    .search-results-info {
        margin-bottom: 30px;
    }
    
    .result-count {
        color: var(--accent-color);
        font-weight: 500;
        margin: 5px 0 15px;
    }
    
    .btn-clear-search {
        display: inline-block;
        font-size: 0.85rem;
        color: var(--text-medium);
        border: 1px solid rgba(209, 183, 138, 0.3);
        padding: 5px 15px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    
    .btn-clear-search:hover {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    /* Products header */
    .products-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(209, 183, 138, 0.2);
    }
    
    .products-count {
        color: var(--text-medium);
        margin: 0;
        font-size: 0.95rem;
    }
    
    .view-options {
        display: flex;
        gap: 10px;
    }
    
    .view-option {
        background: none;
        border: none;
        color: var(--text-light);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .view-option:hover {
        color: var(--primary-color);
    }
    
    .view-option.active {
        color: var(--accent-color);
    }
    
    /* Elegant product grid */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 30px;
    }
    
    .product-grid.view-list {
        grid-template-columns: 1fr;
    }
    
    .product-card {
        background-color: white;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(209, 183, 138, 0.1);
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .product-image {
        position: relative;
        overflow: hidden;
    }
    
    .product-image img {
        width: 100%;
        height: 300px;
        object-fit: cover;
        transition: transform 0.8s ease;
    }
    
    .product-card:hover .product-image img {
        transform: scale(1.08);
    }
    
    .product-actions {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease;
    }
    
    .product-card:hover .product-actions {
        opacity: 1;
        transform: translateX(0);
    }
    
    .product-action {
        width: 40px;
        height: 40px;
        background-color: white;
        color: var(--primary-color);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }
    
    .product-action:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
        transform: translateY(-3px);
    }
    
    .product-content {
        padding: 20px;
        text-align: center;
    }
    
    .view-list .product-card {
        display: grid;
        grid-template-columns: 300px 1fr;
    }
    
    .view-list .product-content {
        text-align: left;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .view-list .product-image img {
        height: 100%;
    }
    
    .product-category {
        color: var(--text-light);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: block;
        margin-bottom: 10px;
    }
    
    .product-title {
        font-family: var(--font-primary);
        font-size: 1.1rem;
        font-weight: 500;
        margin-bottom: 8px;
        line-height: 1.4;
    }
    
    .product-title a {
        color: var(--primary-color);
        transition: color 0.3s ease;
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    
    .product-title a:hover {
        color: var(--accent-color);
    }
    
    .product-price {
        color: var(--primary-color);
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .product-description {
        color: var(--text-medium);
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 15px;
        display: none;
    }
    
    .view-list .product-description {
        display: block;
    }
    
    .product-button {
        margin-top: auto;
    }
    
    .btn-add-to-cart {
        background-color: transparent;
        border: 1px solid var(--accent-color);
        color: var(--primary-color);
        padding: 10px 20px;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        cursor: pointer;
        width: 100%;
    }
    
    .btn-add-to-cart:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .btn-outline {
        display: inline-block;
        background-color: transparent;
        border: 1px solid var(--accent-color);
        color: var(--primary-color);
        padding: 12px 25px;
        font-size: 0.9rem;
        font-weight: 500;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-outline:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    /* No products */
    .no-products {
        text-align: center;
        padding: 60px 0;
    }
    
    .no-products-icon {
        font-size: 3rem;
        color: var(--accent-color);
        margin-bottom: 20px;
        opacity: 0.6;
    }
    
    .no-products h3 {
        color: var(--primary-color);
        margin-bottom: 15px;
    }
    
    .no-products p {
        color: var(--text-medium);
        margin-bottom: 30px;
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Elegant Pagination */
    .elegant-pagination {
        margin-top: 60px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    
    .pagination-info {
        color: var(--text-medium);
        font-size: 0.9rem;
    }
    
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .pagination-arrow {
        width: 40px;
        height: 40px;
        border: 1px solid rgba(209, 183, 138, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: var(--text-medium);
        transition: all 0.3s ease;
    }
    
    .pagination-arrow:hover {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .pagination-numbers {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .page-number {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(209, 183, 138, 0.3);
        color: var(--text-medium);
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .page-number:hover {
        border-color: var(--accent-color);
        color: var(--accent-color);
    }
    
    .page-number.active {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .ellipsis {
        color: var(--text-medium);
    }
    
    /* Quote Section */
    .chocolate-quote {
        background-color: var(--background-beige);
        padding: 60px 0;
        position: relative;
    }
    
    .quote-container {
        max-width: 800px;
        margin: 0 auto;
        text-align: center;
        position: relative;
        padding: 30px 0;
    }
    
    .quote-marks {
        font-family: Georgia, serif;
        font-size: 6rem;
        color: var(--accent-color);
        opacity: 0.3;
        position: absolute;
        top: -50px;
        left: -30px;
        line-height: 1;
    }
    
    blockquote {
        font-family: var(--font-elegant);
        font-size: 1.6rem;
        line-height: 1.5;
        color: var(--primary-color);
        font-weight: 300;
        margin-bottom: 20px;
        font-style: italic;
    }
    
    .quote-author {
        font-size: 1rem;
        color: var(--text-medium);
    }
    
    /* Success styling */
    .btn-success {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
        color: white !important;
    }
    
    /* Responsive styling */
    @media (max-width: 991px) {
        .products-banner {
            height: 300px;
        }
        
        .products-banner h1 {
            font-size: 2.5rem;
        }
        
        blockquote {
            font-size: 1.4rem;
        }
        
        .quote-marks {
            font-size: 4rem;
            top: -40px;
        }
    }
    
    @media (max-width: 767px) {
        .products-banner {
            height: 250px;
        }
        
        .products-banner h1 {
            font-size: 2rem;
        }
        
        .category-description {
            font-size: 1rem;
        }
        
        .products-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
        
        blockquote {
            font-size: 1.2rem;
            padding: 0 20px;
        }
        
        .quote-marks {
            left: 0px;
            top: -30px;
            font-size: 3rem;
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
    }
    
    @media (max-width: 575px) {
        .pagination-numbers {
            gap: 5px;
        }
        
        .page-number, .pagination-arrow {
            width: 35px;
            height: 35px;
        }
        
        .view-list .product-card {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Grid/List View Toggle
    const gridViewBtn = document.querySelector('.view-option.grid');
    const listViewBtn = document.querySelector('.view-option.list');
    const productGrid = document.querySelector('.product-grid');
    
    if (gridViewBtn && listViewBtn && productGrid) {
        gridViewBtn.addEventListener('click', function() {
            productGrid.classList.remove('view-list');
            productGrid.classList.add('view-grid');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            
            // Save preference in localStorage
            localStorage.setItem('productViewPreference', 'grid');
        });
        
        listViewBtn.addEventListener('click', function() {
            productGrid.classList.remove('view-grid');
            productGrid.classList.add('view-list');
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            
            // Save preference in localStorage
            localStorage.setItem('productViewPreference', 'list');
        });
        
        // Load user preference from localStorage
        const viewPreference = localStorage.getItem('productViewPreference');
        if (viewPreference === 'list') {
            productGrid.classList.remove('view-grid');
            productGrid.classList.add('view-list');
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
        }
    }
    
    // Add to cart functionality
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            
            // AJAX request to add item to cart
            fetch('/php/chocolate-shop/ajax/add_to_cart.php', {  // Use absolute path here
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response:', data); // Debug output
                
                if (data.redirect) {
                    // Show login required notification
                    showLoginNotification(data.message, data.redirect_url);
                } else if (data.success) {
                    // Update cart count display
                    updateCartCount(data.cart_count);
                    
                    // Visual feedback
                    const originalContent = this.innerHTML;
                    
                    if (this.classList.contains('product-action')) {
                        this.innerHTML = '<i class="fas fa-check"></i>';
                    } else {
                        this.innerHTML = 'Added to Cart';
                    }
                    
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        if (this.classList.contains('product-action')) {
                            this.innerHTML = '<i class="fas fa-shopping-bag"></i>';
                        } else {
                            this.innerHTML = 'Add to Cart';
                        }
                        this.classList.remove('btn-success');
                    }, 1500);
                } else {
                    alert(data.message || 'Error adding product to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });

    // Helper function to show login notification
    function showLoginNotification(message, redirectUrl) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'login-notification';
        notification.innerHTML = `
            <div class="login-message">
                <i class="fas fa-lock"></i>
                <p>${message}</p>
            </div>
            <div class="login-actions">
                <a href="${redirectUrl}" class="btn-login">Log In</a>
                <a href="auth/register.php" class="btn-register">Create Account</a>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Add styles for the notification
        const style = document.createElement('style');
        style.textContent = `
            .login-notification {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 1050;
                max-width: 400px;
                width: 90%;
                text-align: center;
                animation: fadeIn 0.3s ease;
            }
            
            .login-message {
                display: flex;
                flex-direction: column;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .login-message i {
                font-size: 3rem;
                color: var(--accent-color);
                margin-bottom: 15px;
            }
            
            .login-message p {
                margin: 0;
                font-size: 1.1rem;
                color: var(--text-medium);
                line-height: 1.5;
            }
            
            .login-actions {
                display: flex;
                gap: 15px;
                margin-top: 10px;
            }
            
            .btn-login, .btn-register {
                padding: 12px 20px;
                border-radius: 4px;
                font-size: 0.95rem;
                text-align: center;
                transition: all 0.3s ease;
                flex: 1;
                text-decoration: none;
            }
            
            .btn-login {
                background-color: var(--accent-color);
                color: var(--primary-color);
                font-weight: 500;
            }
            
            .btn-login:hover {
                background-color: var(--primary-color);
                color: white;
            }
            
            .btn-register {
                border: 1px solid var(--accent-color);
                color: var(--primary-color);
                background-color: transparent;
            }
            
            .btn-register:hover {
                background-color: rgba(209, 183, 138, 0.1);
            }
            
            .backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'backdrop';
        document.body.appendChild(backdrop);
        
        // Close notification when clicking on backdrop
        backdrop.addEventListener('click', function() {
            notification.remove();
            backdrop.remove();
        });
    }
    
    // Helper function to update cart count in header
    function updateCartCount(count) {
        // Find all elements with the cart-count class
        const cartCountElements = document.querySelectorAll('.cart-count');
        
        cartCountElements.forEach(element => {
            // Update the text content
            element.textContent = count;
            
            // Add animation effect
            element.classList.add('pulse');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                element.classList.remove('pulse');
            }, 500);
        });
        
        // If count is 0, maybe hide the count badge
        if (count === 0) {
            cartCountElements.forEach(element => {
                element.style.display = 'none';
            });
        } else {
            cartCountElements.forEach(element => {
                element.style.display = 'inline-flex';
            });
        }
    }
    
    // Add this CSS to animate the cart count update
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.5);
                }
                100% {
                    transform: scale(1);
                }
            }
            
            .cart-count.pulse {
                animation: pulse 0.5s ease;
                color: #fff;
                background-color: #e44d26;
            }
        `;
        document.head.appendChild(style);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>