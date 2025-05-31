<?php
require_once 'config/database.php';

function addShippingAddress($user_id, $address_data) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // If this is the first address or marked as default, unset other default addresses
        if ($address_data['is_default']) {
            $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = FALSE WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO shipping_addresses (
                user_id, address_line1, address_line2, city, 
                state, postal_code, country, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $address_data['address_line1'],
            $address_data['address_line2'] ?? null,
            $address_data['city'],
            $address_data['state'],
            $address_data['postal_code'],
            $address_data['country'],
            $address_data['is_default'] ?? false
        ]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function updateShippingAddress($address_id, $user_id, $address_data) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // If setting as default, unset other default addresses
        if ($address_data['is_default']) {
            $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = FALSE WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
        
        $stmt = $pdo->prepare("
            UPDATE shipping_addresses 
            SET address_line1 = ?,
                address_line2 = ?,
                city = ?,
                state = ?,
                postal_code = ?,
                country = ?,
                is_default = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $address_data['address_line1'],
            $address_data['address_line2'] ?? null,
            $address_data['city'],
            $address_data['state'],
            $address_data['postal_code'],
            $address_data['country'],
            $address_data['is_default'] ?? false,
            $address_id,
            $user_id
        ]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

function deleteShippingAddress($address_id, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM shipping_addresses WHERE id = ? AND user_id = ?");
        return $stmt->execute([$address_id, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function getShippingAddresses($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shipping_addresses 
            WHERE user_id = ? 
            ORDER BY is_default DESC, created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getDefaultShippingAddress($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shipping_addresses 
            WHERE user_id = ? AND is_default = TRUE 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function setDefaultShippingAddress($address_id, $user_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Unset all default addresses
        $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = FALSE WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Set new default address
        $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$address_id, $user_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
} 