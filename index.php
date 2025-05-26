<?php
session_start();

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: homepage.php");
    }
    exit();
}

// If not logged in, redirect to login page
header("Location: login.php");
exit();
?> 