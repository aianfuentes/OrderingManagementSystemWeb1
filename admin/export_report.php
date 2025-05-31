<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales data
$stmt = $pdo->prepare("
    SELECT 
        o.id as order_id,
        o.created_at,
        o.total_amount,
        o.status,
        o.payment_status as payment_method,
        u.name,
        u.email,
        GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ' x ₱', oi.price, ')') SEPARATOR '; ') as items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_data = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

// Create CSV file
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Order ID',
    'Date',
    'Customer Name',
    'Email',
    'Items',
    'Total Amount',
    'Payment Method',
    'Status'
]);

// Add data rows
foreach ($sales_data as $sale) {
    fputcsv($output, [
        $sale['order_id'],
        date('Y-m-d H:i:s', strtotime($sale['created_at'])),
        $sale['name'],
        $sale['email'],
        $sale['items'],
        '₱' . number_format($sale['total_amount'], 2),
        ucfirst($sale['payment_method']),
        ucfirst($sale['status'])
    ]);
}

// Close the file
fclose($output); 