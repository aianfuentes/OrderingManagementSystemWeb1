<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is an admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: customer_dashboard.php");
    exit();
}

require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'config/database.php';

// Get total number of products
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = $stmt->fetchColumn();

// Get total number of orders
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

// Get total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'");
$total_revenue = $stmt->fetchColumn() ?? 0;

// Get low stock products (less than 10)
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10");
$low_stock_products = $stmt->fetchColumn();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Get low stock products list
$stmt = $pdo->query("
    SELECT * FROM products 
    WHERE stock < 10 
    ORDER BY stock ASC 
    LIMIT 5
");
$low_stock_list = $stmt->fetchAll();
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Info boxes -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-box"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Products</span>
                            <span class="info-box-number"><?php echo $total_products; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-shopping-cart"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Orders</span>
                            <span class="info-box-number"><?php echo $total_orders; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-dollar-sign"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Revenue</span>
                            <span class="info-box-number">$<?php echo number_format($total_revenue, 2); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Low Stock Products</span>
                            <span class="info-box-number"><?php echo $low_stock_products; ?></span>
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
                                <a href="orders.php" class="btn btn-tool">
                                    <i class="fas fa-list"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $order['status'] == 'completed' ? 'success' : 
                                                        ($order['status'] == 'processing' ? 'warning' : 
                                                        ($order['status'] == 'cancelled' ? 'danger' : 'info')); 
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
                </div>

                <!-- Low Stock Products -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Low Stock Products</h3>
                            <div class="card-tools">
                                <a href="products.php" class="btn btn-tool">
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
                                            <th>Stock</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($low_stock_list as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $product['stock'] < 5 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $product['stock']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-xs btn-info">
                                                    <i class="fas fa-edit"></i>
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

    <!-- Product Management Section -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Add New Product</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            // Handle product creation
                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
                                $name = trim($_POST['name']);
                                $description = trim($_POST['description']);
                                $price = floatval($_POST['price']);
                                $stock = intval($_POST['stock']);
                                
                                // Handle image upload
                                $image_path = null;
                                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                                    $upload_dir = 'assets/images/products/';
                                    
                                    // Create directory if it doesn't exist
                                    if (!file_exists($upload_dir)) {
                                        mkdir($upload_dir, 0777, true);
                                    }

                                    // Generate unique filename
                                    $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                                    
                                    if (in_array($file_extension, $allowed_extensions)) {
                                        $new_filename = uniqid() . '.' . $file_extension;
                                        $upload_path = $upload_dir . $new_filename;
                                        
                                        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                                            $image_path = $new_filename;
                                        }
                                    }
                                }

                                try {
                                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image_path) VALUES (?, ?, ?, ?, ?)");
                                    $stmt->execute([$name, $description, $price, $stock, $image_path]);
                                    echo '<div class="alert alert-success">Product added successfully!</div>';
                                } catch (PDOException $e) {
                                    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                                }
                            }
                            ?>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">Product Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="price">Price ($)</label>
                                            <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="stock">Stock Quantity</label>
                                            <input type="number" class="form-control" id="stock" name="stock" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="product_image">Product Image</label>
                                            <div class="input-group">
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="product_image" name="product_image" accept="image/*">
                                                    <label class="custom-file-label" for="product_image">Choose file</label>
                                                </div>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('product_image').click()">
                                                        <i class="fas fa-upload"></i> Upload Image
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Allowed formats: JPG, JPEG, PNG, GIF</small>
                                            <div class="mt-3">
                                                <img id="image_preview" src="#" alt="Preview" style="max-width: 200px; max-height: 200px; display: none; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-3">
                                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// Image preview functionality
document.getElementById('product_image').addEventListener('change', function(e) {
    const preview = document.getElementById('image_preview');
    const file = e.target.files[0];
    const label = document.querySelector('.custom-file-label');
    
    if (file) {
        // Update the label with the file name
        label.textContent = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        label.textContent = 'Choose file';
        preview.style.display = 'none';
    }
});
</script>

<?php
require_once 'includes/footer.php';
?> 