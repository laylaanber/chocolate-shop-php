<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = $_GET['id'];

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get product details
$product_query = "SELECT p.*, c.name as category_name 
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE p.id = ? AND p.is_active = 'available'";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute([$product_id]);

// If product not found, redirect to products page
if ($product_stmt->rowCount() === 0) {
    header("Location: products.php");
    exit;
}

$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

// Get related products (same category, excluding current product)
$related_query = "SELECT p.id, p.name, p.price, p.image 
                 FROM products p
                 WHERE p.category_id = ? AND p.id != ? AND p.is_active = 'available'
                 LIMIT 4";
$related_stmt = $db->prepare($related_query);
$related_stmt->execute([$product['category_id'], $product_id]);
$related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<!-- Elegant Banner with Parallax Effect -->
<div class="page-banner product-detail-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Collection</a></li>
                <?php if (!empty($product['category_name'])): ?>
                    <li class="breadcrumb-item"><a href="products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Product Detail Section -->
<section class="product-detail-section">
    <div class="container">
        <div class="product-detail-wrapper">
            <div class="row g-5">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="product-detail-image-wrapper">
                        <div class="product-detail-image">
                            <img src="uploads/products/<?= $product['image'] ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 onerror="this.src='https://images.unsplash.com/photo-1549007994-cb92caebd54b?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&h=600&q=80'">
                        </div>
                        <?php if ($product['is_featured'] == 1): ?>
                            <div class="product-badge featured">Featured</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="col-lg-6">
                    <div class="product-detail-info">
                        <?php if (!empty($product['category_name'])): ?>
                            <div class="product-category"><?= htmlspecialchars($product['category_name']) ?></div>
                        <?php endif; ?>
                        
                        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                        
                        <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                        
                        <div class="product-description">
                            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                        </div>
                        
                        <div class="product-divider"></div>
                        
                        <!-- Replace the product actions div with this improved version -->
                        <div class="product-actions">
                            <form class="product-quantity" onsubmit="return false;">
                                <label for="quantity">Quantity</label>
                                <div class="quantity-controls">
                                    <button type="button" class="quantity-btn minus">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" id="quantity" name="quantity" class="quantity-input" value="1" min="1" max="<?= $product['stock'] ?>">
                                    <button type="button" class="quantity-btn plus">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                            
                            <button class="btn-add-to-cart add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
                                <i class="fas fa-shopping-bag"></i> Add to Cart
                            </button>
                        </div>
                        
                        <div class="product-meta-info">
                            <?php if ($product['stock'] > 0): ?>
                                <div class="product-meta in-stock">
                                    <i class="fas fa-check-circle"></i> In Stock
                                </div>
                            <?php else: ?>
                                <div class="product-meta out-of-stock">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-meta">
                                <span class="meta-label">SKU:</span> 
                                <span class="meta-value">CHOC-<?= str_pad($product['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                        
                        <div class="product-additional-info">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Premium Quality</h4>
                                    <p>Made with the finest ingredients</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Free Shipping</h4>
                                    <p>On orders over $50</p>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-leaf"></i>
                                </div>
                                <div class="info-content">
                                    <h4>Sustainable Sourcing</h4>
                                    <p>Ethically sourced cacao</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Product Details Tabs -->
<section class="product-details-tabs">
    <div class="container">
        <div class="tabs-wrapper">
            <div class="tabs-header">
                <button class="tab-button active" data-tab="description">Description</button>
                <button class="tab-button" data-tab="ingredients">Ingredients</button>
                <button class="tab-button" data-tab="shipping">Shipping & Returns</button>
            </div>
            <div class="tabs-content">
                <div class="tab-panel active" id="description">
                    <h3>Product Description</h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    <p>Each chocolate is meticulously crafted by our master chocolatiers, who have honed their skills over decades to bring you an unparalleled tasting experience. Our commitment to quality is evident in every bite, from the smooth texture to the complex flavor profile that develops as you savor each piece.</p>
                </div>
                <div class="tab-panel" id="ingredients">
                    <h3>Ingredients</h3>
                    <p>Cacao beans, cocoa butter, sugar, milk powder (in milk chocolate varieties), vanilla, soy lecithin.</p>
                    <p><strong>Allergen Information:</strong> May contain traces of nuts, dairy, and soy. Produced in a facility that processes nuts.</p>
                    <p><strong>Storage Instructions:</strong> Store in a cool, dry place between 16-18°C (60-65°F). Avoid direct sunlight and strong odors.</p>
                </div>
                <div class="tab-panel" id="shipping">
                    <h3>Shipping & Returns</h3>
                    <p>We take special care to ensure your chocolates arrive in perfect condition. During warmer months (May-September), we may use insulated packaging with ice packs.</p>
                    <p><strong>Shipping Time:</strong> Orders are processed within 1-2 business days. Standard delivery takes 3-5 business days. Express shipping options available at checkout.</p>
                    <p><strong>Returns:</strong> Due to the perishable nature of our products, we cannot accept returns. If your order arrives damaged or defective, please contact us within 48 hours for a replacement or refund.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Products Section -->
<?php if (!empty($related_products)): ?>
<section class="related-products">
    <div class="container">
        <h2 class="section-title">You May Also Enjoy</h2>
        <div class="row g-4">
            <?php foreach($related_products as $related): ?>
                <div class="col-md-3">
                    <div class="product-card">
                        <div class="product-image">
                            <a href="product-detail.php?id=<?= $related['id'] ?>">
                                <img src="uploads/products/<?= $related['image'] ?>" 
                                     alt="<?= htmlspecialchars($related['name']) ?>"
                                     onerror="this.src='https://images.unsplash.com/photo-1549007994-cb92caebd54b?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&h=300&q=80'">
                            </a>
                            <div class="product-actions">
                                <a href="product-detail.php?id=<?= $related['id'] ?>" class="product-action view" title="Quick View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="product-action cart add-to-cart-btn" data-product-id="<?= $related['id'] ?>" title="Add to Cart">
                                    <i class="fas fa-shopping-bag"></i>
                                </button>
                            </div>
                        </div>
                        <div class="product-content">
                            <span class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Artisanal') ?></span>
                            <h3 class="product-title">
                                <a href="product-detail.php?id=<?= $related['id'] ?>"><?= htmlspecialchars($related['name']) ?></a>
                            </h3>
                            <div class="product-price">$<?= number_format($related['price'], 2) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
    /* Luxurious Product Detail Styling */
    
    /* Banner styling with parallax effect */
    .product-detail-banner {
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
    
    .product-detail-banner h1 {
        font-size: 4rem; /* Larger font size to match our-brand.php */
        font-weight: 300;
        margin-bottom: 1.2rem;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        color: #fff;
        /* Add subtle animation on page load */
        animation: fadeInDown 1.2s ease-out;
        position: relative;
    }
    
    .breadcrumb {
        justify-content: center;
        background: none;
        margin: 0;
        position: relative;
        animation: fadeInUp 1.2s ease-out 0.3s both;
    }

    /* Improved breadcrumb text visibility */
    .breadcrumb-item, 
    .breadcrumb-item.active, 
    .breadcrumb-item + .breadcrumb-item::before {
        color: rgba(255, 255, 255, 0.9);
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        font-weight: 500;
    }

    .breadcrumb-item a {
        color: var(--accent-color);
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        transition: color 0.3s ease;
    }

    .breadcrumb-item a:hover {
        color: #fff;
        text-decoration: none;
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.2rem;
        line-height: 1;
        padding: 0 10px;
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
    
    /* Product Detail Section */
    .product-detail-section {
        padding: 80px 0;
        background-color: var(--background-light);
    }
    
    .product-detail-wrapper {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        padding: 40px;
        position: relative;
    }
    
    /* Product Image */
    .product-detail-image-wrapper {
        position: relative;
        margin-bottom: 20px;
    }
    
    .product-detail-image {
        text-align: center;
        border: 1px solid rgba(209, 183, 138, 0.2);
        padding: 20px;
        background-color: white;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .product-detail-image img {
        max-width: 100%;
        height: auto;
        transition: transform 0.8s ease;
    }
    
    .product-detail-image:hover img {
        transform: scale(1.05);
    }
    
    .product-badge {
        position: absolute;
        top: 20px;
        left: 20px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        font-size: 0.8rem;
        font-weight: 500;
        padding: 5px 15px;
        border-radius: 20px;
        z-index: 2;
    }
    
    /* Product Info */
    .product-detail-info {
        padding: 0 0 0 20px;
    }
    
    .product-category {
        color: var(--text-light);
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 15px;
        display: inline-block;
        font-weight: 500;
    }
    
    .product-title {
        font-family: var(--font-primary);
        font-size: 2.5rem;
        margin-bottom: 20px;
        color: var(--primary-color);
        font-weight: 500;
        line-height: 1.2;
    }
    
    .product-price {
        font-size: 2rem;
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 25px;
        font-family: var(--font-primary);
    }
    
    .product-description {
        color: var(--text-medium);
        margin-bottom: 30px;
        line-height: 1.8;
        font-size: 1.05rem;
    }
    
    .product-divider {
        width: 100%;
        height: 1px;
        background: linear-gradient(to right, rgba(209,183,138,0.5) 0%, rgba(209,183,138,0) 100%);
        margin: 30px 0;
    }
    
    /* Quantity controls */
    .product-actions {
        display: flex;
        flex-wrap: wrap;
        margin: 30px 0;
        gap: 20px;
    }
    
    .product-quantity {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .product-quantity label {
        font-size: 0.9rem;
        color: var(--text-medium);
        font-weight: 500;
    }
    
    .quantity-controls {
        display: flex;
        align-items: center;
        border: 1px solid rgba(209, 183, 138, 0.3);
        border-radius: 30px;
        overflow: hidden;
    }
    
    .quantity-btn {
        background: none;
        border: none;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.8rem;
        color: var(--text-medium);
        transition: all 0.3s ease;
    }
    
    .quantity-btn:hover {
        background-color: rgba(209, 183, 138, 0.1);
        color: var(--accent-color);
    }
    
    .quantity-input {
        width: 50px;
        text-align: center;
        border: none;
        outline: none;
        font-size: 1rem;
        font-weight: 500;
        color: var(--primary-color);
        -moz-appearance: textfield;
    }
    
    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .btn-add-to-cart {
        background-color: var(--primary-color);
        color: white !important; /* Force text color */
        border: none;
        padding: 15px 30px;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 30px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        flex-grow: 1;
        display: inline-flex !important; /* Force display */
        align-items: center;
        justify-content: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        margin-top: 10px;
        width: 100%;
        max-width: 300px;
        position: relative;
        z-index: 5; /* Higher z-index */
    }

    /* Add more visibility to the button */
    .btn-add-to-cart i {
        margin-right: 10px;
        font-size: 1.2rem;
    }

    /* Make sure the button is visible on mobile too */
    @media (max-width: 767px) {
        .btn-add-to-cart {
            width: 100%;
            max-width: none;
            padding: 15px;
            margin-top: 15px;
        }
    }
    
    .btn-add-to-cart:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(209, 183, 138, 0.3);
    }
    
    .btn-add-to-cart:active {
        transform: translateY(-1px);
    }
    
    /* Product meta info */
    .product-meta-info {
        margin-bottom: 30px;
    }
    
    .product-meta {
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .meta-label {
        color: var(--text-light);
        font-weight: 500;
    }
    
    .meta-value {
        color: var(--text-medium);
    }
    
    .in-stock {
        color: #28a745;
    }
    
    .out-of-stock {
        color: #dc3545;
    }
    
    /* Additional product info */
    .product-additional-info {
        border-top: 1px solid rgba(209, 183, 138, 0.2);
        padding-top: 30px;
    }
    
    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .info-icon {
        width: 40px;
        height: 40px;
        background-color: rgba(209, 183, 138, 0.1);
        color: var(--accent-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .info-content h4 {
        margin: 0 0 5px 0;
        font-size: 1rem;
        font-weight: 500;
        color: var(--primary-color);
    }
    
    .info-content p {
        margin: 0;
        font-size: 0.9rem;
        color: var(--text-medium);
    }
    
    /* Product tabs */
    .product-details-tabs {
        padding: 0 0 80px;
        background-color: var(--background-light);
    }
    
    .tabs-wrapper {
        max-width: 900px;
        margin: 0 auto;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    
    .tabs-header {
        display: flex;
        border-bottom: 1px solid rgba(209, 183, 138, 0.2);
        background-color: var(--background-beige);
    }
    
    .tab-button {
        padding: 18px 25px;
        background: none;
        border: none;
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-medium);
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        flex: 1;
        text-align: center;
    }
    
    .tab-button:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 3px;
        background-color: var(--accent-color);
        transition: all 0.3s ease;
    }
    
    .tab-button:hover {
        color: var(--primary-color);
    }
    
    .tab-button.active {
        color: var(--primary-color);
    }
    
    .tab-button.active:after {
        width: 100%;
    }
    
    .tabs-content {
        padding: 40px;
    }
    
    .tab-panel {
        display: none;
    }
    
    .tab-panel.active {
        display: block;
    }
    
    .tab-panel h3 {
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        font-weight: 500;
        font-family: var(--font-primary);
    }
    
    .tab-panel p {
        color: var(--text-medium);
        margin-bottom: 20px;
        line-height: 1.8;
    }
    
    /* Related Products Section */
    .related-products {
        background-color: var(--background-beige);
        padding: 80px 0;
    }
    
    .section-title {
        font-size: 2.2rem;
        text-align: center;
        margin-bottom: 50px;
        position: relative;
        color: var(--primary-color);
        font-family: var(--font-primary);
        font-weight: 400;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 1px;
        background-color: var(--accent-color);
    }
    
    /* Product cards styling from products.php */
    .product-card {
        background-color: white;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(209, 183, 138, 0.1);
        height: 100%;
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
        height: 250px;
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
        text-decoration: none;
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
    
    /* Success styling */
    .btn-success {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
        color: white !important;
    }
    
    /* Responsive styling */
    @media (max-width: 991px) {
        .product-detail-banner {
            height: 300px;
        }
        
        .product-detail-banner h1 {
            font-size: 2.5rem;
        }
        
        .product-title {
            font-size: 2rem;
        }
        
        .product-price {
            font-size: 1.8rem;
        }
        
        .product-detail-wrapper {
            padding: 30px;
        }
        
        .product-detail-info {
            padding: 0;
        }
        
        .tabs-content {
            padding: 30px;
        }
    }
    
    @media (max-width: 767px) {
        .product-detail-banner {
            height: 250px;
        }
        
        .product-detail-banner h1 {
            font-size: 2rem;
        }
        
        .product-detail-section {
            padding: 60px 0;
        }
        
        .product-title {
            font-size: 1.8rem;
        }
        
        .product-price {
            font-size: 1.6rem;
        }
        
        .product-detail-wrapper {
            padding: 20px;
        }
        
        .product-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .btn-add-to-cart {
            margin-top: 15px;
            padding: 12px 20px;
        }
        
        .tabs-header {
            flex-direction: column;
        }
        
        .tab-button {
            width: 100%;
            text-align: left;
            padding: 15px 20px;
        }
        
        .tabs-content {
            padding: 20px;
        }
    }
    
    @media (max-width: 575px) {
        .product-detail-banner h1 {
            font-size: 1.8rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanels = document.querySelectorAll('.tab-panel');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and panels
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanels.forEach(panel => panel.classList.remove('active'));
            
            // Add active class to the clicked button
            this.classList.add('active');
            
            // Show the corresponding panel
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Quantity increment/decrement
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    const quantityInput = document.querySelector('.quantity-input');
    
    if (minusBtn && plusBtn && quantityInput) {
        const maxStock = parseInt(quantityInput.getAttribute('max')) || 100;
        
        minusBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
        
        plusBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            if (currentValue < maxStock) {
                quantityInput.value = currentValue + 1;
            }
        });
        
        // Ensure the input value doesn't exceed stock
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                this.value = 1;
            } else if (value > maxStock) {
                this.value = maxStock;
            }
        });
    }
    
    // Add to cart with quantity - MAIN PRODUCT
    const addToCartBtn = document.querySelector('.btn-add-to-cart');

    if (addToCartBtn) {
        console.log("Add to Cart button found:", addToCartBtn); // Debug line
        
        addToCartBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent any form submission
            console.log("Add to Cart button clicked"); // Debug line
            
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(document.querySelector('.quantity-input')?.value || 1);
            
            console.log("Adding to cart:", productId, quantity); // Debug line
            
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=' + quantity
            })
            .then(response => {
                console.log("Response received:", response); // Debug line
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data); // Debug line
                
                if (data.redirect) {
                    // Show login required notification
                    showLoginNotification(data.message, data.redirect_url);
                } else if (data.success) {
                    // Product successfully added to cart
                    updateCartCount(data.cart_count);
                    showAddedToCartMessage();
                } else {
                    alert(data.message || 'Error adding product to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    } else {
        console.error("Add to Cart button not found in DOM"); // Debug line
    }
    
    // Add to cart buttons in RELATED PRODUCTS
    const relatedProductButtons = document.querySelectorAll('.product-action.cart');
    
    relatedProductButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    // Show login required notification
                    showLoginNotification(data.message, data.redirect_url);
                } else if (data.success) {
                    updateCartCount(data.cart_count);
                    
                    // Visual feedback
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
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

    // Helper function to show login notification - update this in product-detail.php
    function showLoginNotification(message, redirectUrl) {
        // Fix the redirectUrl to use absolute path if needed
        if (redirectUrl.startsWith('../')) {
            // Convert relative ../auth/login.php to absolute /php/chocolate-shop/auth/login.php
            redirectUrl = '/php/chocolate-shop/auth/login.php' + redirectUrl.substring(redirectUrl.indexOf('?'));
        }

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
                <a href="/php/chocolate-shop/auth/register.php" class="btn-register">Create Account</a>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'backdrop';
        document.body.appendChild(backdrop);
        
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
        
        // Close notification when clicking on backdrop
        backdrop.addEventListener('click', function() {
            notification.remove();
            backdrop.remove();
        });
        
        // Also add direct click handlers to the buttons
        setTimeout(() => {
            const loginBtn = notification.querySelector('.btn-login');
            const registerBtn = notification.querySelector('.btn-register');
            
            loginBtn.addEventListener('click', function(e) {
                window.location.href = this.getAttribute('href');
            });
            
            registerBtn.addEventListener('click', function(e) {
                window.location.href = this.getAttribute('href');
            });
        }, 10);
    }

    function showAddedToCartMessage() {
        // Visual feedback when product is added
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.innerHTML = `
            <div class="notification-icon"><i class="fas fa-check-circle"></i></div>
            <div class="notification-content">
                <p>Item added to your cart</p>
                <div class="notification-actions">
                    <a href="cart.php" class="view-cart">View Cart</a>
                    <button class="continue-shopping">Continue Shopping</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Add CSS for notification
        const style = document.createElement('style');
        style.textContent = `
            .cart-notification {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 15px;
                z-index: 1000;
                animation: slideIn 0.3s forwards;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            .notification-icon {
                font-size: 1.8rem;
                color: #28a745;
            }
            
            .notification-content p {
                margin: 0 0 10px;
                font-weight: 500;
            }
            
            .notification-actions {
                display: flex;
                gap: 10px;
            }
            
            .view-cart {
                background-color: var(--accent-color);
                color: var(--primary-color);
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 0.9rem;
                transition: all 0.3s ease;
                text-decoration: none;
            }
            
            .view-cart:hover {
                background-color: var(--primary-color);
                color: white;
            }
            
            .continue-shopping {
                background: none;
                border: none;
                color: var(--text-medium);
                cursor: pointer;
                font-size: 0.9rem;
                padding: 0;
                text-decoration: underline;
            }
            
            .continue-shopping:hover {
                color: var(--primary-color);
            }
        `;
        
        document.head.appendChild(style);
        
        // Auto-remove after delay
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s forwards';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close notification when continue shopping is clicked
        const continueBtn = notification.querySelector('.continue-shopping');
        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                notification.style.animation = 'slideOut 0.3s forwards';
                setTimeout(() => notification.remove(), 300);
            });
        }
        
        // Add slide out animation
        const slideOutStyle = document.createElement('style');
        slideOutStyle.textContent = `
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(slideOutStyle);
    }

    // Update cart count function
    function updateCartCount(count) {
        const cartBadge = document.querySelector('.cart-count');
        if (cartBadge) {
            cartBadge.textContent = count;
            if (count > 0) {
                cartBadge.style.display = 'flex';
            } else {
                cartBadge.style.display = 'none';
            }
        } else if (count > 0) {
            const cartIcon = document.querySelector('.header-action i.fa-shopping-bag');
            if (cartIcon) {
                const badge = document.createElement('span');
                badge.className = 'cart-count';
                badge.textContent = count;
                cartIcon.parentNode.appendChild(badge);
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>