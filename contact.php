<?php
require_once 'includes/session_check.php';
require_once 'includes/auth_check.php';
require_once 'config/database.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Here you would typically send the email or save to database
        // For now, we'll just show a success message
        $success_message = "Thank you for your message! We'll get back to you soon.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Food Express</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f7f8fa; }
        .contact-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .contact-hero {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/images/products/shop-banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        .contact-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            height: 100%;
        }
        .contact-icon {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .contact-icon i {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        .contact-form {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #2c3e50;
        }
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .social-links a {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s;
        }
        .social-links a:hover {
            background: #2c3e50;
            color: white;
        }
        .food-gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .food-gallery-item:hover {
            transform: translateY(-5px);
        }
        .food-gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .food-gallery-item:hover img {
            transform: scale(1.05);
        }
        .food-gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 1rem;
            text-align: center;
        }
        .food-gallery-overlay h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
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
            <a href="about.php" class="nav-link text-dark fw-semibold">About</a>
            <a href="contact.php" class="nav-link text-dark fw-semibold position-relative">
                Contact
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                    New
                </span>
            </a>
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
    <section class="contact-hero">
        <div class="container">
            <h1 class="display-4 mb-4">Contact Us</h1>
            <p class="lead">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        </div>
    </section>

    <!-- Food Gallery -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Customers most ordered foods</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="food-gallery-item">
                        <img src="assets/images/products/food1.jpg" alt="Delicious Food" class="img-fluid rounded">
                        <div class="food-gallery-overlay">
                            <h5>Kare-Kare</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="food-gallery-item">
                        <img src="assets/images/products/food2.jpg" alt="Delicious Food" class="img-fluid rounded">
                        <div class="food-gallery-overlay">
                            <h5>Bulalo</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="food-gallery-item">
                        <img src="assets/images/products/food3.jpg" alt="Delicious Food" class="img-fluid rounded">
                        <div class="food-gallery-overlay">
                            <h5>Authentic Sisig</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="contact-card text-center">
                        <div class="contact-icon mx-auto">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h4>Call Us</h4>
                        <p class="text-muted">+63 123 456 7890</p>
                        <p class="text-muted">Mon-Fri: 8:00 AM - 10:00 PM</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card text-center">
                        <div class="contact-icon mx-auto">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4>Email Us</h4>
                        <p class="text-muted">info@foodexpress.com</p>
                        <p class="text-muted">support@foodexpress.com</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card text-center">
                        <div class="contact-icon mx-auto">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4>Visit Us</h4>
                        <p class="text-muted"> Food Street</p>
                        <p class="text-muted">General Santoc City, Philippines</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-form">
                        <h2 class="text-center mb-4">Send Us a Message</h2>
                        
                        <?php if ($success_message): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $success_message; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Social Links -->
    <section class="py-5">
        <div class="container text-center">
            <h3 class="mb-4">Follow Us</h3>
            <div class="social-links justify-content-center">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 