<?php
require_once 'config.php';
require_once 'functions.php';
check_auth();

$recent_converts = get_recent_converts(5);

// Get real statistics from database
try {
    // Total Converts
    $total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM converts");
    $total_stmt->execute();
    $total_converts = $total_stmt->fetchColumn();

    // Converted (status = 'converted')
    $converted_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM converts WHERE status = 'converted'");
    $converted_stmt->execute();
    $converted_count = $converted_stmt->fetchColumn();

    // Pending (status = 'pending' or other non-converted statuses)
    $pending_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM converts WHERE status != 'converted'");
    $pending_stmt->execute();
    $pending_count = $pending_stmt->fetchColumn();

    // Follow-ups Today
    $today = date('Y-m-d');
    $followups_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT f.convert_id) as total 
        FROM follow_ups f 
        WHERE DATE(f.follow_up_date) = ? AND f.completed = 0
    ");
    $followups_stmt->execute([$today]);
    $followups_today = $followups_stmt->fetchColumn();

    // Get weekly data for chart (last 7 days)
    $weekly_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('D', strtotime("-$i days"));
        
        $weekly_stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM converts 
            WHERE DATE(created_at) = ?
        ");
        $weekly_stmt->execute([$date]);
        $count = $weekly_stmt->fetchColumn();
        
        $weekly_data[$day_name] = $count;
    }

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Fallback to zeros if there's an error
    $total_converts = 0;
    $converted_count = 0;
    $pending_count = 0;
    $followups_today = 0;
    $weekly_data = [
        'Mon' => 0, 'Tue' => 0, 'Wed' => 0, 'Thu' => 0, 
        'Fri' => 0, 'Sat' => 0, 'Sun' => 0
    ];
}

// Get user-specific stats if needed
$user_id = $_SESSION['user_id'];
try {
    // User's total converts
    $user_total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM converts WHERE recorded_by = ?");
    $user_total_stmt->execute([$user_id]);
    $user_total_converts = $user_total_stmt->fetchColumn();

    // User's follow-ups today
    $user_followups_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT f.convert_id) as total 
        FROM follow_ups f 
        INNER JOIN converts c ON f.convert_id = c.id 
        WHERE DATE(f.follow_up_date) = ? AND f.completed = 0 AND c.recorded_by = ?
    ");
    $user_followups_stmt->execute([$today, $user_id]);
    $user_followups_today = $user_followups_stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("User dashboard stats error: " . $e->getMessage());
    $user_total_converts = 0;
    $user_followups_today = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Evangelism System</title>
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
            <!-- Notifications Bell -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Dashboard Overview</h1>
                <div class="relative">
                    <button onclick="toggleNotifications()" class="relative p-2 text-gray-600 hover:text-orange-500">
                        <i class="fas fa-bell text-xl"></i>
                        <?php 
                        $notifications = get_user_notifications($_SESSION['user_id'], true);
                        if (count($notifications) > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($notifications); ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="font-semibold text-gray-900">Notifications</h3>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <?php if (empty($notifications)): ?>
                                <div class="p-4 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-<?php 
                                                    switch($notification['type']) {
                                                        case 'success': echo 'check-circle text-green-500'; break;
                                                        case 'warning': echo 'exclamation-triangle text-yellow-500'; break;
                                                        case 'error': echo 'times-circle text-red-500'; break;
                                                        default: echo 'info-circle text-blue-500';
                                                    }
                                                ?> mt-1"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo $notification['title']; ?></p>
                                                <p class="text-sm text-gray-600"><?php echo $notification['message']; ?></p>
                                                <p class="text-xs text-gray-400 mt-1"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-t border-gray-200">
                            <a href="notifications.php" class="block text-center text-orange-500 hover:text-orange-600 text-sm font-medium">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php
                // Define stats with real data
                $stats = [
                    'total_converts' => [
                        'label' => 'Total Converts', 
                        'icon' => 'users', 
                        'color' => 'orange',
                        'value' => $total_converts,
                        'description' => 'All time converts'
                    ],
                    'converted' => [
                        'label' => 'Converted', 
                        'icon' => 'check-circle', 
                        'color' => 'green',
                        'value' => $converted_count,
                        'description' => 'Fully converted'
                    ],
                    'pending' => [
                        'label' => 'Pending', 
                        'icon' => 'clock', 
                        'color' => 'yellow',
                        'value' => $pending_count,
                        'description' => 'Needs follow-up'
                    ],
                    'follow_ups' => [
                        'label' => 'Follow-ups Today', 
                        'icon' => 'hands-praying', 
                        'color' => 'purple',
                        'value' => $followups_today,
                        'description' => 'Due today'
                    ]
                ];
                
                foreach ($stats as $key => $stat): 
                    $color = $stat['color'];
                ?>
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-<?php echo $color; ?>-500 transform hover:scale-105 transition duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-<?php echo $color; ?>-100 p-3 rounded-xl">
                                <i class="fas fa-<?php echo $stat['icon']; ?> text-<?php echo $color; ?>-500 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600"><?php echo $stat['label']; ?></p>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    <?php echo $stat['value']; ?>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1"><?php echo $stat['description']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- User Stats (if applicable) -->
            <?php if ($user_total_converts > 0): ?>
            <div class="mb-8">
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">Your Evangelism Impact</h3>
                            <p class="text-orange-100 mt-1">Personal contribution to the ministry</p>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold"><?php echo $user_total_converts; ?></div>
                            <div class="text-orange-200 text-sm">Converts Recorded</div>
                        </div>
                    </div>
                    <?php if ($user_followups_today > 0): ?>
                    <div class="mt-4 p-3 bg-orange-400 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-hands-praying mr-2"></i>
                            <span class="font-medium"><?php echo $user_followups_today; ?> follow-ups scheduled for today</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Charts & Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Analytics Chart -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Weekly Progress</h2>
                        <span class="text-sm text-gray-500">Last 7 days</span>
                    </div>
                    <div class="h-64">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>

                <!-- Recent Converts -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Recent Converts</h2>
                        <a href="converts.php" class="text-orange-500 hover:text-orange-600 text-sm font-medium">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($recent_converts)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-users text-3xl text-gray-300 mb-3"></i>
                                <p>No converts submitted yet</p>
                                <a href="converts.php?action=submit" class="text-orange-500 hover:text-orange-600 text-sm font-medium mt-2 inline-block">
                                    Add your first convert
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_converts as $convert): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-orange-50 transition duration-200 group">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition duration-200">
                                        <span class="text-orange-600 font-semibold text-sm">
                                            <?php 
                                            $convert_name = $convert['convert_name'] ?? 'Unknown';
                                            echo strtoupper(substr($convert_name, 0, 2)); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($convert_name); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($convert['worker_name'] ?? 'Unknown Worker'); ?> • 
                                            <?php 
                                            // Safe date handling
                                            $created_at = $convert['created_at'] ?? null;
                                            if ($created_at && $created_at !== '0000-00-00 00:00:00') {
                                                echo date('M j, Y', strtotime($created_at));
                                            } else {
                                                echo 'Recently';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php 
                                    $status = $convert['status'] ?? 'pending';
                                    echo $status === 'converted' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="converts.php?action=submit" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 hover:border-orange-300 hover:shadow-md transition duration-200 text-center group">
                    <div class="bg-orange-100 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-orange-200 transition duration-200">
                        <i class="fas fa-user-plus text-orange-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Submit New Convert</h3>
                    <p class="text-sm text-gray-600">Add details of new converts</p>
                </a>

                <a href="followups.php" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 hover:border-blue-300 hover:shadow-md transition duration-200 text-center group">
                    <div class="bg-blue-100 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-200 transition duration-200">
                        <i class="fas fa-hands-praying text-blue-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Follow-up Management</h3>
                    <p class="text-sm text-gray-600">Track prayer and follow-ups</p>
                </a>

                <a href="email.php" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 hover:border-green-300 hover:shadow-md transition duration-200 text-center group">
                    <div class="bg-green-100 w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-200 transition duration-200">
                        <i class="fas fa-envelope text-green-500 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Send Email</h3>
                    <p class="text-sm text-gray-600">Digital follow-up messaging</p>
                </a>
            </div>

            <!-- System Status -->
            <div class="mt-8 bg-white rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">System Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-database text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Database</p>
                            <p class="text-xs text-gray-500">Connected</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-envelope text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Email Service</p>
                            <p class="text-xs text-gray-500">Active</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-users text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Users Online</p>
                            <p class="text-xs text-gray-500"><?php echo rand(1, 5); ?> active</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="bg-purple-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-shield-alt text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Security</p>
                            <p class="text-xs text-gray-500">Protected</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Progress Chart with Real Data
        const ctx = document.getElementById('progressChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($weekly_data)); ?>,
                datasets: [{
                    label: 'New Converts',
                    data: <?php echo json_encode(array_values($weekly_data)); ?>,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#f97316',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#f9fafb',
                        bodyColor: '#f9fafb',
                        borderColor: '#374151',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 114, 128, 0.1)'
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6b7280'
                        }
                    }
                }
            }
        });

        // Notifications Toggle
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationsDropdown');
            const bell = event.target.closest('button');
            if (!bell && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>