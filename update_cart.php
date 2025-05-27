<?php
require_once 'includes/session_check.php';
require_once 'config/database.php';
require_once 'includes/cart_handler.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$cartHandler = new CartHandler($pdo, $_SESSION['user_id']);

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$product_id = (int)$data['product_id'];

// Check if product exists and has stock
$stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Handle remove action
if (isset($data['remove']) && $data['remove']) {
    if ($cartHandler->removeFromCart($product_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing item from cart']);
    }
    exit;
}

// Handle quantity update
if (isset($data['quantity'])) {
    $quantity = (int)$data['quantity'];
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit;
    }
    
    if ($quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    if ($cartHandler->updateQuantity($product_id, $quantity)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating cart quantity']);
    }
    exit;
}

// Handle add to cart
if (isset($data['add'])) {
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit;
    }
    
    if ($quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    if ($cartHandler->addToCart($product_id, $quantity)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding item to cart']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']); 