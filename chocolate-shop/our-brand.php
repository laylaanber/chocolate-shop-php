<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';
?>

<!-- Link to shared styles -->
<link rel="stylesheet" href="css/brand-pages.css">

<!-- Elegant Header Banner -->
<div class="page-banner story-banner">
    <div class="banner-overlay"></div>
    <div class="container">
        <h1>Our Brand Story</h1>
        <p class="banner-subtitle">Crafting fine chocolates with passion since 1987</p>
    </div>
</div>

<!-- Brand Story Intro Section -->
<section class="content-section story-introduction">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 decorative-border right">
                <div class="story-intro-content">
                    <div class="section-heading">
                        <span class="heading-subtitle">Our Heritage</span>
                        <h2 class="heading-title">A Passion for Chocolate Excellence</h2>
                    </div>
                    <p class="intro-lead">Founded in 1987 by master chocolatier Pierre Laurent, our journey began with a singular vision: to create extraordinary chocolate experiences that elevate the senses.</p>
                    <p>After years of apprenticeship under Europe's most celebrated chocolatiers, Pierre returned home with a dream to establish a chocolate house that would honor traditional techniques while exploring innovative flavors and designs.</p>
                    <p>What started as a small workshop has grown into an internationally recognized name in fine chocolates, yet we remain true to our founding principles: impeccable quality, artistic expression, and the pursuit of chocolate perfection.</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="portrait-frame">
                    <img src="https://images.unsplash.com/photo-1581349485608-9469926a8e5e?ixlib=rb-4.0.3&auto=format&fit=crop&w=900&q=80" alt="Our chocolate shop founder">
                    <div class="portrait-caption">
                        <p>Pierre Laurent, Founder & Master Chocolatier</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Philosophy Core Values Section -->
<section class="content-section philosophy-values">
    <div class="container">
        <div class="section-heading text-center">
            <span class="heading-subtitle">Our Philosophy</span>
            <h2 class="heading-title">What Guides Our Artistry</h2>
        </div>
        
        <p class="text-center mb-5">At Chocolate Shop, we believe that chocolate is more than a confection—it is an expression of art, culture, and heritage. Every creation that leaves our workshop embodies our unwavering commitment to excellence.</p>
        
        <div class="cards-grid">
            <div class="elegant-card">
                <div class="card-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Sustainability</h3>
                <p>We source our ingredients responsibly, working with partners who prioritize environmental stewardship and fair labor practices.</p>
            </div>
            
            <div class="elegant-card">
                <div class="card-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <h3>Excellence</h3>
                <p>Every chocolate that leaves our workshop meets our exacting standards, reflecting our commitment to uncompromising quality.</p>
            </div>
            
            <div class="elegant-card">
                <div class="card-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>Innovation</h3>
                <p>While honoring tradition, we continually explore new flavors, textures, and techniques to create unique chocolate experiences.</p>
            </div>
            
            <div class="elegant-card">
                <div class="card-icon">
                    <i class="fas fa-hands"></i>
                </div>
                <h3>Craftsmanship</h3>
                <p>Each piece is handcrafted with meticulous attention to detail, ensuring visual elegance matches exceptional taste.</p>
            </div>
        </div>
    </div>
</section>

<!-- Quote Section - Fixed signature image -->
<section class="elegant-quote">
    <div class="container">
        <div class="quote-container">
            <div class="quote-mark">"</div>
            <blockquote>
                Chocolate is not merely a confection—it is an art form with deep cultural roots, a sensorial journey, and a universal language of pleasure that transcends boundaries.
            </blockquote>
            <div class="quote-attribution">
                <p class="quote-author">Pierre Laurent</p>
                <p class="quote-title">Master Chocolatier & Founder</p>
            </div>
            <!-- Working signature image -->
            <img src="https://upload.wikimedia.org/wikipedia/commons/3/3a/Jon_Kirsch%27s_Signature.png" alt="Signature" class="quote-signature">
        </div>
    </div>
</section>

<!-- Process Section - Fixed images -->
<section class="content-section philosophy-process">
    <div class="container">
        <div class="section-heading text-center">
            <span class="heading-subtitle">Our Artisanal Process</span>
            <h2 class="heading-title">From Bean to Bonbon</h2>
        </div>
        
        <div class="process-steps">
            <div class="process-step">
                <div class="step-number">01</div>
                <div class="row align-items-center">
                    <div class="col-lg-5">
                        <div class="step-content">
                            <h3>Sourcing</h3>
                            <p>We carefully select the finest cacao varieties from sustainable farms in Ecuador, Madagascar, and Venezuela, each offering distinct flavor profiles.</p>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="image-wrapper">
                            <!-- Updated cacao harvesting image with reliable source -->
                            <img src="https://i.pinimg.com/736x/84/0b/c6/840bc6917e68f8d62a3c261d2be479c9.jpg" alt="Cacao harvesting">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="process-step">
                <div class="step-number">02</div>
                <div class="row align-items-center flex-lg-row-reverse">
                    <div class="col-lg-5">
                        <div class="step-content">
                            <h3>Roasting & Conching</h3>
                            <p>Our beans are roasted to perfection to develop complex flavors, then ground and conched for up to 72 hours to achieve remarkable smoothness.</p>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="image-wrapper">
                            <img src="https://cdn.cocoarunners.com/uploads/2021/10/roasted-beans.jpg" alt="Chocolate making process">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="process-step">
                <div class="step-number">03</div>
                <div class="row align-items-center">
                    <div class="col-lg-5">
                        <div class="step-content">
                            <h3>Crafting</h3>
                            <p>Our master chocolatiers transform the chocolate into exquisite creations—ganaches, pralinés, and bonbons—adding carefully selected ingredients.</p>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="image-wrapper">
                            <img src="https://img.freepik.com/premium-photo/closeup-molten-chocolate-ready-be-poured-into-molds_124507-150506.jpg">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="process-step">
                <div class="step-number">04</div>
                <div class="row align-items-center flex-lg-row-reverse">
                    <div class="col-lg-5">
                        <div class="step-content">
                            <h3>Finishing</h3>
                            <p>Each creation is meticulously finished by hand, ensuring flawless appearance and texture before being presented in our signature packaging.</p>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="image-wrapper">
                            <img src="https://www.ou.org/holidays/files/shutterstock_1318915214.jpg" alt="">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="content-section story-team">
    <div class="container">
        <div class="section-heading text-center">
            <span class="heading-subtitle">The Artisans</span>
            <h2 class="heading-title">Meet Our Master Chocolatiers</h2>
        </div>
        
        <div class="team-grid">
            <div class="team-member">
                <div class="member-image">
                    <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Marie Laurent">
                </div>
                <div class="member-info">
                    <h3>Marie Laurent</h3>
                    <p class="member-title">Head Chocolatier & CEO</p>
                    <p class="member-bio">Daughter of founder Pierre Laurent, Marie combines her formal training in Paris with a lifetime of chocolate immersion to lead our creative vision.</p>
                </div>
            </div>
            
            <div class="team-member">
                <div class="member-image">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Thomas Mercier">
                </div>
                <div class="member-info">
                    <h3>Thomas Mercier</h3>
                    <p class="member-title">Master of Ganaches</p>
                    <p class="member-bio">With 18 years of experience, Thomas specializes in creating our signature smooth ganaches and innovative flavor combinations.</p>
                </div>
            </div>
            
            <div class="team-member">
                <div class="member-image">
                    <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Sophie Durand">
                </div>
                <div class="member-info">
                    <h3>Sophie Durand</h3>
                    <p class="member-title">Chocolate Sculptor</p>
                    <p class="member-bio">An artist at heart, Sophie transforms chocolate into breathtaking sculptures and decorative pieces that showcase chocolate as both culinary delight and visual art.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <a href="products.php" class="btn-elegant">Discover Our Chocolates</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>