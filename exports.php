<?php
require_once 'functions.php';
check_auth();

// Check permissions
if (!has_permission(['super_admin', 'coordinator'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle export requests
if (isset($_GET['export'])) {
    $type = sanitize_input($_GET['export']);
    $filters = $_GET;
    
    switch ($type) {
        case 'converts_report':
            $data = get_converts_report($filters);
            export_data($data, 'csv', 'converts_report_' . date('Y-m-d'));
            break;
            
        case 'followups_report':
            $data = get_followups_report($filters);
            export_data($data, 'csv', 'followups_report_' . date('Y-m-d'));
            break;
            
        case 'users_report':
            $data = get_users_report($filters);
            export_data($data, 'csv', 'users_report_' . date('Y-m-d'));
            break;
            
        case 'sms_report':
            $data = get_sms_report($filters);
            export_data($data, 'csv', 'sms_report_' . date('Y-m-d'));
            break;
    }
}

// Get analytics data
$analytics_data = get_analytics_data();
$weekly_stats = get_weekly_stats();
$top_workers = get_top_workers();
$conversion_rates = get_conversion_rates();

// Analytics functions
function get_analytics_data() {
    global $pdo;
    
    $data = [];
    
    // Total converts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM converts");
    $data['total_converts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Converted vs Pending
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM converts GROUP BY status");
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data['converted'] = 0;
    $data['pending'] = 0;
    foreach ($status_data as $row) {
        if ($row['status'] === 'converted') {
            $data['converted'] = $row['count'];
        } else {
            $data['pending'] = $row['count'];
        }
    }
    
    // Total follow-ups
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM follow_ups");
    $data['total_followups'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active workers
    $stmt = $pdo->query("SELECT COUNT(DISTINCT worker_id) as active FROM converts WHERE date_submitted >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $data['active_workers'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    return $data;
}

function get_weekly_stats() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(date_submitted, '%Y-%m-%d') as date,
            COUNT(*) as converts,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted
        FROM converts 
        WHERE date_submitted >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(date_submitted)
        ORDER BY date
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_top_workers() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            u.name,
            u.matric_number,
            COUNT(c.id) as total_converts,
            SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) as converted
        FROM users u
        LEFT JOIN converts c ON u.id = c.worker_id
        WHERE u.role = 'worker'
        GROUP BY u.id, u.name, u.matric_number
        ORDER BY total_converts DESC
        LIMIT 10
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_conversion_rates() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            u.name,
            COUNT(c.id) as total,
            SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) as converted,
            ROUND((SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) / COUNT(c.id)) * 100, 1) as rate
        FROM users u
        LEFT JOIN converts c ON u.id = c.worker_id
        WHERE u.role = 'worker'
        GROUP BY u.id, u.name
        HAVING total >= 5
        ORDER BY rate DESC
        LIMIT 10
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Report functions
function get_converts_report($filters = []) {
    global $pdo;
    
    $sql = "SELECT 
                c.name as convert_name,
                c.phone_number,
                c.room_number,
                c.hostel,
                c.gender,
                c.status,
                c.date_submitted,
                u.name as worker_name,
                u.matric_number as worker_matric,
                u.department as worker_department
            FROM converts c
            JOIN users u ON c.worker_id = u.id
            WHERE 1=1";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $sql .= " AND DATE(c.date_submitted) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $sql .= " AND DATE(c.date_submitted) <= ?";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $filters['status'];
    }
    
    $sql .= " ORDER BY c.date_submitted DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_followups_report($filters = []) {
    global $pdo;
    
    $sql = "SELECT 
                c.name as convert_name,
                c.phone_number,
                f.follow_up_date,
                f.method,
                f.status as followup_status,
                f.notes,
                u.name as prayer_team_member,
                w.name as worker_name
            FROM follow_ups f
            JOIN converts c ON f.convert_id = c.id
            JOIN users u ON f.prayer_team_id = u.id
            JOIN users w ON c.worker_id = w.id
            WHERE 1=1";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $sql .= " AND DATE(f.follow_up_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $sql .= " AND DATE(f.follow_up_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $sql .= " ORDER BY f.follow_up_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_users_report($filters = []) {
    global $pdo;
    
    $sql = "SELECT 
                name,
                matric_number,
                level,
                department,
                course,
                role,
                email,
                phone,
                is_active,
                last_login,
                created_at
            FROM users 
            WHERE role != 'super_admin'";
    $params = [];
    
    if (!empty($filters['role'])) {
        $sql .= " AND role = ?";
        $params[] = $filters['role'];
    }
    
    $sql .= " ORDER BY role, name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_sms_report($filters = []) {
    global $pdo;
    
    $sql = "SELECT 
                s.recipient_phone,
                c.name as recipient_name,
                s.message,
                s.message_type,
                s.status,
                s.sent_at,
                u.name as sender_name
            FROM sms_logs s
            LEFT JOIN converts c ON s.recipient_phone = c.phone_number
            JOIN users u ON s.sender_id = u.id
            WHERE 1=1";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $sql .= " AND DATE(s.sent_at) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $sql .= " AND DATE(s.sent_at) <= ?";
        $params[] = $filters['end_date'];
    }
    
    $sql .= " ORDER BY s.sent_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <?php include 'header.php'; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-6 lg:ml-64 mt-16">
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Analytics & Reports</h1>
                        <p class="text-gray-600 mt-1">Comprehensive insights and export capabilities</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <button onclick="generateComprehensiveReport()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-file-pdf mr-2"></i>Generate Full Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Converts</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $analytics_data['total_converts']; ?></h3>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="text-green-500"><?php echo $analytics_data['converted']; ?> converted</span>
                                <span class="text-gray-400 mx-1">•</span>
                                <span class="text-orange-500"><?php echo $analytics_data['pending']; ?> pending</span>
                            </p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-users text-blue-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Follow-ups</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $analytics_data['total_followups']; ?></h3>
                            <p class="text-xs text-gray-500 mt-1">Prayer and support activities</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-hands-praying text-purple-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Workers</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $analytics_data['active_workers']; ?></h3>
                            <p class="text-xs text-gray-500 mt-1">Last 30 days activity</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-user-check text-green-500 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Conversion Rate</p>
                            <h3 class="text-2xl font-bold text-gray-900">
                                <?php 
                                $rate = $analytics_data['total_converts'] > 0 
                                    ? round(($analytics_data['converted'] / $analytics_data['total_converts']) * 100, 1) 
                                    : 0;
                                echo $rate; 
                                ?>%
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">Overall success rate</p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-xl">
                            <i class="fas fa-chart-line text-orange-500 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Weekly Activity Chart -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Weekly Activity</h3>
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
                                Converts
                            </span>
                            <span class="flex items-center">
                                <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                Converted
                            </span>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>

                <!-- Conversion Rates -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Workers - Converts Update</h3>
                    <div class="space-y-4">
                        <?php if (empty($top_workers)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-chart-bar text-3xl text-gray-300 mb-3"></i>
                                <p>No data available yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($top_workers as $index => $worker): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center text-white text-sm font-bold mr-3">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($worker['name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $worker['matric_number']; ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900"><?php echo $worker['total_converts']; ?> converts</div>
                                    <div class="text-xs text-green-500"><?php echo $worker['converted']; ?> converted</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Export Reports Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Export Reports</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Converts Report -->
                    <div class="border border-gray-200 rounded-xl p-4 hover:border-orange-300 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="bg-orange-100 p-2 rounded-lg">
                                <i class="fas fa-users text-orange-500 text-lg"></i>
                            </div>
                            <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full font-medium">
                                CSV
                            </span>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Converts Report</h4>
                        <p class="text-sm text-gray-600 mb-4">Complete list of all converts with details and status</p>
                        <button onclick="openExportModal('converts')" 
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>

                    <!-- Follow-ups Report -->
                    <div class="border border-gray-200 rounded-xl p-4 hover:border-blue-300 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <i class="fas fa-hands-praying text-blue-500 text-lg"></i>
                            </div>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium">
                                CSV
                            </span>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Follow-ups Report</h4>
                        <p class="text-sm text-gray-600 mb-4">Detailed follow-up activities and prayer sessions</p>
                        <button onclick="openExportModal('followups')" 
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>

                    <!-- Users Report -->
                    <div class="border border-gray-200 rounded-xl p-4 hover:border-green-300 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <i class="fas fa-user-friends text-green-500 text-lg"></i>
                            </div>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">
                                CSV
                            </span>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Users Report</h4>
                        <p class="text-sm text-gray-600 mb-4">System users with roles and activity information</p>
                        <button onclick="openExportModal('users')" 
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>

                    <!-- SMS Report -->
                    <div class="border border-gray-200 rounded-xl p-4 hover:border-purple-300 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <i class="fas fa-sms text-purple-500 text-lg"></i>
                            </div>
                            <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full font-medium">
                                CSV
                            </span>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">SMS Report</h4>
                        <p class="text-sm text-gray-600 mb-4">Digital follow-up messages and delivery status</p>
                        <button onclick="openExportModal('sms')" 
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-lg text-sm font-medium transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Analytics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Department Distribution -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Department Distribution</h3>
                    <div class="h-64">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>

                <!-- Follow-up Methods -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Follow-up Methods</h3>
                    <div class="h-64">
                        <canvas id="methodsChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Recent System Activity</h3>
                        <a href="activity.php" class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                            View All
                        </a>
                    </div>
                    <div class="space-y-4">
                        <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0 mt-1">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-plus text-orange-500 text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">New convert registered</p>
                                <p class="text-sm text-gray-600 mt-1">John Doe was added by MAT123</p>
                                <p class="text-xs text-gray-400 mt-1">2 hours ago</p>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900" id="exportModalTitle">Export Report</h3>
                <button onclick="closeExportModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="exportForm" method="GET">
                <input type="hidden" name="export" id="exportType">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <input type="date" name="start_date" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <input type="date" name="end_date" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                    </div>

                    <div id="convertsFilters" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Filter</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="converted">Converted</option>
                        </select>
                    </div>

                    <div id="usersFilters" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role Filter</label>
                        <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Roles</option>
                            <option value="worker">Worker</option>
                            <option value="prayer_team">Prayer Team</option>
                            <option value="coordinator">Coordinator</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeExportModal()" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition duration-200">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Charts
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($weekly_stats, 'date')); ?>,
                datasets: [
                    {
                        label: 'Converts',
                        data: <?php echo json_encode(array_column($weekly_stats, 'converts')); ?>,
                        backgroundColor: '#f97316',
                        borderRadius: 4
                    },
                    {
                        label: 'Converted',
                        data: <?php echo json_encode(array_column($weekly_stats, 'converted')); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Department Chart (Mock data)
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: ['Computer Science', 'Electrical Engineering', 'Medicine', 'Law', 'Business'],
                datasets: [{
                    data: [30, 25, 20, 15, 10],
                    backgroundColor: [
                        '#f97316', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Methods Chart (Mock data)
        const methodsCtx = document.getElementById('methodsChart').getContext('2d');
        new Chart(methodsCtx, {
            type: 'pie',
            data: {
                labels: ['Phone Call', 'SMS', 'Visit', 'WhatsApp'],
                datasets: [{
                    data: [40, 30, 20, 10],
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f97316', '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Export Modal Functions
        function openExportModal(type) {
            const modal = document.getElementById('exportModal');
            const title = document.getElementById('exportModalTitle');
            const exportType = document.getElementById('exportType');
            const convertsFilters = document.getElementById('convertsFilters');
            const usersFilters = document.getElementById('usersFilters');
            
            // Reset filters
            convertsFilters.classList.add('hidden');
            usersFilters.classList.add('hidden');
            
            // Set based on type
            switch(type) {
                case 'converts':
                    title.textContent = 'Export Converts Report';
                    exportType.value = 'converts_report';
                    convertsFilters.classList.remove('hidden');
                    break;
                case 'followups':
                    title.textContent = 'Export Follow-ups Report';
                    exportType.value = 'followups_report';
                    break;
                case 'users':
                    title.textContent = 'Export Users Report';
                    exportType.value = 'users_report';
                    usersFilters.classList.remove('hidden');
                    break;
                case 'sms':
                    title.textContent = 'Export SMS Report';
                    exportType.value = 'sms_report';
                    break;
            }
            
            modal.classList.remove('hidden');
        }

        function closeExportModal() {
            document.getElementById('exportModal').classList.add('hidden');
        }

        function generateComprehensiveReport() {
            // This would generate a PDF with all analytics
            alert('Comprehensive PDF report generation would be implemented here!');
        }

        // Auto-close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeExportModal();
            }
        });

        // Set default dates for export
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
            const oneWeekAgoStr = oneWeekAgo.toISOString().split('T')[0];
            
            document.querySelector('input[name="start_date"]').value = oneWeekAgoStr;
            document.querySelector('input[name="end_date"]').value = today;
        });
    </script>
</body>
</html>