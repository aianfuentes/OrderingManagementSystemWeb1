<?php
require_once 'config/database.php';

function getLoyaltyPoints($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT points FROM loyalty_points WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['points'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function addLoyaltyPoints($user_id, $points, $order_id = null) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Check if user has loyalty points record
        $stmt = $pdo->prepare("SELECT id FROM loyalty_points WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $record = $stmt->fetch();
        
        if ($record) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE loyalty_points SET points = points + ? WHERE user_id = ?");
            $stmt->execute([$points, $user_id]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("INSERT INTO loyalty_points (user_id, points) VALUES (?, ?)");
            $stmt->execute([$user_id, $points]);
        }
        
        // If points are from an order, update the order record
        if ($order_id) {
            $stmt = $pdo->prepare("UPDATE orders SET loyalty_points_earned = ? WHERE id = ?");
            $stmt->execute([$points, $order_id]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function calculateOrderPoints($total_amount) {
    // Calculate points based on order amount (1 point per $10 spent)
    return floor($total_amount / 10);
}

function getLoyaltyTier($points) {
    if ($points >= 1000) return 'Gold';
    if ($points >= 500) return 'Silver';
    if ($points >= 100) return 'Bronze';
    return 'Regular';
}

function getLoyaltyBenefits($tier) {
    $benefits = [
        'Regular' => [
            'discount' => 0,
            'free_shipping' => false,
            'points_multiplier' => 1
        ],
        'Bronze' => [
            'discount' => 5,
            'free_shipping' => false,
            'points_multiplier' => 1.2
        ],
        'Silver' => [
            'discount' => 10,
            'free_shipping' => true,
            'points_multiplier' => 1.5
        ],
        'Gold' => [
            'discount' => 15,
            'free_shipping' => true,
            'points_multiplier' => 2
        ]
    ];
    
    return $benefits[$tier] ?? $benefits['Regular'];
}

function getLoyaltyHistory($user_id, $limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT o.id as order_id, o.total_amount, o.loyalty_points_earned, o.created_at
            FROM orders o
            WHERE o.user_id = ? AND o.loyalty_points_earned > 0
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
} 