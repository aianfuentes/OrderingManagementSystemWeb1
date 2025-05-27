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

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Apply items per page limit
$products = array_slice($products, 0, $per_page);

// If it's an AJAX request, start output buffering before the product list HTML
if ($is_ajax) {
    ob_start();
}

// Cart modal data (only needed for non-AJAX requests)
if (!$is_ajax) {
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
}
?>
<?php if (!$is_ajax): // Only include full HTML structure for non-AJAX requests ?>
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
    body { background: white; }
    .shop-banner {
        min-height: 320px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .shop-banner .carousel-item {
        height: 320px;
    }
    .shop-banner .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: blur(2px) brightness(0.95);
        transition: all 0.5s ease;
    }
    .shop-banner:hover .carousel-item img {
        transform: scale(1.05);
        filter: blur(1px) brightness(1);
    }
    .shop-banner .carousel-caption {
        top: 50%;
        transform: translateY(-50%);
        bottom: auto;
        z-index: 2;
    }
    .shop-banner .carousel-indicators {
        z-index: 3;
    }
    .shop-banner .carousel-control-prev,
    .shop-banner .carousel-control-next {
        z-index: 3;
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
                    <h1 class="fw-bold text-dark mb-2" style="font-size:2.5rem;">Welcome to Our Shop</h1>
                    <div class="text-dark-50">Home <i class="fas fa-chevron-right mx-1" style="font-size:0.9rem;"></i> Shop</div>
                </div>
            </div>
            <div class="carousel-item">
                <img src="assets/images/products/shop-banner2.png" class="d-block w-100" alt="Shop Banner 2">
                <div class="carousel-caption">
                    <h1 class="fw-bold text-dark mb-2" style="font-size:2.5rem;">Fresh Food Daily</h1>
                    <div class="text-dark-50">Discover Our Menu</div>
                </div>
            </div>
            <div class="carousel-item">
                <img src="assets/images/products/shop-banner3.png" class="d-block w-100" alt="Shop Banner 3">
                <div class="carousel-caption">
                    <h1 class="fw-bold text-dark mb-2" style="font-size:2.5rem;">Fast Delivery</h1>
                    <div class="text-dark-50">Order Now</div>
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
                <input type="text" class="form-control form-control-sm" placeholder="Search..." name="search">
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
<?php endif; // End of non-AJAX full HTML structure ?>
<!-- Product Grid/List -->
<div class="container mb-5">
    <div id="product-list" class="row g-4">
        <?php foreach ($products as $product): ?>
        <div class="col-12 <?php echo $view === 'grid' ? 'col-sm-6 col-md-4 col-lg-3' : 'col-12'; ?>">
            <?php if ($view === 'grid'): ?>
            <a href="product.php?id=<?php echo $product['id']; ?>" class="product-card-link text-decoration-none">
                <div class="product-card-simple" style="background:#fff; border-radius:1.25rem; box-shadow:0 4px 24px rgba(0,0,0,0.08); overflow:hidden; transition:box-shadow 0.2s, transform 0.2s;">
                    <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         style="width:100%; height:220px; object-fit:cover; border-radius:1.25rem 1.25rem 0 0;">
                    <?php else: ?>
                    <div class="text-center p-4" style="width:100%; height:220px; display:flex; align-items:center; justify-content:center; background:#f8f9fa;">
                        <i class="fas fa-image fa-3x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div style="background:#f4f4f4; padding:1.5rem 1.2rem; text-align:left; border-radius:0 0 1.25rem 1.25rem;">
                        <div style="font-weight:700; font-size:1.3rem; color:#222; margin-bottom:0.3rem;"> <?php echo htmlspecialchars($product['name']); ?> </div>
                        <div style="color:#b0b0b0; font-size:1.05rem; font-weight:400; margin-bottom:0.7rem;"> <?php echo htmlspecialchars($product['description']); ?> </div>
                        <div style="font-weight:700; font-size:1.25rem; color:#222;">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
                    </div>
                </div>
            </a>
            <?php else: ?>
            <div class="card">
                <div class="row g-0">
                    <div class="col-md-2">
                        <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                        <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="img-fluid rounded-start" style="height:100%; object-fit:cover;">
                        <?php else: ?>
                        <div class="text-center p-4" style="height:100%; display:flex; align-items:center; justify-content:center; background:#f8f9fa;">
                            <i class="fas fa-image fa-3x text-muted"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-4">₱ <?php echo number_format($product['price'], 2, '.', ','); ?></div>
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
<?php
    // If it's an AJAX request, get the buffered content, clean the buffer, and exit
    if ($is_ajax) {
        echo ob_get_clean();
        exit;
    }
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
            .then(response => response.text())
            .then(html => {
                productList.innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching search results:', error);
            });
        });
    }
</script>
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
          <table class="table align-middle">x
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
</body>
</html> 