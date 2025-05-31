<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'];
    $stock_to_add = $_POST['new_stock'];
    $adjustment_type = $_POST['adjustment_type'];
    $reason = $_POST['reason'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get current stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_stock = $stmt->fetchColumn();

        // Calculate new stock by adding to current stock
        $new_stock = $current_stock + $stock_to_add;

        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);

        // Log stock adjustment
        $stmt = $pdo->prepare("
            INSERT INTO stock_adjustments (product_id, previous_stock, new_stock, adjustment_type, reason, adjusted_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product_id,
            $current_stock,
            $new_stock,
            $adjustment_type,
            $reason,
            $_SESSION['user_id']
        ]);

        $pdo->commit();
        $success_message = "Stock added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error adding stock: " . $e->getMessage();
    }
}

// Get all products with their stock information
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY p.stock ASC
");
$stmt->execute();
$products = $stmt->fetchAll();

// Get stock adjustment history
$stmt = $pdo->prepare("
    SELECT 
        sa.*,
        p.name as product_name,
        u.name as adjusted_by_name
    FROM stock_adjustments sa
    JOIN products p ON sa.product_id = p.id
    JOIN users u ON sa.adjusted_by = u.id
    ORDER BY sa.created_at DESC
    LIMIT 50
");
$stmt->execute();
$stock_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Admin Dashboard</title>
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

        .stock-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }

        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stock-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stock-status.in-stock {
            background: #e6f4ea;
            color: #1e7e34;
        }

        .stock-status.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .stock-status.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        .history-item {
            padding: 1rem;
            border-bottom: 1px solid #e3e6f0;
        }

        .history-item:last-child {
            border-bottom: none;
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

    <div class="main-content">
        <div class="stock-header">
            <h2>Stock Management</h2>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stock Overview -->
        <div class="stock-card">
            <h4>Current Stock Status</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Total Sold</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="../assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="product-image me-2">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td><?php echo $product['stock']; ?></td>
                                <td><?php echo $product['total_sold']; ?></td>
                                <td>
                                    <?php
                                    $stock_status = '';
                                    if ($product['stock'] <= 0) {
                                        $stock_status = 'out-of-stock';
                                        $status_text = 'Out of Stock';
                                    } elseif ($product['stock'] <= 10) {
                                        $stock_status = 'low-stock';
                                        $status_text = 'Low Stock';
                                    } else {
                                        $stock_status = 'in-stock';
                                        $status_text = 'In Stock';
                                    }
                                    ?>
                                    <span class="stock-status <?php echo $stock_status; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateStockModal"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-current-stock="<?php echo $product['stock']; ?>">
                                        <i class="fas fa-edit"></i> Update Stock
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock History -->
        <div class="stock-card">
            <h4>Stock Adjustment History</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Previous Stock</th>
                            <th>New Stock</th>
                            <th>Adjustment Type</th>
                            <th>Reason</th>
                            <th>Adjusted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_history as $adjustment): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($adjustment['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($adjustment['product_name']); ?></td>
                                <td><?php echo $adjustment['previous_stock']; ?></td>
                                <td><?php echo $adjustment['new_stock']; ?></td>
                                <td><?php echo ucfirst($adjustment['adjustment_type']); ?></td>
                                <td><?php echo htmlspecialchars($adjustment['reason']); ?></td>
                                <td><?php echo htmlspecialchars($adjustment['adjusted_by_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="product_id">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="product_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" class="form-control" id="current_stock" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock to Add</label>
                            <input type="number" name="new_stock" class="form-control" required min="1" placeholder="Enter amount to add">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="restock">Restock</option>
                                <option value="correction">Stock Correction</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle stock update modal
        document.addEventListener('DOMContentLoaded', function() {
            const updateStockModal = document.getElementById('updateStockModal');
            if (updateStockModal) {
                updateStockModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productId = button.getAttribute('data-product-id');
                    const productName = button.getAttribute('data-product-name');
                    const currentStock = button.getAttribute('data-current-stock');

                    updateStockModal.querySelector('#product_id').value = productId;
                    updateStockModal.querySelector('#product_name').value = productName;
                    updateStockModal.querySelector('#current_stock').value = currentStock;
                });
            }
        });
    </script>
</body>
</html> 