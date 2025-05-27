<?php
require_once 'config/database.php';

class CartHandler {
    private $pdo;
    private $user_id;

    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }

    public function addToCart($product_id, $quantity) {
        try {
            // Check if product exists in cart
            $stmt = $this->pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$this->user_id, $product_id]);
            $existing_item = $stmt->fetch();

            if ($existing_item) {
                // Update quantity if product exists
                $stmt = $this->pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $existing_item['id']]);
            } else {
                // Insert new cart item
                $stmt = $this->pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$this->user_id, $product_id, $quantity]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            return false;
        }
    }

    public function removeFromCart($product_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            return $stmt->execute([$this->user_id, $product_id]);
        } catch (PDOException $e) {
            error_log("Error removing from cart: " . $e->getMessage());
            return false;
        }
    }

    public function updateQuantity($product_id, $quantity) {
        try {
            $stmt = $this->pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            return $stmt->execute([$quantity, $this->user_id, $product_id]);
        } catch (PDOException $e) {
            error_log("Error updating cart quantity: " . $e->getMessage());
            return false;
        }
    }

    public function getCartItems() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, p.name, p.price, p.image_path, p.stock
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting cart items: " . $e->getMessage());
            return [];
        }
    }

    public function getCartTotal() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(c.quantity * p.price) as total
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error calculating cart total: " . $e->getMessage());
            return 0;
        }
    }

    public function clearCart() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            return $stmt->execute([$this->user_id]);
        } catch (PDOException $e) {
            error_log("Error clearing cart: " . $e->getMessage());
            return false;
        }
    }

    public function getCartCount() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
            $stmt->execute([$this->user_id]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting cart count: " . $e->getMessage());
            return 0;
        }
    }
} 