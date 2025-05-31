<?php
require_once 'includes/session_check.php';
require_once 'config/database.php';
require_once 'includes/cart_handler.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log("Cart data fetch failed: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$cartHandler = new CartHandler($pdo, $_SESSION['user_id']);

$cartItems = $cartHandler->getCartItems();
$cartTotal = $cartHandler->getCartTotal();

error_log("Cart data fetched - Items: " . print_r($cartItems, true));
error_log("Cart data fetched - Total: " . $cartTotal);

echo json_encode([
    'success' => true,
    'cart_items' => $cartItems,
    'cart_total' => $cartTotal,
    'cart_count' => $cartHandler->getCartCount()
]);
?> 