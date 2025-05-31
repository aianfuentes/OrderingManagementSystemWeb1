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

// Get data from either POST or JSON
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cart_id'])) {
        $data['cart_id'] = $_POST['cart_id'];
    }
    if (isset($_POST['quantity'])) {
        $data['quantity'] = $_POST['quantity'];
    }
    if (isset($_POST['action'])) {
        $data['action'] = $_POST['action'];
    }
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

// Handle remove action
if (isset($data['action']) && $data['action'] === 'remove') {
    if (isset($data['cart_id'])) {
        $stmt = $pdo->prepare("SELECT product_id FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['cart_id'], $_SESSION['user_id']]);
        $cart_item = $stmt->fetch();
        
        if ($cart_item && $cartHandler->removeFromCart($cart_item['product_id'])) {
            echo json_encode([
                'success' => true,
                'cart_count' => $cartHandler->getCartCount(),
                'cart_items' => $cartHandler->getCartItems(),
                'cart_total' => $cartHandler->getCartTotal(),
                'message' => 'Item removed from cart'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error removing item from cart']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Cart item ID is required']);
    }
    exit;
}

// Handle quantity update
if (isset($data['cart_id']) && isset($data['quantity'])) {
    $quantity = (int)$data['quantity'];
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT c.*, p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
    $stmt->execute([$data['cart_id'], $_SESSION['user_id']]);
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }
    
    if ($quantity > $cart_item['stock']) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    if ($cartHandler->updateQuantity($cart_item['product_id'], $quantity)) {
        echo json_encode([
            'success' => true,
            'cart_count' => $cartHandler->getCartCount(),
            'cart_items' => $cartHandler->getCartItems(),
            'cart_total' => $cartHandler->getCartTotal(),
            'message' => 'Cart updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating cart quantity']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 