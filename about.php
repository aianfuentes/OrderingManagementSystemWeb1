<?php
require_once 'includes/session_check.php';
require_once 'includes/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Food Express</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f7f8fa; }
        .about-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/images/products/shop-banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .team-member {
            background: #fff;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            height: 100%;
            text-align: center;
        }
        .team-member:hover {
            transform: translateY(-5px);
        }
        .partner-img {
            background: #f8f9fa;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            border-bottom: 1px solid #eee;
            height: 250px;
        }
        .partner-photo {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .team-info {
            padding: 1.5rem;
            text-align: center;
        }
        .team-info h4 {
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }
        .team-info .text-muted {
            font-size: 0.9rem;
            margin-bottom: 1rem;
            color: #6c757d;
        }
        .team-description {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.5;
        }
        .testimonial-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .testimonial-text {
            font-style: italic;
            margin-bottom: 1rem;
        }
        .testimonial-author {
            font-weight: 600;
            color: #2c3e50;
        }
        .location-card {
            background: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .location-card i {
            color: #2c3e50;
            margin-right: 0.5rem;
        }
        .shop-banner {
            min-height: 320px;
            position: relative;
            overflow: hidden;
            margin: 0 !important;
            padding: 0 !important;
            width: 100vw;
            max-width: 100vw;
            transition: all 0.3s ease;
        }
        .shop-banner:hover {
            transform: scale(1.01);
        }
        .shop-banner:hover .banner-bg {
            transform: scale(1.05);
            filter: blur(1px) brightness(1);
        }
        .shop-banner:hover .banner-content {
            transform: translate(-50%, -52%);
        }
        .banner-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/products/shop-banner.png') center center/cover no-repeat;
            filter: blur(2px) brightness(0.95);
            z-index: 1;
            transition: all 0.5s ease;
        }
        .banner-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            z-index: 2;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="shop-header bg-white shadow-sm d-flex align-items-center justify-content-between py-3" style="width:100vw;">
        <div class="d-flex align-items-center gap-2 ps-4">
            <img src="assets/images/products/test.png" alt="FoodExpress Logo" style="height:44px;width:44px;object-fit:contain;">
            <span class="fw-bold fs-4" style="letter-spacing:1px;">Food Express</span>
        </div>
        <nav class="d-none d-md-flex gap-4 align-items-center">
            <a href="homepage.php" class="nav-link text-dark fw-semibold">Home</a>
            <a href="about.php" class="nav-link text-dark fw-semibold position-relative">
                About
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                    New
                </span>
            </a>
            <a href="contact.php" class="nav-link text-dark fw-semibold">Contact</a>
        </nav>
        <div class="d-flex align-items-center gap-5 pe-4">
            <div class="dropdown">
                <a href="#" class="text-dark dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="customer_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>My Dashboard</a></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
            <a href="#" class="text-dark"><i class="fas fa-search"></i></a>
            <a href="#" class="text-dark"><i class="far fa-heart"></i></a>
            <a href="#" class="text-dark position-relative" data-bs-toggle="modal" data-bs-target="#cartModal"><i class="fas fa-shopping-cart"></i></a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="display-4 mb-4">About Food Express</h1>
            <p class="lead">Delivering quality food and exceptional service since 2020</p>
        </div>
    </section>

    <!-- Our Story -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-4">Our Story</h2>
                    <p class="lead mb-4">Food Express started with a simple mission: to provide delicious, high-quality food with exceptional service.</p>
                    <p>Founded in 2020, we've grown from a small local restaurant to a beloved food delivery service, serving thousands of happy customers across the city. Our commitment to quality ingredients, innovative recipes, and outstanding customer service has made us a trusted name in the food industry.</p>
                </div>
                <div class="col-md-6">
                    <img src="assets/images/products/shop-banner.png" alt="Our Story" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Team -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Our Co-Partners</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="team-member">
                        <div class="partner-img">
                            <img src="assets/images/products/partner1.jpg" alt="Co-Founder & CEO" class="partner-photo">
                        </div>
                        <div class="team-info">
                            <h4>Arante Reyson</h4>
                            <p class="text-muted">Co-Founder & CEO</p>
                            <p class="team-description">Leading our company's vision and strategic direction with extensive experience in business management.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="team-member">
                        <div class="partner-img">
                            <img src="assets/images/products/partner2.jpg" alt="Co-Founder & COO" class="partner-photo">
                        </div>
                        <div class="team-info">
                            <h4>Fuentes Ceasar Ian</h4>
                            <p class="text-muted">Co-Founder & COO</p>
                            <p class="team-description">Overseeing daily operations and ensuring smooth business processes across all departments.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="team-member">
                        <div class="partner-img">
                            <img src="assets/images/products/partner3.jpg" alt="Co-Founder & CTO" class="partner-photo">
                        </div>
                        <div class="team-info">
                            <h4>Batuto Joshua</h4>
                            <p class="text-muted">Co-Founder & CTO</p>
                            <p class="team-description">Driving technological innovation and digital transformation of our business operations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">What Our Customers Say</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"The food is always fresh and delicious. Their delivery service is prompt and reliable."</p>
                        <p class="testimonial-author">- John Wick</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"Best food delivery service in town! The quality and taste are consistently excellent."</p>
                        <p class="testimonial-author">- Melo Duo</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"Amazing customer service and the food is always hot and fresh when it arrives."</p>
                        <p class="testimonial-author">- Prince John Sanado</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Locations -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Our Locations</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="location-card">
                        <h5><i class="fas fa-map-marker-alt"></i> Main Branch</h5>
                        <p>123 Food Street,General Santos City Center<br>Open: 8:00 AM - 10:00 PM</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="location-card">
                        <h5><i class="fas fa-map-marker-alt"></i> Biringan Branch</h5>
                        <p>456 Konoha Branch , General Santos City <br>Open: 8:00 AM - 10:00 PM</p>
                    </div>  
                </div>
                <div class="col-md-4">
                    <div class="location-card">
                        <h5><i class="fas fa-map-marker-alt"></i> South Bay Branch</h5>
                        <p>789 South Road, Bagang-ga Aps<br>Open: 8:00 AM - 10:00 PM</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 