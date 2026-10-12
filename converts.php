<?php
require_once 'functions.php';
check_auth();

// Check permissions
if (!has_permission(['super_admin', 'coordinator', 'worker', 'prayer_team'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_convert'])) {
        // Submit new convert
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone_number']);
        $email = sanitize_input($_POST['email']); // New email field
        $room = sanitize_input($_POST['room_number']);
        $hostel = sanitize_input($_POST['hostel']);
        $gender = sanitize_input($_POST['gender']);
        $status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        
        // Check for duplicate phone number
        $check_stmt = $pdo->prepare("SELECT id FROM converts WHERE phone_number = ?");
        $check_stmt->execute([$phone]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = "A convert with this phone number already exists!";
        } else {
            // Check for duplicate email if provided
            if (!empty($email)) {
                $email_check_stmt = $pdo->prepare("SELECT id FROM converts WHERE email = ?");
                $email_check_stmt->execute([$email]);
                if ($email_check_stmt->rowCount() > 0) {
                    $_SESSION['error'] = "A convert with this email address already exists!";
                    header("Location: converts.php");
                    exit();
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO converts (worker_id, name, phone_number, email, room_number, hostel, gender, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $name, $phone, $email, $room, $hostel, $gender, $status, $notes]);
            
            // Create notification for coordinators and prayer team
            if ($status === 'converted') {
                $notify_users = $pdo->prepare("SELECT id FROM users WHERE role IN ('coordinator', 'prayer_team')");
                $notify_users->execute();
                $users = $notify_users->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($users as $user) {
                    create_notification(
                        $user['id'],
                        'New Convert Added',
                        "{$name} has been marked as converted by {$_SESSION['user_name']}",
                        'success',
                        'converts',
                        $pdo->lastInsertId()
                    );
                }
            }
            
            // Log activity
            log_activity($_SESSION['user_id'], 'convert_submitted', "Submitted convert: {$name}");
            
            $_SESSION['success'] = "Convert submitted successfully!";
        }
    } elseif (isset($_POST['bulk_action'])) {
        // Handle bulk actions
        $action = sanitize_input($_POST['bulk_action']);
        $selected_converts = $_POST['selected_converts'] ?? [];
        
        if (empty($selected_converts)) {
            $_SESSION['error'] = "Please select at least one convert.";
        } else {
            $placeholders = str_repeat('?,', count($selected_converts) - 1) . '?';
            
            switch ($action) {
                case 'mark_converted':
                    $stmt = $pdo->prepare("UPDATE converts SET status = 'converted' WHERE id IN ($placeholders)");
                    $stmt->execute($selected_converts);
                    $_SESSION['success'] = "Selected converts marked as converted!";
                    break;
                    
                case 'mark_pending':
                    $stmt = $pdo->prepare("UPDATE converts SET status = 'pending' WHERE id IN ($placeholders)");
                    $stmt->execute($selected_converts);
                    $_SESSION['success'] = "Selected converts marked as pending!";
                    break;
                    
                case 'delete':
                    if (has_permission(['super_admin', 'coordinator'])) {
                        $stmt = $pdo->prepare("DELETE FROM converts WHERE id IN ($placeholders)");
                        $stmt->execute($selected_converts);
                        $_SESSION['success'] = "Selected converts deleted successfully!";
                    } else {
                        $_SESSION['error'] = "You don't have permission to delete converts.";
                    }
                    break;
            }
            
            log_activity($_SESSION['user_id'], 'bulk_action', "Performed {$action} on " . count($selected_converts) . " converts");
        }
    }
}

// Handle exports
if (isset($_GET['export'])) {
    $filters = $_GET;
    $converts = search_converts($filters);
    export_data($converts, $_GET['export'], 'converts_export');
}

// Get filter parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'search' => $_GET['search'] ?? '',
    'worker_id' => $_GET['worker_id'] ?? '',
    'gender' => $_GET['gender'] ?? ''
];

// Get converts data
$converts = search_converts($filters);

// Get workers for filter (only for coordinators and super admin)
$workers = [];
if (has_permission(['super_admin', 'coordinator'])) {
    $worker_stmt = $pdo->prepare("SELECT id, name, matric_number FROM users WHERE role = 'worker' ORDER BY name");
    $worker_stmt->execute();
    $workers = $worker_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converts Management - Evangelism System</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">Converts Management</h1>
                        <p class="text-gray-600 mt-1">Manage and track all evangelism converts</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <!-- Export Buttons -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                <i class="fas fa-file-export mr-2"></i>Export
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" 
                                class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-200 z-10">
                                <div class="py-1">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-file-csv mr-3 text-green-500"></i>CSV Format
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'json'])); ?>" 
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-file-code mr-3 text-blue-500"></i>JSON Format
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Add Convert Button -->
                        <?php if (has_permission(['super_admin', 'coordinator', 'worker'])): ?>
                        <button onclick="openConvertModal()" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                            <i class="fas fa-user-plus mr-2"></i>Add Convert
                        </button>
                        <?php endif; ?>
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

            <!-- Smart Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Smart Filters</h3>
                    <button onclick="toggleFilters()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                        <i class="fas fa-filter text-lg"></i>
                    </button>
                </div>

                <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="converted" <?php echo $filters['status'] === 'converted' ? 'selected' : ''; ?>>Converted</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="start_date" value="<?php echo $filters['start_date']; ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="end_date" value="<?php echo $filters['end_date']; ?>" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                    </div>

                    <!-- Additional Filters (Hidden by default on mobile) -->
                    <div id="additionalFilters" class="hidden lg:block lg:col-span-2 lg:grid lg:grid-cols-2 lg:gap-4">
                        <?php if (has_permission(['super_admin', 'coordinator'])): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Worker</label>
                            <select name="worker_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">All Workers</option>
                                <?php foreach ($workers as $worker): ?>
                                    <option value="<?php echo $worker['id']; ?>" <?php echo $filters['worker_id'] == $worker['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($worker['name']); ?> (<?php echo $worker['matric_number']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                            <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">All Gender</option>
                                <option value="male" <?php echo $filters['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $filters['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="md:col-span-2 lg:col-span-4 flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="converts.php" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-refresh mr-2"></i>Reset
                            </a>
                        </div>
                        <div class="text-sm text-gray-500">
                            Found <span class="font-semibold text-orange-500"><?php echo count($converts); ?></span> converts
                        </div>
                    </div>
                </form>
            </div>

            <!-- Converts Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Table Header with Bulk Actions -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="selectAll" 
                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300 rounded">
                            <label for="selectAll" class="ml-2 text-sm font-medium text-gray-700">Select all</label>
                        </div>
                        
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-orange-500">
                                Bulk Actions
                                <i class="fas fa-chevron-down ml-2 text-xs"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" 
                                class="absolute left-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-200 z-10">
                                <form method="POST" id="bulkActionForm">
                                    <div class="py-1">
                                        <button type="submit" name="bulk_action" value="mark_converted" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-check-circle mr-3 text-green-500"></i>Mark as Converted
                                        </button>
                                        <button type="submit" name="bulk_action" value="mark_pending" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-clock mr-3 text-yellow-500"></i>Mark as Pending
                                        </button>
                                        <?php if (has_permission(['super_admin', 'coordinator'])): ?>
                                        <hr class="my-1">
                                        <button type="submit" name="bulk_action" value="delete" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash mr-3"></i>Delete Selected
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="selected_converts" id="selectedConverts">
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <button onclick="refreshTable()" class="p-2 hover:bg-gray-200 rounded-lg transition duration-200">
                            <i class="fas fa-refresh"></i>
                        </button>
                        <span><?php echo count($converts); ?> records</span>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
                                    <!-- Checkbox -->
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Convert Details
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact Info
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status & Follow-ups
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Submitted By
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($converts)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <i class="fas fa-users text-3xl text-gray-300 mb-3"></i>
                                        <p class="text-gray-500 font-medium">No converts found</p>
                                        <p class="text-gray-400 text-sm mt-1">Try adjusting your filters or add new converts</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($converts as $convert): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="convert_select" value="<?php echo $convert['id']; ?>" 
                                            class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300 rounded convert-checkbox">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($convert['name'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($convert['name']); ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <i class="fas fa-<?php echo $convert['gender'] === 'male' ? 'mars text-blue-500' : 'venus text-pink-500'; ?> mr-1"></i>
                                                    <?php echo ucfirst($convert['gender']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-phone text-green-500 mr-2"></i>
                                            <?php echo htmlspecialchars($convert['phone_number']); ?>
                                        </div>
                                        <?php if (!empty($convert['email'])): ?>
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-envelope text-blue-500 mr-2"></i>
                                            <?php echo htmlspecialchars($convert['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-bed text-purple-500 mr-2"></i>
                                            <?php echo htmlspecialchars($convert['hostel']); ?> - Room <?php echo htmlspecialchars($convert['room_number']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2 mb-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo get_badge_color($convert['status']); ?>">
                                                <i class="fas fa-<?php echo $convert['status'] === 'converted' ? 'check' : 'clock'; ?> mr-1"></i>
                                                <?php echo ucfirst($convert['status']); ?>
                                            </span>
                                            <?php if ($convert['follow_up_count'] > 0): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-hands-praying mr-1"></i>
                                                    <?php echo $convert['follow_up_count']; ?> follow-ups
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($convert['date_submitted'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($convert['worker_name']); ?>
                                        <div class="text-xs text-gray-500"><?php echo $convert['worker_matric']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="viewConvert(<?php echo $convert['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 transition duration-150" 
                                                title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (has_permission(['super_admin', 'coordinator', 'worker']) && $_SESSION['user_id'] == $convert['worker_id']): ?>
                                            <button onclick="editConvert(<?php echo $convert['id']; ?>)" 
                                                class="text-orange-600 hover:text-orange-900 transition duration-150"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (has_permission(['super_admin', 'coordinator'])): ?>
                                            <button onclick="deleteConvert(<?php echo $convert['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900 transition duration-150"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($converts); ?></span> of
                        <span class="font-medium"><?php echo count($converts); ?></span> results
                    </div>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </button>
                        <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium text-white bg-orange-500 hover:bg-orange-600">
                            1
                        </button>
                        <button class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Convert Modal -->
    <div id="convertModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Add New Convert</h3>
                <button onclick="closeConvertModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="convertForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="name" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter convert's full name">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" name="phone_number" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter phone number">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="convert@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                        <input type="text" name="room_number" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., A101">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hostel</label>
                        <input type="text" name="hostel" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., Moremi Hall">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                        <select name="gender" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="pending">Pending</option>
                            <option value="converted">Converted</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="3" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Any additional information about the convert..."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeConvertModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" name="submit_convert" 
                        class="px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200 transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Save Convert
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Convert Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-eye text-orange-500 mr-2"></i> Convert Details
                </h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div id="viewContent" class="px-6 py-5 space-y-3 text-sm text-gray-700">
                <p class="text-center text-gray-400 italic">Loading...</p>
            </div>
            <div class="px-6 pb-5 flex justify-end">
                <button onclick="closeViewModal()" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Convert Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-pen text-orange-500 mr-2"></i> Edit Convert
                </h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="editForm" class="px-6 py-5 space-y-4">
                <input type="hidden" name="id" id="edit_id">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Full Name</label>
                    <input type="text" id="edit_name" name="name" class="w-full border rounded-lg p-2" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Phone Number</label>
                    <input type="text" id="edit_phone" name="phone_number" class="w-full border rounded-lg p-2" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Email Address</label>
                    <input type="email" id="edit_email" name="email" class="w-full border rounded-lg p-2">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Room</label>
                        <input type="text" id="edit_room" name="room_number" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Hostel</label>
                        <input type="text" id="edit_hostel" name="hostel" class="w-full border rounded-lg p-2">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Gender</label>
                        <select id="edit_gender" name="gender" class="w-full border rounded-lg p-2">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Status</label>
                        <select id="edit_status" name="status" class="w-full border rounded-lg p-2">
                            <option value="pending">Pending</option>
                            <option value="converted">Converted</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3" class="w-full border rounded-lg p-2"></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm mr-2">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Convert Modal Functions
        function openConvertModal() {
            const modal = document.getElementById('convertModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeConvertModal() {
            const modal = document.getElementById('convertModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Filter toggle for mobile
        function toggleFilters() {
            const filters = document.getElementById('additionalFilters');
            filters.classList.toggle('hidden');
        }

        // Bulk selection functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.convert-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkSelection();
        });

        document.querySelectorAll('.convert-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkSelection);
        });

        function updateBulkSelection() {
            const selected = Array.from(document.querySelectorAll('.convert-checkbox:checked'))
                .map(checkbox => checkbox.value);
            document.getElementById('selectedConverts').value = JSON.stringify(selected);
        }

        // Bulk action form submission
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            const selected = JSON.parse(document.getElementById('selectedConverts').value || '[]');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one convert.');
                return false;
            }
            return true;
        });

        // Refresh table function
        function refreshTable() {
            window.location.reload();
        }

        /* ==== View Convert ==== */
        function viewConvert(id) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewContent');
            modal.classList.remove('hidden'); 
            modal.classList.add('flex');
            content.innerHTML = '<p class="text-center text-gray-400 italic">Loading...</p>';
            
            fetch('fetch_convert.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.error) return content.innerHTML = `<p class='text-red-500'>${data.error}</p>`;
                    content.innerHTML = `
                        <div class="grid grid-cols-2 gap-3">
                            <div><span class="text-gray-500 text-xs">Name:</span><br><span class="font-medium">${data.name}</span></div>
                            <div><span class="text-gray-500 text-xs">Phone:</span><br>${data.phone_number}</div>
                            <div><span class="text-gray-500 text-xs">Email:</span><br>${data.email || '-'}</div>
                            <div><span class="text-gray-500 text-xs">Gender:</span><br>${data.gender}</div>
                            <div><span class="text-gray-500 text-xs">Hostel:</span><br>${data.hostel || '-'}</div>
                            <div><span class="text-gray-500 text-xs">Room:</span><br>${data.room_number || '-'}</div>
                            <div><span class="text-gray-500 text-xs">Status:</span><br>
                                <span class="px-2 py-1 rounded text-xs ${data.status==='converted'?'bg-green-100 text-green-700':'bg-yellow-100 text-yellow-700'}">
                                    ${data.status}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="text-gray-500 text-xs block mb-1">Notes:</span>
                            <p class="bg-gray-50 border rounded p-2 text-sm">${data.notes || '—'}</p>
                        </div>`;
                })
                .catch(() => content.innerHTML = "<p class='text-red-500'>Failed to load convert info.</p>");
        }

        function closeViewModal() {
            const m = document.getElementById('viewModal');
            m.classList.add('hidden'); 
            m.classList.remove('flex');
        }

        /* ==== Edit Convert ==== */
        function editConvert(id) {
            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden'); 
            modal.classList.add('flex');
            
            fetch('fetch_convert.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.error) return alert(data.error);
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_phone').value = data.phone_number;
                    document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_room').value = data.room_number || '';
                    document.getElementById('edit_hostel').value = data.hostel || '';
                    document.getElementById('edit_gender').value = data.gender;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('edit_notes').value = data.notes || '';
                });
        }

        function closeEditModal() {
            const m = document.getElementById('editModal');
            m.classList.add('hidden'); 
            m.classList.remove('flex');
        }

        /* ==== Save Edited Convert ==== */
        document.getElementById('editForm').addEventListener('submit', e => {
            e.preventDefault();
            const form = new FormData(e.target);
            fetch('update_convert.php', { method: 'POST', body: form })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Updated successfully!');
                        location.reload();
                    } else {
                        alert(res.error || 'Update failed.');
                    }
                });
        });

        /* ==== Delete Convert ==== */
        function deleteConvert(id) {
            if (!confirm('Are you sure you want to delete this convert?')) return;
            fetch('delete_convert.php?id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Deleted successfully.');
                        document.querySelector(`button[onclick="viewConvert(${id})"]`)?.closest('tr').remove();
                    } else alert(res.error || 'Delete failed.');
                })
                .catch(() => alert('Error deleting record.'));
        }

        // Close modals when clicking outside
        document.getElementById('convertModal').addEventListener('click', function(e) {
            if (e.target.id === 'convertModal') {
                closeConvertModal();
            }
        });

        document.addEventListener('click', function(e) {
            // View modal
            const viewModal = document.getElementById('viewModal');
            if (e.target === viewModal) {
                closeViewModal();
            }
            
            // Edit modal
            const editModal = document.getElementById('editModal');
            if (e.target === editModal) {
                closeEditModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConvertModal();
                closeViewModal();
                closeEditModal();
            }
        });
    </script>

</body>
</html>