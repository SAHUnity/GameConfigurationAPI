<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Check authentication
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

if (!$auth->validateAdminSession()) {
    header('Location: index.php');
    exit;
}

$admin = $auth->getCurrentAdmin();

// Get statistics
$gamesCount = $pdo->query("SELECT COUNT(*) as count FROM games")->fetch(PDO::FETCH_ASSOC)['count'];
$configsCount = $pdo->query("SELECT COUNT(*) as count FROM configurations")->fetch(PDO::FETCH_ASSOC)['count'];
$activeGamesCount = $pdo->query("SELECT COUNT(*) as count FROM games WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent games
$recentGames = $pdo->query("
    SELECT id, name, game_id, status, created_at 
    FROM games 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent configurations
$recentConfigs = $pdo->query("
    SELECT c.config_key, c.category, c.created_at, g.name as game_name
    FROM configurations c
    JOIN games g ON c.game_id = g.id
    ORDER BY c.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Game Configuration API</title>
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
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

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
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

        .empty {
            text-align: center;
            color: #666;
            padding: 2rem;
        }

        .date {
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
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="games.php">Games</a></li>
            <li><a href="configurations.php">Configurations</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Games</h3>
                <div class="number"><?php echo $gamesCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Games</h3>
                <div class="number"><?php echo $activeGamesCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Configurations</h3>
                <div class="number"><?php echo $configsCount; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Games</h2>
                <a href="games.php" class="btn btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentGames)): ?>
                    <div class="empty">No games found. <a href="games.php">Create your first game</a>.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Game ID</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentGames as $game): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($game['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($game['game_id']); ?></code></td>
                                    <td>
                                        <span class="status <?php echo $game['status']; ?>">
                                            <?php echo ucfirst($game['status']); ?>
                                        </span>
                                    </td>
                                    <td class="date"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Configurations</h2>
                <a href="configurations.php" class="btn btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentConfigs)): ?>
                    <div class="empty">No configurations found.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Configuration Key</th>
                                <th>Game</th>
                                <th>Category</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentConfigs as $config): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($config['config_key']); ?></code></td>
                                    <td><?php echo htmlspecialchars($config['game_name']); ?></td>
                                    <td><span class="category"><?php echo htmlspecialchars($config['category']); ?></span></td>
                                    <td class="date"><?php echo date('M j, Y', strtotime($config['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>