<?php
// Hardcoded credentials from your cPanel Free dashboard
$host     = 'sql312.cpanelfree.com';
$dbname   = 'cpfr_42384598_lumber_chat';
$username = 'cpfr_42384598';
$password = '8nnjYy69P0';

try {
    // Attempt to connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
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