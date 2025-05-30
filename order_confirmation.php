<?php
require_once 'includes/session_check.php';
require_once 'includes/auth_check.php';
require_once 'config/database.php';

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: homepage.php');
    exit();
}

$order_id = $_GET['id'];

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, u.name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: homepage.php');
    exit();
}

require_once 'includes/header.php';

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">Thank You for Your Order!</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-4">
                        <h4><i class="icon fas fa-check"></i> Order Placed Successfully!</h4>
                        Your order has been received and is being processed.<br>
                        <strong>Order ID:</strong> #<?php echo $order_id; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <h5 class="mb-3">Order Details</h5>
                            <table class="table table-borderless mb-0">
                                <tr>
                                    <th>Order ID:</th>
                                    <td>#<?php echo $order_id; ?></td>
                                </tr>
                                <tr>
                                    <th>Order Date:</th>
                                    <td><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge badge-warning"><?php echo ucfirst($order['status']); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Order Items</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-right">Total:</th>
                                            <th>₱<?php echo number_format($order['total_amount'], 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="homepage.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Return to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/customer_footer.php';
?> 