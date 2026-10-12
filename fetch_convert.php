<?php
require_once 'functions.php';
check_auth();

$id = $_GET['id'] ?? null;
if (!$id) { echo json_encode(['error' => 'Invalid ID']); exit; }

$stmt = $pdo->prepare("SELECT * FROM converts WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo $data ? json_encode($data) : json_encode(['error' => 'Convert not found']);
