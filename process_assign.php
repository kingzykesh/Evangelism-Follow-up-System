<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Allow both super_admin and coordinator roles
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['super_admin', 'coordinator'])) {
    exit('Unauthorized');
}

// ✅ Ensure required POST data
if (isset($_POST['convert_id'], $_POST['worker_id'])) {
    $convert_id = $_POST['convert_id'];
    $worker_id  = $_POST['worker_id'];
    $assigned_by = $_SESSION['user_id']; // or 'user_matric' depending on your login structure

    // Assign convert
    if (assign_convert($convert_id, $worker_id, $assigned_by)) {
        header("Location: coordinator_dashboard.php?assigned=1");
        exit();
    } else {
        header("Location: coordinator_dashboard.php?error=assign_failed");
        exit();
    }
} else {
    header("Location: coordinator_dashboard.php?error=invalid_data");
    exit();
}
