<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get total orders count
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

// Get total products count
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = $stmt->fetchColumn();

// Get total customers count
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_customers = $stmt->fetchColumn();

// Get total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'");
$total_revenue = $stmt->fetchColumn() ?? 0;

// Get today's orders and revenue
$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$today_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$today_revenue = $stmt->fetchColumn() ?? 0;

// Get low stock count
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10");
$low_stock_count = $stmt->fetchColumn();

// Get recent orders with more details
$stmt = $pdo->query("
    SELECT o.*, u.name as customer_name, 
           GROUP_CONCAT(p.name SEPARATOR ', ') as items,
           GROUP_CONCAT(oi.quantity SEPARATOR ', ') as quantities
    FROM orders o 
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll();

// Get low stock products
$stmt = $pdo->query("
    SELECT * FROM products 
    WHERE stock <= 10 
    ORDER BY stock ASC 
    LIMIT 5
");
$low_stock_products = $stmt->fetchAll();

// Get top selling products
$stmt = $pdo->query("
    SELECT p.*, SUM(oi.quantity) as total_sold
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ordering System</title>
    <link rel="icon" href="../assets/images/products/test.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }
        
        body {
            background-color: #f8f9fc;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary { background: rgba(78, 115, 223, 0.1); color: var(--primary-color); }
        .stat-icon.success { background: rgba(28, 200, 138, 0.1); color: var(--secondary-color); }
        .stat-icon.warning { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }
        .stat-icon.danger { background: rgba(231, 74, 59, 0.1); color: var(--danger-color); }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.875rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .card-body {
            padding: 1.5rem;
        }

        .table {
            margin: 0;
        }

        .table th {
            font-weight: 600;
            color: #4a5568;
            border-bottom-width: 1px;
        }

        .table td {
            vertical-align: middle;
            color: #4a5568;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: white;
            border: none;
            border-radius: 10px;
            color: #2d3748;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Dashboard Overview</h1>
            <div class="header-actions">
                <button class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="manage_products.php" class="action-btn">
                <i class="fas fa-plus"></i>
                <span>Add New Product</span>
            </a>
            <a href="manage_orders.php" class="action-btn">
                <i class="fas fa-shopping-cart"></i>
                <span>View Orders</span>
            </a>
            <a href="manage_customers.php" class="action-btn">
                <i class="fas fa-users"></i>
                <span>Manage Customers</span>
            </a>
            <a href="reports.php" class="action-btn">
                <i class="fas fa-chart-line"></i>
                <span>View Reports</span>
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_customers); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($low_stock_count); ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Orders</h2>
                    <a href="manage_orders.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['items']; ?></td>
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['status'] === 'completed' ? 'success' : 
                                                ($order['status'] === 'processing' ? 'warning' : 
                                                ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Low Stock Products</h2>
                    <a href="manage_products.php" class="btn btn-sm btn-primary">Manage</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="rounded me-2"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                <small class="text-muted">₱<?php echo number_format($product['price'], 2); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['stock'] <= 5 ? 'danger' : 'warning'; ?>">
                                            <?php echo $product['stock'] <= 5 ? 'Critical' : 'Low'; ?>
                                        </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 