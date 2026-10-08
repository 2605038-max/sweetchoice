<?php
/**
 * Sweet Choice - Database Connection (PDO)
 * Reusable database configuration and PDO instance provider
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'sweetchoice';
$db_user = getenv('DB_USER') ?: 'sweetchoice';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'sweetchoice123';

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Fallback attempt with root / blank password (common for default XAMPP/MAMP)
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e2) {
        // Output clean error message without exposing credentials
        error_log("Database connection error: " . $e2->getMessage());
        die("Database connection failed. Please ensure MySQL is running and database 'sweetchoice' is imported.");
    }
}

/**
 * Global database getter function
 * @return PDO
 */
function getDB(): PDO {
    global $pdo;
    return $pdo;
}
