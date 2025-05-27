<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get total count for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_customers = $stmt->fetchColumn();

// Initialize search variable
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause for search
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'AND ' . implode(' AND ', $where) : '';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_customers / $limit);

// Get customers with pagination
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.role = 'user'
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
");

// Add search parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Admin Dashboard</title>
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

        .customer-details {
            font-size: 0.9rem;
        }

        .customer-details .row {
            margin-bottom: 0.5rem;
        }

        .customer-details .label {
            font-weight: 600;
            color: #6c757d;
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .stats-card .label {
            color: #6c757d;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .table th {
            font-weight: 600;
            color: #4a5568;
            border-bottom-width: 1px;
            text-align: left;
        }

        .table td {
            vertical-align: middle;
            color: #4a5568;
            text-align: left;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }

        /* Specific column alignment */
        .table th:nth-child(2), /* Email Header */
        .table td:nth-child(2), /* Email Data */
        .table th:nth-child(3), /* Total Orders Header */
        .table td:nth-child(3), /* Total Orders Data */
        .table th:nth-child(4), /* Total Spent Header */
        .table td:nth-child(4), /* Total Spent Data */
        .table th:nth-child(5), /* Last Order Header */
        .table td:nth-child(5), /* Last Order Data */
        .table th:nth-child(6), /* Actions Header */
        .table td:nth-child(6) /* Actions Data */
         {
            text-align: right;
        }

        .table th:nth-child(6) {
             text-align: right; /* Ensure header is right-aligned */
             padding-right: 1rem; /* Adjust as needed */
        }

        .table td:nth-child(6) {
             padding-right: 1rem; /* Adjust as needed to match header padding */
        }

        .table td:nth-child(6) .d-flex {
            justify-content: flex-end; /* Ensure buttons are pushed to the right */
            width: 100%; /* Allow flex container to take full width of cell */
            margin-right: -0.5rem; /* Adjust this value to fine-tune alignment */
        }

        /* Add back margin between buttons */
        .table td:nth-child(6) .btn-action:not(:last-child) {
            margin-right: 0.25rem; /* Adjust as needed for spacing */
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h4 class="mb-0">Manage Customers</h4>
        </div>

        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search customers..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th class="text-end">Total Orders</th>
                                <th class="text-end">Total Spent</th>
                                <th class="text-end">Last Order</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($customer['name']); ?></div>
                                </td>
                                <td class="text-end">
                                    <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                </td>
                                <td class="text-end"><?php echo $customer['total_orders']; ?></td>
                                <td class="text-end">₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td class="text-end">
                                    <?php if ($customer['last_order_date']): ?>
                                    <small>
                                        <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?><br>
                                        <?php echo date('h:i A', strtotime($customer['last_order_date'])); ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="text-muted">No orders yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end">
                                        <button class="btn btn-sm btn-info btn-action me-1" 
                                                onclick="viewCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary btn-action" 
                                                onclick="viewOrders(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
                                    </div>
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- View Customer Modal -->
    <div class="modal fade" id="viewCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="value" id="view_total_orders">0</div>
                                <div class="label">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="value" id="view_total_spent">₱0.00</div>
                                <div class="label">Total Spent</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="value" id="view_avg_order">₱0.00</div>
                                <div class="label">Average Order Value</div>
                            </div>
                        </div>
                    </div>
                    <div class="customer-details">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Name</div>
                                <div id="view_name"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="label">Email</div>
                                <div id="view_email"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="label">Member Since</div>
                                <div id="view_created_at"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="label">Last Order</div>
                                <div id="view_last_order"></div>
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

    <!-- View Orders Modal -->
    <div class="modal fade" id="viewOrdersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="orders_list"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCustomer(customer) {
            document.getElementById('view_name').textContent = customer.name;
            document.getElementById('view_email').textContent = customer.email;
            document.getElementById('view_created_at').textContent = new Date(customer.created_at).toLocaleDateString();
            document.getElementById('view_last_order').textContent = customer.last_order_date ? 
                new Date(customer.last_order_date).toLocaleString() : 'No orders yet';
            
            document.getElementById('view_total_orders').textContent = customer.total_orders;
            document.getElementById('view_total_spent').textContent = '₱' + parseFloat(customer.total_spent).toFixed(2);
            document.getElementById('view_avg_order').textContent = customer.total_orders > 0 ? 
                '₱' + (parseFloat(customer.total_spent) / customer.total_orders).toFixed(2) : '₱0.00';
            
            new bootstrap.Modal(document.getElementById('viewCustomerModal')).show();
        }
        
        function viewOrders(customerId) {
            // Fetch customer orders
            fetch(`get_customer_orders.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(orders => {
                    let ordersHtml = '';
                    if (orders.length === 0) {
                        ordersHtml = '<p class="text-muted">No orders found.</p>';
                    } else {
                        ordersHtml = '<div class="table-responsive"><table class="table">' +
                            '<thead><tr>' +
                            '<th>Order ID</th>' +
                            '<th>Date</th>' +
                            '<th>Total</th>' +
                            '<th>Status</th>' +
                            '</tr></thead><tbody>';
                        
                        orders.forEach(order => {
                            ordersHtml += `<tr>
                                <td>#${order.id}</td>
                                <td>${new Date(order.created_at).toLocaleString()}</td>
                                <td>₱${parseFloat(order.total_amount).toFixed(2)}</td>
                                <td><span class="badge badge-${getStatusColor(order.status)}">${order.status}</span></td>
                            </tr>`;
                        });
                        
                        ordersHtml += '</tbody></table></div>';
                    }
                    
                    document.getElementById('orders_list').innerHTML = ordersHtml;
                    new bootstrap.Modal(document.getElementById('viewOrdersModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orders_list').innerHTML = '<p class="text-danger">Error loading orders.</p>';
                });
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'delivered': return 'success';
                case 'cancelled': return 'danger';
                case 'pending': return 'warning';
                default: return 'info';
            }
        }
    </script>
</body>
</html> 