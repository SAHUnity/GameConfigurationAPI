<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Initialize auth and logout
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);
$auth->logoutAdmin();

// Redirect to login page
header('Location: index.php');
exit;
