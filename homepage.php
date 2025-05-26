<?php
require_once 'includes/session_check.php';
require_once 'includes/auth_check.php';
require_once 'config/database.php';
// Handle adding to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    $success = "Product added to cart successfully!";
}
// Get filter parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$view = isset($_GET['view']) ? $_GET['view'] : 'grid';
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 16;

// Base query
$query = "SELECT * FROM products WHERE stock > 0";

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
$stmt->execute();
$products = $stmt->fetchAll();

// Apply items per page limit
$products = array_slice($products, 0, $per_page);
// Cart modal data
$cart_items = [];
$cart_total = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
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
}
?>
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
    .shop-card { background: #fff; border-radius: 1.25rem; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; transition: box-shadow 0.2s, transform 0.2s; position: relative; }
    .shop-card-img { height: 220px; object-fit: cover; border-radius: 1.25rem 1.25rem 0 0; }
    .shop-card-body { min-height: 120px; }
    .shop-card-hover { opacity: 0; pointer-events: none; }
    .shop-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.13); transform: translateY(-5px) scale(1.01); }
    .shop-card:hover .shop-card-hover { opacity: 1; pointer-events: auto; }
    .shop-card-hover .btn { font-size: 1rem; border-radius: 2rem; }
    .shop-card-hover a { font-size: 1.05rem; text-decoration: none; }
    .badge { font-size: 1rem; padding: 0.5em 1em; border-radius: 1.5rem; }
    @media (max-width: 767.98px) {
        .shop-header .container { flex-direction: column; gap: 1rem; }
        .shop-banner { min-height: 120px; }
        .shop-card-img { height: 150px; }
    }
    .filter-bar { background: #f7f1e6; border-bottom: 1px solid #eee; }
    .filter-bar .form-control, .filter-bar .form-select { background: #fff; border: 1px solid #eee; color: #222; font-weight: 500; }
    .filter-bar .form-control:focus, .filter-bar .form-select:focus { box-shadow: none; border-color: #e0e0e0; }
    .filter-bar .divider { border-left: 1.5px solid #d6d6d6; height: 28px; margin: 0 1.2rem; display: inline-block; vertical-align: middle; }
    .filter-bar .text-muted, .filter-bar label { color: #222 !important; font-weight: 500; }
    .product-card-link:hover .product-card-simple {
        box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        transform: translateY(-5px) scale(1.01);
    }
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
        <a href="homepage.php" class="nav-link text-dark fw-semibold position-relative">
            Home
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                New
            </span>
        </a>
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
        <h1 class="fw-bold text-dark mb-2" style="font-size:2.5rem;">Shop</h1>
        <div class="text-dark-50">Home <i class="fas fa-chevron-right mx-1" style="font-size:0.9rem;"></i> Shop</div>
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
<!-- Product Grid/List -->
<div class="container mb-5">
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
        <div class="col-12 <?php echo $view === 'grid' ? 'col-sm-6 col-md-4 col-lg-3' : 'col-12'; ?>">
            <?php if ($view === 'grid'): ?>
            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-card-link text-decoration-none">
                <div class="product-card-simple" style="background:#fff; border-radius:1.25rem; box-shadow:0 4px 24px rgba(0,0,0,0.08); overflow:hidden; transition:box-shadow 0.2s, transform 0.2s;">
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image_path']); ?>" style="width:100%; height:220px; object-fit:cover; border-radius:1.25rem 1.25rem 0 0;">
                    <div style="background:#f4f4f4; padding:1.5rem 1.2rem; text-align:left; border-radius:0 0 1.25rem 1.25rem;">
                        <div style="font-weight:700; font-size:1.3rem; color:#222; margin-bottom:0.3rem;"> <?php echo htmlspecialchars($product['name']); ?> </div>
                        <div style="color:#b0b0b0; font-size:1.05rem; font-weight:400; margin-bottom:0.7rem;"> <?php echo htmlspecialchars($product['description']); ?> </div>
                        <div style="font-weight:700; font-size:1.25rem; color:#222;">₱ <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </a>
            <?php else: ?>
            <div class="card">
                <div class="row g-0">
                    <div class="col-md-2">
                        <img src="assets/images/products/<?php echo htmlspecialchars($product['image_path']); ?>" class="img-fluid rounded-start" style="height:100%; object-fit:cover;">
                    </div>
                    <div class="col-md-10">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-4">₱ <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View Details</a>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
                <td>₱ <?php echo number_format($item['price'], 2); ?></td>
                <td>₱ <?php echo number_format($item['subtotal'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="fw-bold fs-5">Total: ₱ <?php echo number_format($cart_total, 2); ?></div>
          <a href="checkout.php" class="btn btn-primary">Go to Checkout</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center mb-0">Your cart is empty.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html> 