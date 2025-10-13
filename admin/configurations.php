<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';
require_once '../includes/AuthMiddleware.php';
require_once '../includes/SecurityMiddleware.php';
require_once '../includes/UtilityFunctions.php';

// Check authentication
$database = Database::getInstance();
$pdo = $database->getConnection();
$authMiddleware = new AuthMiddleware($pdo);
$security = new SecurityMiddleware($pdo);
$admin = $authMiddleware->requireAuth();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                // Validate input
                $rules = [
                    'game_id' => ['required' => true, 'type' => 'int'],
                    'config_key' => ['required' => true, 'type' => 'string', 'max_length' => 255],
                    'config_value' => ['required' => true, 'type' => 'json', 'max_length' => 65535],
                    'data_type' => ['required' => true, 'type' => 'string', 'max_length' => 20],
                    'category' => ['required' => false, 'type' => 'string', 'max_length' => 50, 'default' => 'general'],
                    'description' => ['required' => false, 'type' => 'string', 'max_length' => 1000, 'default' => null]
                ];

                list($validated, $errors) = $authMiddleware->validateConfigInput($_POST, $rules);

                if (!empty($errors)) {
                    $message = 'Validation errors: ' . implode(', ', $errors);
                    $messageType = 'error';
                } else {
                    // Convert value based on data type
                    $processedValue = processConfigValue($validated['config_value'], $validated['data_type']);

                    // Validate key and value
                    list($isValid, $error) = $authMiddleware->validateConfigKeyValue(
                        $validated['config_key'],
                        $processedValue,
                        $validated['data_type']
                    );

                    if (!$isValid) {
                        $message = $error;
                        $messageType = 'error';
                    } else {
                        // Check if configuration already exists
                        $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE game_id = ? AND config_key = ?");
                        $checkStmt->execute([$validated['game_id'], $validated['config_key']]);
                        if ($checkStmt->fetch()) {
                            $message = 'Configuration key already exists for this game';
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");

                            if ($stmt->execute([
                                $validated['game_id'],
                                $validated['config_key'],
                                json_encode($processedValue),
                                $validated['data_type'],
                                $validated['category'],
                                $validated['description']
                            ])) {
                                $message = 'Configuration created successfully';
                                $messageType = 'success';
                            } else {
                                $message = 'Failed to create configuration';
                                $messageType = 'error';
                            }
                        }
                    }
                }
                break;

            case 'delete':
                $configId = $_POST['config_id'] ?? '';
                $configId = $security->validateInput($configId, 'int');

                if ($configId !== false && !empty($configId)) {
                    // Verify configuration exists
                    $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE id = ?");
                    $checkStmt->execute([$configId]);
                    if ($checkStmt->fetch()) {
                        $stmt = $pdo->prepare("DELETE FROM configurations WHERE id = ?");
                        if ($stmt->execute([$configId])) {
                            $message = 'Configuration deleted successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to delete configuration';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Configuration not found';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid configuration ID';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get games for dropdown
$games = $pdo->query("SELECT id, name, game_id FROM games ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get configurations
$gameFilter = $_GET['game_id'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$sql = "
    SELECT c.*, g.name as game_name, g.game_id 
    FROM configurations c 
    JOIN games g ON c.game_id = g.id
    WHERE 1=1
";
$params = [];

if ($gameFilter) {
    $sql .= " AND c.game_id = ?";
    $params[] = $gameFilter;
}

if ($categoryFilter) {
    $sql .= " AND c.category = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY c.category, c.config_key";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM configurations ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurations - Game Configuration API</title>
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

        .filters {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            flex: 1;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .filter-group select {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
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
            min-height: 100px;
            font-family: 'Courier New', monospace;
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

        .category {
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            color: #495057;
        }

        .data-type {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }

        .config-value {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
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

        .tabs {
            display: flex;
            border-bottom: 1px solid #e1e5e9;
            margin-bottom: 1rem;
        }

        .tab {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            <li><a href="games.php">Games</a></li>
            <li><a href="configurations.php" class="active">Configurations</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <div class="filter-group">
                <label for="filter_game">Filter by Game:</label>
                <select id="filter_game" onchange="filterConfigs()">
                    <option value="">All Games</option>
                    <?php foreach ($games as $game): ?>
                        <option value="<?php echo $game['id']; ?>" <?php echo $gameFilter == $game['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($game['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter_category">Filter by Category:</label>
                <select id="filter_category" onchange="filterConfigs()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category; ?>" <?php echo $categoryFilter == $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Create New Configuration</h2>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="game_id">Game:</label>
                            <select id="game_id" name="game_id" required>
                                <option value="">Select a game</option>
                                <?php foreach ($games as $game): ?>
                                    <option value="<?php echo $game['id']; ?>"><?php echo htmlspecialchars($game['name']); ?> (<?php echo htmlspecialchars($game['game_id']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="config_key">Configuration Key:</label>
                            <input type="text" id="config_key" name="config_key" required placeholder="e.g., config.maxPlayers">
                        </div>
                        <div class="form-group">
                            <label for="data_type">Data Type:</label>
                            <select id="data_type" name="data_type" onchange="updateValueInput()">
                                <option value="string">String</option>
                                <option value="number">Number</option>
                                <option value="boolean">Boolean</option>
                                <option value="array">Array (JSON)</option>
                                <option value="object">Object (JSON)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <input type="text" id="category" name="category" value="general" placeholder="e.g., config, state, list">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="config_value">Configuration Value:</label>
                        <textarea id="config_value" name="config_value" required placeholder="Enter value..."></textarea>
                        <small id="valueHint">Enter a string value</small>
                    </div>
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" placeholder="Optional description..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Configuration</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Configurations</h2>
                <span><?php echo count($configurations); ?> total</span>
            </div>
            <div class="card-body">
                <?php if (empty($configurations)): ?>
                    <div class="empty">No configurations found. Create your first configuration above.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Key</th>
                                <th>Value</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configurations as $config): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($config['game_name']); ?></strong>
                                        <br><small class="date"><?php echo htmlspecialchars($config['game_id']); ?></small>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($config['config_key']); ?></code></td>
                                    <td>
                                        <div class="config-value" title="<?php echo htmlspecialchars(json_decode($config['config_value'])); ?>">
                                            <?php
                                            $value = json_decode($config['config_value']);
                                            if (is_bool($value)) {
                                                echo $value ? 'true' : 'false';
                                            } elseif (is_array($value) || is_object($value)) {
                                                echo json_encode($value);
                                            } else {
                                                echo htmlspecialchars((string)$value);
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td><span class="data-type"><?php echo $config['data_type']; ?></span></td>
                                    <td><span class="category"><?php echo htmlspecialchars($config['category']); ?></span></td>
                                    <td class="date"><?php echo date('M j, Y', strtotime($config['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $config['id']; ?>, '<?php echo htmlspecialchars($config['config_key']); ?>')">Delete</button>
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
    <div id="deleteModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: white; margin: 15% auto; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px;">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete configuration "<span id="deleteConfigName"></span>"?</p>
            <form method="post" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="config_id" id="deleteConfigId">
                <button type="submit" class="btn btn-danger">Delete Configuration</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function updateValueInput() {
            const dataType = document.getElementById('data_type').value;
            const valueInput = document.getElementById('config_value');
            const valueHint = document.getElementById('valueHint');

            switch (dataType) {
                case 'string':
                    valueHint.textContent = 'Enter a string value';
                    break;
                case 'number':
                    valueHint.textContent = 'Enter a numeric value (e.g., 10 or 2.5)';
                    break;
                case 'boolean':
                    valueHint.textContent = 'Enter true or false';
                    break;
                case 'array':
                    valueHint.textContent = 'Enter a JSON array (e.g., ["item1", "item2"])';
                    break;
                case 'object':
                    valueHint.textContent = 'Enter a JSON object (e.g., {"key": "value"})';
                    break;
            }
        }

        function confirmDelete(configId, configKey) {
            document.getElementById('deleteConfigId').value = configId;
            document.getElementById('deleteConfigName').textContent = configKey;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function filterConfigs() {
            const gameId = document.getElementById('filter_game').value;
            const category = document.getElementById('filter_category').value;

            let url = 'configurations.php';
            const params = new URLSearchParams();

            if (gameId) params.append('game_id', gameId);
            if (category) params.append('category', category);

            if (params.toString()) {
                url += '?' + params.toString();
            }

            window.location.href = url;
        }

        function clearFilters() {
            window.location.href = 'configurations.php';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Initialize
        updateValueInput();
    </script>
</body>

</html>