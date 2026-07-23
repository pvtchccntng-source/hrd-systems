<?php
require_once 'db_config.php'; // $pdo is already initialized and ready to use

// 1. Define the new user's credentials
$new_username  = 'Carlicakes';
$new_full_name = 'Carl Anthony';
$password_text = '12345'; // The plain text password

// 2. Encrypt the password securely (PASSWORD_DEFAULT uses the strongest current algorithm)
$secure_hash = password_hash($password_text, PASSWORD_DEFAULT);

try {
    // 3. Check if username already exists to avoid duplicate entry errors
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$new_username]);
    
    if ($check->fetch()) {
        die("<h3 style='color: red;'>Error: The username '" . htmlspecialchars($new_username) . "' is already taken.</h3>");
    }

    // 4. Insert the secure record
    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password) VALUES (?, ?, ?)");
    $stmt->execute([$new_username, $new_full_name, $secure_hash]);

    echo "<h3>Success! User registration completed.</h3>";
    echo "Username: <b>" . htmlspecialchars($new_username) . "</b><br>";
    echo "You can now log into the system log terminal.";

} catch (PDOException $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage());
}
?>
