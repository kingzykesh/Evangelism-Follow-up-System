<?php
require_once 'functions.php';
check_auth();

// Check permissions - Only coordinators and super admin can view activity logs
if (!has_permission(['coordinator', 'super_admin'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle filters and search
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'ip_address' => $_GET['ip_address'] ?? ''
];

// Get activity logs data
$activity_logs = get_activity_logs($filters);

// Get unique actions for filter
$action_stmt = $pdo->prepare("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action");
$action_stmt->execute();
$actions = $action_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users_stmt = $pdo->prepare("SELECT id, name FROM users ORDER BY name");
$users_stmt->execute();
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get activity logs with filters
function get_activity_logs($filters = []) {
    global $pdo;
    
    $sql = "SELECT al.*, u.name as user_name 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE 1=1";
    $params = [];
    
    // User filter
    if (!empty($filters['user_id'])) {
        $sql .= " AND al.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    // Action filter
    if (!empty($filters['action'])) {
        $sql .= " AND al.action = ?";
        $params[] = $filters['action'];
    }
    
    // IP address filter
    if (!empty($filters['ip_address'])) {
        $sql .= " AND al.ip_address LIKE ?";
        $params[] = "%{$filters['ip_address']}%";
    }
    
    // Date range filter
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Search term
    if (!empty($filters['search'])) {
        $sql .= " AND (al.description LIKE ? OR u.name LIKE ? OR al.ip_address LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    $sql .= " ORDER BY al.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get activity statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT action) as unique_actions,
        COUNT(DISTINCT ip_address) as unique_ips,
        MAX(created_at) as latest_activity
    FROM activity_logs
");
$stats_stmt->execute();
$activity_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    if ($_SESSION['user_role'] === 'super_admin') {
        $days = intval($_POST['days']);
        if ($days > 0) {
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $deleted_count = $stmt->rowCount();
            
            log_activity($_SESSION['user_id'], 'logs_cleared', "Cleared {$deleted_count} activity logs older than {$days} days");
            $_SESSION['success'] = "Cleared {$deleted_count} activity logs older than {$days} days";
        }
    } else {
        $_SESSION['error'] = "Only super admin can clear activity logs";
    }
    header('Location: activity.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
                        <h1 class="text-2xl font-bold text-gray-900">Activity Logs</h1>
                        <p class="text-gray-600 mt-1">Monitor system activities and user actions</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                        <button onclick="openClearLogsModal()" 
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <i class="fas fa-trash mr-2"></i>Clear Logs
                        </button>
                        <?php endif; ?>
                        <button onclick="exportLogs()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-lg mr-3"></i>
                    <span class="text-green-700 font-medium"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg mr-3"></i>
                    <span class="text-red-700 font-medium"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Activity Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-list-alt text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Logs</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $activity_stats['total_logs'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-users text-green-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Unique Users</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $activity_stats['unique_users'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-xl">
                            <i class="fas fa-bolt text-orange-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Action Types</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $activity_stats['unique_actions'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-network-wired text-purple-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">IP Addresses</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $activity_stats['unique_ips'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-xl">
                            <i class="fas fa-clock text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Latest Activity</p>
                            <h3 class="text-sm font-bold text-gray-900">
                                <?php echo $activity_stats['latest_activity'] ? date('M j, g:i A', strtotime($activity_stats['latest_activity'])) : 'No activity'; ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Activity Filters</h3>
                </div>

                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                placeholder="Search logs..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- User Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                        <select name="user_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Users</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Action Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filters['action'] === $action ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- IP Address Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">IP Address</label>
                        <input type="text" name="ip_address" value="<?php echo htmlspecialchars($filters['ip_address']); ?>" 
                            placeholder="Filter by IP..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <!-- Filter Actions -->
                    <div class="md:col-span-2 lg:col-span-4 flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="activity.php" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-refresh mr-2"></i>Reset
                            </a>
                        </div>
                        <div class="text-sm text-gray-500">
                            Found <span class="font-semibold text-orange-500"><?php echo count($activity_logs); ?></span> logs
                        </div>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Table Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <h3 class="text-lg font-semibold text-gray-900">Activity Logs</h3>
                    </div>

                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <button onclick="refreshTable()" class="p-2 hover:bg-gray-200 rounded-lg transition duration-200">
                            <i class="fas fa-refresh"></i>
                        </button>
                        <span><?php echo count($activity_logs); ?> logs</span>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    IP Address
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User Agent
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Timestamp
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($activity_logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <i class="fas fa-list-alt text-3xl text-gray-300 mb-3"></i>
                                        <p class="text-gray-500 font-medium">No activity logs found</p>
                                        <p class="text-gray-400 text-sm mt-1">Try adjusting your filters</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activity_logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if ($log['user_name']): ?>
                                                <div class="flex-shrink-0 h-8 w-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-semibold text-xs">
                                                    <?php echo strtoupper(substr($log['user_name'], 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                    <div class="text-xs text-gray-500">ID: <?php echo $log['user_id']; ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-sm text-gray-500 italic">System</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch($log['action']) {
                                                case 'login': echo 'bg-green-100 text-green-800'; break;
                                                case 'logout': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'user_registered': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'super_admin_registered': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'convert_added': echo 'bg-orange-100 text-orange-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <i class="fas fa-<?php 
                                                switch($log['action']) {
                                                    case 'login': echo 'sign-in-alt'; break;
                                                    case 'logout': echo 'sign-out-alt'; break;
                                                    case 'user_registered': 
                                                    case 'super_admin_registered': echo 'user-plus'; break;
                                                    case 'convert_added': echo 'user-check'; break;
                                                    default: echo 'bolt';
                                                }
                                            ?> mr-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-md truncate"><?php echo htmlspecialchars($log['description']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($log['ip_address']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-xs text-gray-500 max-w-xs truncate" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                            <?php echo htmlspecialchars($log['user_agent']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock mr-2 text-gray-400"></i>
                                            <?php echo date('M j, g:i A', strtotime($log['created_at'])); ?>
                                        </div>
                                       <div class="text-xs text-gray-400 mt-1">
    <?php 
    // Simple time ago function inline
    $timestamp = strtotime($log['created_at']);
    $currentTime = time();
    $timeDifference = $currentTime - $timestamp;
    
    if ($timeDifference < 60) {
        echo 'just now';
    } elseif ($timeDifference < 3600) {
        $minutes = floor($timeDifference / 60);
        echo $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($timeDifference < 86400) {
        $hours = floor($timeDifference / 3600);
        echo $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($timeDifference < 2592000) {
        $days = floor($timeDifference / 86400);
        echo $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        $months = floor($timeDifference / 2592000);
        echo $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
    ?>
</div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            Showing <span class="font-semibold"><?php echo count($activity_logs); ?></span> logs
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="px-3 py-1 bg-orange-500 text-white rounded-lg text-sm">1</span>
                            <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Clear Logs Modal -->
    <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
    <div id="clearLogsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Clear Activity Logs</h3>
                <button onclick="closeClearLogsModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="clearLogsForm">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Clear logs older than:</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="days" value="7" class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">7 days</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="days" value="30" class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">30 days</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="days" value="90" class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">90 days</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="days" value="365" class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">1 year</span>
                        </label>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                        <div>
                            <p class="text-sm font-medium text-red-800">Warning</p>
                            <p class="text-sm text-red-700">This action cannot be undone. All logs older than the selected period will be permanently deleted.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3">
                    <button type="button" onclick="closeClearLogsModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" name="clear_logs" 
                        class="px-6 py-3 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600 transition duration-200">
                        <i class="fas fa-trash mr-2"></i>Clear Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
// Modal Functions
function openClearLogsModal() {
    const modal = document.getElementById('clearLogsModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeClearLogsModal() {
    const modal = document.getElementById('clearLogsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const clearLogsModal = document.getElementById('clearLogsModal');
    if (e.target === clearLogsModal) {
        closeClearLogsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeClearLogsModal();
    }
});

// Refresh table function
function refreshTable() {
    window.location.reload();
}

// Export logs function
function exportLogs() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Redirect to export script with current filters
    window.location.href = 'export_activity.php?' + params.toString();
}

// Auto-refresh every 30 seconds for real-time monitoring
setTimeout(() => {
    if (document.visibilityState === 'visible') {
        refreshTable();
    }
}, 30000);
    </script>
</body>
</html>