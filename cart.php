<?php
require_once 'includes/header.php';

// Get cart items with product details
$stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.image 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cart_items)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h5>Your cart is empty</h5>
                                <p class="text-muted">Add some items to your cart to see them here</p>
                                <a href="homepage.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                             class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                                        <div class="ms-3">
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                                <td>
                                                    <div class="input-group" style="width: 120px;">
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                onclick="updateQuantity(<?php echo $item['id']; ?>, 'decrease')">-</button>
                                                        <input type="number" class="form-control form-control-sm text-center" 
                                                               value="<?php echo $item['quantity']; ?>" 
                                                               min="1" max="99" 
                                                               onchange="updateQuantity(<?php echo $item['id']; ?>, 'set', this.value)">
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                onclick="updateQuantity(<?php echo $item['id']; ?>, 'increase')">+</button>
                                                    </div>
                                                </td>
                                                <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                <td>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span>₱<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery Fee</span>
                            <span>₱50.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total</strong>
                            <strong>₱<?php echo number_format($cart_total + 50, 2); ?></strong>
                        </div>
                        <a href="checkout.php" class="btn btn-primary w-100" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>
                            <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateQuantity(cartId, action, value = null) {
    let quantity;
    const input = event.target.parentElement.querySelector('input');
    
    if (action === 'increase') {
        quantity = parseInt(input.value) + 1;
    } else if (action === 'decrease') {
        quantity = parseInt(input.value) - 1;
    } else if (action === 'set') {
        quantity = parseInt(value);
    }
    
    if (quantity < 1) quantity = 1;
    if (quantity > 99) quantity = 99;
    
    fetch('update_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `cart_id=${cartId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error updating cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating cart');
    });
}

function removeFromCart(cartId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `cart_id=${cartId}&action=remove`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error removing item');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing item');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?> 