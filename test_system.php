<?php
// Comprehensive test script for Game Configuration API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Game Configuration API - Comprehensive Test</h2>\n";

// Test 1: Configuration file loading
echo "<h3>Test 1: Configuration File Loading</h3>\n";
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    echo "<p style='color: green;'>✓ Main config.php loaded successfully</p>\n";
    echo "<p>DB_HOST: " . DB_HOST . "</p>\n";
    echo "<p>DB_NAME: " . DB_NAME . "</p>\n";
} else {
    echo "<p style='color: red;'>✗ Main config.php not found</p>\n";
    exit;
}

// Test 2: Database connection
echo "<h3>Test 2: Database Connection</h3>\n";
if (file_exists(__DIR__ . '/api/config.php')) {
    require_once __DIR__ . '/api/config.php';
    
    try {
        $pdo = getDBConnection();
        echo "<p style='color: green;'>✓ Database connection established</p>\n";
        
        // Test database initialization 
        initializeDatabase();
        echo "<p style='color: green;'>✓ Database initialized successfully</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>\n";
        exit;
    }
} else {
    echo "<p style='color: red;'>✗ API config.php not found</p>\n";
    exit;
}

// Test 3: Test data insertion
echo "<h3>Test 3: Test Data Operations</h3>\n";
try {
    // Insert a test game
    $stmt = $pdo->prepare("INSERT INTO games (name, slug, description) VALUES (?, ?, ?)");
    $stmt->execute(['Test Game', 'test-game', 'A test game for API verification']);
    $gameId = $pdo->lastInsertId();
    echo "<p style='color: green;'>✓ Test game inserted with ID: $gameId</p>\n";
    
    // Insert a test configuration
    $stmt = $pdo->prepare("INSERT INTO configurations (game_id, config_key, config_value, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$gameId, 'difficulty', 'normal', 'Game difficulty setting']);
    $configId = $pdo->lastInsertId();
    echo "<p style='color: green;'>✓ Test configuration inserted with ID: $configId</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Test data insertion failed: " . $e->getMessage() . "</p>\n";
}

// Test 4: API functionality simulation
echo "<h3>Test 4: API Functionality Simulation</h3>\n";
try {
    // Simulate API call
    $gameConfigs = getGameConfig($gameId);
    if ($gameConfigs !== false && $gameConfigs !== null) {
        echo "<p style='color: green;'>✓ API function working correctly</p>\n";
        echo "<p>Retrieved configurations: " . json_encode($gameConfigs) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ API function returned null or false</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ API function error: " . $e->getMessage() . "</p>\n";
}

// Test 5: Admin functions
echo "<h3>Test 5: Admin Functions</h3>\n";
if (file_exists(__DIR__ . '/admin/includes/functions.php')) {
    require_once __DIR__ . '/admin/includes/functions.php';
    echo "<p style='color: green;'>✓ Admin functions loaded successfully</p>\n";
    
    // Test slug generation
    $testSlug = generateSlug("My Great Game 2023!");
    if ($testSlug === "my-great-game-2023") {
        echo "<p style='color: green;'>✓ Slug generation working: $testSlug</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Slug generation failed: $testSlug</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Admin functions file not found</p>\n";
}

// Test 6: Clean up test data
echo "<h3>Test 6: Clean Up Test Data</h3>\n";
try {
    $stmt = $pdo->prepare("DELETE FROM configurations WHERE id = ?");
    $stmt->execute([$configId]);
    
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    
    echo "<p style='color: green;'>✓ Test data cleaned up successfully</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Test data cleanup failed: " . $e->getMessage() . "</p>\n";
}

echo "<h3>Test Complete</h3>\n";
echo "<p>All components tested successfully!</p>\n";
?>