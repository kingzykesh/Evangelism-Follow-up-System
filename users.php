<?php
require_once 'functions.php';
check_auth();

// Check permissions - Only coordinators and super admin can manage users
if (!has_permission(['super_admin'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Register new user
        $matric_number = sanitize_input($_POST['matric_number']);
        $name = sanitize_input($_POST['name']);
        $level = sanitize_input($_POST['level'] ?? '');
        $department = sanitize_input($_POST['department'] ?? '');
        $course = sanitize_input($_POST['course'] ?? '');
        $role = sanitize_input($_POST['role']);
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? null);
        $hostel = sanitize_input($_POST['hostel'] ?? '');
        $room_number = sanitize_input($_POST['room_number'] ?? '');
        $password = password_hash('password123', PASSWORD_DEFAULT); // Default password

        // Check for duplicate matric number
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE matric_number = ?");
        $check_stmt->execute([$matric_number]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = "A user with this matric number already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (matric_number, name, level, department, course, role, email, phone, gender, hostel, room_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$matric_number, $name, $level, $department, $course, $role, $email, $phone, $gender, $hostel, $room_number, $password]);
            
            // Create notification for the new user if they have an account
            create_notification(
                $pdo->lastInsertId(),
                'Welcome to EvangelismTracker!',
                "Your account has been created. Your default password is 'password123'. Please change it after login.",
                'success',
                'profile',
                null
            );
            
            // Notify coordinators
            if ($_SESSION['user_role'] !== 'super_admin') {
                $coordinators = $pdo->prepare("SELECT id FROM users WHERE role = 'coordinator' AND id != ?");
                $coordinators->execute([$_SESSION['user_id']]);
                foreach ($coordinators->fetchAll(PDO::FETCH_ASSOC) as $coordinator) {
                    create_notification(
                        $coordinator['id'],
                        'New User Registered',
                        "{$name} has been registered as " . str_replace('_', ' ', $role) . " by {$_SESSION['user_name']}",
                        'info',
                        'users',
                        $pdo->lastInsertId()
                    );
                }
            }
            
            log_activity($_SESSION['user_id'], 'user_registered', "Registered new user: {$name} ({$matric_number}) as {$role}");
            
            $_SESSION['success'] = "User registered successfully! Default password: password123";
        }
    } elseif (isset($_POST['bulk_action'])) {
        // Handle bulk actions
        $action = sanitize_input($_POST['bulk_action']);
        $selected_users = $_POST['selected_users'] ?? [];
        
        if (empty($selected_users)) {
            $_SESSION['error'] = "Please select at least one user.";
        } else {
            $placeholders = str_repeat('?,', count($selected_users) - 1) . '?';
            
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id IN ($placeholders)");
                    $stmt->execute($selected_users);
                    $_SESSION['success'] = "Selected users activated successfully!";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id IN ($placeholders)");
                    $stmt->execute($selected_users);
                    $_SESSION['success'] = "Selected users deactivated successfully!";
                    break;
                    
                case 'reset_password':
                    $new_password = password_hash('password123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$new_password], $selected_users));
                    $_SESSION['success'] = "Passwords reset successfully! New password: password123";
                    break;
                    
                case 'delete':
                    // Only super admin can delete users
                    if ($_SESSION['user_role'] === 'super_admin') {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role != 'super_admin'");
                        $stmt->execute($selected_users);
                        $_SESSION['success'] = "Selected users deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Only super admin can delete users.";
                    }
                    break;
            }
            
            log_activity($_SESSION['user_id'], 'bulk_user_action', "Performed {$action} on " . count($selected_users) . " users");
        }
    } elseif (isset($_POST['update_user'])) {
        // Update user profile
        $user_id = sanitize_input($_POST['user_id']);
        $name = sanitize_input($_POST['name']);
        $level = sanitize_input($_POST['level'] ?? '');
        $department = sanitize_input($_POST['department'] ?? '');
        $course = sanitize_input($_POST['course'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? null);
        $hostel = sanitize_input($_POST['hostel'] ?? '');
        $room_number = sanitize_input($_POST['room_number'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if user can modify this user's role
        $current_user_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $current_user_stmt->execute([$user_id]);
        $current_user = $current_user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $role = $current_user['role'];
        if ($_SESSION['user_role'] === 'super_admin' && isset($_POST['role'])) {
            $role = sanitize_input($_POST['role']);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, level = ?, department = ?, course = ?, role = ?, email = ?, phone = ?, gender = ?, hostel = ?, room_number = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $level, $department, $course, $role, $email, $phone, $gender, $hostel, $room_number, $is_active, $user_id]);
        
        log_activity($_SESSION['user_id'], 'user_updated', "Updated user profile: {$name}");
        $_SESSION['success'] = "User updated successfully!";
    }
}

// Get filter parameters
$filters = [
    'role' => $_GET['role'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'department' => $_GET['department'] ?? '',
    'hostel' => $_GET['hostel'] ?? ''
];

// Get users data
$users = get_users($filters);

// Get unique departments for filter
$dept_stmt = $pdo->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique hostels for filter
$hostel_stmt = $pdo->prepare("SELECT DISTINCT hostel FROM users WHERE hostel IS NOT NULL AND hostel != '' ORDER BY hostel");
$hostel_stmt->execute();
$hostels = $hostel_stmt->fetchAll(PDO::FETCH_COLUMN);

// Function to get users with filters
function get_users($filters = []) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    // Role filter
    if (!empty($filters['role'])) {
        $sql .= " AND role = ?";
        $params[] = $filters['role'];
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'active') {
            $sql .= " AND is_active = TRUE";
        } elseif ($filters['status'] === 'inactive') {
            $sql .= " AND is_active = FALSE";
        }
    }
    
    // Department filter
    if (!empty($filters['department'])) {
        $sql .= " AND department = ?";
        $params[] = $filters['department'];
    }
    
    // Hostel filter
    if (!empty($filters['hostel'])) {
        $sql .= " AND hostel = ?";
        $params[] = $filters['hostel'];
    }
    
    // Search term
    if (!empty($filters['search'])) {
        $sql .= " AND (name LIKE ? OR matric_number LIKE ? OR email LIKE ? OR hostel LIKE ? OR room_number LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    }
    
    // Don't show super admin to coordinators
    if ($_SESSION['user_role'] === 'coordinator') {
        $sql .= " AND role != 'super_admin'";
    }
    
    $sql .= " ORDER BY 
        CASE role 
            WHEN 'super_admin' THEN 1
            WHEN 'coordinator' THEN 2
            WHEN 'prayer_team' THEN 3
            WHEN 'worker' THEN 4
            ELSE 5
        END, name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'worker' THEN 1 ELSE 0 END) as workers,
        SUM(CASE WHEN role = 'prayer_team' THEN 1 ELSE 0 END) as prayer_team,
        SUM(CASE WHEN role = 'coordinator' THEN 1 ELSE 0 END) as coordinators,
        SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_users
    FROM users 
    WHERE role != 'super_admin'
");
$stats_stmt->execute();
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Evangelism System</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                        <p class="text-gray-600 mt-1">Manage system users and their permissions</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <button onclick="openUserModal()" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                            <i class="fas fa-user-plus mr-2"></i>Add User
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

            <!-- User Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-users text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $user_stats['total_users'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-user-check text-green-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Users</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $user_stats['active_users'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-xl">
                            <i class="fas fa-hands-helping text-orange-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Workers</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $user_stats['workers'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-hands-praying text-purple-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Prayer Team</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $user_stats['prayer_team'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-xl">
                            <i class="fas fa-user-shield text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Coordinators</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $user_stats['coordinators'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">User Filters</h3>
                </div>

                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                placeholder="Search users..." 
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Roles</option>
                            <option value="worker" <?php echo $filters['role'] === 'worker' ? 'selected' : ''; ?>>Worker</option>
                            <option value="prayer_team" <?php echo $filters['role'] === 'prayer_team' ? 'selected' : ''; ?>>Prayer Team</option>
                            <option value="coordinator" <?php echo $filters['role'] === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                            <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                            <option value="super_admin" <?php echo $filters['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Department Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Hostel Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hostel</label>
                        <select name="hostel" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Hostels</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo htmlspecialchars($hostel); ?>" <?php echo $filters['hostel'] === $hostel ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hostel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div class="md:col-span-2 lg:col-span-5 flex items-center justify-between pt-4 border-t border-gray-200">
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="users.php" 
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-xl font-medium transition duration-200">
                                <i class="fas fa-refresh mr-2"></i>Reset
                            </a>
                        </div>
                        <div class="text-sm text-gray-500">
                            Found <span class="font-semibold text-orange-500"><?php echo count($users); ?></span> users
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
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
                                        <button type="submit" name="bulk_action" value="activate" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-check-circle mr-3 text-green-500"></i>Activate Users
                                        </button>
                                        <button type="submit" name="bulk_action" value="deactivate" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-times-circle mr-3 text-red-500"></i>Deactivate Users
                                        </button>
                                        <button type="submit" name="bulk_action" value="reset_password" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-key mr-3 text-orange-500"></i>Reset Passwords
                                        </button>
                                        <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                                        <hr class="my-1">
                                        <button type="submit" name="bulk_action" value="delete" 
                                            class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash mr-3"></i>Delete Users
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="selected_users" id="selectedUsers">
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <button onclick="refreshTable()" class="p-2 hover:bg-gray-200 rounded-lg transition duration-200">
                            <i class="fas fa-refresh"></i>
                        </button>
                        <span><?php echo count($users); ?> users</span>
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
                                    User Details
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Academic Info
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Accommodation
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role & Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Activity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <i class="fas fa-users text-3xl text-gray-300 mb-3"></i>
                                        <p class="text-gray-500 font-medium">No users found</p>
                                        <p class="text-gray-400 text-sm mt-1">Try adjusting your filters or add new users</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="user_select" value="<?php echo $user['id']; ?>" 
                                            class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300 rounded user-checkbox">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['matric_number']); ?></div>
                                                <?php if ($user['email']): ?>
                                                <div class="text-xs text-gray-400">
                                                    <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['level']); ?> Level</div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['department']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['course']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['hostel'] || $user['room_number']): ?>
                                            <div class="text-sm text-gray-900">
                                                <i class="fas fa-building mr-2 text-blue-500"></i>
                                                <?php echo htmlspecialchars($user['hostel'] ?: 'Not specified'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-door-closed mr-2 text-green-500"></i>
                                                Room <?php echo htmlspecialchars($user['room_number'] ?: 'Not specified'); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-sm text-gray-400 italic">Not specified</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-2 mb-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?php 
                                                switch($user['role']) {
                                                    case 'super_admin': echo 'bg-red-100 text-red-800'; break;
                                                    case 'coordinator': echo 'bg-purple-100 text-purple-800'; break;
                                                    case 'prayer_team': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'worker': echo 'bg-orange-100 text-orange-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <i class="fas fa-<?php 
                                                    switch($user['role']) {
                                                        case 'super_admin': echo 'crown'; break;
                                                        case 'coordinator': echo 'user-shield'; break;
                                                        case 'prayer_team': echo 'hands-praying'; break;
                                                        case 'worker': echo 'user'; break;
                                                        default: echo 'user';
                                                    }
                                                ?> mr-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'check' : 'times'; ?> mr-1"></i>
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <?php if ($user['gender']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                <?php echo $user['gender'] === 'male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                                                <i class="fas fa-<?php echo $user['gender'] === 'male' ? 'mars' : 'venus'; ?> mr-1"></i>
                                                <?php echo ucfirst($user['gender']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($user['phone']): ?>
                                        <div class="text-xs text-gray-500">
                                            <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($user['phone']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($user['last_login']): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-sign-in-alt mr-2 text-green-500"></i>
                                                Last login: <?php echo date('M j, g:i A', strtotime($user['last_login'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center text-orange-500">
                                                <i class="fas fa-clock mr-2"></i>
                                                Never logged in
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-calendar-plus mr-1"></i>
                                            Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 transition duration-150" 
                                                title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="resetUserPassword(<?php echo $user['id']; ?>)" 
                                                class="text-orange-600 hover:text-orange-900 transition duration-150"
                                                title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($_SESSION['user_role'] === 'super_admin' && $user['role'] !== 'super_admin'): ?>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900 transition duration-150"
                                                title="Delete User">
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
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Add New User</h3>
                <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="userForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Matric Number *</label>
                        <input type="text" name="matric_number" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter matric number">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" name="name" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter full name">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                        <select name="level" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Select Level</option>
                            <option value="100">100 Level</option>
                            <option value="200">200 Level</option>
                            <option value="300">300 Level</option>
                            <option value="400">400 Level</option>
                            <option value="500">500 Level</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" name="department" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., Computer Science">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Course of Study</label>
                        <input type="text" name="course" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., B.Sc Computer Science">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <select name="role" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                            <option value="worker">Worker</option>
                            <option value="prayer_team">Prayer Team</option>
                            <option value="coordinator">Coordinator</option>
                            <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="user@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" name="phone" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="+234 XXX XXX XXXX">
                    </div>

                    <!-- Gender Field -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                        <div class="flex space-x-6">
                            <label class="inline-flex items-center">
                                <input type="radio" name="gender" value="male" 
                                    class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Male</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="gender" value="female"
                                    class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Female</span>
                            </label>
                        </div>
                    </div>

                    <!-- Hostel and Room Number Fields -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hostel</label>
                        <input type="text" name="hostel" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., Male Hostel A">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                        <input type="text" name="room_number" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="e.g., 101">
                    </div>
                </div>

                <div class="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-xl">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-orange-500 mr-3"></i>
                        <div>
                            <p class="text-sm font-medium text-orange-800">Default Password</p>
                            <p class="text-sm text-orange-700">The user will receive "password123" as their default password. They should change it after first login.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeUserModal()" 
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="submit" name="add_user" 
                        class="px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200 transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fa-solid fa-pen text-orange-500 mr-2"></i> Edit User
                </h3>
                <button onclick="closeEditUserModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form method="POST" id="editUserForm" class="px-6 py-5 space-y-4">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Full Name</label>
                    <input type="text" id="edit_user_name" name="name" class="w-full border rounded-lg p-2" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Matric Number</label>
                        <input type="text" id="edit_user_matric" name="matric_number" class="w-full border rounded-lg p-2" disabled>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Role</label>
                        <select id="edit_user_role" name="role" class="w-full border rounded-lg p-2">
                            <option value="worker">Worker</option>
                            <option value="prayer_team">Prayer Team</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Department</label>
                        <input type="text" id="edit_user_department" name="department" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Level</label>
                        <input type="text" id="edit_user_level" name="level" class="w-full border rounded-lg p-2">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Email</label>
                        <input type="email" id="edit_user_email" name="email" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Phone</label>
                        <input type="text" id="edit_user_phone" name="phone" class="w-full border rounded-lg p-2">
                    </div>
                </div>
                <!-- Gender Field in Edit Modal -->
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Gender</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="male" id="edit_user_gender_male"
                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Male</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="female" id="edit_user_gender_female"
                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Female</span>
                        </label>
                    </div>
                </div>
                <!-- Hostel and Room Number Fields in Edit Modal -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Hostel</label>
                        <input type="text" id="edit_user_hostel" name="hostel" class="w-full border rounded-lg p-2" placeholder="e.g., Male Hostel A">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Room Number</label>
                        <input type="text" id="edit_user_room_number" name="room_number" class="w-full border rounded-lg p-2" placeholder="e.g., 101">
                    </div>
                </div>
                <div class="flex items-center justify-between pt-3">
                    <label class="flex items-center space-x-2 text-sm text-gray-700">
                        <input type="checkbox" id="edit_user_active" name="is_active" class="rounded text-orange-500">
                        <span>Active</span>
                    </label>
                    <div>
                        <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm mr-2">Cancel</button>
                        <button type="submit" name="update_user" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // User Modal Functions
        function openUserModal() {
            const modal = document.getElementById('userModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeUserModal() {
            const modal = document.getElementById('userModal');
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Edit User function
        function editUser(id) {
            const modal = document.getElementById('editUserModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            fetch('fetch_user.php?id=' + id)
                .then(response => response.json())
                .then(user => {
                    if (user.error) {
                        alert(user.error);
                        closeEditUserModal();
                        return;
                    }
                    
                    document.getElementById('edit_user_id').value = user.id;
                    document.getElementById('edit_user_name').value = user.name;
                    document.getElementById('edit_user_matric').value = user.matric_number;
                    document.getElementById('edit_user_role').value = user.role;
                    document.getElementById('edit_user_department').value = user.department || '';
                    document.getElementById('edit_user_level').value = user.level || '';
                    document.getElementById('edit_user_email').value = user.email || '';
                    document.getElementById('edit_user_phone').value = user.phone || '';
                    document.getElementById('edit_user_hostel').value = user.hostel || '';
                    document.getElementById('edit_user_room_number').value = user.room_number || '';
                    document.getElementById('edit_user_active').checked = !!user.is_active;
                    
                    // Set gender
                    if (user.gender === 'male') {
                        document.getElementById('edit_user_gender_male').checked = true;
                    } else if (user.gender === 'female') {
                        document.getElementById('edit_user_gender_female').checked = true;
                    }
                })
                .catch(() => {
                    alert('Failed to load user data.');
                    closeEditUserModal();
                });
        }

        function closeEditUserModal() {
            const modal = document.getElementById('editUserModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const userModal = document.getElementById('userModal');
            if (e.target === userModal) {
                closeUserModal();
            }
            
            const editUserModal = document.getElementById('editUserModal');
            if (e.target === editUserModal) {
                closeEditUserModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUserModal();
                closeEditUserModal();
            }
        });

        // Bulk selection functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkSelection();
        });

        function updateBulkSelection() {
            const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                .map(checkbox => checkbox.value);
            document.getElementById('selectedUsers').value = JSON.stringify(selected);
        }

        // Bulk action form submission
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            const selected = JSON.parse(document.getElementById('selectedUsers').value || '[]');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one user.');
                return false;
            }
            return true;
        });

        // Refresh table function
        function refreshTable() {
            window.location.reload();
        }

        // Reset user password function
        function resetUserPassword(id) {
            if (!confirm('Are you sure you want to reset this user\'s password? The new password will be "password123".')) {
                return;
            }
            
            fetch('reset_user_password.php?id=' + id)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Password reset successfully! New password: password123');
                    } else {
                        alert(result.error || 'Password reset failed.');
                    }
                })
                .catch(() => {
                    alert('Error resetting password.');
                });
        }

        // Delete user function
        function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            fetch('delete_user.php?id=' + id)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('User deleted successfully.');
                        location.reload();
                    } else {
                        alert(result.error || 'Delete failed.');
                    }
                })
                .catch(() => {
                    alert('Error deleting user.');
                });
        }
    </script>
</body>
</html>