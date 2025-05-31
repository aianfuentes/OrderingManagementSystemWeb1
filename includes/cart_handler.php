<?php
require_once 'config/database.php';

class CartHandler {
    private $pdo;
    private $user_id;

    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        
        // Initialize cart if not exists
        // Removed explicit session-based initialization
        // The methods like addToCart and getCartItems will interact with the database directly.
        /*
        if (!isset($_SESSION['cart_initialized'])) {
            $this->initializeCart();
        }
        */
    }

    private function initializeCart() {
        // This method is no longer needed with the simplified constructor
        /*
        try {
            // Check if user has cart items
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
            $stmt->execute([$this->user_id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['cart_initialized'] = true;
            }
        } catch (PDOException $e) {
            error_log("Error initializing cart: " . $e->getMessage());
        }
        */
    }

    public function addToCart($product_id, $quantity) {
        error_log("Attempting to add product " . $product_id . " with quantity " . $quantity . " to cart for user " . $this->user_id);
        try {
            // Check if product exists in cart
            $stmt = $this->pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$this->user_id, $product_id]);
            $existing_item = $stmt->fetch();

            if ($existing_item) {
                // Update quantity if product exists
                error_log("Product exists in cart. Updating quantity.");
                $stmt = $this->pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $existing_item['id']]);
                 error_log("Update query executed. Rows affected: " . $stmt->rowCount());
            } else {
                // Insert new cart item
                 error_log("Product not in cart. Inserting new item.");
                $stmt = $this->pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$this->user_id, $product_id, $quantity]);
                 error_log("Insert query executed. Last Insert ID: " . $this->pdo->lastInsertId());
            }

            // Update session cart count
            $_SESSION['cart_count'] = $this->getCartCount();
            error_log("Cart count updated in session: " . $_SESSION['cart_count']);
            return true;
        } catch (PDOException $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            return false;
        }
    }

    public function removeFromCart($product_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $result = $stmt->execute([$this->user_id, $product_id]);
            
            // Update session cart count
            if ($result) {
                $_SESSION['cart_count'] = $this->getCartCount();
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Error removing from cart: " . $e->getMessage());
            return false;
        }
    }

    public function updateQuantity($product_id, $quantity) {
        try {
            $stmt = $this->pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $result = $stmt->execute([$quantity, $this->user_id, $product_id]);
            
            // Update session cart count
            if ($result) {
                $_SESSION['cart_count'] = $this->getCartCount();
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating cart quantity: " . $e->getMessage());
            return false;
        }
    }

    public function getCartItems() {
        error_log("Attempting to get cart items for user " . $this->user_id);
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, p.name, p.price, p.image, p.stock, p.description
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.id DESC
            ");
            $stmt->execute([$this->user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Raw cart items fetched: " . print_r($items, true));

            // Format the data
            foreach ($items as &$item) {
                $item['total'] = $item['quantity'] * $item['price'];
                $item['image'] = !empty($item['image']) ? $item['image'] : 'default.png';
            }
            
            return $items;
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
            $result = $stmt->execute([$this->user_id]);
            
            // Update session cart count
            if ($result) {
                $_SESSION['cart_count'] = 0;
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Error clearing cart: " . $e->getMessage());
            return false;
        }
    }

    public function getCartCount() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
            $stmt->execute([$this->user_id]);
            $count = $stmt->fetchColumn();
            
            // Update session cart count
            $_SESSION['cart_count'] = $count;
            return $count;
        } catch (PDOException $e) {
            error_log("Error getting cart count: " . $e->getMessage());
            return 0;
        }
    }
} 