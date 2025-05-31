<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];

    // Validate status
    $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        header('Location: manage_orders.php?error=Invalid order status');
        exit();
    }

    try {
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        header('Location: manage_orders.php?success=Order status updated successfully');
        exit();
    } catch (Exception $e) {
        header('Location: manage_orders.php?error=Failed to update order status: ' . urlencode($e->getMessage()));
        exit();
    }
}

// Get total count for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

// Initialize search and status variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause for search and status
$where = [];
$params = [];

if ($search) {
    $where[] = "(o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "o.status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count with filters
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $whereClause
");
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_orders / $limit);

// Get orders with pagination and filters
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email,
           GROUP_CONCAT(p.name SEPARATOR ', ') as items,
           GROUP_CONCAT(oi.quantity SEPARATOR ', ') as quantities
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
");

// Add search and status parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// Get order statuses for filter
$statuses = ['pending', 'processing', 'completed', 'cancelled'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Dashboard</title>
    <link rel="icon" href="../assets/images/products/test.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
        }

        body {
            background-color: #f8f9fc;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border-radius: 10px;
        }

        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .table-card .card-header {
            background: none;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }

        .badge-success {
            background: var(--secondary-color);
        }

        .badge-warning {
            background: var(--warning-color);
        }

        .badge-danger {
            background: var(--danger-color);
        }

        .badge-info {
            background: var(--primary-color);
        }

        .search-box {
            max-width: 300px;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
        }

        .modal-header .btn-close {
            color: white;
        }

        .order-details {
            font-size: 0.9rem;
        }

        .order-details .row {
            margin-bottom: 0.5rem;
        }

        .order-details .label {
            font-weight: 600;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h4 class="mb-0">Manage Orders</h4>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search orders..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                                <?php echo ucfirst($s); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?php
                                        $items = explode(', ', $order['items']);
                                        $quantities = explode(', ', $order['quantities']);
                                        for($i = 0; $i < count($items); $i++) {
                                            echo htmlspecialchars($items[$i]) . ' (x' . $quantities[$i] . ')<br>';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php $status = strtolower($order['status']); ?>
                                    <span class="badge bg-<?php
                                        echo $status === 'completed' ? 'success' :
                                        ($status === 'processing' ? 'info' :
                                        ($status === 'cancelled' ? 'danger' :
                                        ($status === 'pending' ? 'warning' : 'secondary')));
                                    ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?><br>
                                        <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info btn-action"
                                            onclick="viewOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary btn-action"
                                            onclick="updateStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="modal fade" id="viewOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="order-details">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Order ID</div>
                                <div id="view_order_id"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="label">Order Date</div>
                                <div id="view_order_date"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Customer Name</div>
                                <div id="view_customer_name"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="label">Customer Email</div>
                                <div id="view_customer_email"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Status</div>
                                <div id="view_status"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="label">Total Amount</div>
                                <div id="view_total"></div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <div class="label">Order Items</div>
                                <div id="view_items"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="manage_orders.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" id="update_order_id">

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="update_status" required>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewOrder(order) {
            document.getElementById('view_order_id').textContent = '#' + order.id;
            document.getElementById('view_order_date').textContent = new Date(order.created_at).toLocaleString();
            document.getElementById('view_customer_name').textContent = order.customer_name;
            document.getElementById('view_customer_email').textContent = order.email;
            document.getElementById('view_status').innerHTML = '<span class="badge bg-' +
                (order.status === 'completed' ? 'success' :
                 order.status === 'processing' ? 'info' :
                 order.status === 'cancelled' ? 'danger' :
                 order.status === 'pending' ? 'warning' : 'secondary') + '">' +
                order.status.charAt(0).toUpperCase() + order.status.slice(1) + '</span>';
            document.getElementById('view_total').textContent = '₱' + parseFloat(order.total_amount).toFixed(2);

            let itemsHtml = '';
            const items = order.items.split(', ');
            const quantities = order.quantities.split(', ');
            for (let i = 0; i < items.length; i++) {
                itemsHtml += items[i] + ' (x' + quantities[i] + ')<br>';
            }
            document.getElementById('view_items').innerHTML = itemsHtml;

            new bootstrap.Modal(document.getElementById('viewOrderModal')).show();
        }

        function updateStatus(orderId, currentStatus) {
            document.getElementById('update_order_id').value = orderId;
            document.getElementById('update_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
    </script>
</body>
</html>
