<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if customer_id is provided
if (!isset($_GET['customer_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Customer ID is required']);
    exit();
}

$customer_id = (int)$_GET['customer_id'];

// Get customer orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(p.name SEPARATOR ', ') as items,
           GROUP_CONCAT(oi.quantity SEPARATOR ', ') as quantities
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return orders as JSON
header('Content-Type: application/json');
echo json_encode($orders); 