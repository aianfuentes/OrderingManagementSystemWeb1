<?php
require_once 'config/database.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $response = ['success' => true, 'product' => $product];
        } else {
            $response = ['success' => false, 'message' => 'Product not found.'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
} else {
    $response = ['success' => false, 'message' => 'Product ID not provided.'];
}

header('Content-Type: application/json');
echo json_encode($response);
?> 