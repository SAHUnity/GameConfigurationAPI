<?php
// Utility functions for the admin panel

// Include API config to get database functions
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/functions.php';

// Start secure session
startSecureSession();

// Initialize database if needed (create tables and default admin user)
initializeDatabase();

// Require authentication for admin pages
function requireLogin() {
    if (!isSessionValid() || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        session_destroy();
        header('Location: ./login.php');
        exit();
    }
}

// Sanitize output for display
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Generate a hidden input field with CSRF token
function csrfTokenField() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

// Verify CSRF token
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}