<?php
require_once 'db_config.php';

// 1. Define the new user's credentials
$new_username  = 'Ichan';
$new_full_name = 'Christian Paulo';
$password_text = 'p@ssw0rd'; // The plain text password

// 2. Encrypt the password securely using standard Bcrypt
$secure_hash = password_hash($password_text, PASSWORD_BCRYPT);

try {
    // Establish connection using your config variables
    $pdo = new PDO($db, $un, $pw, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Check if username already exists to avoid duplicate constraint errors
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$new_username]);
    
    if ($check->fetch()) {
        die("Error: The username '{$new_username}' is already taken.");
    }

    // Insert the secure record
    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password) VALUES (?, ?, ?)");
    $stmt->execute([$new_username, $new_full_name, $secure_hash]);

    echo "<h3>Success! User registration completed.</h3>";
    echo "Username: <b>" . htmlspecialchars($new_username) . "</b><br>";
    echo "You can now log into the system log terminal.";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>