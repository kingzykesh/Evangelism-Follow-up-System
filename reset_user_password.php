<?php
require_once 'config.php';
require_once 'functions.php';
check_auth();

if (!has_permission(['super_admin'])) {
    echo json_encode(['error' => 'Permission denied']);
    exit();
}

if (isset($_GET['id'])) {
    $user_id = sanitize_input($_GET['id']);
    $new_password = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$new_password, $user_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to reset password']);
    }
} else {
    echo json_encode(['error' => 'User ID required']);
}
?>