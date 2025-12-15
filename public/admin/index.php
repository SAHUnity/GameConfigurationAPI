<?php

use App\Auth;
use App\CacheService;
use App\Config;
use App\Models\Configuration;
use App\Models\Game;

// 1. Setup & Auth
$possiblePaths = [
    __DIR__ . '/../../', // Standard
    __DIR__ . '/../',    // Flattened
];

$rootPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . 'autoload.php')) {
        $rootPath = $path;
        break;
    }
}

if (!$rootPath) die("Critical Error: Core files not found.");

require $rootPath . 'autoload.php';

try {
    Config::load($rootPath . '.env');
} catch (Exception $e) {
    die("Configuration Error");
}

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

// 2. Handle Actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cacheService = new CacheService();

    try {
        if ($action === 'logout') {
            Auth::logout();
            header('Location: login.php');
            exit;
        }
        
        if ($action === 'create_game') {
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                Game::create($name);
                $message = "Game created successfully.";
            }
        }
        
        if ($action === 'regenerate_key') {
            $gameId = (int)$_POST['game_id'];
            if ($gameId) {
                // Delete old cache first
                if (!empty($_POST['old_key'])) {
                    $cacheService->delete($_POST['old_key']);
                }
                
                Game::regenerateKey($gameId);
                $cacheService->refresh($gameId); // Will create new file
                $message = "API Key regenerated.";
            }
        }

        if ($action === 'delete_game') {
            $gameId = (int)$_POST['game_id'];
            $apiKey = $_POST['api_key'] ?? '';
            if ($gameId) {
                Game::delete($gameId);
                if ($apiKey) $cacheService->delete($apiKey);
                $message = "Game deleted.";
            }
        }

        if ($action === 'add_config') {
            $gameId = (int)$_POST['game_id'];
            $key = trim($_POST['key'] ?? '');
            $value = trim($_POST['value'] ?? ''); // Assume valid JSON or String
            $desc = trim($_POST['description'] ?? '');
            
            if ($gameId && $key) {
                Configuration::create($gameId, $key, $value, $desc);
                $cacheService->refresh($gameId); // CRITICAL: Update Cache
                $message = "Config added.";
            }
        }
        
        if ($action === 'delete_config') {
            $configId = (int)$_POST['config_id'];
            $gameId = (int)$_POST['game_id'];
            if ($configId && $gameId) {
                Configuration::delete($configId);
                $cacheService->refresh($gameId); // CRITICAL: Update Cache
                $message = "Config deleted.";
            }
        }

        if ($action === 'edit_config') {
            $configId = (int)$_POST['config_id'];
            $gameId = (int)$_POST['game_id'];
            $value = trim($_POST['value'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            
            if ($configId && $gameId) {
                Configuration::update($configId, $value, $desc);
                $cacheService->refresh($gameId); // CRITICAL: Update Cache
                $message = "Config updated.";
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 3. Render View
$games = Game::getAll();
$activeGameId = (int)($_GET['game_id'] ?? ($_POST['game_id'] ?? 0));
$activeGame = null;
$configs = [];

if ($activeGameId) {
    foreach ($games as $g) {
        if ($g['id'] === $activeGameId) {
            $activeGame = $g;
            break;
        }
    }
    if ($activeGame) {
        $configs = Configuration::getAllForGame($activeGameId);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Config Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .sidebar { min-height: 100vh; background: #f8f9fa; border-right: 1px solid #dee2e6; }
        .nav-link.active { background: #e9ecef; }
        /* Fix table layout issues */
        .table-fixed { table-layout: fixed; width: 100%; }
        .text-truncate-multiline {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word; /* Ensure extremely long strings break */
        }
        pre.val-preview {
            white-space: pre-wrap;       /* css-3 */
            white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
            white-space: -pre-wrap;      /* Opera 4-6 */
            white-space: -o-pre-wrap;    /* Opera 7 */
            word-wrap: break-word;       /* Internet Explorer 5.5+ */
            background: transparent;
            padding: 0;
            border: 0;
            margin: 0;
            max-height: 100px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar: Games List -->
            <div class="col-md-3 col-lg-2 sidebar py-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Games</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createGameModal"><i class="bi bi-plus"></i></button>
                </div>
                
                <div class="list-group">
                    <?php foreach ($games as $game): ?>
                        <a href="?game_id=<?= $game['id'] ?>" class="list-group-item list-group-item-action <?= $activeGameId === $game['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($game['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="mt-4 border-top pt-3">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-outline-danger w-100 btn-sm">Logout</button>
                </form>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 py-3">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible show"><?= htmlspecialchars($message) ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible show"><?= htmlspecialchars($error) ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <?php if ($activeGame): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><?= htmlspecialchars($activeGame['name']) ?></h2>
                        <div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Regenerate API Key? This will invalidate the current key.');">
                                <input type="hidden" name="action" value="regenerate_key">
                                <input type="hidden" name="game_id" value="<?= $activeGame['id'] ?>">
                                <input type="hidden" name="old_key" value="<?= $activeGame['api_key'] ?>">
                                <button class="btn btn-warning btn-sm">Regenerate Key</button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete Game? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete_game">
                                <input type="hidden" name="api_key" value="<?= $activeGame['api_key'] ?>">
                                <input type="hidden" name="game_id" value="<?= $activeGame['id'] ?>">
                                <button class="btn btn-danger btn-sm">Delete Game</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">API Key</h6>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Key</span>
                                <input type="text" class="form-control font-monospace" value="<?= $activeGame['api_key'] ?>" readonly id="apiKeyInfo">
                                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('apiKeyInfo').value)">Copy</button>
                            </div>
                            <small class="text-muted d-block">Endpoint: <code>/api/v1</code></small>
                            <small class="text-muted d-block">Header: <code>X-API-KEY: <?= $activeGame['api_key'] ?></code></small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Configurations</h4>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConfigModal">Add Config</button>
                    </div>

                    <div class="table-responsive">
                    <table class="table table-hover table-bordered table-fixed">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 20%">Key</th>
                                <th style="width: 45%">Value</th>
                                <th style="width: 25%">Description</th>
                                <th style="width: 10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configs as $cfg): ?>
                                <tr>
                                    <td class="text-break"><?= htmlspecialchars($cfg['key_name']) ?></td>
                                    <td>
                                        <div class="text-truncate-multiline font-monospace" style="font-size: 0.9em;">
                                            <?= htmlspecialchars($cfg['value']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($cfg['description']) ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                        <button class="btn btn-primary btn-sm p-1 px-2" 
                                            onclick="openEditModal(
                                                <?= $cfg['id'] ?>, 
                                                '<?= htmlspecialchars(addslashes($cfg['key_name'])) ?>', 
                                                `<?= htmlspecialchars($cfg['value']) ?>`, 
                                                '<?= htmlspecialchars(addslashes($cfg['description'])) ?>'
                                            )">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Delete this config?');">
                                            <input type="hidden" name="action" value="delete_config">
                                            <input type="hidden" name="game_id" value="<?= $activeGame['id'] ?>">
                                            <input type="hidden" name="config_id" value="<?= $cfg['id'] ?>">
                                            <button class="btn btn-danger btn-sm p-1 px-2"><i class="bi bi-trash"></i></button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($configs)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No configurations found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>

                <?php else: ?>
                    <div class="text-center mt-5 text-muted">
                        <h4>Select a game from the sidebar or create a new one.</h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Game Modal -->
    <div class="modal fade" id="createGameModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="create_game">
                <div class="modal-header">
                    <h5 class="modal-title">Create Game</h5>
                </div>
                <div class="modal-body">
                    <input type="text" name="name" class="form-control" placeholder="Game Name" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Config Modal -->
    <?php if ($activeGame): ?>
    <div class="modal fade" id="addConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="add_config">
                <input type="hidden" name="game_id" value="<?= $activeGame['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Configuration</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Key Name</label>
                        <input type="text" name="key" class="form-control" required placeholder="e.g. max_players">
                    </div>
                    <div class="mb-3">
                        <label>Value (JSON or String)</label>
                        <textarea name="value" class="form-control font-monospace" rows="5" required placeholder='{"foo": "bar"}'></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Description (Optional)</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Config</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Config Modal (Same structure, populated via JS) -->
    <div class="modal fade" id="editConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="edit_config">
                <input type="hidden" name="game_id" value="<?= $activeGame['id'] ?>">
                <input type="hidden" name="config_id" id="edit_config_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Configuration</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Key Name</label>
                        <input type="text" id="edit_key" class="form-control" disabled>
                        <small class="text-muted">Key name cannot be changed.</small>
                    </div>
                    <div class="mb-3">
                        <label>Value</label>
                        <textarea name="value" id="edit_value" class="form-control font-monospace" rows="10" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <input type="text" name="description" id="edit_description" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(id, key, value, desc) {
            document.getElementById('edit_config_id').value = id;
            document.getElementById('edit_key').value = key;
            document.getElementById('edit_value').value = value;
            document.getElementById('edit_description').value = desc;
            new bootstrap.Modal(document.getElementById('editConfigModal')).show();
        }
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
