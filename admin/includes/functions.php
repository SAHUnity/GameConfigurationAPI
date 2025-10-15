<?php
// Utility functions for the admin panel

// Include API config to get database functions
require_once __DIR__ . '/../../api/config.php';

// Initialize database if needed (create tables and default admin user)
initializeDatabase();

// Require authentication for admin pages
function requireLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ./login.php');
        exit();
    }
}

// Sanitize output for display
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}