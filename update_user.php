<?php
require_once 'functions.php';
check_auth();

$id = $_POST['id'] ?? null;
if (!$id) { echo json_encode(['error'=>'Missing ID']); exit; }

$stmt = $pdo->prepare("UPDATE users SET name=?, department=?, level=?, email=?, phone=?, role=?, is_active=? WHERE id=?");
$ok = $stmt->execute([
  sanitize_input($_POST['name']),
  sanitize_input($_POST['department']),
  sanitize_input($_POST['level']),
  sanitize_input($_POST['email']),
  sanitize_input($_POST['phone']),
  sanitize_input($_POST['role']),
  isset($_POST['is_active']) ? 1 : 0,
  $id
]);

echo json_encode($ok ? ['success'=>true] : ['error'=>'Failed to update']);
