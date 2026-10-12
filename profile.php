<?php
require_once 'functions.php';
check_auth();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = sanitize_input($_POST['name']);
        $level = sanitize_input($_POST['level'] ?? '');
        $department = sanitize_input($_POST['department'] ?? '');
        $course = sanitize_input($_POST['course'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? '');
        $hostel = sanitize_input($_POST['hostel'] ?? '');
        $room_number = sanitize_input($_POST['room_number'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, level = ?, department = ?, course = ?, email = ?, phone = ?, gender = ?, hostel = ?, room_number = ? WHERE id = ?");
        $stmt->execute([$name, $level, $department, $course, $email, $phone, $gender, $hostel, $room_number, $_SESSION['user_id']]);
        
        // Update session variables
        $_SESSION['user_name'] = $name;
        
        // Log activity (if activity_log table exists)
        try {
            log_activity($_SESSION['user_id'], 'profile_updated', "Updated profile information");
        } catch (Exception $e) {
            // Silently continue if activity_log doesn't exist
        }
        
        $_SESSION['success'] = "Profile updated successfully!";
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            $_SESSION['error'] = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = "New password must be at least 6 characters long!";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
            
            // Log activity (if activity_log table exists)
            try {
                log_activity($_SESSION['user_id'], 'password_changed', "Changed password");
            } catch (Exception $e) {
                // Silently continue if activity_log doesn't exist
            }
            
            $_SESSION['success'] = "Password changed successfully!";
        }
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user activity stats with error handling
$activity_stats = [
    'total_converts' => 0,
    'converted' => 0,
    'active_days' => 0
];

try {
    $activity_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_converts,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
            COUNT(DISTINCT DATE(date_submitted)) as active_days
        FROM converts 
        WHERE worker_id = ?
    ");
    $activity_stmt->execute([$_SESSION['user_id']]);
    $activity_stats = $activity_stmt->fetch(PDO::FETCH_ASSOC) ?: $activity_stats;
} catch (Exception $e) {
    // Use default stats if converts table doesn't exist or has issues
    $activity_stats = [
        'total_converts' => 0,
        'converted' => 0,
        'active_days' => 0
    ];
}

// Get recent activity with error handling
$recent_activity = [];
try {
    $recent_activity_stmt = $pdo->prepare("
        SELECT action, description, created_at 
        FROM activity_log 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_activity_stmt->execute([$_SESSION['user_id']]);
    $recent_activity = $recent_activity_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Create some sample recent activity if table doesn't exist
    $recent_activity = [
        [
            'action' => 'profile_updated',
            'description' => 'Updated profile information',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'action' => 'account_created', 
            'description' => 'Account was created',
            'created_at' => $user['created_at']
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Evangelism System</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
                        <p class="text-gray-600 mt-1">Manage your account information and settings</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
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
                        <?php if (!empty($user['gender'])): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo $user['gender'] === 'male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                            <i class="fas fa-<?php echo $user['gender'] === 'male' ? 'mars' : 'venus'; ?> mr-1"></i>
                            <?php echo ucfirst($user['gender']); ?>
                        </span>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Profile Info & Stats -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Profile Information -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Profile Information</h3>
                            <button onclick="toggleEditMode()" class="text-orange-500 hover:text-orange-600 font-medium text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit Profile
                            </button>
                        </div>

                        <form method="POST" id="profileForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="nameInput" readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Matric Number</label>
                                    <input type="text" value="<?php echo htmlspecialchars($user['matric_number']); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500"
                                        readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                                    <select name="level" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="levelSelect" disabled>
                                        <option value="">Select Level</option>
                                        <option value="100" <?php echo $user['level'] == '100' ? 'selected' : ''; ?>>100 Level</option>
                                        <option value="200" <?php echo $user['level'] == '200' ? 'selected' : ''; ?>>200 Level</option>
                                        <option value="300" <?php echo $user['level'] == '300' ? 'selected' : ''; ?>>300 Level</option>
                                        <option value="400" <?php echo $user['level'] == '400' ? 'selected' : ''; ?>>400 Level</option>
                                        <option value="500" <?php echo $user['level'] == '500' ? 'selected' : ''; ?>>500 Level</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                    <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="departmentInput" readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Course of Study</label>
                                    <input type="text" name="course" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="courseInput" readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                    <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500"
                                        readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="emailInput" readonly>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="phoneInput" readonly>
                                </div>

                                <!-- Gender Field -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                    <div class="flex space-x-6" id="genderContainer">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="gender" value="male" 
                                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300"
                                                <?php echo ($user['gender'] ?? '') === 'male' ? 'checked' : ''; ?>
                                                id="genderMale" disabled>
                                            <span class="ml-2 text-sm text-gray-700">Male</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="gender" value="female"
                                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300"
                                                <?php echo ($user['gender'] ?? '') === 'female' ? 'checked' : ''; ?>
                                                id="genderFemale" disabled>
                                            <span class="ml-2 text-sm text-gray-700">Female</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Hostel and Room Number Fields -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Hostel</label>
                                    <input type="text" name="hostel" value="<?php echo htmlspecialchars($user['hostel'] ?? ''); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="hostelInput" readonly
                                        placeholder="e.g., Male Hostel A">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                                    <input type="text" name="room_number" value="<?php echo htmlspecialchars($user['room_number'] ?? ''); ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                        id="roomNumberInput" readonly
                                        placeholder="e.g., 101">
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200 hidden" id="profileActions">
                                <button type="button" onclick="toggleEditMode()" 
                                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                                    Cancel
                                </button>
                                <button type="submit" name="update_profile" 
                                    class="px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200">
                                    <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Activity Statistics -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">My Evangelism Activity</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="text-center p-4 bg-orange-50 rounded-xl">
                                <div class="text-2xl font-bold text-orange-600"><?php echo $activity_stats['total_converts'] ?? 0; ?></div>
                                <div class="text-sm text-orange-700 font-medium">Total Converts</div>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-xl">
                                <div class="text-2xl font-bold text-green-600"><?php echo $activity_stats['converted'] ?? 0; ?></div>
                                <div class="text-sm text-green-700 font-medium">Converted</div>
                            </div>
                            <div class="text-center p-4 bg-blue-50 rounded-xl">
                                <div class="text-2xl font-bold text-blue-600"><?php echo $activity_stats['active_days'] ?? 0; ?></div>
                                <div class="text-sm text-blue-700 font-medium">Active Days</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Password Change & Recent Activity -->
                <div class="space-y-8">
                    <!-- Change Password -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Change Password</h3>
                        <form method="POST">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                    <input type="password" name="current_password" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                    <input type="password" name="new_password" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                </div>
                                <button type="submit" name="change_password" 
                                    class="w-full px-6 py-3 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Account Information -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Account Information</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Account Status:</span>
                                <span class="font-medium <?php echo $user['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last Login:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Member Since:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Accommodation:</span>
                                <span class="font-medium text-gray-900">
                                    <?php 
                                    $hostel = $user['hostel'] ?? '';
                                    $room_number = $user['room_number'] ?? '';
                                    
                                    if (!empty($hostel) && !empty($room_number)) {
                                        echo htmlspecialchars($hostel) . ' - Room ' . htmlspecialchars($room_number);
                                    } elseif (!empty($hostel)) {
                                        echo htmlspecialchars($hostel);
                                    } elseif (!empty($room_number)) {
                                        echo 'Room ' . htmlspecialchars($room_number);
                                    } else {
                                        echo 'Not specified';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Recent Activity</h3>
                        <div class="space-y-4">
                            <?php if (empty($recent_activity)): ?>
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-history text-2xl text-gray-300 mb-2"></i>
                                    <p class="text-sm">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-<?php 
                                                switch($activity['action']) {
                                                    case 'convert_submitted': echo 'user-plus text-orange-500'; break;
                                                    case 'profile_updated': echo 'edit text-blue-500'; break;
                                                    case 'password_changed': echo 'key text-green-500'; break;
                                                    case 'account_created': echo 'user-plus text-purple-500'; break;
                                                    default: echo 'circle text-gray-500';
                                                }
                                            ?> text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo $activity['description']; ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let editMode = false;

        function toggleEditMode() {
            editMode = !editMode;
            
            const editableFields = [
                'nameInput', 'levelSelect', 'departmentInput', 
                'courseInput', 'emailInput', 'phoneInput',
                'hostelInput', 'roomNumberInput'
            ];
            
            const profileActions = document.getElementById('profileActions');
            const genderRadios = document.querySelectorAll('input[name="gender"]');
            
            // Toggle regular fields
            editableFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    if (editMode) {
                        field.removeAttribute('readonly');
                        field.removeAttribute('disabled');
                        field.classList.remove('bg-gray-50', 'text-gray-500');
                    } else {
                        if (field.type === 'select-one') {
                            field.setAttribute('disabled', 'true');
                        } else {
                            field.setAttribute('readonly', 'true');
                        }
                        field.classList.add('bg-gray-50', 'text-gray-500');
                    }
                }
            });
            
            // Toggle gender radio buttons
            genderRadios.forEach(radio => {
                if (editMode) {
                    radio.removeAttribute('disabled');
                } else {
                    radio.setAttribute('disabled', 'true');
                }
            });
            
            if (editMode) {
                profileActions.classList.remove('hidden');
            } else {
                profileActions.classList.add('hidden');
            }
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const email = document.getElementById('emailInput').value;
            const phone = document.getElementById('phoneInput').value;
            
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (phone && !isValidPhone(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number.');
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidPhone(phone) {
            // Basic phone validation - adjust as needed
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
            return phoneRegex.test(phone);
        }

        // Auto-format phone number
        document.getElementById('phoneInput').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = '+234' + value.substring(1);
            }
            e.target.value = value;
        });

        // Initialize the form state on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set all fields to read-only mode initially
            editMode = false;
            const editableFields = [
                'nameInput', 'levelSelect', 'departmentInput', 
                'courseInput', 'emailInput', 'phoneInput',
                'hostelInput', 'roomNumberInput'
            ];
            
            editableFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    if (field.type === 'select-one') {
                        field.setAttribute('disabled', 'true');
                    } else {
                        field.setAttribute('readonly', 'true');
                    }
                    field.classList.add('bg-gray-50', 'text-gray-500');
                }
            });
            
            // Disable gender radio buttons
            const genderRadios = document.querySelectorAll('input[name="gender"]');
            genderRadios.forEach(radio => {
                radio.setAttribute('disabled', 'true');
            });
        });
    </script>
</body>
</html>