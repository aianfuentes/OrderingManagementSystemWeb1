<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to check admin access
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: homepage.php");
        exit();
    }
}

// Function to check customer access
function requireCustomer() {
    if (isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}
?> 