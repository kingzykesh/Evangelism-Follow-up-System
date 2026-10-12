<?php
require_once 'functions.php';
check_auth();

$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['error'=>'Invalid ID']); exit; }

$stmt = $pdo->prepare("SELECT id, name, matric_number, role, level, department, course, email, phone, is_active, created_at FROM users WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo $data ? json_encode($data) : json_encode(['error'=>'User not found']);
