<?php
require_once 'config/database.php';
// Move session handling to before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // In a real-world scenario, you'd send an email here
        // For this demo, we'll just display a success message
        $message = "Thank you for your message! We will get back to you soon.";
        
        // Clear form data on success
        $name = $email = $subject = $message_content = '';
    }
}

require_once 'includes/header.php';
?>

<!-- Elegant Page Banner -->
<div class="page-banner contact-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1>Contact Us</h1>
        <p class="banner-subtitle">We'd love to hear from you</p>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Contact Us</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Enhanced Contact Section -->
<section class="contact-section">
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-5">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-5">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Contact Information with Visual Enhancement -->
            <div class="col-lg-5">
                <div class="contact-info-card">
                    <div class="card-header">
                        <h2>Get in Touch</h2>
                        <div class="header-accent"></div>
                    </div>
                    
                    <p class="intro-text">We'd love to hear from you. Whether you have a question about our products, need assistance with an order, or want to learn more about our chocolate, our team is here to help.</p>
                    
                    <div class="contact-items">
                        <div class="contact-item">
                            <div class="icon-wrapper">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h3>Visit Us</h3>
                                <p>123 Chocolate Avenue<br>Sweet City, SC 12345<br>United States</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="icon-wrapper">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h3>Call Us</h3>
                                <p>(123) 456-7890</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="icon-wrapper">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h3>Email Us</h3>
                                <p>info@chocolateshop.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="icon-wrapper">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-details">
                                <h3>Opening Hours</h3>
                                <p>Monday - Friday: 9:00 AM - 6:00 PM<br>
                                   Saturday: 10:00 AM - 4:00 PM<br>
                                   Sunday: Closed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <h4>Follow Us</h4>
                        <div class="social-icons">
                            <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-pinterest-p"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Contact Form -->
            <div class="col-lg-7">
                <div class="contact-form-card">
                    <div class="card-header">
                        <h2>Send Us a Message</h2>
                        <div class="header-accent"></div>
                    </div>
                    
                    <form class="contact-form" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Your Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Your Email <span class="required">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject <span class="required">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?= htmlspecialchars($subject ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message <span class="required">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="6" required><?= htmlspecialchars($message_content ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="privacy" name="privacy" required>
                            <label class="form-check-label" for="privacy">
                                I've read and agree to the <a href="privacy.php">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-submit">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Elegant Map Section -->
<section class="map-section">
    <div class="container">
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.2176678888773!2d-73.9888787!3d40.7575421!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c259a9b30eac9f%3A0xaca05ca48ab0c3fa!2sChocolate%20shop!5e0!3m2!1sen!2sus!4v1684232975987!5m2!1sen!2sus" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>

<!-- Redesigned FAQs Section -->
<section class="faq-section">
    <div class="container">
        <div class="section-header">
            <h2>Frequently Asked Questions</h2>
            <p>Find answers to our most commonly asked questions</p>
            <div class="header-accent centered"></div>
        </div>
        
        <div class="accordion faq-accordion" id="contactFaqs">
            <div class="accordion-item">
                <h3 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        Do you offer international shipping?
                    </button>
                </h3>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#contactFaqs">
                    <div class="accordion-body">
                        <p>Yes, we currently ship to select countries internationally. Shipping rates and delivery times vary by location. During checkout, you'll see if your country is eligible for shipping and what the associated costs are.</p>
                        <p>For temperature-sensitive shipments, we use specialized packaging to ensure your chocolates arrive in perfect condition, regardless of destination.</p>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h3 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        How long will my chocolates stay fresh?
                    </button>
                </h3>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#contactFaqs">
                    <div class="accordion-body">
                        <p>Our chocolates are best enjoyed within 2-3 weeks of purchase. They should be stored in a cool, dry place away from direct sunlight and strong odors, ideally between 16-18°C (60-65°F).</p>
                        <p>We don't recommend refrigeration as it can affect texture and flavor. However, during extremely warm weather, brief refrigeration (less than 30 minutes) before serving can help maintain optimal texture.</p>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h3 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Do you offer gift wrapping?
                    </button>
                </h3>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#contactFaqs">
                    <div class="accordion-body">
                        <p>Yes, we offer elegant gift wrapping options at checkout. You can also include a personalized message card with your gift. Our signature gold gift boxes are a customer favorite and perfect for any special occasion.</p>
                        <p>For corporate gifts or bulk orders, we offer custom branding options. Please contact our customer service team for more information on corporate gifting.</p>
                    </div>
                </div>
            </div>
            
            <div class="accordion-item">
                <h3 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                        Are your chocolates suitable for people with allergies?
                    </button>
                </h3>
                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#contactFaqs">
                    <div class="accordion-body">
                        <p>Our production facility handles nuts, dairy, soy, and gluten. While we have strict protocols to prevent cross-contamination, we cannot guarantee that our products are entirely free from these allergens.</p>
                        <p>Each product page lists specific allergen information. We do offer a selection of dark chocolates that are naturally dairy-free, but they are produced in the same facility. For severe allergies, please contact us directly to discuss your specific requirements.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Refined Contact Page Styling */

/* Banner styling */
.contact-banner {
    background: url('https://images.unsplash.com/photo-1599599810769-bcde5a160d32?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80') center/cover no-repeat fixed;
    height: 400px;
    display: flex;
    align-items: center;
    position: relative;
    color: white;
    text-align: center;
}

.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
}

.contact-banner h1 {
    font-size: 3.5rem;
    font-weight: 400;
    margin-bottom: 0.5rem;
    letter-spacing: 1px;
    position: relative;
}

.banner-subtitle {
    font-family: var(--font-elegant);
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    font-style: italic;
}

.breadcrumb {
    justify-content: center;
    background: none;
    margin: 0;
    position: relative;
}

.breadcrumb-item a {
    color: var(--accent-color);
    text-decoration: none;
}

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.8);
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "•";
    color: rgba(255, 255, 255, 0.6);
}

/* Contact Section */
.contact-section {
    padding: 100px 0;
    background-color: var(--background-light);
}

/* Contact Info Card */
.contact-info-card {
    background-color: var(--background-beige);
    padding: 50px;
    height: 100%;
    border-radius: 8px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
}

.contact-info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 8px;
    background: linear-gradient(to right, var(--accent-color), var(--primary-color));
}

.contact-info-card .card-header {
    margin-bottom: 30px;
    padding: 0;
    background: none;
    border: none;
}

.contact-info-card h2 {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 15px;
    font-weight: 500;
    font-family: var(--font-primary);
}

.header-accent {
    width: 50px;
    height: 2px;
    background-color: var(--accent-color);
    margin-bottom: 30px;
}

.header-accent.centered {
    margin-left: auto;
    margin-right: auto;
}

.intro-text {
    color: var(--text-medium);
    margin-bottom: 40px;
    line-height: 1.7;
    font-size: 1.05rem;
}

.contact-items {
    margin-bottom: 40px;
}

.contact-item {
    display: flex;
    margin-bottom: 30px;
}

.contact-item:last-child {
    margin-bottom: 0;
}

.icon-wrapper {
    width: 60px;
    height: 60px;
    background-color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: var(--accent-color);
    margin-right: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.contact-item:hover .icon-wrapper {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(209, 183, 138, 0.3);
    color: var(--primary-color);
    background-color: var(--accent-color);
}

.contact-details {
    flex: 1;
}

.contact-details h3 {
    font-size: 1.2rem;
    color: var(--primary-color);
    margin-bottom: 8px;
    font-weight: 500;
    font-family: var(--font-primary);
}

.contact-details p {
    color: var(--text-medium);
    margin-bottom: 0;
    line-height: 1.6;
}

.social-links {
    margin-top: 50px;
    padding-top: 30px;
    border-top: 1px solid rgba(209, 183, 138, 0.3);
}

.social-links h4 {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 20px;
    font-weight: 500;
    font-family: var(--font-primary);
}

.social-icons {
    display: flex;
    gap: 15px;
}

.social-icon {
    width: 40px;
    height: 40px;
    background-color: white;
    color: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.social-icon:hover {
    background-color: var(--accent-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(209, 183, 138, 0.3);
}

/* Contact Form Card */
.contact-form-card {
    background-color: white;
    padding: 50px;
    border-radius: 8px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    height: 100%;
    position: relative;
    overflow: hidden;
}

.contact-form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 8px;
    background: linear-gradient(to right, var(--primary-color), var(--accent-color));
}

.contact-form-card .card-header {
    margin-bottom: 30px;
    padding: 0;
    background: none;
    border: none;
}

.contact-form-card h2 {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 15px;
    font-weight: 500;
    font-family: var(--font-primary);
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-dark);
    font-weight: 500;
    font-size: 0.95rem;
}

.required {
    color: #dc3545;
}

.form-control {
    height: auto;
    padding: 12px 15px;
    border: 1px solid rgba(209, 183, 138, 0.3);
    border-radius: 4px;
    font-family: var(--font-secondary);
    font-size: 0.95rem;
    color: var(--text-dark);
    transition: all 0.3s ease;
    width: 100%;
    background-color: rgba(209, 183, 138, 0.05);
}

.form-control:focus {
    border-color: var(--accent-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(209, 183, 138, 0.2);
}

textarea.form-control {
    resize: vertical;
    min-height: 150px;
}

.form-check {
    margin-bottom: 30px;
    padding-left: 0;
    position: relative;
}

.form-check-input {
    position: absolute;
    opacity: 0;
}

.form-check-label {
    position: relative;
    padding-left: 30px;
    color: var(--text-medium);
    cursor: pointer;
    display: inline-block;
    font-size: 0.9rem;
}

.form-check-label::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 20px;
    height: 20px;
    border: 1px solid rgba(209, 183, 138, 0.5);
    background-color: white;
    border-radius: 3px;
}

.form-check-input:checked + .form-check-label::after {
    content: '✓';
    position: absolute;
    left: 4px;
    top: -2px;
    color: var(--accent-color);
    font-size: 1.2rem;
    font-weight: bold;
}

.form-check-input:focus + .form-check-label::before {
    box-shadow: 0 0 0 3px rgba(209, 183, 138, 0.2);
}

.form-check-label a {
    color: var(--accent-color);
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: all 0.3s ease;
}

.form-check-label a:hover {
    border-bottom-color: var(--accent-color);
}

.btn-submit {
    display: inline-block;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 30px;
    padding: 12px 30px;
    font-family: var(--font-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-submit:hover {
    background-color: var(--accent-color);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(209, 183, 138, 0.3);
}

/* Map Section */
.map-section {
    padding: 0 0 100px;
    background-color: var(--background-light);
}

.map-container {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    position: relative;
}

.map-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 8px;
    background: linear-gradient(to right, var(--primary-color), var(--accent-color), var(--primary-color));
    z-index: 10;
}

/* FAQ Section */
.faq-section {
    padding: 100px 0;
    background-color: var(--background-beige);
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-header h2 {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
    font-family: var(--font-primary);
    font-weight: 400;
}

.section-header p {
    color: var(--text-medium);
    font-size: 1.1rem;
    margin-bottom: 25px;
}

.faq-accordion {
    max-width: 900px;
    margin: 0 auto;
}

.accordion-item {
    background-color: white;
    border: none;
    margin-bottom: 20px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}

.accordion-button {
    padding: 20px 25px;
    font-family: var(--font-primary);
    font-size: 1.2rem;
    font-weight: 500;
    color: var(--primary-color);
    background-color: white;
    box-shadow: none;
    position: relative;
}

.accordion-button:not(.collapsed) {
    color: var(--primary-color);
    background-color: white;
    font-weight: 600;
}

.accordion-button::after {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23D1B78A'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    transition: all 0.3s ease;
}

.accordion-button:not(.collapsed)::after {
    transform: rotate(-180deg);
}

.accordion-body {
    padding: 0 25px 25px;
}

.accordion-body p {
    color: var(--text-medium);
    line-height: 1.7;
    margin-bottom: 15px;
}

.accordion-body p:last-child {
    margin-bottom: 0;
}

/* Alert styling */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    color: #155724;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.2);
    color: #721c24;
}

.btn-close {
    font-size: 1rem;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.btn-close:hover {
    opacity: 0.8;
}

/* Responsive Adjustments */
@media (max-width: 1199px) {
    .contact-info-card,
    .contact-form-card {
        padding: 40px;
    }
}

@media (max-width: 991px) {
    .contact-section {
        padding: 80px 0;
    }
    
    .contact-info-card {
        margin-bottom: 30px;
    }
    
    .contact-banner {
        height: 350px;
    }
    
    .contact-banner h1 {
        font-size: 3rem;
    }
    
    .banner-subtitle {
        font-size: 1.3rem;
    }
}

@media (max-width: 767px) {
    .contact-section {
        padding: 60px 0;
    }
    
    .contact-info-card,
    .contact-form-card {
        padding: 30px;
    }
    
    .contact-banner {
        height: 300px;
    }
    
    .contact-banner h1 {
        font-size: 2.5rem;
    }
    
    .banner-subtitle {
        font-size: 1.2rem;
    }
    
    .icon-wrapper {
        width: 50px;
        height: 50px;
        font-size: 1.1rem;
    }
    
    .accordion-button {
        padding: 15px 20px;
        font-size: 1.1rem;
    }
    
    .accordion-body {
        padding: 0 20px 20px;
    }
    
    .faq-section {
        padding: 60px 0;
    }
    
    .section-header h2 {
        font-size: 2.2rem;
    }
}

@media (max-width: 575px) {
    .contact-banner h1 {
        font-size: 2.2rem;
    }
    
    .banner-subtitle {
        font-size: 1.1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Interactive form validation
    const contactForm = document.querySelector('.contact-form');
    
    if (contactForm) {
        const formFields = contactForm.querySelectorAll('.form-control');
        
        formFields.forEach(field => {
            // Add focus effect
            field.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
                
                // Simple validation on blur
                if (this.value.trim() === '' && this.hasAttribute('required')) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
                
                // Email validation
                if (this.type === 'email' && this.value.trim() !== '') {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(this.value)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
            });
        });
        
        // Form submission animation
        contactForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-submit');
            
            if (submitBtn) {
                submitBtn.innerHTML = 'Sending...';
                submitBtn.disabled = true;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>