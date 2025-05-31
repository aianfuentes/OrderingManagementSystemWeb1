<?php

// Start output buffering at the very beginning
ob_start();

session_start();

require_once 'includes/session_check.php';
require_once 'includes/auth_check.php';
require_once 'config/database.php';
require_once 'includes/cart_handler.php';

// Initialize cart handler
$cartHandler = new CartHandler($pdo, $_SESSION['user_id'] ?? null); // Handle case where user_id might not be set yet

// Check if it's an AJAX request specifically for adding to cart
$is_add_to_cart_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_POST['add_to_cart']);

// --- Handle adding to cart (for AJAX) ---
// This should be at the very top to ensure no other output interferes
if ($is_add_to_cart_ajax) {
    // Clear any potential output buffered so far
    ob_clean();

    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $user_id = $_SESSION['user_id'] ?? null; // Use null coalescing to avoid undefined index
    
    // Validate inputs and user session
    if ($product_id === false || $product_id === null || $quantity === false || $quantity === null || $quantity <= 0 || $user_id === null) {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'message' => 'Invalid request or user not logged in.']);
         exit;
    }

    $response = ['success' => false, 'message' => 'Failed to add to cart'];

    try {
        // Check stock availability
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product_stock = $stmt->fetchColumn();

        if ($product_stock === false || $product_stock <= 0) {
            $response['message'] = "This item is out of stock";
        } else {
            // Get current cart quantity using the handler
            $cart_items = $cartHandler->getCartItems();
            $current_quantity = 0;
            foreach ($cart_items as $item) {
                if ($item['product_id'] == $product_id) {
                    $current_quantity = $item['quantity'];
                    break;
                }
            }

            // Check if adding this quantity would exceed available stock
            if (($current_quantity + $quantity) > $product_stock) {
                $response['message'] = "Cannot add more items. Only " . ($product_stock - $current_quantity) . " more items available.";
            } else {
                // Attempt to add to cart via handler
                if ($cartHandler->addToCart($product_id, $quantity)) {
                    $response['success'] = true;
                    $response['message'] = "Product added to cart successfully!";
                    $response['cart_count'] = $cartHandler->getCartCount();
                } else {
                     $response['message'] = 'Error adding item to cart via handler.';
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = "Failed to add to cart: " . $e->getMessage();
        error_log("Add to Cart Error (homepage.php): " . $e->getMessage());
    }

    // Always return JSON and exit for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit; // Crucial: Stop processing the rest of the page for AJAX
}

// --- End of AJAX Handle adding to cart ---

// If not an AJAX request for adding to cart, proceed with normal page rendering

// Get filter parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$view = isset($_GET['view']) ? $_GET['view'] : 'grid';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 16;

// Check for search query
$search_query = $_GET['search'] ?? '';

// Base query
$query = "SELECT * FROM products WHERE stock > 0";
$params = [];

// Apply search filter if search query is present
if (!empty($search_query)) {
    $query .= " AND name LIKE ?";
    $params[] = '%' . $search_query . '%';
}

// Apply sorting
switch ($sort) {
    case 'price_low_high':
        $query .= " ORDER BY price ASC";
        break;
    case 'price_high_low':
        $query .= " ORDER BY price DESC";
        break;
    case 'name_az':
        $query .= " ORDER BY name ASC";
        break;
    default:
        $query .= " ORDER BY created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Apply items per page limit - This logic might need adjustment if you implement pagination properly
// For now, we just limit the displayed results
$products = array_slice($products, 0, $per_page);

// Cart modal data (only needed for non-AJAX requests)
$cart_items = [];
$cart_total = 0;

if (isset($_SESSION['user_id'])) {
    $cart_items = $cartHandler->getCartItems();
    $cart_total = $cartHandler->getCartTotal();
}

// If AJAX request for search, return only the product list HTML
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && isset($_GET['search'])) {
    ob_clean();
    ?>
    <?php foreach (
        $products as $product): ?>
        <div class="col-12 <?php echo $view === 'grid' ? 'col-sm-6 col-md-4 col-lg-3' : 'col-12'; ?>">
            <?php if ($view === 'grid'): ?>
            <div class="product-card">
                <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-card-img">
                </a>
                <?php else: ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                    <div class="product-card-img d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-image fa-3x text-muted"></i>
                    </div>
                </a>
                <?php endif; ?>
                <div class="product-card-stock <?php echo $product['stock'] > 10 ? 'in-stock' : ($product['stock'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                    <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                </div>
                <div class="product-card-body">
                    <h3 class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="product-card-description"><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="product-card-price">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
                </div>
                <div class="product-card-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary btn-sm view-details-btn" data-id="<?php echo $product['id']; ?>" data-bs-toggle="modal" data-bs-target="#productDetailsModal">
                            <i class="fas fa-eye me-1"></i> View Details
                        </button>
                        <?php if ($product['stock'] > 0): ?>
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-3">
                <div class="row g-0">
                    <div class="col-md-3">
                        <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                            <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="img-fluid rounded-start h-100" style="object-fit: cover;">
                        </a>
                        <?php else: ?>
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($product['description']); ?></p>
                                </div>
                                <span class="badge <?php echo $product['stock'] > 10 ? 'bg-success' : ($product['stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                    <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-4">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary view-details-btn" data-id="<?php echo $product['id']; ?>" data-bs-toggle="modal" data-bs-target="#productDetailsModal">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                    <?php if ($product['stock'] > 0): ?>
                                    <button type="submit" name="add_to_cart" class="btn btn-primary add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php
    exit;
}
?>

<?php // Only include full HTML structure for non-AJAX requests ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Ordering System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
    body, html {
        margin: 0;
        padding: 0;
    }
    body { background: #f8f9fa; }
    .shop-banner {
        min-height: 320px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        background-color: #e9ecef; /* fallback background */
    }
    .shop-banner .carousel-item {
        height: 320px;
        position: relative; /* Needed for caption positioning */
    }
    .shop-banner .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: brightness(0.8); /* Darken image slightly for text */
        transition: transform 0.5s ease, filter 0.5s ease;
    }
    .shop-banner:hover .carousel-item img {
        transform: scale(1.05);
        filter: brightness(0.9); /* Less dark on hover */
    }
    .shop-banner .carousel-caption {
        top: 50%;
        transform: translateY(-50%);
        bottom: auto;
        z-index: 2;
        color: #fff;
        text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        left: 0;
        right: 0;
        text-align: center;
        padding: 0 15%;
    }
    .shop-banner .carousel-caption h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: inherit;
        text-align: center;
    }
    .shop-banner .carousel-caption div {
        color: rgba(255,255,255,0.9);
        font-size: 1.1rem;
        text-align: center;
    }
    .shop-banner .carousel-indicators {
        z-index: 3;
    }
    .shop-banner .carousel-control-prev,
    .shop-banner .carousel-control-next {
        z-index: 3;
        width: 5%; /* Make controls less wide */
    }
    .shop-banner .carousel-control-prev-icon,
    .shop-banner .carousel-control-next-icon {
        background-color: rgba(0,0,0,0.3); /* Add background to icons */
        border-radius: 50%; /* Make background circular */
        padding: 1rem; /* Add some padding */
        box-sizing: content-box; /* Prevent padding affecting size */
    }
    .product-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: all 0.3s ease;
        height: 100%;
        position: relative;
    }
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .product-card-img {
        height: 250px;
        width: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    .product-card:hover .product-card-img {
        transform: scale(1.05);
    }
    .product-card-body {
        padding: 1.5rem;
    }
    .product-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 3rem;
    }
    .product-card-description {
        color: #666;
        font-size: 0.95rem;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.8rem;
    }
    .product-card-price {
        font-size: 1.35rem;
        font-weight: 700;
        color: #2c3e50;
    }
    .product-card-stock {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255,255,255,0.9);
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .product-card-stock.in-stock {
        color: #28a745;
    }
    .product-card-stock.low-stock {
        color: #ffc107;
    }
    .product-card-stock.out-of-stock {
        color: #dc3545;
    }
    .product-card-actions {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1rem 1.5rem;
        background: linear-gradient(to top, rgba(255,255,255,1) 0%, rgba(255,255,255,0.9) 100%);
        opacity: 0;
        transform: translateY(100%);
        transition: all 0.3s ease;
    }
    .product-card:hover .product-card-actions {
        opacity: 1;
        transform: translateY(0);
    }
    .filter-bar {
        background: #fff;
        border-bottom: 1px solid #eee;
        padding: 1rem 0;
        margin-bottom: 2rem;
    }
    .filter-bar .form-control,
    .filter-bar .form-select {
        border: 1px solid #e0e0e0;
        border-radius: 0.5rem;
        padding: 0.5rem 1rem;
    }
    .filter-bar .form-control:focus,
    .filter-bar .form-select:focus {
        box-shadow: none;
        border-color: #80bdff;
    }
    @media (max-width: 767.98px) {
        .product-card-img {
            height: 200px;
        }
        .product-card-title {
            font-size: 1.1rem;
        }
        .product-card-price {
            font-size: 1.2rem;
        }
    }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<!-- Banner Section -->
<div class="shop-banner position-relative mb-4">
    <div id="shopBannerCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#shopBannerCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#shopBannerCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#shopBannerCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="assets/images/products/shop-banner.png" class="d-block w-100" alt="Shop Banner 1">
                <div class="carousel-caption">
                    <h1 class="fw-bold mb-2">Welcome to Our Shop</h1>
                    <div>Home <i class="fas fa-chevron-right mx-1" style="font-size:0.9rem;"></i> Shop</div>
                </div>
            </div>
            <div class="carousel-item">
                <img src="assets/images/products/shop-banner2.png" class="d-block w-100" alt="Shop Banner 2">
                <div class="carousel-caption">
                    <h1 class="fw-bold mb-2">Fresh Food Daily</h1>
                    <div>Discover Our Menu</div>
                </div>
            </div>
            <div class="carousel-item">
                <img src="assets/images/products/shop-banner3.png" class="d-block w-100" alt="Shop Banner 3">
                <div class="carousel-caption">
                    <h1 class="fw-bold mb-2">Fast Delivery</h1>
                    <div>Order Now</div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#shopBannerCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#shopBannerCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<!-- Filter/Sort Bar -->
<div class="filter-bar py-3 mb-4 border-bottom">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <div class="btn-group">
                <a href="?view=grid<?php echo $sort !== 'default' ? '&sort=' . $sort : ''; ?><?php echo $per_page !== 16 ? '&per_page=' . $per_page : ''; ?>" 
                   class="btn btn-outline-secondary btn-sm <?php echo $view === 'grid' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                </a>
                <a href="?view=list<?php echo $sort !== 'default' ? '&sort=' . $sort : ''; ?><?php echo $per_page !== 16 ? '&per_page=' . $per_page : ''; ?>" 
                   class="btn btn-outline-secondary btn-sm <?php echo $view === 'list' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                </a>
            </div>
            <span class="divider"></span>
            <span class="ms-1 text-muted">Showing 1–<?php echo count($products); ?> of <?php echo count($products); ?> results</span>
        </div>
        <!-- Search Bar -->
        <form action="homepage.php" method="GET" class="d-flex">
            <div class="input-group" style="width: 300px;">
                <input type="text" class="form-control form-control-sm" placeholder="Search..." name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        <div class="d-flex align-items-center gap-2">
            <span>Sort by</span>
            <select class="form-select form-select-sm" style="width:150px;" onchange="window.location.href=this.value">
                <?php
                $sort_options = [
                    'default' => 'Default',
                    'price_low_high' => 'Price: Low to High',
                    'price_high_low' => 'Price: High to Low',
                    'name_az' => 'Name: A-Z'
                ];
                foreach ($sort_options as $value => $label) {
                    $selected = $sort === $value ? 'selected' : '';
                    $url = "?sort=$value" . ($per_page !== 16 ? "&per_page=$per_page" : '') . ($view !== 'grid' ? "&view=$view" : '');
                    echo "<option value=\"$url\" $selected>$label</option>";
                }
                ?>
            </select>
        </div>
    </div>
</div>
<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterModalLabel">Filter Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="filterForm" method="GET">
                    <div class="mb-3">
                        <label class="form-label">Price Range</label>
                        <div class="d-flex gap-2">
                            <input type="number" class="form-control" name="min_price" placeholder="Min" min="0">
                            <input type="number" class="form-control" name="max_price" placeholder="Max" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Availability</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="in_stock" id="inStock" checked>
                            <label class="form-check-label" for="inStock">In Stock</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="filterForm" class="btn btn-primary">Apply Filters</button>
            </div>
        </div>
    </div>
</div>
<?php // Start of non-AJAX HTML content ?>
<!-- Product Grid/List -->
<div class="container mb-5">
    <div id="product-list" class="row g-4">
        <?php foreach ($products as $product): ?>
        <div class="col-12 <?php echo $view === 'grid' ? 'col-sm-6 col-md-4 col-lg-3' : 'col-12'; ?>">
            <?php if ($view === 'grid'): ?>
            <div class="product-card">
                <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-card-img">
                </a>
                <?php else: ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                    <div class="product-card-img d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-image fa-3x text-muted"></i>
                    </div>
                </a>
                <?php endif; ?>
                
                <div class="product-card-stock <?php echo $product['stock'] > 10 ? 'in-stock' : ($product['stock'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                    <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                </div>

                <div class="product-card-body">
                    <h3 class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="product-card-description"><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="product-card-price">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
                </div>

                <div class="product-card-actions">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary btn-sm view-details-btn" data-id="<?php echo $product['id']; ?>" data-bs-toggle="modal" data-bs-target="#productDetailsModal">
                            <i class="fas fa-eye me-1"></i> View Details
                        </button>
                        <?php if ($product['stock'] > 0): ?>
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-cart-plus me-1"></i> Add to Cart
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-3">
                <div class="row g-0">
                    <div class="col-md-3">
                        <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                            <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="img-fluid rounded-start h-100" style="object-fit: cover;">
                        </a>
                        <?php else: ?>
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($product['description']); ?></p>
                                </div>
                                <span class="badge <?php echo $product['stock'] > 10 ? 'bg-success' : ($product['stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                    <?php echo $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock'); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-4">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary view-details-btn" data-id="<?php echo $product['id']; ?>" data-bs-toggle="modal" data-bs-target="#productDetailsModal">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                    <?php if ($product['stock'] > 0): ?>
                                    <button type="submit" name="add_to_cart" class="btn btn-primary add-to-cart-form" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="productDetailsContent">
        <!-- Product details will be loaded here via AJAX -->
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading product details...</p>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cartModalLabel"><i class="fas fa-shopping-cart me-2"></i>Your Cart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (!empty($cart_items)): ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cart_items as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>₱ <?php echo number_format($item['price'], 2, '.', ','); ?></td>
                <td>₱ <?php echo number_format($item['subtotal'], 2, '.', ','); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="fw-bold fs-5">Total: ₱ <?php echo number_format($cart_total, 2, '.', ','); ?></div>
          <a href="checkout.php" class="btn btn-primary">Go to Checkout</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center mb-0">Your cart is empty.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script to handle Search AJAX
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        const productList = document.getElementById('product-list');

        if (searchInput && productList) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value;

                fetch(`homepage.php?search=${encodeURIComponent(searchTerm)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                    }
                })
                .then(response => {
                     if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     return response.text(); // Get response as text (HTML)
                })
                .then(html => {
                    productList.innerHTML = html; // Update product list HTML
                })
                .catch(error => {
                    console.error('Error fetching search results:', error);
                    // Optionally display an error message to the user
                });
            });
        }

        // Script to handle Product Details Modal AJAX
        const productDetailsModal = document.getElementById('productDetailsModal');
        const productDetailsContent = document.getElementById('productDetailsContent');

        // Add event listener only if the modal element exists
        if (productDetailsModal) {
            productDetailsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const productId = button.getAttribute('data-id');

                // Clear previous content and show loading spinner
                productDetailsContent.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading product details...</p>
                    </div>
                `;

                fetch(`get_product_details.php?id=${productId}`)
                    .then(response => {
                         if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                         }
                         return response.json(); // Expect JSON response
                    })
                    .then(data => {
                        console.log('Fetched data:', data);
                        if (data.success && data.product) {
                            const product = data.product;
                            productDetailsContent.innerHTML = `
                                <div class="row">
                                    <div class="col-md-5">
                                        ${product.image && product.image !== 'default.png' ? 
                                            `<img src="assets/images/products/${product.image}" class="img-fluid rounded" alt="${product.name}">` :
                                            `<div class="h-100 d-flex align-items-center justify-content-center bg-light rounded"><i class="fas fa-image fa-5x text-muted"></i></div>`
                                        }
                                    </div>
                                    <div class="col-md-7">
                                        <h3>${product.name}</h3>
                                        <p class="text-muted">${product.description}</p>
                                        <h4 class="text-primary">₱ ${parseFloat(product.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</h4>
                                        <p><strong>Stock:</strong> ${product.stock > 10 ? 'In Stock' : (product.stock > 0 ? 'Low Stock' : 'Out of Stock')}</p>
                                        ${product.stock > 0 ? 
                                            `<form action="" method="POST" class="d-inline add-to-cart-form"> <!-- Use homepage.php for add to cart from modal -->
                                                <input type="hidden" name="product_id" value="${product.id}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" name="add_to_cart" class="btn btn-primary mt-3 add-to-cart-form" data-product-id="${product.id}"> <!-- Add class for event delegation -->
                                                    <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                                </button>
                                            </form>` : '<p class="text-danger">This item is out of stock.</p>'}
                                    </div>
                                </div>
                            `;
                             // Re-attach event listeners to the new Add to Cart button in the modal
                             attachAddToCartListeners();

                        } else {
                            productDetailsContent.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load product details.'}</div>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching product details:', error);
                        productDetailsContent.innerHTML = '<div class="alert alert-danger">Failed to load product details.</div>';
                    });
            });
        }

        // Function to attach event listeners to Add to Cart forms (using event delegation now)
        function attachAddToCartListeners() {
            document.body.removeEventListener('click', handleAddToCartDelegation);
            document.body.addEventListener('click', handleAddToCartDelegation);
        }

        // Unified delegated event handler for Add to Cart button clicks
        function handleAddToCartDelegation(e) {
            const submitButton = e.target.closest('.add-to-cart-form');
            if (submitButton) {
                e.preventDefault();
                const productId = submitButton.getAttribute('data-product-id');
                const quantity = 1;
                if (!productId) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Could not add product to cart. Product information missing.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                    return;
                }
                const originalButtonHtml = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';
                submitButton.classList.add('adding');
                const formData = new FormData();
                formData.append('add_to_cart', '1');
                formData.append('product_id', productId);
                formData.append('quantity', quantity);
                fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonHtml;
                    submitButton.classList.remove('adding');
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const cartCountElement = document.querySelector('.shop-header .badge');
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                            if (data.cart_count > 0) cartCountElement.classList.remove('d-none');
                            else cartCountElement.classList.add('d-none');
                        }
                        Swal.fire({ icon: 'success', title: 'Added to Cart!', text: data.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                        if (typeof window.fetchCartDataAndRenderModal === 'function') window.fetchCartDataAndRenderModal();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error Adding to Cart', text: data.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                    }
                })
                .catch(error => {
                    Swal.fire({ icon: 'error', title: 'Request Failed', text: 'An error occurred while adding to cart. Please try again. Check console for details.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
                });
            }
        }

        // Attach listeners initially using delegation
        attachAddToCartListeners();

        // Also update the script for the quantity buttons if they are on this page (they seem to be in product.php now, but keeping this for completeness if needed elsewhere)
        function incrementQuantity(inputElementId) {
            const input = document.getElementById(inputElementId);
            if (input) {
                let value = parseInt(input.value);
                input.value = value + 1;
            }
        }

        function decrementQuantity(inputElementId) {
             const input = document.getElementById(inputElementId);
             if (input) {
                let value = parseInt(input.value);
                if (value > 1) {
                    input.value = value - 1;
                }
             }
        }
    }); // End DOMContentLoaded
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the carousel with specific options
        const shopBannerCarousel = document.getElementById('shopBannerCarousel');
        if (shopBannerCarousel) {
            const carousel = new bootstrap.Carousel(shopBannerCarousel, {
                interval: 3000, // Change slide every 3 seconds
                wrap: true, // Continuous loop
                keyboard: true, // Enable keyboard controls
                pause: 'hover', // Pause on mouse hover
                touch: true // Enable touch swiping on mobile
            });
        }
    });
</script>

<!-- Add SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
</body>
</html> 