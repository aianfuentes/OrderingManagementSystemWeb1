<?php
require_once 'config/database.php';
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header("Location: homepage.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Get order and payment details
$stmt = $pdo->prepare("
    SELECT o.*, pt.payment_method_id, pm.name as payment_method_name
    FROM orders o
    LEFT JOIN payment_transactions pt ON o.id = pt.order_id
    LEFT JOIN payment_methods pm ON pt.payment_method_id = pm.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: homepage.php");
    exit();
}

$payment_method_id = $order['payment_method_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method_id = $order['payment_method_id'] ?? null;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Process payment based on method
        switch ($payment_method_id) {
            case 1: // Credit Card
                $transaction_id = 'CC_' . time() . '_' . rand(1000, 9999);
                $payment_details = json_encode([
                    'card_type' => 'Visa',
                    'last_four' => '1234',
                    'transaction_id' => $transaction_id
                ]);
                break;
            case 2: // PayPal
                $transaction_id = 'PP_' . time() . '_' . rand(1000, 9999);
                $payment_details = json_encode([
                    'paypal_email' => 'user@example.com',
                    'transaction_id' => $transaction_id
                ]);
                break;
            case 3: // Cash on Delivery
                $transaction_id = 'COD_' . time() . '_' . rand(1000, 9999);
                $payment_details = json_encode([
                    'payment_type' => 'Cash on Delivery',
                    'transaction_id' => $transaction_id
                ]);
                break;
        }
        // Update payment transaction
        $stmt = $pdo->prepare("
            UPDATE payment_transactions 
            SET status = 'completed',
                transaction_id = ?,
                payment_details = ?
            WHERE order_id = ?
        ");
        $stmt->execute([$transaction_id, $payment_details, $order_id]);
        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                status = 'processing'
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);
        // Add order tracking
        $stmt = $pdo->prepare("
            INSERT INTO order_tracking (
                order_id,
                status,
                tracking_number,
                notes
            ) VALUES (?, 'Order Confirmed', ?, 'Payment received, order is being processed')
        ");
        $stmt->execute([$order_id, $transaction_id]);
        $pdo->commit();
        // Redirect to order confirmation
        header("Location: order_confirmation.php?order_id=" . $order_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Payment processing failed. Please try again.";
    }
}
require_once 'includes/header.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Process Payment</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="checkout.php">Checkout</a></li>
                        <li class="breadcrumb-item active">Payment</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Payment Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Order Summary</h5>
                                    <p>Order ID: #<?php echo $order_id; ?></p>
                                    <p>Total Amount: ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Payment Method</h5>
                                    <p><?php echo isset($order['payment_method_name']) ? htmlspecialchars($order['payment_method_name']) : 'N/A'; ?></p>
                                </div>
                            </div>

                            <?php if ($payment_method_id == 1): // Credit Card ?>
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label>Card Number</label>
                                    <input type="text" class="form-control" placeholder="1234 5678 9012 3456" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Expiry Date</label>
                                            <input type="text" class="form-control" placeholder="MM/YY" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>CVV</label>
                                            <input type="text" class="form-control" placeholder="123" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Cardholder Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">Pay Now</button>
                            </form>

                            <?php elseif ($payment_method_id == 2): // PayPal ?>
                            <div class="text-center">
                                <div id="paypal-button-container"></div>
                            </div>
                            <script src="https://www.paypal.com/sdk/js?client-id=AfnAsy5IAZUZ6wSw8e4-zWmZ3yuVVcL3-TvY3hZrT51Lpg99mSoIogJzyE4jWc-u2tXvBrXjbxvU1xt9&currency=PHP"></script>
                            <script>
                            paypal.Buttons({
                                createOrder: function(data, actions) {
                                    return actions.order.create({
                                        purchase_units: [{
                                            amount: {
                                                value: '<?php echo number_format($order['total_amount'], 2, '.', ''); ?>'
                                            }
                                        }]
                                    });
                                },
                                onApprove: function(data, actions) {
                                    return actions.order.capture().then(function(details) {
                                        // Optionally, send details/orderID to your server via AJAX to mark as paid
                                        window.location.href = 'order_confirmation.php?id=<?php echo $order_id; ?>&paypal=success';
                                    });
                                }
                            }).render('#paypal-button-container');
                            </script>
                            <?php else: // Cash on Delivery ?>
                            <div class="text-center">
                                <p>You have chosen to pay when you receive your order.</p>
                                <form method="POST" action="">
                                    <button type="submit" class="btn btn-primary">Confirm Order</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?> 