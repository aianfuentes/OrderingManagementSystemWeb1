<?php
require_once 'config/database.php';

function addOrderTracking($order_id, $status, $tracking_number = null, $estimated_delivery = null, $notes = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO order_tracking (
                order_id, status, tracking_number, 
                estimated_delivery, notes
            ) VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $order_id, $status, $tracking_number,
            $estimated_delivery, $notes
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function getOrderTracking($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM order_tracking 
            WHERE order_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getLatestOrderStatus($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT status, tracking_number, estimated_delivery 
            FROM order_tracking 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function updateOrderStatus($order_id, $status) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        // Add tracking record
        $stmt = $pdo->prepare("
            INSERT INTO order_tracking (order_id, status) 
            VALUES (?, ?)
        ");
        $stmt->execute([$order_id, $status]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function getOrderProgress($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT o.status, o.created_at, 
                   ot.tracking_number, ot.estimated_delivery,
                   GROUP_CONCAT(ot.status ORDER BY ot.created_at) as status_history
            FROM orders o
            LEFT JOIN order_tracking ot ON o.id = ot.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) return null;
        
        // Define order status progression
        $status_progression = [
            'pending' => 0,
            'processing' => 1,
            'shipped' => 2,
            'delivered' => 3,
            'completed' => 4
        ];
        
        // Calculate progress percentage
        $current_status = $order['status'];
        $progress = isset($status_progression[$current_status]) 
            ? ($status_progression[$current_status] / (count($status_progression) - 1)) * 100 
            : 0;
        
        return [
            'status' => $current_status,
            'progress' => $progress,
            'tracking_number' => $order['tracking_number'],
            'estimated_delivery' => $order['estimated_delivery'],
            'status_history' => $order['status_history'] ? explode(',', $order['status_history']) : [],
            'created_at' => $order['created_at']
        ];
    } catch (PDOException $e) {
        return null;
    }
}

function getDeliveryTimeSlots() {
    return [
        'morning' => '9:00 AM - 12:00 PM',
        'afternoon' => '12:00 PM - 3:00 PM',
        'evening' => '3:00 PM - 6:00 PM',
        'night' => '6:00 PM - 9:00 PM'
    ];
}

function estimateDeliveryDate($order_date) {
    // Add 3-5 business days to order date
    $delivery_date = new DateTime($order_date);
    $business_days = rand(3, 5);
    $added_days = 0;
    
    while ($added_days < $business_days) {
        $delivery_date->modify('+1 day');
        // Skip weekends
        if ($delivery_date->format('N') < 6) {
            $added_days++;
        }
    }
    
    return $delivery_date->format('Y-m-d');
} 