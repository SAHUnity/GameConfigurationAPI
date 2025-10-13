<?php
require_once '../config/config.php';

// Start session and destroy it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

// Redirect to login page
header('Location: index.php');
exit;
