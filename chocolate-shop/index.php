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

// Update your featured products query to ensure is_featured is selected
$featured_query = "SELECT p.id, p.name, p.description, p.price, p.image, p.is_featured, c.name as category_name
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.is_featured = 1 AND p.is_active = 'available'
                  ORDER BY p.created_at DESC
                  LIMIT 4";
$featured_stmt = $db->prepare($featured_query);
$featured_stmt->execute();
$featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bestsellers
$bestseller_query = "SELECT p.id, p.name, p.description, p.price, p.image, 
                    (SELECT COUNT(*) FROM order_items oi JOIN orders o ON oi.order_id = o.id 
                     WHERE oi.product_id = p.id AND o.status != 'cancelled') as order_count
                    FROM products p
                    WHERE p.is_active = 'available'
                    ORDER BY order_count DESC
                    LIMIT 6";
$bestseller_stmt = $db->prepare($bestseller_query);
$bestseller_stmt->execute();
$bestsellers = $bestseller_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories 
$categories_query = "SELECT id, name, image, description FROM categories ORDER BY name LIMIT 3";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Slider Section -->
<section class="hero-slider">
    <div class="slider">
        <div class="slide">
            <div class="slide-image">
                <img src="https://images.unsplash.com/photo-1549007994-cb92caebd54b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2071&q=80" alt="Luxury Chocolates">
                <div class="slide-overlay"></div>
            </div>
            <div class="slide-content">
                <div class="content-inner">
                    <span class="subtitle">Artisan Collection</span>
                    <h1>Exquisite Chocolate Creations</h1>
                    <p>Crafted with passion and tradition using the finest ingredients</p>
                    <div class="slide-buttons">
                        <a href="products.php" class="btn-primary">Shop Now</a>
                        <a href="our-brand.php" class="btn-outline">Our Story</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="slide">
            <div class="slide-image">
                <img src="https://images.unsplash.com/photo-1606312619070-d48b4c652a52?ixlib=rb-4.0.3&auto=format&fit=crop&w=2071&q=80" alt="Chocolate Collection">
                <div class="slide-overlay"></div>
            </div>
            <div class="slide-content">
                <div class="content-inner">
                    <span class="subtitle">New Arrivals</span>
                    <h1>Spring Collection 2025</h1>
                    <p>Discover our limited edition flavors inspired by the season's freshest ingredients</p>
                    <div class="slide-buttons">
                        <a href="products.php?collection=spring" class="btn-primary">Discover</a>
                        <a href="our-brand.php" class="btn-outline">Our Philosophy</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="slide">
            <div class="slide-image">
                <img src="https://images.unsplash.com/photo-1481391319762-47dff72954d9?ixlib=rb-4.0.3&auto=format&fit=crop&w=2071&q=80" alt="Gift Collections">
                <div class="slide-overlay"></div>
            </div>
            <div class="slide-content">
                <div class="content-inner">
                    <span class="subtitle">Perfect Gifts</span>
                    <h1>Luxury Gift Collections</h1>
                    <p>Thoughtfully curated gift boxes for every occasion and celebration</p>
                    <div class="slide-buttons">
                        <a href="products.php?category=gifts" class="btn-primary">Explore Gifts</a>
                        <a href="contact.php" class="btn-outline">Custom Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <button class="slider-arrow prev">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="slider-arrow next">
        <i class="fas fa-chevron-right"></i>
    </button>
    
    <div class="slider-dots">
        <button class="dot active"></button>
        <button class="dot"></button>
        <button class="dot"></button>
    </div>
</section>

<!-- Brand Promise Section -->
<section class="brand-promise">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="promise-item">
                    <div class="promise-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3>Fast Delivery</h3>
                    <p>Enjoy free shipping on orders over $50 and quick delivery to ensure your chocolates arrive in perfect condition.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="promise-item">
                    <div class="promise-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>Premium Quality</h3>
                    <p>Our chocolates are handcrafted using only the finest ingredients, sourced from sustainable farms around the world.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="promise-item">
                    <div class="promise-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3>Perfect Gifts</h3>
                    <p>Elegant packaging and personalized gift options make our chocolates the perfect present for any occasion.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="featured-products">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        <div class="row g-4">
            <?php if(!empty($featured_products)): ?>
                <?php foreach($featured_products as $product): ?>
                    <div class="col-md-3">
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product-detail.php?id=<?= $product['id'] ?>">
                                    <img src="uploads/products/<?= $product['image'] ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         onerror="this.src='https://via.placeholder.com/300x300?text=<?= urlencode($product['name']) ?>'">
                                </a>
                                <div class="product-badge">Featured</div>
                            </div>
                            <div class="product-info">
                                <span class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                                <h3 class="product-title">
                                    <a href="product-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                                </h3>
                                <p class="product-price">$<?= number_format($product['price'], 2) ?></p>
                                <div class="product-actions">
                                    <button class="btn-primary add-to-cart-btn" data-product-id="<?= $product['id'] ?>"><i class="fas fa-shopping-cart"></i></button>
                                    <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn-secondary"><i class="fas fa-eye"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Featured Collections -->
<section class="collection-showcase">
    <div class="container">
        <div class="section-heading">
            <span class="heading-tag">Curated Selection</span>
            <h2>Our Collections</h2>
            <p class="heading-description">Explore our distinctive chocolate collections, each crafted with care and expertise</p>
        </div>
        <div class="row g-4">
            <?php if(!empty($categories)): ?>
                <?php foreach($categories as $category): ?>
                    <div class="col-md-4">
                        <a href="products.php?category=<?= $category['id'] ?>" class="collection-card">
                            <div class="collection-image">
                                <img src="uploads/categories/<?= $category['image'] ?>" 
                                     alt="<?= htmlspecialchars($category['name']) ?>"
                                     onerror="this.src='https://images.unsplash.com/photo-1549007994-cb92caebd54b?auto=format&fit=crop&w=400&q=80'">
                            </div>
                            <div class="collection-details">
                                <h3><?= htmlspecialchars($category['name']) ?></h3>
                                <span class="btn-text">Discover</span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No collections available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Bestsellers Section -->
<section class="bestsellers">
    <div class="container">
        <div class="section-heading">
            <span class="heading-tag">Customer Favorites</span>
            <h2>Bestsellers</h2>
            <p class="heading-description">Our most loved chocolate creations that have won the hearts of chocolate connoisseurs</p>
        </div>
        <div class="row g-4">
            <?php if(!empty($bestsellers)): ?>
                <?php foreach($bestsellers as $product): ?>
                    <div class="col-md-4 col-lg-2">
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product-detail.php?id=<?= $product['id'] ?>">
                                    <img src="uploads/products/<?= $product['image'] ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         onerror="this.src='https://via.placeholder.com/300x300?text=<?= urlencode($product['name']) ?>'">
                                </a>
                                <div class="product-badge bestseller">Bestseller</div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title">
                                    <a href="product-detail.php?id=<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                                </h3>
                                <p class="product-price">$<?= number_format($product['price'], 2) ?></p>
                                <div class="product-actions">
                                    <button class="btn-primary add-to-cart-btn" data-product-id="<?= $product['id'] ?>"><i class="fas fa-shopping-cart"></i></button>
                                    <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn-secondary"><i class="fas fa-eye"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No bestseller products available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-5">
            <a href="products.php" class="btn-outline">View All Products</a>
        </div>
    </div>
</section>

<!-- Story Section with Parallax -->
<section class="our-story">
    <div class="story-parallax">
        <div class="container">
            <div class="story-content">
                <span class="story-tag">Our Heritage</span>
                <h2>Our Chocolate Journey</h2>
                <p>For over three decades, we've been crafting exceptional chocolates using time-honored techniques and the finest ingredients. Our passion for chocolate as an art form drives us to create confections that delight all the senses.</p>
                <a href="our-story.php" class="btn-outline">Discover Our Story</a>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial Section - Redesigned for Better UX -->
<section class="testimonials">
    <div class="container">
        <div class="section-heading">
            <span class="heading-tag">What People Say</span>
            <h2>Client Testimonials</h2>
        </div>
        
        <?php
        // Fetch reviews from database
        $reviews_query = "SELECT r.*, p.name as product_name, u.username as user_name 
                         FROM reviews r 
                         LEFT JOIN products p ON r.product_id = p.id 
                         LEFT JOIN users u ON r.user_id = u.id 
                         ORDER BY r.created_at DESC 
                         LIMIT 6";
        $reviews_stmt = $db->prepare($reviews_query);
        $reviews_stmt->execute();
        $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <!-- Testimonial Cards -->
        <div class="testimonial-cards">
            <?php if(!empty($reviews)): ?>
                <?php foreach($reviews as $review): ?>
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <div class="testimonial-avatar">
                                <div class="avatar-initial"><?= strtoupper(substr($review['user_name'] ?? 'A', 0, 1)) ?></div>
                            </div>
                            <div class="testimonial-meta">
                                <h4><?= htmlspecialchars($review['user_name'] ?? 'Anonymous') ?></h4>
                                <div class="testimonial-rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if($i <= $review['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="testimonial-body">
                            <div class="quote-icon">
                                <i class="fas fa-quote-right"></i>
                            </div>
                            <p><?= htmlspecialchars($review['comment']) ?></p>
                        </div>
                        <div class="testimonial-footer">
                            <?php if(!empty($review['product_name'])): ?>
                                <span class="product-reviewed">Purchased: <?= htmlspecialchars($review['product_name']) ?></span>
                            <?php else: ?>
                                <span class="review-date"><?= date('F Y', strtotime($review['created_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default testimonials when no reviews exist in database -->
                <!-- (Your existing default testimonials code) -->
            <?php endif; ?>
        </div>
        
        <!-- View More & Leave Review Buttons -->
        <div class="testimonial-actions">
            <button id="leaveReviewBtn" class="btn-primary">
                <i class="fas fa-pen"></i> Share Your Experience
            </button>
            <?php if(count($reviews) >= 6): ?>
                <a href="testimonials.php" class="btn-outline dark">View All Testimonials</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="review-modal-backdrop" id="reviewModalBackdrop"></div>
    <div class="review-modal" id="reviewModal">
        <div class="modal-header">
            <h3 class="modal-title">Share Your Experience</h3>
            <button class="close-modal" id="closeReviewModal">&times;</button>
        </div>
        <form class="review-form" id="reviewForm">
            <?php if(isset($_SESSION['user_id'])): ?>
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="reviewProduct">Select Product (Optional)</label>
                <select id="reviewProduct" name="product_id" class="form-control">
                    <option value="">General Review</option>
                    <?php
                    $product_options_query = "SELECT id, name FROM products WHERE is_active = 'available' ORDER BY name";
                    $product_options_stmt = $db->prepare($product_options_query);
                    $product_options_stmt->execute();
                    $product_options = $product_options_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach($product_options as $product_option):
                    ?>
                        <option value="<?= $product_option['id'] ?>"><?= htmlspecialchars($product_option['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Your Rating</label>
                <div class="rating-input">
                    <input type="radio" id="star5" name="rating" value="5">
                    <label for="star5"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star3" name="rating" value="3">
                    <label for="star3"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2"><i class="fas fa-star"></i></label>
                    <input type="radio" id="star1" name="rating" value="1" checked>
                    <label for="star1"><i class="fas fa-star"></i></label>
                </div>
            </div>
            
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="form-group">
                    <label for="reviewName">Your Name</label>
                    <input type="text" id="reviewName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="reviewEmail">Your Email</label>
                    <input type="email" id="reviewEmail" name="email" class="form-control" required>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="reviewComment">Your Review</label>
                <textarea id="reviewComment" name="comment" class="form-control" required></textarea>
            </div>
            
            <button type="submit" class="btn-submit-review">Submit Review</button>
        </form>
    </div>
</section>

<style>
    /* Enhanced Testimonials Section */
    .testimonials {
        padding: 100px 0;
        background-color: var(--background-beige);
    }
    
    /* Testimonial Cards Grid Layout */
    .testimonial-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        margin-bottom: 50px;
    }
    
    .testimonial-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .testimonial-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    }
    
    /* Testimonial Card Header */
    .testimonial-header {
        display: flex;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .testimonial-avatar {
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .avatar-initial {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        font-weight: 500;
    }
    
    .testimonial-meta {
        flex-grow: 1;
    }
    
    .testimonial-meta h4 {
        font-size: 1.1rem;
        margin: 0 0 5px;
        color: var(--primary-color);
    }
    
    .testimonial-rating {
        color: var(--accent-color);
        font-size: 0.9rem;
        line-height: 1;
    }
    
    /* Testimonial Card Body */
    .testimonial-body {
        padding: 25px;
        flex-grow: 1;
        position: relative;
    }
    
    .quote-icon {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 1.8rem;
        color: rgba(209, 183, 138, 0.15);
    }
    
    .testimonial-body p {
        margin: 0;
        line-height: 1.7;
        color: var(--text-medium);
        font-size: 0.95rem;
    }
    
    /* Testimonial Card Footer */
    .testimonial-footer {
        padding: 15px 20px;
        background-color: rgba(209, 183, 138, 0.05);
        font-size: 0.85rem;
        color: var(--text-light);
        font-style: italic;
    }
    
    .product-reviewed {
        font-weight: 500;
        color: var(--secondary-color);
    }
    
    /* Testimonial Actions */
    .testimonial-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .testimonial-actions .btn-primary,
    .testimonial-actions .btn-outline {
        min-width: 200px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .testimonial-actions .btn-primary i,
    .testimonial-actions .btn-outline i {
        font-size: 0.9rem;
    }
    
    /* Review Modal (keeping your existing modal styles) */
    
    /* Responsive Design */
    @media (max-width: 1199px) {
        .testimonial-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 767px) {
        .testimonial-cards {
            grid-template-columns: 1fr;
        }
        
        .testimonial-header {
            flex-direction: column;
            text-align: center;
        }
        
        .testimonial-avatar {
            margin-right: 0;
            margin-bottom: 15px;
        }
        
        .testimonial-actions {
            flex-direction: column;
            align-items: center;
        }
        
        .testimonial-actions .btn-primary,
        .testimonial-actions .btn-outline {
            width: 100%;
        }
    }
    
    /* Ensure these styles are present */
    .review-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none; /* Initially hidden */
    }
    
    .review-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 500px;
        background-color: white;
        padding: 30px;
        border-radius: 5px;
        z-index: 1001;
        display: none; /* Initially hidden */
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
</style>



<!-- Custom styling for La Maison du Chocolat aesthetic -->
<style>
    /* Modern luxury chocolate shop variables */
    :root {
        --primary-color: #3F2113;      /* Dark chocolate */
        --secondary-color: #85634D;     /* Milk chocolate */
        --accent-color: #D1B78A;        /* Gold accent */
        --text-dark: #1E1E1E;
        --text-medium: #5A5A5A;
        --text-light: #888888;
        --background-light: #FFFFFF;    /* White background */
        --background-beige: #F9F4EF;    /* Light beige for sections */
        --transition: all 0.3s ease;
        --font-primary: 'Playfair Display', serif;
        --font-secondary: 'Poppins', sans-serif;
    }
    
    body {
        color: var(--text-dark);
        background-color: var(--background-light);
        font-family: var(--font-secondary);
        font-weight: 300;
        line-height: 1.7;
        overflow-x: hidden;
    }
    
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-primary);
        font-weight: 500;
        line-height: 1.3;
    }
    
    .container {
        padding: 0 30px;
    }
    
    /* Button styles */
    .btn-primary, .btn-secondary, .btn-outline {
        display: inline-block;
        padding: 12px 30px;
        font-size: 0.9rem;
        font-family: var(--font-secondary);
        font-weight: 500;
        letter-spacing: 1px;
        text-transform: uppercase;
        text-decoration: none;
        border: 1px solid transparent;
        transition: var(--transition);
        cursor: pointer;
    }
    
    .btn-primary {
        background-color: var(--accent-color);
        color: var(--primary-color);
        border-color: var(--accent-color);
    }
    
    .btn-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-secondary {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .btn-secondary:hover {
        background-color: var(--secondary-color);
        color: white;
    }
    
    .btn-outline {
        background-color: transparent;
        border: 2px solid var(--accent-color);
        color: white;
    }
    
    .btn-outline:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
    }
    
    .btn-text {
        font-family: var(--font-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--accent-color);
        position: relative;
        display: inline-block;
    }
    
    .btn-text::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 0;
        height: 1px;
        background-color: var(--accent-color);
        transition: width 0.3s ease;
    }
    
    .btn-text:hover::after {
        width: 100%;
    }
    
    /* Section styling */
    section {
        padding: 80px 0;
    }
    
    .section-title {
        font-size: 2.2rem;
        text-align: center;
        margin-bottom: 50px;
        position: relative;
        color: var(--primary-color);
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
    
    .section-heading {
        text-align: center;
        margin-bottom: 60px;
    }
    
    .heading-tag {
        display: block;
        font-family: var(--font-secondary);
        text-transform: uppercase;
        letter-spacing: 3px;
        font-size: 0.9rem;
        margin-bottom: 15px;
        color: var(--accent-color);
    }
    
    .section-heading h2 {
        font-family: var(--font-primary);
        font-size: 2.5rem;
        font-weight: 400;
        color: var(--primary-color);
        margin-bottom: 15px;
    }
    
    .heading-description {
        color: var(--text-medium);
        max-width: 700px;
        margin: 0 auto;
        font-size: 1.1rem;
    }

    /* Hero slider section */
    .hero-slider {
        position: relative;
        height: 650px;
        overflow: hidden;
    }

    .slider {
        height: 100%;
        position: relative;
    }

    .slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transition: opacity 1s ease;
        z-index: 1;
    }

    .slide.active {
        opacity: 1;
        z-index: 2;
    }

    .slide-image {
        width: 100%;
        height: 100%;
    }

    .slide-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .slide-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
    }

    .slide-content {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-align: center;
    }

    .content-inner {
        max-width: 700px;
        padding: 0 20px;
    }

    .slide-content .subtitle {
        font-family: var(--font-secondary);
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 3px;
        display: block;
        margin-bottom: 15px;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.5s ease, transform 0.5s ease;
        transition-delay: 0.3s;
    }

    .slide.active .slide-content .subtitle {
        opacity: 1;
        transform: translateY(0);
    }

    .slide-content h1 {
        font-family: var(--font-primary);
        font-size: 4rem;
        font-weight: 400;
        margin-bottom: 20px;
        line-height: 1.2;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.5s ease, transform 0.5s ease;
        transition-delay: 0.5s;
    }

    .slide.active .slide-content h1 {
        opacity: 1;
        transform: translateY(0);
    }

    .slide-content p {
        font-size: 1.2rem;
        margin-bottom: 30px;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.5s ease, transform 0.5s ease;
        transition-delay: 0.7s;
    }

    .slide.active .slide-content p {
        opacity: 1;
        transform: translateY(0);
    }

    .slide-buttons {
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.5s ease, transform 0.5s ease;
        transition-delay: 0.9s;
    }

    .slide.active .slide-buttons {
        opacity: 1;
        transform: translateY(0);
    }

    /* Slider controls */
    .slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: none;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }

    .slider-arrow:hover {
        background: var(--accent-color);
        color: var(--primary-color);
    }

    .slider-arrow.prev {
        left: 20px;
    }

    .slider-arrow.next {
        right: 20px;
    }

    .slider-dots {
        position: absolute;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 10;
    }

    .dot {
        width: 12px;
        height: 12px;
        border: 2px solid white;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .dot.active {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
    }
    
    /* Brand Promise Section */
    .brand-promise {
        padding: 80px 0;
        background-color: var(--background-light);
    }

    .promise-item {
        text-align: center;
        padding: 30px;
        height: 100%;
        transition: transform 0.3s ease;
    }

    .promise-item:hover {
        transform: translateY(-10px);
    }

    .promise-icon {
        width: 80px;
        height: 80px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        margin: 0 auto 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        border-radius: 50%;
    }

    .promise-item h3 {
        font-family: var(--font-primary);
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 15px;
        font-weight: 500;
    }

    .promise-item p {
        color: var(--text-medium);
        line-height: 1.7;
    }
    
    /* Collection Showcase */
    .collection-showcase {
        padding: 100px 0;
        background-color: var(--background-beige);
    }

    .collection-card {
        position: relative;
        overflow: hidden;
        height: 100%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        display: block;
        text-decoration: none;
    }

    .collection-image {
        height: 300px;
        overflow: hidden;
    }

    .collection-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.8s ease;
    }

    .collection-card:hover .collection-image img {
        transform: scale(1.05);
    }

    .collection-details {
        padding: 30px;
        background-color: white;
        text-align: center;
    }

    .collection-details h3 {
        font-family: var(--font-primary);
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 15px;
        font-weight: 500;
    }

    .collection-details p {
        color: var(--text-medium);
        margin-bottom: 20px;
        line-height: 1.7;
    }

    /* Featured Products & Bestsellers Section */
    .featured-products,
    .bestsellers {
        padding: 100px 0;
        background-color: var(--background-light);
    }
    
    .bestsellers {
        background-color: var(--background-beige);
    }

    .product-card {
        position: relative;
        margin-bottom: 30px;
        height: 100%;
    }

    .product-image {
        position: relative;
        overflow: hidden;
        margin-bottom: 15px;
    }

    .product-image img {
        width: 100%;
        height: 300px;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .product-card:hover .product-image img {
        transform: scale(1.05);
    }

    .product-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background-color: var(--accent-color);
        color: var(--primary-color);
        font-family: var(--font-secondary);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 5px 10px;
    }

    .product-badge.bestseller {
        background-color: var(--primary-color);
        color: white;
    }

    .product-actions {
        position: absolute;
        bottom: 20px;
        left: 0;
        width: 100%;
        display: flex;
        justify-content: center;
        gap: 10px;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    .product-card:hover .product-actions {
        opacity: 1;
        transform: translateY(0);
    }

    .product-actions .btn-primary,
    .product-actions .btn-secondary {
        width: 40px;
        height: 40px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .product-info {
        text-align: center;
        padding: 10px;
    }

    .product-category {
        color: var(--text-light);
        font-size: 0.9rem;
        display: block;
        margin-bottom: 5px;
    }

    .product-title {
        font-size: 1.1rem;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .product-title a {
        color: var(--primary-color);
        transition: color 0.3s ease;
        text-decoration: none;
    }

    .product-title a:hover {
        color: var(--accent-color);
    }

    .product-price {
        color: var(--primary-color);
        font-weight: 600;
    }

    /* Our Story Section */
    .our-story {
        padding: 0;
    }

    .story-parallax {
        background: url('https://images.unsplash.com/photo-1526081347589-7fa3cb41b4b2?ixlib=rb-4.0.3&auto=format&fit=crop&w=2071&q=80') fixed center/cover;
        position: relative;
        height: 600px;
        display: flex;
        align-items: center;
    }

    .story-parallax::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
    }

    .story-content {
        position: relative;
        max-width: 600px;
        text-align: center;
        color: white;
        padding: 60px;
        margin: 0 auto;
        background-color: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .story-tag {
        font-family: var(--font-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 3px;
        display: block;
        margin-bottom: 15px;
        color: var(--accent-color);
    }

    .story-content h2 {
        font-family: var(--font-primary);
        font-size: 2.5rem;
        font-weight: 400;
        color: white;
        margin-bottom: 20px;
    }

    .story-content p {
        margin-bottom: 30px;
        line-height: 1.8;
    }

    /* Testimonials Section */
    .testimonials {
        padding: 100px 0;
        background-color: var(--background-beige);
    }
    
    .testimonial-slider {
        position: relative;
        padding: 0 60px;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .testimonial-rating {
        color: var(--accent-color);
        font-size: 1.2rem;
        margin-bottom: 15px;
    }
    
    .author-initial {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 500;
    }
    
    .leave-review-cta {
        text-align: center;
        margin-top: 40px;
    }
    
    .btn-outline.dark {
        background-color: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        padding: 12px 25px;
    }
    
    .btn-outline.dark:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    /* Review Modal Styling */
    .review-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
    }
    
    .review-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 500px;
        background-color: white;
        padding: 30px;
        border-radius: 5px;
        z-index: 1001;
        display: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .modal-title {
        font-family: var(--font-primary);
        font-size: 1.5rem;
        color: var(--primary-color);
        margin: 0;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 1.8rem;
        cursor: pointer;
        color: var(--text-light);
        transition: color 0.3s ease;
    }
    
    .close-modal:hover {
        color: var(--primary-color);
    }
    
    .review-form .form-group {
        margin-bottom: 20px;
    }
    
    .review-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--primary-color);
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 3px;
        font-family: inherit;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    select.form-control {
        appearance: none;
        background-image: url('data:image/svg+xml;utf8,<svg fill="%23333" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M7 10l5 5 5-5z"/></svg>');
        background-repeat: no-repeat;
        background-position: right 12px center;
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }
    
    .rating-input input {
        display: none;
    }
    
    .rating-input label {
        color: #ddd;
        font-size: 1.5rem;
        padding: 0 3px;
        cursor: pointer;
        display: inline-block;
        margin: 0;
        transition: color 0.3s ease;
    }
    
    .rating-input label:hover,
    .rating-input label:hover ~ label,
    .rating-input input:checked ~ label {
        color: var(--accent-color);
    }
    
    .btn-submit-review {
        background-color: var(--accent-color);
        color: var(--primary-color);
        border: none;
        padding: 12px 25px;
        font-family: var(--font-secondary);
        font-size: 0.95rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-submit-review:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    @media (max-width: 767px) {
        .testimonial-slider {
            padding: 0 40px;
        }
        
        .review-modal {
            width: 95%;
            padding: 20px;
        }
    }
</style>

<!-- JavaScript for interactive elements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hero Slider
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.slider-arrow.prev');
    const nextBtn = document.querySelector('.slider-arrow.next');
    let currentSlide = 0;
    let slideInterval;

    // Initialize the slider
    function initSlider() {
        // Set first slide as active
        slides[0].classList.add('active');
        dots[0].classList.add('active'); // Set first dot as active
        
        // Start automatic slideshow
        startSlideInterval();
        
        // Add event listeners
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        
        // Add click events to dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                goToSlide(index);
            });
        });
    }

    // Go to specific slide
    function goToSlide(index) {
        // Remove active class from current slide and dot
        slides[currentSlide].classList.remove('active');
        dots[currentSlide].classList.remove('active');
        
        // Set new slide and dot as active
        currentSlide = index;
        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
        
        // Restart interval
        resetInterval();
    }

    // Go to next slide
    function nextSlide() {
        let nextIndex = currentSlide + 1;
        if (nextIndex >= slides.length) {
            nextIndex = 0;
        }
        goToSlide(nextIndex);
    }

    // Go to previous slide
    function prevSlide() {
        let prevIndex = currentSlide - 1;
        if (prevIndex < 0) {
            prevIndex = slides.length - 1;
        }
        goToSlide(prevIndex);
    }

    // Start automatic slideshow
    function startSlideInterval() {
        slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
    }

    // Reset interval when manually changing slides
    function resetInterval() {
        clearInterval(slideInterval);
        startSlideInterval();
    }

    // Initialize the slider if elements exist
    if(slides.length > 0 && dots.length > 0) {
        initSlider();
    }
    
    // Testimonial Slider
    const testimonialTrack = document.querySelector('.testimonial-track');
    const testimonialItems = document.querySelectorAll('.testimonial-item');
    const testimonialPrev = document.querySelector('.testimonial-arrow.prev');
    const testimonialNext = document.querySelector('.testimonial-arrow.next');
    let currentTestimonial = 0;

    if (testimonialTrack && testimonialItems.length > 0) {
        // Set up testimonial slider
        function setTestimonialPosition() {
            testimonialTrack.style.transform = `translateX(-${currentTestimonial * 100}%)`;
        }

        // Next testimonial
        function nextTestimonial() {
            if (currentTestimonial < testimonialItems.length - 1) {
                currentTestimonial++;
            } else {
                currentTestimonial = 0;
            }
            setTestimonialPosition();
        }

        // Previous testimonial
        function prevTestimonial() {
            if (currentTestimonial > 0) {
                currentTestimonial--;
            } else {
                currentTestimonial = testimonialItems.length - 1;
            }
            setTestimonialPosition();
        }

        // Add event listeners
        if (testimonialNext) testimonialNext.addEventListener('click', nextTestimonial);
        if (testimonialPrev) testimonialPrev.addEventListener('click', prevTestimonial);

        // Auto scroll testimonials
        setInterval(nextTestimonial, 6000);
    }

    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            
            // AJAX request to add item to cart
            fetch('ajax/add_to_cart.php', {
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
                    const isIconButton = this.classList.contains('product-action');
                    
                    if (isIconButton) {
                        this.innerHTML = '<i class="fas fa-check"></i>';
                    } else {
                        this.innerHTML = 'Added to Cart';
                    }
                    
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        if (isIconButton) {
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
        
        // Add styles for the notification if not already present
        if (!document.getElementById('login-notification-style')) {
            const style = document.createElement('style');
            style.id = 'login-notification-style';
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
        }
        
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

    // Update cart count function - add or leave as is if it exists
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
    
    // Newsletter form submission
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            
            // TODO: Add your newsletter subscription functionality
            console.log('Newsletter subscription for:', email);
            
            // Show success message
            this.innerHTML = '<p class="success-message">Thank you for subscribing!</p>';
        });
    }
    
    // Review Modal Functionality - Fixed implementation
    const leaveReviewBtn = document.getElementById('leaveReviewBtn');
    const reviewModal = document.getElementById('reviewModal');
    const reviewModalBackdrop = document.getElementById('reviewModalBackdrop');
    const closeReviewModal = document.getElementById('closeReviewModal');
    
    // Open modal when clicking the button
    if (leaveReviewBtn) {
        leaveReviewBtn.addEventListener('click', function() {
            console.log('Review button clicked');  // Debug line
            reviewModal.style.display = 'block';
            reviewModalBackdrop.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        });
    }
    
    // Close modal functions
    function closeModal() {
        reviewModal.style.display = 'none';
        reviewModalBackdrop.style.display = 'none';
        document.body.style.overflow = ''; // Re-enable scrolling
    }
    
    if (closeReviewModal) {
        closeReviewModal.addEventListener('click', closeModal);
    }
    
    if (reviewModalBackdrop) {
        reviewModalBackdrop.addEventListener('click', closeModal);
    }
    
    // Handle review form submission
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // AJAX request to save review
            fetch('ajax/save_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    closeModal();
                    alert('Thank you! Your review has been submitted successfully.');
                    
                    // Reload page to show the new review
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your review. Please try again.');
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>