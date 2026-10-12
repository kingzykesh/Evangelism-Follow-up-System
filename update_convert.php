<?php
require_once 'functions.php';
check_auth();

$id = $_POST['id'] ?? null;
if (!$id) { echo json_encode(['error'=>'Missing ID']); exit; }

$stmt = $pdo->prepare("UPDATE converts SET name=?, phone_number=?, room_number=?, hostel=?, gender=?, status=?, notes=? WHERE id=?");
$ok = $stmt->execute([
  sanitize_input($_POST['name']),
  sanitize_input($_POST['phone_number']),
  sanitize_input($_POST['room_number']),
  sanitize_input($_POST['hostel']),
  sanitize_input($_POST['gender']),
  sanitize_input($_POST['status']),
  sanitize_input($_POST['notes']),
  $id
]);

echo json_encode($ok ? ['success'=>true] : ['error'=>'Failed to update']);
