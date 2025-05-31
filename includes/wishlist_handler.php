<?php
require_once 'config/database.php';

function addToWishlist($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $product_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function removeFromWishlist($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$user_id, $product_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function getWishlistItems($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, p.name, p.price, p.image, p.stock 
            FROM wishlist w 
            JOIN products p ON w.product_id = p.id 
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function isInWishlist($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getWishlistCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
} 