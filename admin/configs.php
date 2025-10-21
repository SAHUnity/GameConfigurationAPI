<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../api/functions.php'; // Include API functions for validation

requireLogin();

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } elseif (isset($_POST['action'])) {
        $pdo = getDBConnection();

        if ($_POST['action'] === 'add_config') {
            $gameId = (int)($_POST['game_id'] ?? 0);
            $key = trim($_POST['config_key'] ?? '');
            $value = trim($_POST['config_value'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            // Enhanced input validation
            $errors = validateInput(
                ['config_key' => $key, 'game_id' => $gameId, 'config_value' => $value],
                ['config_key', 'game_id', 'config_value'],
                [
                    'config_key' => [
                        'min_length' => 1,
                        'max_length' => 255,
                        'regex' => '/^[a-zA-Z0-9_\-\.]+$/'
                    ],
                    'config_value' => [
                        'min_length' => 1,
                        'max_length' => 10000
                    ],
                    'game_id' => [
                        'type' => 'int'
                    ]
                ]
            );

            // Additional security validation for config value
            if (empty($errors)) {
                // Check for potentially dangerous content
                $dangerousPatterns = [
                    '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                    '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
                    '/javascript:/i',
                    '/on\w+\s*=/i',
                    '/data:text\/html/i'
                ];

                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $errors[] = "Configuration value contains potentially dangerous content";
                        break;
                    }
                }

                // Validate JSON if it looks like JSON
                if ((substr($value, 0, 1) === '{' && substr($value, -1) === '}') ||
                    (substr($value, 0, 1) === '[' && substr($value, -1) === ']')
                ) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Invalid JSON format in configuration value";
                    }
                }
            }

            if (!empty($errors)) {
                $error = implode(', ', $errors);
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO configurations (game_id, config_key, config_value, description, is_active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        sanitizeInput($gameId, 'int'),
                        sanitizeInput($key),
                        sanitizeConfigValue($value), // Use special sanitization that preserves JSON formatting
                        sanitizeInput($description),
                        sanitizeInput($isActive, 'int')
                    ]);
                    $message = 'Configuration added successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = 'A configuration with this key already exists for this game';
                    } else {
                        $error = 'Database error occurred';
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit_config') {
            $id = (int)($_POST['id'] ?? 0);
            $gameId = (int)($_POST['game_id'] ?? 0);
            $key = trim($_POST['config_key'] ?? '');
            $value = trim($_POST['config_value'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            // Enhanced input validation
            $errors = validateInput(
                ['config_key' => $key, 'id' => $id, 'game_id' => $gameId, 'config_value' => $value],
                ['config_key', 'id', 'game_id', 'config_value'],
                [
                    'config_key' => [
                        'min_length' => 1,
                        'max_length' => 255,
                        'regex' => '/^[a-zA-Z0-9_\-\.]+$/'
                    ],
                    'config_value' => [
                        'min_length' => 1,
                        'max_length' => 10000
                    ],
                    'id' => [
                        'type' => 'int'
                    ],
                    'game_id' => [
                        'type' => 'int'
                    ]
                ]
            );

            // Additional security validation for config value
            if (empty($errors)) {
                // Check for potentially dangerous content
                $dangerousPatterns = [
                    '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                    '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
                    '/javascript:/i',
                    '/on\w+\s*=/i',
                    '/data:text\/html/i'
                ];

                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $errors[] = "Configuration value contains potentially dangerous content";
                        break;
                    }
                }

                // Validate JSON if it looks like JSON
                if ((substr($value, 0, 1) === '{' && substr($value, -1) === '}') ||
                    (substr($value, 0, 1) === '[' && substr($value, -1) === ']')
                ) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Invalid JSON format in configuration value";
                    }
                }
            }

            if (!empty($errors)) {
                $error = implode(', ', $errors);
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE configurations SET game_id=?, config_key=?, config_value=?, description=?, is_active=? WHERE id=?");
                    $stmt->execute([
                        sanitizeInput($gameId, 'int'),
                        sanitizeInput($key),
                        sanitizeConfigValue($value), // Use special sanitization that preserves JSON formatting
                        sanitizeInput($description),
                        sanitizeInput($isActive, 'int'),
                        sanitizeInput($id, 'int')
                    ]);
                    $message = 'Configuration updated successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = 'A configuration with this key already exists for this game';
                    } else {
                        $error = 'Database error occurred';
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete_config') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM configurations WHERE id=?");
                    $stmt->execute([sanitizeInput($id, 'int')]);
                    $message = 'Configuration deleted successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = 'Database error occurred';
                }
            }
        }
    }
}

// Get all configurations with game names
$pdo = getDBConnection();
$configs = $pdo->query("
    SELECT c.id, c.game_id, g.name as game_name, c.config_key, c.config_value, c.description, c.is_active, c.created_at
    FROM configurations c
    LEFT JOIN games g ON c.game_id = g.id
    ORDER BY g.name, c.config_key
")->fetchAll();

// Get all games for dropdown
$games = $pdo->query("SELECT id, name FROM games ORDER BY name")->fetchAll();

// Generate CSRF token for the page
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Configurations - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Configurations</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo h($message); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <!-- Add Configuration Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add New Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_config">
                            <?php echo csrfTokenField(); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="game_id" class="form-label">Game</label>
                                        <select class="form-control" id="game_id" name="game_id" required>
                                            <option value="">Select a game</option>
                                            <?php foreach ($games as $game): ?>
                                                <option value="<?php echo $game['id']; ?>"><?php echo h($game['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="config_key" class="form-label">Configuration Key</label>
                                        <input type="text" class="form-control" id="config_key" name="config_key" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="config_value" class="form-label">Configuration Value</label>
                                <textarea class="form-control" id="config_value" name="config_value" rows="3" required></textarea>
                                <div class="form-text">Maximum 10000 characters</div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Configuration</button>
                        </form>
                    </div>
                </div>

                <!-- Configurations List -->
                <div class="card">
                    <div class="card-header">
                        <h5>Configurations List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Game</th>
                                        <th>Key</th>
                                        <th>Value</th>
                                        <th>Description</th>
                                        <th>Active</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($configs as $config): ?>
                                        <tr>
                                            <td><?php echo h($config['game_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo h($config['config_key']); ?></td>
                                            <td><?php echo h(strlen($config['config_value']) > 50 ? substr($config['config_value'], 0, 50) . '...' : $config['config_value']); ?></td>
                                            <td><?php echo h($config['description']); ?></td>
                                            <td><?php echo $config['is_active'] ? 'Yes' : 'No'; ?></td>
                                            <td><?php echo h($config['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $config['id']; ?>">Edit</button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $config['id']; ?>">Delete</button>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $config['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Configuration</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="edit_config">
                                                        <input type="hidden" name="id" value="<?php echo $config['id']; ?>">
                                                        <?php echo csrfTokenField(); ?>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Game</label>
                                                                <select class="form-control" name="game_id" required>
                                                                    <?php foreach ($games as $game): ?>
                                                                        <option value="<?php echo $game['id']; ?>" <?php echo $game['id'] == $config['game_id'] ? 'selected' : ''; ?>><?php echo h($game['name']); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Configuration Key</label>
                                                                <input type="text" class="form-control" name="config_key" value="<?php echo h($config['config_key']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Configuration Value</label>
                                                                <textarea class="form-control" name="config_value" rows="3" required><?php echo h($config['config_value']); ?></textarea>
                                                                <div class="form-text">Maximum 10000 characters</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="2"><?php echo h($config['description']); ?></textarea>
                                                            </div>
                                                            <div class="mb-3 form-check">
                                                                <input type="checkbox" class="form-check-input" name="is_active" <?php echo $config['is_active'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">Active</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $config['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Configuration</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="delete_config">
                                                        <input type="hidden" name="id" value="<?php echo $config['id']; ?>">
                                                        <?php echo csrfTokenField(); ?>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the configuration "<?php echo h($config['config_key']); ?>" for game "<?php echo h($config['game_name'] ?? 'N/A'); ?>"?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>