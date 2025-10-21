<?php
// Utility functions for the admin panel

// Include API config to get database functions
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/functions.php';

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    startSecureSession(true);
}

// Initialize database if needed
initializeDatabase();

// Require authentication for admin pages
function requireLogin()
{
    if (!isSessionValid() || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        session_destroy();
        header('Location: ./login.php');
        exit();
    }
}

// Sanitize output for display with enhanced security
function h($text)
{
    if ($text === null) {
        return '';
    }
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Enhanced CSRF token generation
function generateCsrfToken()
{
    $csrfLifetime = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600; // 1 hour default
    
    if (empty($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) ||
        time() - $_SESSION['csrf_token_time'] > $csrfLifetime) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// Generate a hidden input field with CSRF token
function csrfTokenField()
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

// Verify CSRF token with time validation
function verifyCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token has expired
    $csrfLifetime = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600; // 1 hour default
    
    if (time() - $_SESSION['csrf_token_time'] > $csrfLifetime) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}
