<?php
require_once 'config/database.php';

function addReview($user_id, $product_id, $rating, $review_text) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Add the review
        $stmt = $pdo->prepare("
            INSERT INTO product_reviews (user_id, product_id, rating, review_text) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $product_id, $rating, $review_text]);
        
        // Update product average rating and total reviews
        $stmt = $pdo->prepare("
            UPDATE products 
            SET average_rating = (
                SELECT AVG(rating) 
                FROM product_reviews 
                WHERE product_id = ?
            ),
            total_reviews = (
                SELECT COUNT(*) 
                FROM product_reviews 
                WHERE product_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$product_id, $product_id, $product_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function getProductReviews($product_id, $limit = 10, $offset = 0) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, u.name as reviewer_name 
            FROM product_reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$product_id, $limit, $offset]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getProductRating($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT average_rating, total_reviews 
            FROM products 
            WHERE id = ?
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return ['average_rating' => 0, 'total_reviews' => 0];
    }
}

function hasUserReviewed($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM product_reviews 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$user_id, $product_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getUserReview($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM product_reviews 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$user_id, $product_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
} 