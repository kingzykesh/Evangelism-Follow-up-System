<?php
require_once 'functions.php';
check_auth();

if ($_SESSION['user_role'] !== 'super_admin') {
  echo json_encode(['error'=>'Permission denied']); exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['error'=>'Missing ID']); exit; }

$stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role!='super_admin'");
echo json_encode($stmt->execute([$id]) ? ['success'=>true] : ['error'=>'Delete failed']);
