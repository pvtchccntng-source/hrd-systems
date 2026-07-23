<?php
// Ensure no output happens before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Config file initializes and handles the $pdo connection object
require_once 'db_config.php';

header('Content-Type: application/json');

// 1. SYSTEM SECURITY GUARD: Block any unauthenticated requests
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Unauthorized entry. Please log in again.'
    ]);
    exit;
}

// Securely reference the logged-in user throughout the session life cycle
$current_user = $_SESSION['username'];

// 🌟 PERFORMANCE FIX 1: Release the session file lock immediately!
// This stops simultaneous frontend 'fetch' polling requests from stalling your 'send' messages.
session_write_close(); 

$action = $_GET['action'] ?? '';
$jsonData = json_decode(file_get_contents('php://input'), true) ?? [];

// ==========================================
// 1. FETCH ARCHITECTURE 
// ==========================================
if ($action === 'fetch') {
    $is_open = (int)($_GET['is_open'] ?? 0);

    $maxStmt = $pdo->query("SELECT MAX(id) as max_id FROM chat_messages");
    $maxRow = $maxStmt->fetch();
    $max_id = $maxRow['max_id'] ? (int)$maxRow['max_id'] : 0;

    if ($is_open && $max_id > 0) {
        $seenStmt = $pdo->prepare("INSERT INTO user_last_seen (username, last_message_id) 
            VALUES (:username, :last_id) 
            ON DUPLICATE KEY UPDATE last_message_id = :last_id");
        $seenStmt->execute(['username' => $current_user, 'last_id' => $max_id]);
    }

    // 🌟 PERFORMANCE FIX 2: Optimized the LEFT JOIN. 
    // Removed the 'OR' condition matching full_name, which forced full table scans and dragged speeds down.
    $stmt = $pdo->query("SELECT m.*, 
                                u.full_name AS sender_display_name,
                                p.sender as reply_sender, 
                                p.message as reply_message, 
                                p.is_deleted as reply_parent_deleted
                         FROM (SELECT * FROM chat_messages ORDER BY id DESC LIMIT 50) m 
                         LEFT JOIN users u ON m.sender = u.username
                         LEFT JOIN chat_messages p ON m.reply_to_id = p.id 
                         ORDER BY m.id ASC");
    $messages = $stmt->fetchAll();
    
    if (!empty($messages)) {
        $msgIds = array_column($messages, 'id');
        $inClause = implode(',', array_fill(0, count($msgIds), '?'));
        
        $rStmt = $pdo->prepare("SELECT message_id, emoji, username FROM message_reactions WHERE message_id IN ($inClause)");
        $rStmt->execute($msgIds);
        $reactions = $rStmt->fetchAll();
        
        $reactionsMap = [];
        foreach ($reactions as $r) {
            $reactionsMap[$r['message_id']][] = [
                'emoji' => $r['emoji'],
                'username' => $r['username']
            ];
        }

        $seenMapStmt = $pdo->query("SELECT username, last_message_id FROM user_last_seen");
        $allSeenReceipts = $seenMapStmt->fetchAll();
        
        foreach ($messages as &$m) {
            $m['reactions'] = $reactionsMap[$m['id']] ?? [];
            $m['seen_by'] = [];
            
            foreach ($allSeenReceipts as $receipt) {
                if ((int)$receipt['last_message_id'] >= (int)$m['id']) {
                    $m['seen_by'][] = $receipt['username'];
                }
            }
        }
    }
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

// ==========================================
// 2. SEND MESSAGE & FILE ATTACHMENTS
// ==========================================
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $reply_to_id = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
    
    if (!empty($message)) {
        $message = preg_replace('/@\[([^\]]+)\]/', '@$1', $message);
    }
    
    $file_path = null;
    $file_type = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $filename;
        $extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $blocked_extensions = ['php', 'phtml', 'exe', 'bat', 'sh', 'js'];
        if (in_array($extension, $blocked_extensions)) {
            echo json_encode(['success' => false, 'error' => 'File type unauthorized.']);
            exit;
        }

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $file_path = $target_file;
            $mime = $_FILES['attachment']['type'];
            $file_type = strpos($mime, 'image/') === 0 ? 'image' : 'document';
        }
    }

    if (!empty($message) || $file_path !== null) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender, message, reply_to_id, file_path, file_type) VALUES (:sender, :message, :reply_to_id, :file_path, :file_type)");
        $stmt->execute([
            'sender' => $current_user,
            'message' => $message,
            'reply_to_id' => $reply_to_id,
            'file_path' => $file_path,
            'file_type' => $file_type
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Empty payload context.']);
    }
    exit;
}

// ==========================================
// 3. EDIT TEXT 
// ==========================================
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($jsonData['id'] ?? $_POST['id'] ?? 0);
    $message = trim($jsonData['message'] ?? $_POST['message'] ?? '');

    if ($id > 0 && !empty($message)) {
        $message = preg_replace('/@\[([^\]]+)\]/', '@$1', $message);

        $checkStmt = $pdo->prepare("SELECT message, sender FROM chat_messages WHERE id = :id");
        $checkStmt->execute(['id' => $id]);
        $msgItem = $checkStmt->fetch();

        if ($msgItem) {
            if ($msgItem['sender'] !== $current_user) {
                echo json_encode(['success' => false, 'error' => 'Action rejected: Message ownership mismatch.']);
                exit;
            }

            if ($msgItem['message'] === $message) {
                echo json_encode(['success' => true, 'info' => 'No contextual text changes detected.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE chat_messages SET message = :message, is_edited = 1 WHERE id = :id AND sender = :sender");
            $stmt->execute([
                'message' => $message, 
                'id' => $id,
                'sender' => $current_user
            ]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Target database reference missing.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing contextual parameters for processing updates.']);
    }
    exit;
}

// ==========================================
// 4. DELETE / UNSEND
// ==========================================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($jsonData['id'] ?? $_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = :id AND sender = :sender");
        $stmt->execute([
            'id' => $id,
            'sender' => $current_user
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Action rejected: Message ownership mismatch or missing ID.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID payload.']);
    }
    exit;
}

// ==========================================
// 5. REACTIONS ENGINE
// ==========================================
if ($action === 'react' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message_id = (int)($jsonData['message_id'] ?? $_POST['message_id'] ?? 0);
        $emoji = trim($jsonData['emoji'] ?? $_POST['emoji'] ?? '');

        if ($message_id > 0 && !empty($emoji)) {
            // Use your existing PDO connection
            $stmt = $pdo->prepare("SELECT id, emoji FROM message_reactions WHERE message_id = :message_id AND username = :username");
            $stmt->execute(['message_id' => $message_id, 'username' => $current_user]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($existing['emoji'] === $emoji) {
                    $delStmt = $pdo->prepare("DELETE FROM message_reactions WHERE id = :id");
                    $delStmt->execute(['id' => $existing['id']]);
                } else {
                    $upStmt = $pdo->prepare("UPDATE message_reactions SET emoji = :emoji WHERE id = :id");
                    $upStmt->execute(['emoji' => $emoji, 'id' => $existing['id']]);
                }
            } else {
                $insStmt = $pdo->prepare("INSERT INTO message_reactions (message_id, username, emoji) VALUES (:message_id, :username, :emoji)");
                $insStmt->execute(['message_id' => $message_id, 'username' => $current_user, 'emoji' => $emoji]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incomplete reaction payload metrics.']);
        }
    } catch (PDOException $e) {
        // This catches SQL errors and sends them as JSON instead of HTML
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 6. PROFILE SETTINGS ROUTE
// ==========================================
if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? $jsonData['full_name'] ?? '');

    if (!empty($full_name)) {
        try {
            $findStmt = $pdo->prepare("SELECT username, full_name FROM users WHERE username = :current_user OR full_name = :current_user");
            $findStmt->execute(['current_user' => $current_user]);
            $userRow = $findStmt->fetch();

            if ($userRow) {
                $username_primary = $userRow['username'];
                $old_full_name = !empty($userRow['full_name']) ? $userRow['full_name'] : $userRow['username'];

                if ($old_full_name !== $full_name) {
                    $pdo->beginTransaction();

                    $pStmt = $pdo->prepare("UPDATE users SET full_name = :full_name WHERE username = :username_primary");
                    $pStmt->execute(['full_name' => $full_name, 'username_primary' => $username_primary]);

                    $msgStmt = $pdo->prepare("UPDATE chat_messages SET sender = :full_name WHERE sender = :old_full_name OR sender = :current_user");
                    $msgStmt->execute(['full_name' => $full_name, 'old_full_name' => $old_full_name, 'current_user' => $current_user]);

                    $reactStmt = $pdo->prepare("UPDATE message_reactions SET username = :full_name WHERE username = :old_full_name OR username = :current_user");
                    $reactStmt->execute(['full_name' => $full_name, 'old_full_name' => $old_full_name, 'current_user' => $current_user]);

                    $seenStmt = $pdo->prepare("DELETE FROM user_last_seen WHERE username = :old_full_name OR username = :current_user");
                    $seenStmt->execute(['old_full_name' => $old_full_name, 'current_user' => $current_user]);

                    $systemAlertText = "{$old_full_name} changed their name to {$full_name}.";
                    $sysStmt = $pdo->prepare("INSERT INTO chat_messages (sender, message) VALUES ('System', :message)");
                    $sysStmt->execute(['message' => $systemAlertText]);

                    $pdo->commit();

                    // Re-open session briefly just to change variables if tracking by name
                    if (session_status() === PHP_SESSION_NONE) { session_start(); }
                    $_SESSION['username'] = $full_name;
                    session_write_close();
                    
                    echo json_encode(['success' => true, 'message' => 'Profile name updated and system notification generated successfully.']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'No name alterations detected.']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Account session match extraction failed.']);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'error' => 'Database failure during profile save: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No profile name modification received.']);
    }
    exit;
}

// ==========================================
// 7. LIST AVAILABLE GIFS
// ==========================================
if ($action === 'list_gifs') {
    // Change this to point directly to your root level gif folder
    $gifDir = __DIR__ . '/gif/'; 
    
    $gifs = [];
    if (is_dir($gifDir)) {
        // Filter out directory dots (. and ..)
        $files = array_diff(scandir($gifDir), array('.', '..'));
        foreach ($files as $file) {
            if (preg_match('/\.(gif)$/i', $file)) {
                $gifs[] = $file;
            }
        }
    }
    echo json_encode(['gifs' => array_values($gifs)]);
    exit;
}

// ==========================================
// 8. LIST ACTIVE MEMBERS FOR @MENTIONS
// ==========================================
if ($action === 'list_users') {
    try {
        $stmt = $pdo->query("SELECT DISTINCT username, full_name FROM users WHERE username IS NOT NULL AND username != '' ORDER BY COALESCE(NULLIF(full_name, ''), username) ASC");
        $userRecords = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'users' => $userRecords
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to pull system user rows.']);
    }
    exit;
}

// If no actions match
echo json_encode(['success' => false, 'error' => 'Invalid system action route.']);