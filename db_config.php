<?php
$host     = getenv('DB_HOST');
$dbname   = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

try {

    // If getenv returns false, fall back to safe defaults or catch immediately
    if (!$host || !$dbname) {
        throw new Exception("Environment variables for database configuration are missing.");
    }

    // 1. Create the PDO instance and close the statement with a semicolon
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. NOW execute the timezone fix, right after the connection is successfully built
    $pdo->exec("SET time_zone = '+08:00';");

} catch (Exception $e) {
    // Force header type to JSON so the frontend receives a readable error payload
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Database connection fault: " . $e->getMessage()
    ]);
    exit;
}
?>
