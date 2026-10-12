<?php
require_once 'functions.php';
check_auth();

if (!has_permission(['super_admin','coordinator'])) {
  echo json_encode(['error'=>'Permission denied']); exit;
}
$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['error'=>'Missing ID']); exit; }

$stmt = $pdo->prepare("DELETE FROM converts WHERE id=?");
echo json_encode($stmt->execute([$id]) ? ['success'=>true] : ['error'=>'Delete failed']);
