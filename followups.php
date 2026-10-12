<?php
require_once 'functions.php';
check_auth();

// Check permissions
if (!has_permission(['super_admin', 'coordinator', 'prayer_team'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle follow-up submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_followup'])) {
        $convert_id = sanitize_input($_POST['convert_id']);
        $follow_up_date = sanitize_input($_POST['follow_up_date']);
        $method = sanitize_input($_POST['method']);
        $notes = sanitize_input($_POST['notes']);
        $status = sanitize_input($_POST['status']);
        $next_follow_up = sanitize_input($_POST['next_follow_up']);

        // Insert follow-up
        $stmt = $pdo->prepare("INSERT INTO follow_ups (convert_id, prayer_team_id, follow_up_date, method, notes, status, next_follow_up) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$convert_id, $_SESSION['user_id'], $follow_up_date, $method, $notes, $status, $next_follow_up]);
        
        // Update convert's follow-up count and last follow-up date
        $update_convert = $pdo->prepare("UPDATE converts SET follow_up_count = follow_up_count + 1, last_follow_up = CURRENT_TIMESTAMP WHERE id = ?");
        $update_convert->execute([$convert_id]);
        
        // Get convert details for notification
        $convert_stmt = $pdo->prepare("SELECT name, worker_id FROM converts WHERE id = ?");
        $convert_stmt->execute([$convert_id]);
        $convert = $convert_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify the worker who submitted the convert
        create_notification(
            $convert['worker_id'],
            'Follow-up Completed',
            "{$convert['name']} has been followed up by {$_SESSION['user_name']}",
            'success',
            'followups',
            $pdo->lastInsertId()
        );
        
        // Notify coordinators
        $coordinators = $pdo->prepare("SELECT id FROM users WHERE role = 'coordinator'");
        $coordinators->execute();
        foreach ($coordinators->fetchAll(PDO::FETCH_ASSOC) as $coordinator) {
            create_notification(
                $coordinator['id'],
                'Follow-up Recorded',
                "{$_SESSION['user_name']} completed follow-up for {$convert['name']}",
                'info',
                'followups',
                $pdo->lastInsertId()
            );
        }
        
        log_activity($_SESSION['user_id'], 'followup_added', "Added follow-up for convert: {$convert['name']}");
        
        $_SESSION['success'] = "Follow-up recorded successfully!";
    }
}

// Get filter parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'method' => $_GET['method'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'search' => $_GET['search'] ?? '',
    'prayer_team_id' => $_GET['prayer_team_id'] ?? ''
];

// Get follow-ups data
$follow_ups = get_follow_ups($filters);

// Get converts for dropdown (only converted ones)
$converts_stmt = $pdo->prepare("SELECT id, name, phone_number FROM converts WHERE status = 'converted' ORDER BY name");
$converts_stmt->execute();
$converts = $converts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prayer team members for filter
$prayer_team_stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'prayer_team' ORDER BY name");
$prayer_team_stmt->execute();
$prayer_team_members = $prayer_team_stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get follow-ups with filters
function get_follow_ups($filters = []) {
    global $pdo;
    
    $sql = "SELECT f.*, c.name as convert_name, c.phone_number, c.hostel, c.room_number, 
                   u.name as prayer_team_name, w.name as worker_name, w.matric_number as worker_matric
            FROM follow_ups f 
            JOIN converts c ON f.convert_id = c.id 
            JOIN users u ON f.prayer_team_id = u.id 
            JOIN users w ON c.worker_id = w.id 
            WHERE 1=1";
    $params = [];
    
    if (!empty($filters['status'])) {
        $sql .= " AND f.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['method'])) {
        $sql .= " AND f.method = ?";
        $params[] = $filters['method'];
    }
    
    if (!empty($filters['start_date'])) {
        $sql .= " AND DATE(f.follow_up_date) >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $sql .= " AND DATE(f.follow_up_date) <= ?";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (c.name LIKE ? OR c.phone_number LIKE ? OR u.name LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if (!empty($filters['prayer_team_id'])) {
        $sql .= " AND f.prayer_team_id = ?";
        $params[] = $filters['prayer_team_id'];
    }
    
    $sql .= " ORDER BY f.follow_up_date DESC, f.created_at DESC";
    
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
    <title>Follow-up System - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <h1 class="text-2xl font-bold text-gray-900">Follow-up System</h1>
                        <p class="text-gray-600 mt-1">Track prayer and follow-up activities for converts</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <button onclick="openFollowUpModal()" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                            <i class="fas fa-hands-praying mr-2"></i>Record Follow-up
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

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-hands-praying text-purple-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Follow-ups</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo count($follow_ups); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-check-circle text-green-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Completed</p>
                            <h3 class="text-2xl font-bold text-gray-900">
                                <?php echo count(array_filter($follow_ups, fn($fu) => $fu['status'] === 'completed')); ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-phone text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Phone Calls</p>
                            <h3 class="text-2xl font-bold text-gray-900">
                                <?php echo count(array_filter($follow_ups, fn($fu) => $fu['method'] === 'call')); ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-xl">
                            <i class="fas fa-calendar-day text-orange-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Today's Follow-ups</p>
                            <h3 class="text-2xl font-bold text-gray-900">
                                <?php echo count(array_filter($follow_ups, fn($fu) => $fu['follow_up_date'] === date('Y-m-d'))); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Filter Follow-ups</h3>
                </div>

                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                placeholder="Search converts..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Status</option>
                            <option value="contacted" <?php echo $filters['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="prayed_for" <?php echo $filters['status'] === 'prayed_for' ? 'selected' : ''; ?>>Prayed For</option>
                            <option value="counseled" <?php echo $filters['status'] === 'counseled' ? 'selected' : ''; ?>>Counseled</option>
                            <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="not_reachable" <?php echo $filters['status'] === 'not_reachable' ? 'selected' : ''; ?>>Not Reachable</option>
                        </select>
                    </div>

                    <!-- Method Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Method</label>
                        <select name="method" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Methods</option>
                            <option value="call" <?php echo $filters['method'] === 'call' ? 'selected' : ''; ?>>Phone Call</option>
                            <option value="visit" <?php echo $filters['method'] === 'visit' ? 'selected' : ''; ?>>Visit</option>
                            <option value="sms" <?php echo $filters['method'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                            <option value="whatsapp" <?php echo $filters['method'] === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                        </select>
                    </div>

                    <!-- Prayer Team Member -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prayer Team</label>
                        <select name="prayer_team_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Members</option>
                            <?php foreach ($prayer_team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $filters['prayer_team_id'] == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="start_date" value="<?php echo $filters['start_date']; ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="end_date" value="<?php echo $filters['end_date']; ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>

                    <!-- Filter Actions -->
                    <div class="md:col-span-2 lg:col-span-4 flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="followups.php" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-refresh mr-2"></i>Reset
                            </a>
                        </div>
                        <div class="text-sm text-gray-500">
                            Found <span class="font-semibold text-orange-500"><?php echo count($follow_ups); ?></span> follow-ups
                        </div>
                    </div>
                </form>
            </div>

            <!-- Follow-ups Timeline -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">Follow-up Timeline</h3>
                </div>

                <div class="divide-y divide-gray-200">
                    <?php if (empty($follow_ups)): ?>
                        <div class="px-6 py-12 text-center">
                            <i class="fas fa-hands-praying text-3xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500 font-medium">No follow-ups recorded yet</p>
                            <p class="text-gray-400 text-sm mt-1">Start by recording your first follow-up activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($follow_ups as $follow_up): ?>
                        <div class="p-6 hover:bg-gray-50 transition duration-150">
                            <div class="flex items-start space-x-4">
                                <!-- Timeline Icon -->
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center text-white">
                                        <i class="fas fa-<?php 
                                            switch($follow_up['method']) {
                                                case 'call': echo 'phone'; break;
                                                case 'visit': echo 'house-user'; break;
                                                case 'sms': echo 'sms'; break;
                                                case 'whatsapp': echo 'whatsapp'; break;
                                                default: echo 'hands-praying';
                                            }
                                        ?> text-sm"></i>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-3">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($follow_up['convert_name']); ?>
                                            </h4>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo get_badge_color($follow_up['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $follow_up['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('M j, Y', strtotime($follow_up['follow_up_date'])); ?>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm text-gray-600 mb-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-phone text-green-500 mr-2 w-4"></i>
                                            <span><?php echo htmlspecialchars($follow_up['phone_number']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-bed text-purple-500 mr-2 w-4"></i>
                                            <span><?php echo htmlspecialchars($follow_up['hostel']); ?> - Room <?php echo htmlspecialchars($follow_up['room_number']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-user-check text-blue-500 mr-2 w-4"></i>
                                            <span>By <?php echo htmlspecialchars($follow_up['prayer_team_name']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-method text-orange-500 mr-2 w-4"></i>
                                            <span class="capitalize"><?php echo $follow_up['method']; ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($follow_up['notes'])): ?>
                                        <div class="bg-gray-50 rounded-lg p-3 mb-3">
                                            <p class="text-sm text-gray-700">
                                                <i class="fas fa-sticky-note text-gray-400 mr-2"></i>
                                                <?php echo nl2br(htmlspecialchars($follow_up['notes'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <div class="flex items-center space-x-4">
                                            <span>
                                                <i class="fas fa-user-plus mr-1"></i>
                                                Submitted by <?php echo htmlspecialchars($follow_up['worker_name']); ?> (<?php echo $follow_up['worker_matric']; ?>)
                                            </span>
                                            <span>
                                                <i class="far fa-calendar mr-1"></i>
                                                Recorded: <?php echo date('M j, g:i A', strtotime($follow_up['created_at'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($follow_up['next_follow_up']): ?>
                                            <div class="flex items-center text-orange-600 font-medium">
                                                <i class="fas fa-calendar-check mr-1"></i>
                                                Next: <?php echo date('M j, Y', strtotime($follow_up['next_follow_up'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Follow-up Modal -->
    <div id="followUpModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Record Follow-up</h3>
                <button onclick="closeFollowUpModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="followUpForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Convert *</label>
                        <select name="convert_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="">Select a Convert</option>
                            <?php foreach ($converts as $convert): ?>
                                <option value="<?php echo $convert['id']; ?>">
                                    <?php echo htmlspecialchars($convert['name']); ?> (<?php echo htmlspecialchars($convert['phone_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Follow-up Date *</label>
                        <input type="date" name="follow_up_date" required 
                            value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Method *</label>
                        <select name="method" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="call">Phone Call</option>
                            <option value="visit">Visit</option>
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="contacted">Contacted</option>
                            <option value="prayed_for">Prayed For</option>
                            <option value="counseled">Counseled</option>
                            <option value="completed">Completed</option>
                            <option value="not_reachable">Not Reachable</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Next Follow-up</label>
                        <input type="date" name="next_follow_up" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes & Observations</label>
                        <textarea name="notes" rows="4" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Record details about the follow-up conversation, prayer points, challenges, or next steps..."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeFollowUpModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" name="add_followup" 
                        class="px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200 transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Record Follow-up
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openFollowUpModal() {
            document.getElementById('followUpModal').classList.remove('hidden');
        }

        function closeFollowUpModal() {
            document.getElementById('followUpModal').classList.add('hidden');
        }

        // Auto-close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFollowUpModal();
            }
        });

        // Auto-set next follow-up date to 7 days from today
        document.querySelector('input[name="follow_up_date"]').addEventListener('change', function(e) {
            const followUpDate = new Date(e.target.value);
            const nextFollowUp = new Date(followUpDate);
            nextFollowUp.setDate(nextFollowUp.getDate() + 7);
            
            const nextFollowUpInput = document.querySelector('input[name="next_follow_up"]');
            nextFollowUpInput.value = nextFollowUp.toISOString().split('T')[0];
        });
    </script>
</body>
</html>