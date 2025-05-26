<?php
require_once 'includes/session_check.php';
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Friendly empty cart message instead of redirect
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo '<div class="container mt-5"><div class="alert alert-info">Your cart is empty. <a href="homepage.php">Go shopping</a>.</div></div>';
    require_once 'includes/customer_footer.php';
    exit();
}
require_once 'includes/auth_check.php';
require_once 'config/database.php';

// Handle order submission
if (isset($_POST['place_order'])) {
    try {
        $pdo->beginTransaction();
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total_amount, status, created_at) 
            VALUES (?, ?, 'pending', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $_POST['total_amount']]);
        $order_id = $pdo->lastInsertId();
        // Add order items
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            // Get product price and stock
            $product_stmt = $pdo->prepare("SELECT price, stock, name FROM products WHERE id = ?");
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch();
            // Double-check stock
            if ($product['stock'] < $quantity) {
                throw new Exception("Not enough stock for " . htmlspecialchars($product['name']));
            }
            $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
            // Update product stock
            $update_stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $update_stmt->execute([$quantity, $product_id]);
        }
        $pdo->commit();
        unset($_SESSION['cart']); // Only clear cart after successful order
        header("Location: order_confirmation.php?id=" . $order_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to place order: " . $e->getMessage();
    }
}
// Get cart items
$cart_items = [];
$cart_total = 0;
$product_ids = array_keys($_SESSION['cart']);
$placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($product_ids);
$cart_products = $stmt->fetchAll();
foreach ($cart_products as $product) {
    $quantity = $_SESSION['cart'][$product['id']];
    $subtotal = $product['price'] * $quantity;
    $cart_items[] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'subtotal' => $subtotal
    ];
    $cart_total += $subtotal;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Ordering Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
        }
        .shop-header, .shop-banner {
            margin: 0 !important;
            padding: 0 !important;
            width: 100vw;
            max-width: 100vw;
        }
        body { background: #f7f8fa; }
        .shop-header {
            border-bottom: 1px solid #eee;
            background: #f9fafc;
            padding-top: 2rem !important;
            padding-bottom: 2rem !important;
        }
        .shop-header .nav-link { font-size: 1.1rem; }
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
        .banner-content img {
            height: 40px;
            margin-bottom: 0.5rem;
        }
        .banner-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 0.5rem;
        }
        .banner-content .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 0;
            font-size: 1.1rem;
            color: #222;
            justify-content: center;
        }
        .banner-content .breadcrumb-item.active {
            color: #b0b0b0;
        }
        @media (max-width: 991.98px) {
            .custom-header {
                flex-direction: column;
                gap: 1.2rem;
                padding: 1rem 0 !important;
            }
            .custom-header .nav {
                gap: 1.2rem;
            }
            .custom-header .header-icons {
                gap: 1.2rem;
            }
            .shop-banner { min-height: 120px; }
            .banner-content h1 { font-size: 2rem; }
        }
        .checkout-container { max-width: 1100px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.04); padding: 40px 30px; }
        .checkout-title { font-size: 2rem; font-weight: 700; margin-bottom: 2rem; }
        .form-label { font-weight: 600; }
        .order-summary { background: #fafbfc; border-radius: 12px; padding: 32px 24px; }
        .order-summary h5 { font-weight: 700; margin-bottom: 1.5rem; }
        .order-summary .summary-row { display: flex; justify-content: space-between; margin-bottom: 0.7rem; }
        .order-summary .summary-total { color: #e3a800; font-size: 1.3rem; font-weight: 700; }
        .order-summary .product-title { font-weight: 600; }
        .order-summary .product-qty { color: #888; font-size: 0.95rem; }
        .order-summary .payment-option { margin-bottom: 1rem; }
        .order-summary .payment-option label { font-weight: 500; margin-left: 0.5rem; }
        .order-summary .policy { font-size: 0.95rem; color: #888; margin-top: 1.5rem; }
        .order-summary .policy b { color: #222; }
        .order-summary .btn { font-size: 1.1rem; border-radius: 2rem; padding: 0.7rem 2.5rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
<!-- Modern Shop Header -->
<header class="shop-header bg-white shadow-sm d-flex align-items-center justify-content-between py-3" style="width:100vw;">
    <div class="d-flex align-items-center gap-2 ps-4">
        <img src="assets/images/products/test.png" alt="FoodExpress Logo" style="height:44px;width:44px;object-fit:contain;">
        <span class="fw-bold fs-4" style="letter-spacing:1px;">Food Express</span>
    </div>
    <nav class="d-none d-md-flex gap-4 align-items-center">
        <a href="homepage.php" class="nav-link text-dark fw-semibold">Home</a>
        <a href="about.php" class="nav-link text-dark fw-semibold">About</a>
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
<!-- Banner Section -->
<div class="shop-banner position-relative mb-4">
    <div class="banner-bg"></div>
    <div class="banner-content position-absolute top-50 start-50 translate-middle text-center w-100" style="z-index:2;">
        <h1 class="fw-bold text-dark mb-2" style="font-size:2.5rem;">Checkout</h1>
        <div class="text-dark-50">Home <i class="fas fa-chevron-right mx-1" style="font-size:0.9rem;"></i> Checkout</div>
    </div>
</div>
<div class="container mb-5">
    <div class="checkout-container row g-0" style="background:#fff;box-shadow:0 2px 16px rgba(0,0,0,0.04);border-radius:16px;padding:40px 30px;">
        <!-- Billing Details -->
        <div class="col-md-6 pe-md-5 mb-4 mb-md-0">
            <div class="checkout-title">Billing details</div>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" required>
                </div>
                    <div class="col">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Company Name (Optional)</label>
                    <input type="text" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Country / Region</label>
                    <select class="form-select">
                        <option selected>Sri Lanka</option>
                        <option>Philippines</option>
                        <option>United States</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Street address</label>
                    <input type="text" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Town / City</label>
                    <input type="text" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Province</label>
                    <select class="form-select">
                        <option selected>Western Province</option>
                        <option>Central Province</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">ZIP code</label>
                    <input type="text" class="form-control" required>
            </div>
            </form>
        </div>
        <!-- Order Summary -->
        <div class="col-md-6">
            <div class="order-summary">
                <h5>Product <span class="float-end">Subtotal</span></h5>
                <?php foreach ($cart_items as $item): ?>
                <div class="summary-row">
                    <span class="product-title"><?php echo htmlspecialchars($item['name']); ?> <span class="product-qty">× <?php echo $item['quantity']; ?></span></span>
                    <span>₱ <?php echo number_format($item['subtotal'], 2); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱ <?php echo number_format($cart_total, 2); ?></span>
                                </div>
                <div class="summary-row mb-3">
                    <span>Total</span>
                    <span class="summary-total">₱ <?php echo number_format($cart_total, 2); ?></span>
                                </div>
                <hr>
                <div class="payment-option">
                    <input type="radio" id="bank" name="payment" checked>
                    <label for="bank">Direct Bank Transfer</label>
                    <div class="text-muted ms-4" style="font-size:0.95rem;">Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.</div>
                            </div>
                <div class="payment-option">
                    <input type="radio" id="bank2" name="payment" disabled>
                    <label for="bank2">Direct Bank Transfer</label>
                        </div>
                <div class="payment-option">
                    <input type="radio" id="cod" name="payment">
                    <label for="cod">Cash On Delivery</label>
                        </div>
                <div class="policy">
                    Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our <b>privacy policy</b>.
                </div>
                <form method="POST">
                    <input type="hidden" name="total_amount" value="<?php echo $cart_total; ?>">
                    <button type="submit" name="place_order" class="btn btn-outline-dark w-100 mt-4">Place order</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<style>
.hero-section {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 4rem 0;
    margin-top: -2rem;
    margin-bottom: 2rem;
}

.hero-section h1 {
    font-size: 2.5rem;
    margin-bottom: 1.5rem;
}

.hero-section p {
    font-size: 1.2rem;
    opacity: 0.9;
}

.product-image {
    height: 200px;
    overflow: hidden;
    border-radius: 8px;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.payment-methods {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.custom-control-label {
    cursor: pointer;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 2rem 0;
        text-align: center;
    }
    
    .hero-section h1 {
        font-size: 2rem;
    }
    
    .product-image {
        height: 150px;
    }
}
</style>

<?php
require_once 'includes/customer_footer.php';
?> 