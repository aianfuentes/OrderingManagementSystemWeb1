<?php
require_once 'auth_check.php';
require_once 'config/database.php';

// Get user's order statistics
$user_id = $_SESSION['user_id'];

// Get active orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'processing')");
$stmt->execute([$user_id]);
$active_orders = $stmt->fetchColumn();

// Get completed orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_orders = $stmt->fetchColumn();

// Get pending orders count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_orders = $stmt->fetchColumn();

// Get total spent
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_spent = $stmt->fetchColumn() ?? 0;

// Get recent orders with items
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(p.name SEPARATOR ', ') as items,
           COUNT(oi.id) as item_count
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// Get most ordered items
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(oi.product_id) as order_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$favorite_items = $stmt->fetchAll();

// Get cart items count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_count = $stmt->fetchColumn();

// Get cart total
$stmt = $pdo->prepare("
    SELECT SUM(c.quantity * p.price) as total 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_total = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordering Management System</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Tempusdominus Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.0/css/OverlayScrollbars.min.css">
    <!-- Custom styles -->
    <style>
        .content-wrapper {
            background-color: #f4f6f9;
        }
        .main-sidebar {
            position: fixed;
        }
        @media (max-width: 767.98px) {
            .main-sidebar {
                position: fixed;
                z-index: 1030;
            }
        }
        .nav-link {
            position: relative;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background-color: #007bff;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-link:hover {
            color: #007bff !important;
        }

        .nav-link.active {
            color: #007bff !important;
        }

        /* Dropdown Styles */
        .dropdown-item {
            transition: all 0.2s ease;
            border-radius: 4px;
            margin: 2px 8px;
            width: calc(100% - 16px);
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .dropdown-menu {
            padding: 8px 0;
            border-radius: 8px;
        }

        .dropdown-divider {
            margin: 4px 0;
        }

        /* Header CSS from homepage.php for consistency across all pages */
        .shop-header {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            position: relative;
            z-index: 100;
        }
        .shop-header .fw-bold {
            color: #1a2233;
        }
        .shop-header .nav-link {
            position: relative;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #222 !important;
            font-size: 1.1rem;
        }
        .shop-header .nav-link.active,
        .shop-header .nav-link:hover {
            color: #007bff !important;
        }
        .shop-header .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 50%;
            background-color: #007bff;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .shop-header .nav-link.active::after,
        .shop-header .nav-link:hover::after {
            width: 100%;
        }
        .shop-header .dropdown-menu {
            padding: 8px 0;
            border-radius: 8px;
            min-width: 200px;
        }
        .shop-header .dropdown-item {
            transition: all 0.2s ease;
            border-radius: 4px;
            margin: 2px 8px;
            width: calc(100% - 16px);
        }
        .shop-header .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .shop-header .dropdown-divider {
            margin: 4px 0;
        }
        .shop-header .badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.3em 0.6em;
            min-width: 1.5em;
            min-height: 1.5em;
            display: inline-block;
            text-align: center;
            vertical-align: middle;
            line-height: 1;
        }
        .shop-header .cart-icon-area {
            position: relative;
            min-width: 40px;
            margin-right: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .shop-header .border-start {
            border-left: 1px solid #e0e0e0 !important;
            height: 28px;
        }
        @media (max-width: 767.98px) {
            .shop-header .fw-bold.fs-4 {
                font-size: 1.2rem !important;
            }
            .shop-header .nav-link {
                font-size: 1rem;
                padding: 0.5rem 0.7rem;
            }
            .shop-header .border-start {
                display: none !important;
            }
            .shop-header .cart-icon-area {
                margin-right: 8px;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<!-- Modern Shop Header -->
<header class="shop-header bg-white shadow-sm py-3">
    <div class="container-fluid">
        <div class="row align-items-center justify-content-between">
            <!-- Left: Logo -->
            <div class="col-auto d-flex align-items-center">
                <img src="assets/images/products/test.png" alt="FoodExpress Logo" style="height:44px;width:44px;object-fit:contain;">
                <span class="fw-bold fs-4 ms-2" style="letter-spacing:1px;">Food Express</span>
            </div>
            <!-- Center: Navigation -->
            <div class="col d-none d-md-flex justify-content-center align-items-center">
                <a href="homepage.php" class="nav-link text-dark fw-semibold position-relative <?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : ''; ?>">Home</a>
                <a href="about.php" class="nav-link text-dark fw-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">About</a>
                <a href="contact.php" class="nav-link text-dark fw-semibold <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact</a>
            </div>
            <!-- Right: Cart and Profile -->
            <div class="col-auto d-flex align-items-center gap-4">
                <span class="cart-icon-area">
                    <a href="cart.php" class="text-dark position-relative">
                        <i class="fas fa-shopping-cart fa-lg"></i>
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cart_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </span>
                <span class="d-none d-md-inline border-start mx-2"></span>
                <div class="dropdown ms-2">
                    <a class="text-dark d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle fa-lg"></i>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown" style="min-width: 200px;">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="#" data-bs-toggle="modal" data-bs-target="#dashboardModal">
                                <i class="fas fa-tachometer-alt text-primary"></i>
                                <span>My Dashboard</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-2"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="wrapper">
    <!-- Dashboard Modal -->
    <div class="modal fade" id="dashboardModal" tabindex="-1" aria-labelledby="dashboardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="dashboardModalLabel">
                        <i class="fas fa-user-circle me-2"></i>My Dashboard
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Quick Stats Row -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Active Orders</h6>
                                            <h3 class="mt-2 mb-0"><?php echo $active_orders; ?></h3>
                                        </div>
                                        <i class="fas fa-shopping-bag fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Completed</h6>
                                            <h3 class="mt-2 mb-0"><?php echo $completed_orders; ?></h3>
                                        </div>
                                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Pending</h6>
                                            <h3 class="mt-2 mb-0"><?php echo $pending_orders; ?></h3>
                                        </div>
                                        <i class="fas fa-clock fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Total Spent</h6>
                                            <h3 class="mt-2 mb-0">₱<?php echo number_format($total_spent, 2); ?></h3>
                                        </div>
                                        <span class="fs-1 opacity-50" style="font-weight: bold;">₱</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Row -->
                    <div class="row">
                        <!-- Recent Orders -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Orders</h6>
                                    <a href="orders.php" class="btn btn-sm btn-primary">View All Orders</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Items</th>
                                                    <th>Status</th>
                                                    <th>Total</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>#<?php echo $order['id']; ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $items = explode(', ', $order['items']);
                                                            echo count($items) > 2 ? 
                                                                $items[0] . ', ' . $items[1] . ' +' . (count($items) - 2) . ' more' : 
                                                                $order['items']; 
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $order['status'] == 'completed' ? 'success' : 
                                                                ($order['status'] == 'processing' ? 'warning' : 
                                                                ($order['status'] == 'cancelled' ? 'danger' : 'info')); 
                                                        ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="menu.php" class="btn btn-outline-primary">
                                            <i class="fas fa-plus-circle me-2"></i>New Order
                                        </a>
                                        <button class="btn btn-outline-success" onclick="openProfileModal()">
                                            <i class="fas fa-edit me-2"></i>Edit Profile
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Most Ordered Items -->
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-star me-2"></i>Most Ordered Items</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($favorite_items as $item): ?>
                                        <a href="menu.php?item=<?php echo $item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <small class="text-muted">Ordered <?php echo $item['order_count']; ?> times</small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">₱<?php echo number_format($item['price'], 2); ?></span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Edit Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeProfileModal()" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="profileForm" method="POST" action="update_profile.php">
                        <div class="text-center mb-4">
                            <!-- Profile image upload section (simplified - you may want to add a placeholder or re-implement correctly) -->
                            <div class="p-4 border rounded text-muted">
                                Profile image upload functionality here
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="firstName" name="first_name" 
                                           value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" required>
                                    <label for="firstName">First Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="lastName" name="last_name" 
                                           value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" required>
                                    <label for="lastName">Last Name</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                                    <label for="email">Email Address</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>" required>
                                    <label for="phone">Phone Number</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="address" name="address" 
                                              style="height: 100px" required><?php echo htmlspecialchars($_SESSION['address'] ?? ''); ?></textarea>
                                    <label for="address">Delivery Address</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Change Password</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="currentPassword" name="current_password">
                                    <label for="currentPassword">Current Password</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="newPassword" name="new_password">
                                    <label for="newPassword">New Password</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password">
                                    <label for="confirmPassword">Confirm New Password</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeProfileModal()">Cancel</button>
                    <button type="submit" form="profileForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewOrder(orderId) {
    window.location.href = 'order_details.php?id=' + orderId + '';
}

// Profile Modal Functions
function openProfileModal() {
    const profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
    profileModal.show();
}

function closeProfileModal() {
    const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
    if (profileModal) {
        profileModal.hide();
    }
}

// Handle profile form submission
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            closeProfileModal();
        } else {
            alert(data.message || 'Error updating profile');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating profile');
    });
});
</script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 (Compatible with AdminLTE 3) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html> 