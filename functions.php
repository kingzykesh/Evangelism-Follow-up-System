<?php
require_once 'config.php';

// Security: Sanitize input
function sanitize_input($data) {
    if ($data === null) {
        return null;
    }
    
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Authentication check
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Role-based access control
function has_permission($required_roles) {
    if (!isset($_SESSION['user_role'])) return false;
    if (in_array('super_admin', $required_roles) && $_SESSION['user_role'] === 'super_admin') return true;
    return in_array($_SESSION['user_role'], $required_roles);
}

// Create notification
function create_notification($user_id, $title, $message, $type = 'info', $module = null, $related_id = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_module, related_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $message, $type, $module, $related_id]);
    return $pdo->lastInsertId();
}

// Log activity
function log_activity($user_id, $action, $description = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip, $agent]);
}

//email
function send_email($to, $subject, $message, $name = '') {
    try {
        // Manual PHPMailer inclusion
        require_once 'phpmailer/PHPMailer.php';
        require_once 'phpmailer/SMTP.php';
        require_once 'phpmailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.ezirimkingdom.com.ng';
        $mail->SMTPAuth = true;
        $mail->Username = 'evangelism@ezirimkingdom.com.ng';
        $mail->Password = 'Tu9b8dced';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Recipients
        $mail->setFrom('evangelism@ezirimkingdom.com.ng', 'Evangelism System');
        $mail->addAddress($to, $name);
        $mail->addReplyTo('evangelism@ezirimkingdom.com.ng', 'Evangelism System');
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        
        // Personalize the message and decode HTML entities
        $decoded_message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
        $personalized_message = "Dear $name,\n\n$decoded_message\n\nBlessings,\nYour Christian Family";
        $mail->Body = $personalized_message;
        
        // Send email
        $mail->send();
        
        // Log successful email (store original message with entities for display)
        log_email($_SESSION['user_id'], $to, $subject, $message, 'sent');
        return true;
        
    } catch (Exception $e) {
        // Log failed email
        log_email($_SESSION['user_id'], $to, $subject, $message, 'failed');
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

//email logs
function log_email($sender_id, $recipient_email, $subject, $message, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (sender_id, recipient_email, subject, message, status, sent_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$sender_id, $recipient_email, $subject, $message, $status]);
        
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
        return false;
    }
}

// Get user notifications
function get_user_notifications($user_id, $unread_only = false) {
    global $pdo;
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unread_only) $sql .= " AND is_read = FALSE";
    $sql .= " ORDER BY created_at DESC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Send SMS (Mock function - integrate with actual SMS API)
function send_sms($phone, $message) {
    $api_token = 'BCsaFrIWEA36o54jtYtaQhyvpcCkpNWWj0cu1VtsPZX494qCcurcOhIF5T2r';
    $sender_id = 'kingdom';
    $base_url = 'https://www.bulksmsnigeria.com/api/v1/sms/create';

    $data = [
        'api_token' => $api_token,
        'from' => $sender_id,
        'to' => $phone,
        'body' => $message,
        'dnd' => 2
    ];

    $ch = curl_init($base_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    // Debug info
    echo "<pre>";
    echo "API Response:\n";
    var_dump($response);
    echo "\nCURL Error:\n";
    var_dump($error);
    echo "</pre>";

    if ($error) {
        return false;
    }

    $result = json_decode($response, true);
    if (isset($result['data']) && $result['data']['status'] === 'success') {
        return true;
    }
    return false;
}

function get_recent_converts($limit = 5) {
    global $pdo;
    
    $sql = "SELECT 
                c.name as convert_name,
                c.status,
                c.date_submitted,
                u.name as worker_name
            FROM converts c
            JOIN users u ON c.worker_id = u.id
            ORDER BY c.date_submitted DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Export data to various formats
function export_data($data, $format = 'csv', $filename = 'export') {
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Add data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    exit();
}

// Get badge color based on status
function get_badge_color($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'converted' => 'bg-green-100 text-green-800',
        'contacted' => 'bg-blue-100 text-blue-800',
        'prayed_for' => 'bg-purple-100 text-purple-800',
        'completed' => 'bg-green-100 text-green-800',
        'not_reachable' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

/* =======================================
   🔧 EvangelismTracker — Coordinator & Worker Functions
   ======================================= */

function get_workers() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'worker' AND is_active = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_converts() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM converts ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assign_convert($convert_id, $worker_id, $assigned_by) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO convert_assignments (convert_id, worker_id, assigned_by)
                           VALUES (?, ?, ?)");
    return $stmt->execute([$convert_id, $worker_id, $assigned_by]);
}

function get_assigned_converts($worker_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.*, ca.followup_status, ca.notes
                           FROM converts c
                           JOIN convert_assignments ca ON c.id = ca.convert_id
                           WHERE ca.worker_id = ?");
    $stmt->execute([$worker_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_followup_status($convert_id, $worker_id, $status, $notes, $questions) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE convert_assignments
                           SET followup_status = ?, notes = ?, followup_questions = ?, updated_at = NOW()
                           WHERE convert_id = ? AND worker_id = ?");
    return $stmt->execute([$status, $notes, json_encode($questions), $convert_id, $worker_id]);
}

function get_followup_reports() {
    global $pdo;
    $stmt = $pdo->query("SELECT ca.*, c.name AS convert_name, u.name AS worker_name
                         FROM convert_assignments ca
                         JOIN converts c ON ca.convert_id = c.id
                         JOIN users u ON ca.worker_id = u.id
                         ORDER BY ca.updated_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Search converts with filters
function search_converts($filters = []) {
    global $pdo;
    
    $sql = "SELECT c.*, u.name as worker_name, u.matric_number as worker_matric 
            FROM converts c 
            LEFT JOIN users u ON c.worker_id = u.id 
            WHERE 1=1";
    $params = [];
    
    // Status filter
    if (!empty($filters['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $filters['status'];
    }
    
    // Date range filter
    if (!empty($filters['start_date'])) {
        $sql .= " AND DATE(c.date_submitted) >= ?";
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $sql .= " AND DATE(c.date_submitted) <= ?";
        $params[] = $filters['end_date'];
    }
    
    // Search term
    if (!empty($filters['search'])) {
        $sql .= " AND (c.name LIKE ? OR c.phone_number LIKE ? OR u.name LIKE ? OR u.matric_number LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    // Worker filter
    if (!empty($filters['worker_id'])) {
        $sql .= " AND c.worker_id = ?";
        $params[] = $filters['worker_id'];
    }
    
    $sql .= " ORDER BY c.date_submitted DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =======================================
   🔧 NEW FUNCTIONS FOR ASSIGNMENT MANAGEMENT
   ======================================= */

/**
 * Get converts that haven't been assigned to any worker
 */
function get_unassigned_converts() {
    global $pdo;
    
    $sql = "SELECT c.* 
            FROM converts c 
            LEFT JOIN convert_assignments ca ON c.id = ca.convert_id 
            WHERE ca.id IS NULL 
            ORDER BY c.date_submitted DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting unassigned converts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get workers who have no convert assignments
 */
function get_workers_with_no_assignments() {
    global $pdo;
    
    $sql = "SELECT u.* 
            FROM users u 
            LEFT JOIN convert_assignments ca ON u.id = ca.worker_id 
            WHERE u.role = 'worker' 
            AND u.is_active = 1 
            AND ca.id IS NULL 
            ORDER BY u.name";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting workers with no assignments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get worker assignment statistics
 */
function get_worker_assignment_stats() {
    global $pdo;
    
    $sql = "SELECT 
                u.id as worker_id,
                u.name as worker_name,
                u.department,
                COUNT(ca.id) as total_assignments,
                SUM(CASE WHEN ca.followup_status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN ca.followup_status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_count,
                SUM(CASE WHEN ca.followup_status = 'pending' OR ca.followup_status IS NULL THEN 1 ELSE 0 END) as pending_count,
                MAX(ca.updated_at) as last_assignment
            FROM users u 
            LEFT JOIN convert_assignments ca ON u.id = ca.worker_id 
            WHERE u.role = 'worker' 
            AND u.is_active = 1 
            GROUP BY u.id, u.name, u.department 
            ORDER BY total_assignments DESC, u.name";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting worker assignment stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get worker performance statistics
 */
function get_worker_performance_stats($timeframe = 'month') {
    global $pdo;
    
    $date_condition = "";
    switch ($timeframe) {
        case 'week':
            $date_condition = "AND ca.updated_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "AND ca.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $date_condition = "AND ca.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $date_condition = "AND ca.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
    
    $sql = "SELECT 
                u.id,
                u.name,
                u.department,
                COUNT(ca.id) as total_assignments,
                SUM(CASE WHEN ca.followup_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN ca.followup_status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ca.followup_status = 'pending' OR ca.followup_status IS NULL THEN 1 ELSE 0 END) as pending,
                ROUND((SUM(CASE WHEN ca.followup_status = 'completed' THEN 1 ELSE 0 END) / COUNT(ca.id)) * 100, 2) as completion_rate
            FROM users u 
            LEFT JOIN convert_assignments ca ON u.id = ca.worker_id 
            WHERE u.role = 'worker' 
            AND u.is_active = 1 
            $date_condition
            GROUP BY u.id, u.name, u.department 
            ORDER BY completion_rate DESC, total_assignments DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting worker performance stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get assignment overview for dashboard
 */
function get_assignment_overview() {
    global $pdo;
    
    $sql = "SELECT 
                (SELECT COUNT(*) FROM converts WHERE id NOT IN (SELECT convert_id FROM convert_assignments)) as unassigned_converts,
                (SELECT COUNT(*) FROM users WHERE role = 'worker' AND is_active = 1 AND id NOT IN (SELECT worker_id FROM convert_assignments)) as available_workers,
                (SELECT COUNT(*) FROM convert_assignments WHERE followup_status = 'completed') as completed_assignments,
                (SELECT COUNT(*) FROM convert_assignments WHERE followup_status = 'in-progress') as in_progress_assignments,
                (SELECT COUNT(*) FROM convert_assignments WHERE followup_status = 'pending' OR followup_status IS NULL) as pending_assignments";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting assignment overview: " . $e->getMessage());
        return [
            'unassigned_converts' => 0,
            'available_workers' => 0,
            'completed_assignments' => 0,
            'in_progress_assignments' => 0,
            'pending_assignments' => 0
        ];
    }
}

/**
 * Get recent assignments activity
 */
function get_recent_assignments_activity($limit = 10) {
    global $pdo;
    
    $sql = "SELECT 
                ca.*,
                c.name as convert_name,
                u.name as worker_name,
                ass.name as assigned_by_name
            FROM convert_assignments ca
            JOIN converts c ON ca.convert_id = c.id
            JOIN users u ON ca.worker_id = u.id
            LEFT JOIN users ass ON ca.assigned_by = ass.id
            ORDER BY ca.updated_at DESC
            LIMIT ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting recent assignments activity: " . $e->getMessage());
        return [];
    }
}
?>