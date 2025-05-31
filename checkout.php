<?php
require_once 'config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get cart items
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT c.*, p.name, p.price, p.image 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Get shipping addresses
$stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ?");
$stmt->execute([$user_id]);
$shipping_addresses = $stmt->fetchAll();

// Get payment methods
$stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE is_active = 1");
$stmt->execute();
$payment_methods = $stmt->fetchAll();

// Get user details
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$first_name = $last_name = '';
if ($user && strpos($user['name'], ' ') !== false) {
    list($first_name, $last_name) = explode(' ', $user['name'], 2);
} else if ($user) {
    $first_name = $user['name'];
}
$email = $user['email'] ?? '';

$phone = '';
// Optionally, fetch phone from a user profile table if available

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get address fields from POST
    $address_line1 = $_POST['address_line1'] ?? '';
    $address_line2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    // $country = $_POST['country'] ?? ''; // if you still have this

    // Combine into a single address string
    $full_address = $address_line1;
    if (!empty($address_line2)) $full_address .= ', ' . $address_line2;
    $full_address .= ', ' . $city . ', ' . $state . ', ' . $postal_code;

    // Use $full_address for saving to the order (e.g., in delivery_notes or a new column)
    // Remove: $shipping_address_id = $_POST['shipping_address'];
    // Remove: $delivery_notes = $_POST['delivery_notes'];

    $payment_method_id = $_POST['payment_method'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, 
                total_amount, 
                delivery_notes, 
                payment_status
            ) VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $total, $full_address]);
        $order_id = $pdo->lastInsertId();
        
        // Add order items
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, 
                    product_id, 
                    quantity, 
                    price
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id, 
                $item['product_id'], 
                $item['quantity'], 
                $item['price']
            ]);
            
            // Update product stock
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Create payment transaction
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                order_id, 
                payment_method_id, 
                amount, 
                status
            ) VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$order_id, $payment_method_id, $total]);
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        // Redirect to payment processing page
        header("Location: process_payment.php?order_id=" . $order_id);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to process order. Please try again.";
    }
}

require_once 'includes/header.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Checkout</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item active">Checkout</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Order Summary -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Order Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                        <tr>
                                            <td>
                                                <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                            <td><strong>₱<?php echo number_format($total, 2); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checkout Form -->
                <div class="col-md-4">
                    <form method="POST" action="">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Customer Details</h3>
                            </div>
                            <div class="card-body">
                                <h5>Customer Details</h5>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                                </div>
                                <hr>
                                <h5>Delivery Address</h5>
                                <div class="form-group">
                                    <label>Street Address</label>
                                    <input type="text" name="address_line1" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Apartment/Unit</label>
                                    <input type="text" name="address_line2" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="city" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>State/Province</label>
                                    <input type="text" name="state" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Payment Method</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($payment_methods as $method): ?>
                                <div class="form-check mb-3">
                                    <input type="radio" 
                                           name="payment_method" 
                                           value="<?php echo $method['id']; ?>" 
                                           class="form-check-input" 
                                           required>
                                    <label class="form-check-label">
                                        <?php echo htmlspecialchars($method['name']); ?>
                                        <small class="d-block text-muted">
                                            <?php echo htmlspecialchars($method['description']); ?>
                                        </small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-primary btn-block btn-lg">
                                    Proceed to Payment
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?> 