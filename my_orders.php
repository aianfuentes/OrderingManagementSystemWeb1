<?php
require_once 'includes/session_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is not an admin
if ($_SESSION['user_role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/auth_check.php';
require_once 'includes/customer_header.php';
require_once 'config/database.php';

// Get all customer's orders with product details
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
           GROUP_CONCAT(oi.quantity SEPARATOR ', ') as quantities,
           GROUP_CONCAT(p.price SEPARATOR ', ') as prices
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ? 
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Handle order cancellation if requested
if (isset($_POST['cancel_order']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled' 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        $success = "Order #$order_id has been cancelled successfully.";
    } else {
        $error = "Unable to cancel order. It may have already been processed.";
    }
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Orders</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="customer_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">My Orders</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-check"></i> Success!</h5>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-ban"></i> Error!</h5>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-primary">
                    <h3 class="card-title text-white">
                        <i class="fas fa-shopping-bag mr-2"></i>Order History
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 10%">Order ID</th>
                                    <th style="width: 30%">Products</th>
                                    <th style="width: 15%">Total Amount</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 15%">Order Date</th>
                                    <th style="width: 15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No orders found.</p>
                                        <a href="shop.php" class="btn btn-primary">
                                            <i class="fas fa-shopping-bag mr-2"></i>Start Shopping
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-info">#<?php echo $order['id']; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-link p-0" data-toggle="modal" data-target="#orderModal<?php echo $order['id']; ?>">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $order['status'] == 'completed' ? 'success' : 
                                                ($order['status'] == 'processing' ? 'warning' : 
                                                ($order['status'] == 'cancelled' ? 'danger' : 'info')); 
                                        ?> badge-pill">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($order['status'] == 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="cancel_order" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info ml-1">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Order Details Modal -->
                                <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="orderModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary">
                                                <h5 class="modal-title text-white" id="orderModalLabel<?php echo $order['id']; ?>">
                                                    <i class="fas fa-shopping-bag mr-2"></i>Order #<?php echo $order['id']; ?> Details
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="info-box bg-light">
                                                            <span class="info-box-icon bg-info"><i class="fas fa-shopping-cart"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">Order Status</span>
                                                                <span class="info-box-number">
                                                                    <span class="badge badge-<?php 
                                                                        echo $order['status'] == 'completed' ? 'success' : 
                                                                            ($order['status'] == 'processing' ? 'warning' : 
                                                                            ($order['status'] == 'cancelled' ? 'danger' : 'info')); 
                                                                    ?> badge-pill">
                                                                        <?php echo ucfirst($order['status']); ?>
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-box bg-light">
                                                            <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                                                            <div class="info-box-content">
                                                                <span class="info-box-text">Total Amount</span>
                                                                <span class="info-box-number">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-12">
                                                        <div class="card">
                                                            <div class="card-header">
                                                                <h3 class="card-title">
                                                                    <i class="fas fa-list mr-2"></i>Ordered Products
                                                                </h3>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <ul class="list-group list-group-flush">
                                                                    <?php 
                                                                    $products = explode(', ', $order['product_names']);
                                                                    $quantities = explode(', ', $order['quantities']);
                                                                    $prices = explode(', ', $order['prices']);
                                                                    
                                                                    for ($i = 0; $i < count($products); $i++):
                                                                        $quantity = floatval($quantities[$i]);
                                                                        $price = floatval($prices[$i]);
                                                                        $subtotal = $quantity * $price;
                                                                    ?>
                                                                    <li class="list-group-item">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <h6 class="mb-0"><?php echo htmlspecialchars($products[$i]); ?></h6>
                                                                                <small class="text-muted">Quantity: <?php echo $quantity; ?></small>
                                                                            </div>
                                                                            <div class="text-right">
                                                                                <span class="text-muted">₱<?php echo number_format($price, 2); ?> each</span>
                                                                                <br>
                                                                                <strong>₱<?php echo number_format($subtotal, 2); ?></strong>
                                                                            </div>
                                                                        </div>
                                                                    </li>
                                                                    <?php endfor; ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?> 