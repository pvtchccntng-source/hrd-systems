<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: text/plain; charset=utf-8');

$db = 'mysql:host=localhost;dbname=hrd_lamber_system';
$un = 'root';
$pw = '';

try {
    $pdo = new PDO($db, $un, $pw, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage();
    exit; 
}

// 1. Identify who is running this script (Prefer explicit username parameter over IP)
$current_user = $_GET['username'] ?? ''; 
$current_full_name = '';

if (!empty($current_user)) {
    // Look up full name by the passed username
    $user_stmt = $pdo->prepare("SELECT full_name FROM users WHERE username = :username LIMIT 1");
    $user_stmt->execute(['username' => $current_user]);
    $user_row = $user_stmt->fetch();
    if ($user_row) {
        $current_full_name = $user_row['full_name']; 
    }
} else {
    // Fallback to IP address if username wasn't passed
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($client_ip)) {
        $user_stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE ip_address = :ip LIMIT 1");
        $user_stmt->execute(['ip' => $client_ip]);
        $user_row = $user_stmt->fetch();
        if ($user_row) {
            $current_user = $user_row['username'];       
            $current_full_name = $user_row['full_name']; 
        }
    }
}

// 2. Fetch the latest un-deleted message not sent by the current user or the system
if (!empty($current_user)) {
    $stmt = $pdo->prepare("SELECT m.*, u.full_name AS sender_display_name 
                           FROM chat_messages m
                           LEFT JOIN users u ON m.sender = u.username OR m.sender = u.full_name
                           WHERE m.is_deleted = 0 
                             AND m.sender != :current_user 
                             AND m.sender != :current_full_name
                             AND m.sender != 'System'
                           ORDER BY m.id DESC LIMIT 1");
    $stmt->execute([
        'current_user' => $current_user,
        'current_full_name' => $current_full_name
    ]);
} else {
    $stmt = $pdo->query("SELECT m.*, u.full_name AS sender_display_name 
                         FROM chat_messages m
                         LEFT JOIN users u ON m.sender = u.username OR m.sender = u.full_name
                         WHERE m.is_deleted = 0 AND m.sender != 'System'
                         ORDER BY m.id DESC LIMIT 1");
}

$msg = $stmt->fetch();

if ($msg) {
    $sender_name = $msg['sender_display_name'] ?: $msg['sender'];
    $message_text = trim($msg['message']);
    
    $should_notify = true;  
    $is_special = false;     
    $alert_prefix = "New Message From {$sender_name}";

    // Format fallbacks for attachments
    if (empty($message_text) && !empty($msg['file_path'])) {
        $message_text = ($msg['file_type'] === 'image') ? '📷 Sent an image file.' : '📁 Sent a document attachment.';
        $is_special = true;
    } 
    elseif (preg_match('/^\[GIF:(.+)\]$/', $message_text)) {
        $message_text = '🖼️ Sent a custom GIF.';
        $is_special = true;
    }

    // Smart Mentions Parser
    if (strpos($message_text, '@') !== false) {
        if (stripos($message_text, '@everyone') !== false) { 
            $should_notify = true;
            $alert_prefix = "📢 ALL MENTIONED BY {$sender_name}:";
            $is_special = true;
        } else {
            $should_notify = false;

            // Match Username (e.g., @Ann)
            if (!empty($current_user) && stripos($message_text, '@' . $current_user) !== false) {
                $should_notify = true;
                $is_special = true;
                $alert_prefix = "🎯 YOU WERE MENTIONED by {$sender_name}:";
            }
            
            // Match Full Name (e.g., @GANDA)
            if (!$should_notify && !empty($current_full_name)) {
                if (stripos($message_text, '@' . $current_full_name) !== false || 
                    stripos($message_text, '@' . str_replace(' ', '', $current_full_name)) !== false) {
                    $should_notify = true;
                    $is_special = true;
                    $alert_prefix = "🎯 YOU WERE MENTIONED by {$sender_name}:";
                }
            }
        }
    }

    if ($should_notify) {
        $display_string = $is_special ? "{$alert_prefix} {$message_text}" : "{$alert_prefix}";
        echo "{$display_string}||{$msg['id']}";
    } else {
        echo ""; 
    }
} else {
    echo ""; 
}