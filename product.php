<?php
require_once 'includes/session_check.php';
require_once 'config/database.php';
require_once 'includes/auth_check.php';
require_once 'includes/reviews_handler.php';
require_once 'includes/wishlist_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_GET['id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $user_id = $_SESSION['user_id']; // Make sure user is logged in
    
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
    
    // Check if item already exists in cart
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_quantity = $stmt->fetchColumn();
    
    // Check if adding this quantity would exceed available stock
    if ($existing_quantity && ($existing_quantity + $quantity) > $product_stock) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Cannot add more items. Only ' . ($product_stock - $existing_quantity) . ' more items available.'
        ]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($existing_quantity) {
            // Update existing cart item
            $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $user_id, $product_id]);
        } else {
            // Insert new cart item
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
        }
        
        // Get updated cart content
        $cart_items = [];
        $cart_total = 0;
        
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $cart_products = $stmt->fetchAll();
        
        foreach ($cart_products as $cart_product) {
            $subtotal = $cart_product['price'] * $cart_product['quantity'];
            $cart_total += $subtotal;
            $cart_items[] = [
                'id' => $cart_product['product_id'],
                'name' => $cart_product['name'],
                'quantity' => $cart_product['quantity'],
                'price' => $cart_product['price'],
                'subtotal' => $subtotal
            ];
        }
        
        $pdo->commit();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Product added to cart successfully',
            'cart_items' => $cart_items,
            'cart_total' => $cart_total
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add to cart failed: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add to cart: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle remove from cart
if (isset($_POST['remove_from_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        // Get updated cart content
        $cart_items = [];
        $cart_total = 0;
        
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $cart_products = $stmt->fetchAll();
        
        foreach ($cart_products as $cart_product) {
            $subtotal = $cart_product['price'] * $cart_product['quantity'];
            $cart_total += $subtotal;
            $cart_items[] = [
                'id' => $cart_product['product_id'],
                'name' => $cart_product['name'],
                'quantity' => $cart_product['quantity'],
                'price' => $cart_product['price'],
                'subtotal' => $subtotal
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully',
            'cart_items' => $cart_items,
            'cart_total' => $cart_total
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Remove from cart failed: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to remove from cart: ' . $e->getMessage()
        ]);
        exit;
    }
}

if (isset($_POST['order_now'])) {
    $product_id = (int)$_GET['id'];
    $quantity = max(1, (int)$_POST['quantity']);
    $user_id = $_SESSION['user_id'];

    // Check if item already exists in cart
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_quantity = $stmt->fetchColumn();

    if ($existing_quantity) {
        // Update existing cart item with the new quantity (replace, not add)
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $user_id, $product_id]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity]);
    }
    
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

// Get product rating and reviews
$product_rating = getProductRating($product_id);
$reviews = getProductReviews($product_id);
$user_review = getUserReview($_SESSION['user_id'], $product_id);
$is_in_wishlist = isInWishlist($_SESSION['user_id'], $product_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Add SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content {
            flex: 1 0 auto;
        }
        .footer {
            flex-shrink: 0;
            margin-top: auto;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            font-size: 1.5em;
            color: #ddd;
            padding: 0 0.1em;
        }
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
        .product-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stars {
            color: #ffd700;
        }
    </style>
</head>
<body>
<?php require_once 'includes/header.php'; ?>

<div class="main-content">
    <div class="container py-5">
        <div class="mb-4">
            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
        <div class="row">
            <div class="col-md-6 d-flex flex-column align-items-center">
                <div class="bg-light rounded p-3 mb-3 shadow-sm" style="width:100%;max-width:400px;min-height:350px;display:flex;align-items:center;justify-content:center;">
                    <?php if (!empty($product['image']) && $product['image'] !== 'default.png'): ?>
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="img-fluid" 
                         style="max-height:320px;max-width:100%;object-fit:contain;">
                    <?php else: ?>
                    <div class="text-center p-4" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f8f9fa;">
                        <i class="fas fa-image fa-4x text-muted"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="product-details-content">
                <h2 style="font-size:2.5rem;font-weight:700;margin-bottom: 0.5rem;"><?php echo htmlspecialchars($product['name']); ?></h2>
                <div class="mb-3" style="color:#b0b0b0;font-size:1.5rem;font-weight:600;">₱ <?php echo number_format($product['price'], 2); ?></div>
                <div class="mb-3">
                    <span class="text-warning">&#9733; &#9733; &#9733; &#9733; &#189;</span>
                    <span style="color:#b0b0b0;">5 Customer Review</span>
                </div>
                <div class="mb-4" style="max-width:500px;">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                <div class="mb-4">
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
                <div class="mb-4">
                    <div class="mb-2">Quantity</div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="width:110px;">
                            <button class="btn btn-outline-secondary" type="button" id="decrementBtn">-</button>
                            <input type="text" id="quantity" class="form-control text-center" value="1" style="max-width:40px;" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="incrementBtn">+</button>
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
                </div> <!-- End product-details-content -->
            </div>
        </div>
    </div>
    
    <!-- Reviews Section -->
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="reviews-section">
                <h3>Customer Reviews</h3>
                
                <?php if (!$user_review): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>Write a Review</h5>
                            <form method="POST">
                                <div class="form-group">
                                    <label>Rating</label>
                                    <div class="rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                            <label for="star<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="review_text">Your Review</label>
                                    <textarea name="review_text" id="review_text" class="form-control" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($review['reviewer_name']); ?></h5>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div> <!-- End reviews-section -->
            </div>
        </div>
    </div>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="fas fa-shopping-cart me-2"></i>Your Cart
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_cart_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
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
                    <div class="text-center py-4">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Your cart is empty</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Add SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
// Wrap the script in a DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', function() {

    document.getElementById('addToCartBtn').addEventListener('click', function(e) {
        e.preventDefault();
        
        // Check if product is out of stock
        const stock = <?php echo $product['stock']; ?>;
        if (stock <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Out of Stock',
                text: 'Sorry, this item is out of stock',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        
        const quantity = parseInt(document.getElementById('quantity').value);
        if (quantity > stock) {
            Swal.fire({
                icon: 'warning',
                title: 'Stock Limit',
                text: 'Not enough stock available. Only ' + stock + ' items left.',
                confirmButtonColor: '#ffc107'
            });
            return;
        }

        // Add confirmation dialog using SweetAlert2
        Swal.fire({
            title: 'Add to Cart?',
            html: `Are you sure you want to add <b>${quantity}</b> item(s) to your cart?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Yes, add to cart',
            cancelButtonText: 'No, cancel'
        }).then((result) => {
            if (result.isConfirmed) {
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
                        cartHtml += '<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th><th>Action</th></tr></thead><tbody>';
                        
                        data.cart_items.forEach(item => {
                            cartHtml += `<tr>
                                <td>${item.name}</td>
                                <td>${item.quantity}</td>
                                <td>₱${parseFloat(item.price).toFixed(2)}</td>
                                <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${item.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
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

                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Added to Cart!',
                            text: 'Product has been added to your cart successfully',
                            confirmButtonColor: '#28a745'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to add to cart',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to add to cart. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    });

    // Update removeFromCart function to use SweetAlert2
    function removeFromCart(productId) {
        Swal.fire({
            title: 'Remove Item?',
            text: 'Are you sure you want to remove this item from your cart?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, remove it',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('remove_from_cart', '1');
                formData.append('product_id', productId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart display
                        if (data.cart_items.length === 0) {
                            document.getElementById('cartModalBody').innerHTML = `
                                <div class="text-center py-4">
                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">Your cart is empty</p>
                                </div>
                            `;
                        } else {
                            let cartHtml = '<div class="table-responsive"><table class="table">';
                            cartHtml += '<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th><th>Action</th></tr></thead><tbody>';
                            
                            data.cart_items.forEach(item => {
                                cartHtml += `<tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>₱${parseFloat(item.price).toFixed(2)}</td>
                                    <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" onclick="removeFromCart(${item.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                            });
                            
                            cartHtml += '</tbody></table></div>';
                            cartHtml += '<div class="d-flex justify-content-between align-items-center mt-3">';
                            cartHtml += `<div class="h5 mb-0">Total: ₱${parseFloat(data.cart_total).toFixed(2)}</div>`;
                            cartHtml += '<a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>';
                            cartHtml += '</div>';
                            
                            document.getElementById('cartModalBody').innerHTML = cartHtml;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Removed!',
                            text: 'Item has been removed from your cart',
                            confirmButtonColor: '#28a745'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to remove item from cart',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to remove item from cart. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                });
            }
        });
    }

    // Function to close cart modal
    function closeCartModal() {
        const cartModalElement = document.getElementById('cartModal');
        const cartModal = new bootstrap.Modal(cartModalElement);
        if (cartModal) {
            cartModal.hide();
        }
    }

    // Add event listener for cart modal close button
    document.querySelector('#cartModal .btn-close').addEventListener('click', function() {
        closeCartModal();
    });

    document.getElementById('cartIcon').addEventListener('click', function(e) {
        e.preventDefault();
        const cartModalElement = document.getElementById('cartModal');
        const cartModal = new bootstrap.Modal(cartModalElement);
        cartModal.show();
    });
}); // End DOMContentLoaded listener

// Add event listeners for quantity buttons outside the DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const orderQuantityInput = document.getElementById('order_quantity');
    const incrementBtn = document.getElementById('incrementBtn');
    const decrementBtn = document.getElementById('decrementBtn');
    const maxStock = <?php echo $product['stock']; ?>;

    incrementBtn.addEventListener('click', function() {
        let currentQuantity = parseInt(quantityInput.value);
        
        if (maxStock <= 0) {
            Swal.fire({
                icon: 'error',
                title: 'Out of Stock',
                text: 'Sorry, this item is out of stock',
                confirmButtonColor: '#dc3545'
            });
            return;
        }

        if (currentQuantity < maxStock) {
            quantityInput.value = currentQuantity + 1;
            orderQuantityInput.value = quantityInput.value;
        } else {
             Swal.fire({
                icon: 'warning',
                title: 'Stock Limit',
                text: 'Maximum available stock reached (' + maxStock + ' items)',
                confirmButtonColor: '#ffc107'
            });
        }
    });

    decrementBtn.addEventListener('click', function() {
        let currentQuantity = parseInt(quantityInput.value);
        if (currentQuantity > 1) {
            quantityInput.value = currentQuantity - 1;
            orderQuantityInput.value = quantityInput.value;
        }
    });
});

</script>

<div class="footer">
    <?php require_once 'includes/footer.php'; ?>
</div>
</body>
</html> 