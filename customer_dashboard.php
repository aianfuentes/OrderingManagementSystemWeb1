<?php
session_start();

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
require_once 'includes/loyalty_handler.php';
require_once 'includes/wishlist_handler.php';
require_once 'includes/order_tracking_handler.php';

// Get customer's orders with product details
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
           GROUP_CONCAT(oi.quantity SEPARATOR ', ') as quantities
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ? 
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();

// Get total orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_orders = $stmt->fetchColumn();

// Get total spent
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$total_spent = $stmt->fetchColumn() ?? 0;

// Get pending orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_orders = $stmt->fetchColumn();

// Get completed orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$completed_orders = $stmt->fetchColumn();

// Get customer's favorite products (most ordered)
$stmt = $pdo->prepare("
    SELECT p.name, COUNT(oi.id) as order_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$favorite_products = $stmt->fetchAll();

// Get loyalty points and tier
$loyalty_points = getLoyaltyPoints($_SESSION['user_id']);
$loyalty_tier = getLoyaltyTier($loyalty_points);
$loyalty_benefits = getLoyaltyBenefits($loyalty_tier);

// Get wishlist items
$wishlist_items = getWishlistItems($_SESSION['user_id']);

// Get recent order tracking
$tracking_updates = [];
foreach ($recent_orders as $order) {
    $tracking_updates[$order['id']] = getOrderProgress($order['id']);
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="customer_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Loyalty Status -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <h3>Loyalty Tier: <?php echo $loyalty_tier; ?></h3>
                                    <div class="loyalty-badge">
                                        <i class="fas fa-crown fa-3x text-warning"></i>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h3>Points Balance</h3>
                                    <h2 class="text-primary"><?php echo $loyalty_points; ?> points</h2>
                                </div>
                                <div class="col-md-4">
                                    <h3>Your Benefits</h3>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-percentage"></i> <?php echo $loyalty_benefits['discount']; ?>% discount on all orders</li>
                                        <li><i class="fas fa-shipping-fast"></i> <?php echo $loyalty_benefits['free_shipping'] ? 'Free Shipping' : 'Standard Shipping'; ?></li>
                                        <li><i class="fas fa-star"></i> <?php echo $loyalty_benefits['points_multiplier']; ?>x points on all purchases</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info boxes -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-shopping-cart"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Orders</span>
                            <span class="info-box-number"><?php echo $total_orders; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-dollar-sign"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Spent</span>
                            <span class="info-box-number">₱<?php echo number_format($total_spent, 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pending Orders</span>
                            <span class="info-box-number"><?php echo $pending_orders; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Completed Orders</span>
                            <span class="info-box-number"><?php echo $completed_orders; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Orders -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Orders</h3>
                            <div class="card-tools">
                                <a href="my_orders.php" class="btn btn-tool">
                                    <i class="fas fa-list"></i> View All Orders
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Products</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): 
                                            $tracking = $tracking_updates[$order['id']] ?? null;
                                        ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($order['product_names']); ?>
                                                </small>
                                            </td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $order['status'] == 'completed' ? 'success' : 
                                                        ($order['status'] == 'processing' ? 'warning' : 
                                                        ($order['status'] == 'cancelled' ? 'danger' : 'info')); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($tracking): ?>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar bg-primary" role="progressbar" 
                                                         style="width: <?php echo $tracking['progress']; ?>%"
                                                         aria-valuenow="<?php echo $tracking['progress']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view_order.php?id=<?php echo $order['id']; ?>" class="btn btn-xs btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wishlist and Favorites -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">My Wishlist</h3>
                            <div class="card-tools">
                                <a href="wishlist.php" class="btn btn-tool">
                                    <i class="fas fa-list"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wishlist_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <a href="product.php?id=<?php echo $item['product_id']; ?>" class="btn btn-xs btn-primary">
                                                    <i class="fas fa-shopping-cart"></i> Buy Now
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Favorite Products</h3>
                            <div class="card-tools">
                                <a href="shop.php" class="btn btn-tool">
                                    <i class="fas fa-list"></i> View All Products
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Times Ordered</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($favorite_products as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo $product['order_count']; ?> times
                                                </span>
                                            </td>
                                            <td>
                                                <a href="shop.php" class="btn btn-xs btn-primary">
                                                    <i class="fas fa-shopping-cart"></i> Order Again
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?> 