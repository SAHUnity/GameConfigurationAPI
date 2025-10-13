<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/AuthMiddleware.php';
require_once '../includes/SecurityMiddleware.php';

// Check authentication
$database = Database::getInstance();
$pdo = $database->getConnection();
$authMiddleware = new AuthMiddleware($pdo);
$admin = $authMiddleware->requireAuth();
$security = new SecurityMiddleware($pdo);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                // Validate input
                $rules = [
                    'name' => ['required' => true, 'type' => 'string', 'max_length' => 100],
                    'game_id' => ['required' => true, 'type' => 'alphanumeric', 'max_length' => 50],
                    'description' => ['required' => false, 'type' => 'string', 'max_length' => 1000, 'default' => null],
                    'status' => ['required' => false, 'type' => 'string', 'max_length' => 20, 'default' => 'active']
                ];

                list($validated, $errors) = $authMiddleware->validateConfigInput($_POST, $rules);

                if (!empty($errors)) {
                    $message = 'Validation errors: ' . implode(', ', $errors);
                    $messageType = 'error';
                } else {
                    // Check if game_id already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_id = ?");
                    $checkStmt->execute([$validated['game_id']]);
                    if ($checkStmt->fetch()) {
                        $message = 'Game ID already exists';
                        $messageType = 'error';
                    } else {
                        // Generate API key
                        $auth = new Auth($pdo);
                        $apiKey = $auth->generateApiKey();
                        $hashedApiKey = hash('sha384', $apiKey) . hash('ripemd160', $apiKey);

                        $stmt = $pdo->prepare("
                            INSERT INTO games (name, game_id, api_key, description, status)
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        if ($stmt->execute([
                            $validated['name'],
                            $validated['game_id'],
                            $hashedApiKey,
                            $validated['description'],
                            $validated['status']
                        ])) {
                            $message = "Game created successfully! API Key: <code>" . htmlspecialchars($apiKey) . "</code>";
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create game';
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'delete':
                $gameId = $_POST['game_id'] ?? '';
                $gameId = $security->validateInput($gameId, 'int');

                if ($gameId !== false && !empty($gameId)) {
                    // Verify game exists
                    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
                    $checkStmt->execute([$gameId]);
                    if ($checkStmt->fetch()) {
                        $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
                        if ($stmt->execute([$gameId])) {
                            $message = 'Game deleted successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to delete game';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Game not found';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid game ID';
                    $messageType = 'error';
                }
                break;

            case 'update_status':
                $gameId = $_POST['game_id'] ?? '';
                $status = $_POST['status'] ?? '';

                $gameId = $security->validateInput($gameId, 'int');
                $status = $security->validateInput($status, 'string', 20);

                if ($gameId !== false && $status !== false && !empty($gameId)) {
                    // Validate status value
                    if (in_array($status, ['active', 'inactive'])) {
                        $stmt = $pdo->prepare("UPDATE games SET status = ? WHERE id = ?");
                        if ($stmt->execute([$status, $gameId])) {
                            $message = 'Game status updated successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update game status';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Invalid status value';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid input parameters';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all games
$games = $pdo->query("
    SELECT id, name, game_id, description, status, created_at, updated_at,
           (SELECT COUNT(*) FROM configurations WHERE game_id = games.id) as config_count
    FROM games 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - Game Configuration API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 1.5rem;
        }

        .header .user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav {
            background: #fff;
            border-bottom: 1px solid #e1e5e9;
            padding: 0 2rem;
        }

        .nav ul {
            list-style: none;
            display: flex;
        }

        .nav li {
            margin-right: 2rem;
        }

        .nav a {
            display: block;
            padding: 1rem 0;
            text-decoration: none;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .nav a:hover,
        .nav a.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: #333;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid #e1e5e9;
        }

        .table th {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status.active {
            background: #d4edda;
            color: #155724;
        }

        .status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .empty {
            text-align: center;
            color: #666;
            padding: 2rem;
        }

        .date {
            color: #666;
            font-size: 0.9rem;
        }

        .badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🎮 Game Configuration API</h1>
        <div class="user">
            <span>Welcome, <?php echo htmlspecialchars($admin['username']); ?></span>
            <a href="logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="games.php" class="active">Games</a></li>
            <li><a href="configurations.php">Configurations</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>Create New Game</h2>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Game Name:</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="game_id">Game ID:</label>
                            <input type="text" id="game_id" name="game_id" required pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Game</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Games</h2>
                <span><?php echo count($games); ?> total</span>
            </div>
            <div class="card-body">
                <?php if (empty($games)): ?>
                    <div class="empty">No games found. Create your first game above.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Game ID</th>
                                <th>Status</th>
                                <th>Configurations</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($game['name']); ?></strong>
                                        <?php if ($game['description']): ?>
                                            <br><small class="date"><?php echo htmlspecialchars($game['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($game['game_id']); ?></code></td>
                                    <td>
                                        <span class="status <?php echo $game['status']; ?>">
                                            <?php echo ucfirst($game['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge"><?php echo $game['config_count']; ?></span>
                                    </td>
                                    <td class="date"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($game['status'] === 'active'): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Deactivate this game?')">Deactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['name']); ?>')">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete "<span id="deleteGameName"></span>"? This will also delete all configurations associated with this game.</p>
            <form method="post" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="game_id" id="deleteGameId">
                <button type="submit" class="btn btn-danger">Delete Game</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(gameId, gameName) {
            document.getElementById('deleteGameId').value = gameId;
            document.getElementById('deleteGameName').textContent = gameName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>