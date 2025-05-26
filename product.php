<?php
require_once 'includes/session_check.php';
require_once 'config/database.php';
require_once 'includes/auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_GET['id'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    // Check stock availability
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product_stock = $stmt->fetchColumn();
    
    if ($product_stock <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'This item is out of stock'
        ]);
        exit;
    }
    
    // Check if requested quantity is available
    if ($quantity > $product_stock) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Not enough stock available. Only ' . $product_stock . ' items left.'
        ]);
        exit;
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if adding this quantity would exceed available stock
    $cart_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
    if (($cart_quantity + $quantity) > $product_stock) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Cannot add more items. Only ' . ($product_stock - $cart_quantity) . ' more items available.'
        ]);
        exit;
    }
    
    // If we get here, we can safely add to cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    // Get updated cart content
    $cart_items = [];
    $cart_total = 0;
    
    if (!empty($_SESSION['cart'])) {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $cart_products = $stmt->fetchAll();
        
        foreach ($cart_products as $cart_product) {
            $quantity = $_SESSION['cart'][$cart_product['id']];
            $subtotal = $cart_product['price'] * $quantity;
            $cart_total += $subtotal;
            $cart_items[] = [
                'name' => $cart_product['name'],
                'quantity' => $quantity,
                'price' => $cart_product['price'],
                'subtotal' => $subtotal
            ];
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully',
        'cart_items' => $cart_items,
        'cart_total' => $cart_total
    ]);
    exit;
}

if (isset($_POST['order_now'])) {
    $product_id = (int)$_GET['id'];
    $quantity = max(1, (int)$_POST['quantity']);
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][$product_id] = $quantity;
    header('Location: checkout.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Invalid product ID.</div></div>';
    exit;
}

$product_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Product not found.</div></div>';
    exit;
}

// Get current cart items for display
$current_cart_items = [];
$current_cart_total = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cart_product_ids = array_keys($_SESSION['cart']);
    $cart_placeholders = str_repeat('?,', count($cart_product_ids) - 1) . '?';
    $cart_stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($cart_placeholders)");
    $cart_stmt->execute($cart_product_ids);
    $cart_products = $cart_stmt->fetchAll();
    
    foreach ($cart_products as $cart_product) {
        $cart_quantity = $_SESSION['cart'][$cart_product['id']];
        $subtotal = $cart_product['price'] * $cart_quantity;
        $current_cart_total += $subtotal;
        $current_cart_items[] = [
            'name' => $cart_product['name'],
            'quantity' => $cart_quantity,
            'price' => $cart_product['price'],
            'subtotal' => $subtotal
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .custom-header {
            background: #fff;
            border-bottom: 2px solid #f3eefd;
            padding: 1.5rem 0 1.5rem 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 10;
        }
        .custom-header .brand {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 700;
            font-size: 2rem;
            color: #222;
        }
        .custom-header .brand img {
            height: 44px;
            width: 44px;
            object-fit: contain;
        }
        .custom-header .nav {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }
        .custom-header .nav a {
            color: #222;
            font-weight: 500;
            font-size: 1.15rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .custom-header .nav a:hover {
            color: #b88e2f;
        }
        .custom-header .header-icons {
            display: flex;
            align-items: center;
            gap: 2.2rem;
        }
        .custom-header .header-icons a {
            color: #222;
            font-size: 1.35rem;
            transition: color 0.2s;
        }
        .custom-header .header-icons a:hover {
            color: #b88e2f;
        }
        @media (max-width: 991.98px) {
            .custom-header {
                flex-direction: column;
                gap: 1.2rem;
                padding: 1rem 0;
            }
            .custom-header .nav {
                gap: 1.2rem;
            }
            .custom-header .header-icons {
                gap: 1.2rem;
            }
        }
    </style>
</head>
<body>
<!-- Custom Modern Header -->
<header class="custom-header px-4">
    <div class="brand">
        <img src="assets/images/products/test.png" alt="FoodExpress Logo">
        Food Express
    </div>
    <nav class="nav">
        <a href="homepage.php">Home</a>
        <a href="homepage.php#shop">Shop</a>
        <a href="#">About</a>
        <a href="#">Contact</a>
    </nav>
    <div class="header-icons">
        <a href="#"><i class="fas fa-user"></i></a>
        <a href="#"><i class="fas fa-search"></i></a>
        <a href="#"><i class="far fa-heart"></i></a>
        <a href="#" class="position-relative" id="cartIcon">
            <i class="fas fa-shopping-cart"></i>
            <?php if (!empty($current_cart_items)): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo count($current_cart_items); ?>
            </span>
            <?php endif; ?>
        </a>
    </div>
</header>
<div class="container py-5">
  <div class="row">
    <div class="col-md-6 d-flex flex-column align-items-center">
      <div class="bg-light rounded p-3 mb-3 shadow-sm" style="width:100%;max-width:400px;min-height:350px;display:flex;align-items:center;justify-content:center;">
        <img src="assets/images/products/<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid" style="max-height:320px;max-width:100%;object-fit:contain;">
      </div>
    </div>
    <div class="col-md-6">
      <h2 style="font-size:2.5rem;font-weight:700;"><?php echo htmlspecialchars($product['name']); ?></h2>
      <div class="mb-2" style="color:#b0b0b0;font-size:1.5rem;font-weight:600;">₱ <?php echo number_format($product['price'], 2); ?></div>
      <div class="mb-3">
        <span class="text-warning">&#9733; &#9733; &#9733; &#9733; &#189;</span>
        <span style="color:#b0b0b0;">5 Customer Review</span>
      </div>
      <div class="mb-3" style="max-width:500px;">
        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
      </div>
      <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fas fa-box" style="color: var(--primary-color);"></i>
          <span style="font-weight: 600;">Stock Available:</span>
          <span class="badge bg-<?php echo $product['stock'] > 0 ? 'success' : 'danger'; ?>">
            <?php echo $product['stock']; ?> items
          </span>
        </div>
        <?php if ($product['stock'] <= 0): ?>
          <div class="alert alert-danger mb-3">
            <i class="fas fa-exclamation-circle"></i> This item is currently out of stock
          </div>
        <?php endif; ?>
      </div>
      <div class="mb-3">
        <div class="mb-1">Quantity</div>
        <div class="d-flex align-items-center gap-3">
          <div class="input-group" style="width:110px;">
            <button class="btn btn-outline-secondary" type="button" onclick="decrementQuantity()">-</button>
            <input type="text" id="quantity" class="form-control text-center" value="1" style="max-width:40px;" readonly>
            <button class="btn btn-outline-secondary" type="button" onclick="incrementQuantity()">+</button>
          </div>
          <span class="text-muted">pieces</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3 mb-4">
        <button id="addToCartBtn" class="btn btn-outline-dark px-4" style="font-size:1.1rem;">Add To Cart</button>
        <form method="post" action="product.php?id=<?php echo $product['id']; ?>" style="display:inline;">
          <input type="hidden" name="order_now" value="1">
          <input type="hidden" name="quantity" id="order_quantity" value="1">
          <button type="submit" class="btn btn-dark px-4" style="font-size:1.1rem;">Order Now</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="fas fa-shopping-cart me-2"></i>Your Cart
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="cartModalBody">
                <?php if (!empty($current_cart_items)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_cart_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="h5 mb-0">Total: ₱<?php echo number_format($current_cart_total, 2); ?></div>
                        <a href="checkout.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Proceed to Checkout
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>Your cart is empty
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addToCartBtn').addEventListener('click', function(e) {
    e.preventDefault();
    
    // Check if product is out of stock
    const stock = <?php echo $product['stock']; ?>;
    if (stock <= 0) {
        alert('Sorry, this item is out of stock');
        return;
    }
    
    const quantity = parseInt(document.getElementById('quantity').value);
    if (quantity > stock) {
        alert('Not enough stock available. Only ' + stock + ' items left.');
        return;
    }
    
    const formData = new FormData();
    formData.append('add_to_cart', '1');
    formData.append('quantity', quantity);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Build cart HTML
            let cartHtml = '<div class="table-responsive"><table class="table">';
            cartHtml += '<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead><tbody>';
            
            data.cart_items.forEach(item => {
                cartHtml += `<tr>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>₱${parseFloat(item.price).toFixed(2)}</td>
                    <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                </tr>`;
            });
            
            cartHtml += '</tbody></table></div>';
            cartHtml += '<div class="d-flex justify-content-between align-items-center mt-3">';
            cartHtml += `<div class="h5 mb-0">Total: ₱${parseFloat(data.cart_total).toFixed(2)}</div>`;
            cartHtml += '<a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>';
            cartHtml += '</div>';
            
            // Update cart modal and show it
            document.getElementById('cartModalBody').innerHTML = cartHtml;
            const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
            cartModal.show();
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add to cart. Please try again.');
    });
});

// Also update the increment function to check stock
function incrementQuantity() {
    const input = document.getElementById('quantity');
    const orderInput = document.getElementById('order_quantity');
    const currentQuantity = parseInt(input.value);
    const maxStock = <?php echo $product['stock']; ?>;
    
    if (maxStock <= 0) {
        alert('Sorry, this item is out of stock');
        return;
    }
    
    if (currentQuantity < maxStock) {
        input.value = currentQuantity + 1;
        orderInput.value = input.value;
    } else {
        alert('Maximum available stock reached (' + maxStock + ' items)');
    }
}

function decrementQuantity() {
    const input = document.getElementById('quantity');
    const orderInput = document.getElementById('order_quantity');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
        orderInput.value = input.value;
    }
}

document.getElementById('cartIcon').addEventListener('click', function(e) {
    e.preventDefault();
    const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
    cartModal.show();
});
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
<?php require_once 'includes/footer.php'; ?> 
</body>
</html> 