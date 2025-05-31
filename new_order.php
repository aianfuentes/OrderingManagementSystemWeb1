<?php
header('Location: homepage.php');
exit();

require_once 'includes/header.php';
require_once 'config/database.php';

// Get all products
$stmt = $pdo->prepare("SELECT * FROM products WHERE stock > 0 ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $product_ids = $_POST['product_id'];
    $quantities = $_POST['quantity'];
    $total_amount = 0;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount) VALUES (?, 0)");
        $stmt->execute([$user_id]);
        $order_id = $pdo->lastInsertId();
        
        // Add order items
        foreach ($product_ids as $key => $product_id) {
            if ($quantities[$key] > 0) {
                // Get product price
                $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                $price = $product['price'];
                $quantity = $quantities[$key];
                $subtotal = $price * $quantity;
                
                // Add order item
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $quantity, $price]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                
                $total_amount += $subtotal;
            }
        }
        
        // Update order total
        $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $stmt->execute([$total_amount, $order_id]);
        
        $pdo->commit();
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to create order. Please try again.";
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
                    <h1 class="m-0">Create New Order</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                        <li class="breadcrumb-item active">New Order</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Order Details</h3>
                        </div>
                        <form method="POST" action="">
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                <div class="alert alert-danger">
                                    <?php echo $error; ?>
                                </div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo $product['stock']; ?></td>
                                                <td>
                                                    <input type="hidden" name="product_id[]" value="<?php echo $product['id']; ?>">
                                                    <input type="number" class="form-control" name="quantity[]" min="0" max="<?php echo $product['stock']; ?>" value="0">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Create Order</button>
                                <a href="orders.php" class="btn btn-default">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?> 