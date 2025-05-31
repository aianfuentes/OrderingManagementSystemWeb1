<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// List of pages that don't require authentication
$public_pages = array(
    'login.php',
    'register.php',
    'index.php'
);

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// If the current page is not in public pages and user is not logged in, redirect to login
if (!in_array($current_page, $public_pages) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure cart session is initialized
if (!isset($_SESSION['cart_initialized']) && isset($_SESSION['user_id'])) {
    $_SESSION['cart_initialized'] = true;
}
?> 