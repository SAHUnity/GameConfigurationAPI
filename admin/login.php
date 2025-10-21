<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/functions.php'; // Include admin functions for h() function

// Start secure session
startSecureSession(true);

// Initialize database if needed (create tables and default admin user)
// Initialize database if needed
initializeDatabase();

// CSRF Token Generation with enhanced security
$csrf_token = generateCsrfToken();

// If already logged in and session is valid, redirect to dashboard
if (isSessionValid() && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ./index.php');
    exit();
}

$error = '';

// Enhanced rate limiting for login attempts
$clientIP = getClientIP();
if (isRateLimited($clientIP, 300, 10)) { // Reduced to 10 attempts per 5 minutes
    $error = 'Too many login attempts. Please try again later.';
    // Log security event
    logSecurityEvent('LOGIN_RATE_LIMIT_EXCEEDED', $clientIP);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token with enhanced validation
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired CSRF token';
        logSecurityEvent('CSRF_TOKEN_INVALID', $clientIP, ['form' => 'login']);
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            $pdo = getDBConnection();

            try {
                $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Enhanced session security
                    session_regenerate_id(true);

                    // Set admin session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                    $_SESSION['login_ip'] = $clientIP;
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                    // Reset CSRF token after successful login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['last_regeneration'] = time();

                    // Log successful login
                    logSecurityEvent('LOGIN_SUCCESS', $clientIP, [
                        'user_id' => $user['id'],
                        'username' => $user['username']
                    ]);

                    header('Location: ./index.php');
                    exit();
                } else {
                    $error = 'Invalid username or password';

                    // Enhanced delay to prevent brute force
                    usleep(500000); // 0.5 seconds delay

                    // Log failed login attempt
                    logSecurityEvent('LOGIN_FAILED', $clientIP, [
                        'username' => $username,
                        'reason' => 'invalid_credentials'
                    ]);
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        } else {
            $error = 'Please enter both username and password';
        }
    }
}

// CSRF token is already generated above
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
    </style>
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4 mt-5">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Admin Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo h($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>