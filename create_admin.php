<?php
require_once 'config/database.php';

// Admin user details
$name = 'Admin';
$email = 'admin@admin.com';
$password = 'admin123';
$role = 'admin';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo "Admin user already exists!";
    } else {
        // Insert new admin user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $role]);
        
        echo "Admin user created successfully!<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 