<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/functions.php';
// Include API functions for cache invalidation
require_once __DIR__ . '/../api/functions.php';

requireLogin();

// CSRF Token Generation with enhanced security
$csrf_token = generateCsrfToken();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token with enhanced validation
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired CSRF token';
        logSecurityEvent('CSRF_TOKEN_INVALID', getClientIP(), ['form' => 'games']);
    } elseif (isset($_POST['action'])) {
        $pdo = getDBConnection();

        if ($_POST['action'] === 'add_game') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Input validation
            $errors = validateInput(
                ['name' => $name],
                ['name'],
                [
                    'name' => [
                        'min_length' => 1,
                        'max_length' => 255,
                        'regex' => '/^[a-zA-Z0-9\s\-_]+$/'
                    ],
                    'description' => [
                        'max_length' => 10000
                    ]
                ]
            );

            if (!empty($errors)) {
                $error = implode(', ', $errors);
            } else {
                // Generate a secure API key for the new game
                $apiKey = generateApiKey();

                try {
                    $stmt = $pdo->prepare("INSERT INTO games (name, api_key, description) VALUES (?, ?, ?)");
                    $stmt->execute([sanitizeInput($name), $apiKey, sanitizeInput($description)]);
                    $message = 'Game added successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = 'Database error occurred';
                }
            }
        } elseif ($_POST['action'] === 'edit_game') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Input validation
            $errors = validateInput(
                ['name' => $name, 'id' => $id],
                ['name', 'id'],
                [
                    'name' => [
                        'min_length' => 1,
                        'max_length' => 255,
                        'regex' => '/^[a-zA-Z0-9\s\-_]+$/'
                    ],
                    'description' => [
                        'max_length' => 10000
                    ],
                    'id' => [
                        'type' => 'int'
                    ]
                ]
            );

            if (!empty($errors) || $id <= 0) {
                $error = implode(', ', $errors);
                if ($id <= 0) $error .= ($error ? ', ' : '') . 'Valid game ID is required';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE games SET name=?, description=? WHERE id=?");
                    $stmt->execute([sanitizeInput($name), sanitizeInput($description), $id]);
                    
                    // Invalidate cache for this game (name might be used in some responses)
                    clearGameCache($id);
                    
                    $message = 'Game updated successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = 'Database error occurred';
                }
            }
        } elseif ($_POST['action'] === 'delete_game') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                $error = 'Valid game ID is required';
            } else {
                try {
                    // Get API key before deletion to clear cache
                    $stmt = $pdo->prepare("SELECT api_key FROM games WHERE id = ?");
                    $stmt->execute([$id]);
                    $apiKey = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("DELETE FROM games WHERE id=?");
                    $stmt->execute([$id]);
                    
                    // Invalidate cache
                    if ($apiKey) {
                        clearCacheByApiKey($apiKey);
                    }
                    
                    $message = 'Game deleted successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = 'Database error occurred';
                }
            }
        } elseif ($_POST['action'] === 'regenerate_api_key') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                $error = 'Valid game ID is required';
            } else {
                try {
                    // Get old API key to clear cache
                    $stmt = $pdo->prepare("SELECT api_key FROM games WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldApiKey = $stmt->fetchColumn();

                    $newApiKey = generateApiKey();
                    $stmt = $pdo->prepare("UPDATE games SET api_key = ? WHERE id = ?");
                    $stmt->execute([$newApiKey, $id]);
                    
                    // Invalidate cache for old key
                    if ($oldApiKey) {
                        clearCacheByApiKey($oldApiKey);
                    }
                    
                    $message = 'API key regenerated successfully';
                    // Regenerate CSRF token after successful action
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (PDOException $e) {
                    $error = 'Database error occurred';
                }
            }
        }
    }
}

// Get all games
$pdo = getDBConnection();
$games = $pdo->query("SELECT id, name, api_key, description, created_at FROM games ORDER BY name")->fetchAll();

// CSRF token is already generated above
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show a success message without alert
                const originalText = event.target.textContent;
                event.target.textContent = 'Copied!';
                setTimeout(() => {
                    event.target.textContent = originalText;
                }, 2000);
            }, function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy API key to clipboard');
            });
        }
    </script>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Games</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo h($message); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <!-- Add Game Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add New Game</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_game">
                            <?php echo csrfTokenField(); ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Game Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Game</button>
                        </form>
                    </div>
                </div>

                <!-- Games List -->
                <div class="card">
                    <div class="card-header">
                        <h5>Games List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>API Key</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($games as $game): ?>
                                        <tr>
                                            <td><?php echo h($game['name']); ?></td>
                                            <td>
                                                <?php if ($game['api_key']): ?>
                                                    <span class="text-muted">••••••••</span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#apiKeyModal<?php echo $game['id']; ?>">View</button>
                                                <?php else: ?>
                                                    <span class="text-warning">No API key</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($game['description']); ?></td>
                                            <td><?php echo h($game['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $game['id']; ?>">Edit</button>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#regenerateModal<?php echo $game['id']; ?>">Regenerate Key</button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $game['id']; ?>">Delete</button>
                                            </td>
                                        </tr>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $game['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Game</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="edit_game">
                                                        <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                                                        <?php echo csrfTokenField(); ?>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Game Name</label>
                                                                <input type="text" class="form-control" name="name" value="<?php echo h($game['name']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3"><?php echo h($game['description']); ?></textarea>
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

                                        <!-- Regenerate API Key Modal -->
                                        <div class="modal fade" id="regenerateModal<?php echo $game['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Regenerate API Key</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="regenerate_api_key">
                                                        <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                                                        <?php echo csrfTokenField(); ?>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to regenerate the API key for "<?php echo h($game['name']); ?>"? This will change the API key and all applications using the old key will need to be updated.</p>
                                                            <div class="alert alert-warning">
                                                                <strong>Warning:</strong> After regenerating the API key, all game clients that use this key will need to be updated with the new key.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Regenerate Key</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- API Key Modal -->
                                        <div class="modal fade" id="apiKeyModal<?php echo $game['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">API Key for <?php echo h($game['name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Your API key is:</p>
                                                        <div class="alert alert-info">
                                                            <code id="apiKeyDisplay<?php echo $game['id']; ?>"><?php echo h($game['api_key']); ?></code>
                                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="copyToClipboard('<?php echo h(addslashes($game['api_key'])); ?>')">Copy</button>
                                                        </div>
                                                        <div class="alert alert-info">
                                                            <strong>Note:</strong> API keys can be viewed anytime by clicking the 'View' button. Please store your API key securely.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $game['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Game</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="delete_game">
                                                        <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                                                        <?php echo csrfTokenField(); ?>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the game "<?php echo h($game['name']); ?>"? This will also delete all configurations associated with this game.</p>
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