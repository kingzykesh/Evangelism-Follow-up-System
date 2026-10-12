<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Use the correct session key 'user_role' and validate properly
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'worker') {
    exit('Unauthorized');
}

// ✅ Validate POST fields
if (isset($_POST['convert_id'], $_POST['followup_status'])) {

    $convert_id = $_POST['convert_id'];
    $status     = $_POST['followup_status'];
    $notes      = $_POST['notes'] ?? '';

    // Questions
    $questions = [
        'contacted'   => $_POST['contacted'] ?? '',
        'responded'   => $_POST['responded'] ?? '',
        'attended'    => $_POST['attended'] ?? '',
        'prayer_need' => $_POST['prayer_need'] ?? ''
    ];

    $worker_id = $_SESSION['user_id']; // or user_matric depending on your login session structure

    // ✅ Update follow-up
    if (update_followup_status($convert_id, $worker_id, $status, $notes, $questions)) {
        header("Location: worker_assigned_converts.php?updated=1");
        exit();
    } else {
        header("Location: worker_assigned_converts.php?error=update_failed");
        exit();
    }
} else {
    header("Location: worker_assigned_converts.php?error=invalid_data");
    exit();
}
