<?php
require_once __DIR__ . '/includes/functions.php';

// Start secure session to make sure we're cleaning up the right session
startSecureSession(true);

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
);

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ./login.php');
exit();
