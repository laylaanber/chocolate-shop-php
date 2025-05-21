</div> <!-- Close the container from header.php -->

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-about">
                        <div class="footer-logo">
                            <img src="https://static.vecteezy.com/system/resources/previews/032/749/138/non_2x/organic-chocolate-or-cacao-fruit-logo-template-design-isolated-background-free-vector.jpg" alt="Chocolate Shop Logo">
                            <span>Chocolate Shop</span>
                        </div>
                        <p>Artisanal chocolates crafted with care using the finest ingredients. Each piece tells a story of tradition, passion, and exquisite flavor.</p>
                        <div class="footer-social">
                            <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-pinterest-p"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-links">
                        <h4>Shop</h4>
                        <ul>
                            <li><a href="products.php?category=1">Truffles</a></li>
                            <li><a href="products.php?category=2">Pralines</a></li>
                            <li><a href="products.php?category=3">Chocolate Bars</a></li>
                            <li><a href="products.php?category=4">Gift Boxes</a></li>
                            <li><a href="products.php?category=5">Seasonal</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <div class="footer-links">
                        <h4>Information</h4>
                        <ul>
                            <li><a href="our-story.php">Our Story</a></li>
                            <li><a href="philosophy.php">Our Philosophy</a></li>
                            <li><a href="faq.php">FAQ</a></li>
                            <li><a href="shipping.php">Shipping & Returns</a></li>
                            <li><a href="privacy.php">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="footer-contact">
                        <h4>Contact Us</h4>
                        <ul class="contact-info">
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <span>123 Chocolate Avenue<br>Sweet City, SC 12345</span>
                            </li>
                            <li>
                                <i class="fas fa-phone"></i>
                                <span>(123) 456-7890</span>
                            </li>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <span>info@chocolateshop.com</span>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>Mon - Fri: 9:00 AM - 6:00 PM<br>Sat: 10:00 AM - 4:00 PM</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="newsletter-section">
        <div class="container">
            <div class="newsletter-container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h3>Subscribe to our Newsletter</h3>
                        <p>Be the first to know about new collections and exclusive offers</p>
                    </div>
                    <div class="col-lg-6">
                        <form id="newsletterForm" class="newsletter-form">
                            <div class="input-group">
                                <input type="email" placeholder="Your email address" required>
                                <button type="submit">Subscribe</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center g-3">
                <div class="col-md-6">
                    <p class="copyright">© <?= date('Y') ?> Chocolate Shop. All rights reserved.</p>
                </div>
                <div class="col-md-6">
                    <div class="footer-payment">
                        <span>We accept:</span>
                        <div class="payment-icons">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <i class="fab fa-cc-paypal"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Footer styling inspired by La Maison du Chocolat */
    .site-footer {
        background-color: #1E1E1E;
        color: rgba(255, 255, 255, 0.8);
        position: relative;
        z-index: 1;
        margin-top: 80px;
    }
    
    .footer-top {
        padding: 70px 0 40px;
        position: relative;
    }
    
    .footer-top::before {
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
    
    .footer-logo {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .footer-logo img {
        height: 40px;
        margin-right: 15px;
    }
    
    .footer-logo span {
        font-family: var(--font-primary);
        font-size: 1.8rem;
        font-weight: 500;
        color: white;
    }
    
    .footer-about p {
        margin-bottom: 25px;
        font-size: 0.95rem;
        line-height: 1.8;
    }
    
    .footer-social {
        display: flex;
        gap: 15px;
    }
    
    .social-link {
        color: white;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transition: var(--transition);
    }
    
    .social-link:hover {
        background-color: var(--accent-color);
        color: var(--primary-color);
        transform: translateY(-3px);
    }
    
    .footer-links h4, .footer-contact h4 {
        color: white;
        font-size: 1.1rem;
        margin-bottom: 25px;
        position: relative;
        font-family: var(--font-primary);
        font-weight: 500;
    }
    
    .footer-links h4::after, .footer-contact h4::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 30px;
        height: 2px;
        background-color: var(--accent-color);
    }
    
    .footer-links ul, .contact-info {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links ul li {
        margin-bottom: 12px;
    }
    
    .footer-links ul li a {
        color: rgba(255, 255, 255, 0.8);
        transition: var(--transition);
        font-size: 0.95rem;
        position: relative;
        padding-left: 15px;
    }
    
    .footer-links ul li a::before {
        content: '›';
        position: absolute;
        left: 0;
        color: var(--accent-color);
        transition: transform 0.3s ease;
    }
    
    .footer-links ul li a:hover {
        color: var(--accent-color);
    }
    
    .footer-links ul li a:hover::before {
        transform: translateX(3px);
    }
    
    .contact-info li {
        display: flex;
        margin-bottom: 20px;
    }
    
    .contact-info li i {
        color: var(--accent-color);
        margin-right: 15px;
        margin-top: 5px;
    }
    
    .contact-info li span {
        font-size: 0.95rem;
        line-height: 1.7;
    }
    
    /* Newsletter section */
    .newsletter-section {
        background-color: #161616;
        padding: 40px 0;
        position: relative;
    }
    
    .newsletter-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(to right, rgba(209,183,138,0) 0%, rgba(209,183,138,0.5) 50%, rgba(209,183,138,0) 100%);
    }
    
    .newsletter-section::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 1px;
        background: linear-gradient(to right, rgba(209,183,138,0) 0%, rgba(209,183,138,0.5) 50%, rgba(209,183,138,0) 100%);
    }
    
    .newsletter-container h3 {
        color: white;
        font-size: 1.5rem;
        margin-bottom: 10px;
        font-family: var(--font-primary);
    }
    
    .newsletter-container p {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 0;
    }
    
    .newsletter-form {
        margin-top: 10px;
    }
    
    .newsletter-form .input-group {
        display: flex;
        max-width: 450px;
        margin-left: auto;
    }
    
    .newsletter-form input {
        flex: 1;
        height: 48px;
        padding: 10px 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background-color: rgba(255, 255, 255, 0.05);
        color: white;
        border-radius: 4px 0 0 4px;
    }
    
    .newsletter-form input::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }
    
    .newsletter-form button {
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
    
    .newsletter-form button:hover {
        background-color: #c2a677;
    }
    
    /* Footer bottom */
    .footer-bottom {
        padding: 20px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .copyright {
        margin: 0;
        font-size: 0.9rem;
    }
    
    .footer-payment {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 15px;
    }
    
    .footer-payment span {
        font-size: 0.9rem;
    }
    
    .payment-icons {
        display: flex;
        gap: 10px;
        font-size: 1.5rem;
    }
    
    @media (max-width: 991px) {
        .newsletter-container {
            text-align: center;
        }
        
        .newsletter-form .input-group {
            margin: 20px auto 0;
        }
    }
    
    @media (max-width: 767px) {
        .footer-top {
            padding: 50px 0 30px;
        }
        
        .footer-payment {
            justify-content: flex-start;
            margin-top: 10px;
        }
        
        .copyright {
            text-align: center;
        }
        
        .footer-payment {
            justify-content: center;
        }
    }
</style>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Newsletter Subscription Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.getElementById('newsletterForm');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const emailInput = this.querySelector('input[type="email"]');
            const email = emailInput.value.trim();
            
            if (email) {
                // Simulate API call
                setTimeout(() => {
                    // Show success message
                    emailInput.value = '';
                    
                    // Create toast notification
                    const toast = document.createElement('div');
                    toast.className = 'toast-notification';
                    toast.innerHTML = '<div class="toast-icon"><i class="fas fa-check-circle"></i></div><div class="toast-message">Thank you for subscribing!</div>';
                    
                    document.body.appendChild(toast);
                    
                    // Animate in
                    setTimeout(() => {
                        toast.classList.add('show');
                    }, 10);
                    
                    // Remove after delay
                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => {
                            document.body.removeChild(toast);
                        }, 300);
                    }, 3000);
                }, 500);
            }
        });
    }
});
</script>

<style>
/* Toast notification styling */
.toast-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: var(--primary-color);
    color: white;
    padding: 15px 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 9999;
}

.toast-notification.show {
    transform: translateY(0);
    opacity: 1;
}

.toast-icon {
    color: var(--accent-color);
    font-size: 1.3rem;
}

.toast-message {
    font-size: 0.95rem;
}

@media (max-width: 576px) {
    .toast-notification {
        left: 20px;
        right: 20px;
        justify-content: center;
    }
}
</style>

</body>
</html>