<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/config.php'; // Include API config for database functions
require_once __DIR__ . '/../api/functions.php'; // Include functions for secure session

// Start secure session
startSecureSession(true);

// Initialize database if needed (create tables and default admin user)
// Only if not already initialized to avoid unnecessary overhead
if (function_exists('initializeDatabase')) {
    initializeDatabase();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// If already logged in and session is valid, redirect to dashboard
if (isSessionValid() && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ./index.php');
    exit();
}

$error = '';

// Apply rate limiting to login attempts
$clientIP = getClientIP();
if (isRateLimited($clientIP, 300, 5)) { // 5 attempts per 5 minutes
    $error = 'Too many login attempts. Please try again later.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
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
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Set admin session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['login_time'] = time();

                    // Reset CSRF token after successful login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['last_regeneration'] = time();

                    header('Location: ./index.php');
                    exit();
                } else {
                    $error = 'Invalid username or password';
                    // Add delay to prevent brute force
                    usleep(200000); // 0.2 seconds delay
                }
            } catch (PDOException $e) {
                $error = 'Database error occurred';
            }
        } else {
            $error = 'Please enter both username and password';
        }
    }
}

// Generate a new CSRF token for the form
$csrf_token = $_SESSION['csrf_token'];
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