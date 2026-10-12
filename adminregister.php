<?php
session_start();

// Database configuration
$host = 'sdb-e.hosting.stackcp.net';
$dbname = 'envangelism-313833d278';
$username = 'envangelism-313833d278';
$password = 'q1oa30y393';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function create_notification($user_id, $title, $message, $type = 'info', $module = null, $related_id = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_module, related_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type, $module, $related_id]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        // Silent fail - don't break registration if notification fails
        return false;
    }
}

function log_activity($user_id, $action, $description = null) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip, $agent]);
        return true;
    } catch (PDOException $e) {
        // Silent fail - don't break registration if logging fails
        return false;
    }
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric_number = sanitize_input($_POST['matric_number']);
    $name = sanitize_input($_POST['name']);
    $level = sanitize_input($_POST['level']);
    $department = sanitize_input($_POST['department']);
    $course = sanitize_input($_POST['course']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $gender = sanitize_input($_POST['gender'] ?? '');
    $hostel = sanitize_input($_POST['hostel'] ?? '');
    $room_number = sanitize_input($_POST['room_number'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'super_admin'; // Force super admin role
    
    // Validation
    $errors = [];
    
    // Check if matric number already exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE matric_number = ?");
    $check_stmt->execute([$matric_number]);
    if ($check_stmt->rowCount() > 0) {
        $errors[] = "Matric number already exists! Please choose a different one.";
    }
    
    // Check if email already exists
    if (!empty($email)) {
        $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->rowCount() > 0) {
            $errors[] = "Email already exists! Please use a different email.";
        }
    }
    
    // Password validation
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long!";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    // If no errors, register user as SUPER ADMIN
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (matric_number, name, level, department, course, email, phone, gender, hostel, room_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$matric_number, $name, $level, $department, $course, $email, $phone, $gender, $hostel, $room_number, $password_hash, $role]);
            
            // Get the new user's ID
            $user_id = $pdo->lastInsertId();
            
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_matric'] = $matric_number;
            
            // Try to create welcome notification (optional)
            create_notification(
                $user_id,
                'Welcome Super Admin!',
                "Your super admin account has been created successfully.",
                'success',
                'dashboard',
                null
            );
            
            // Try to log activity (optional)
            log_activity($user_id, 'super_admin_registered', "Super admin registered: {$name}");
            
            $_SESSION['success'] = "Super Admin account created successfully! Welcome to EvangelismTracker.";
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Super Admin Account - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
        .admin-badge {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-12">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8 mx-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="admin-badge w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-crown text-white text-3xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Create Super Admin Account</h2>
            <p class="text-gray-600 mt-2">Setup your administrator account for EvangelismTracker</p>
            
            <!-- Super Admin Badge -->
            <div class="inline-flex items-center px-4 py-2 bg-red-100 border border-red-200 text-red-800 rounded-full text-sm font-medium mt-3">
                <i class="fas fa-shield-alt mr-2"></i>
                Super Administrator Access
            </div>
        </div>

        <!-- Important Notice -->
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 text-lg mt-1 mr-3"></i>
                <div>
                    <h4 class="text-blue-800 font-semibold">Important Information</h4>
                    <p class="text-blue-700 text-sm mt-1">
                        You are creating a <strong>Super Administrator account</strong>. This account will have full access to:
                    </p>
                    <ul class="text-blue-600 text-sm mt-2 list-disc list-inside space-y-1">
                        <li>User management and role assignments</li>
                        <li>All converts data and reports</li>
                        <li>System configuration and settings</li>
                        <li>Analytics and export capabilities</li>
                        <li>Complete oversight of all evangelism activities</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg mr-3"></i>
                    <div>
                        <h4 class="text-red-800 font-semibold">Please fix the following errors:</h4>
                        <ul class="text-red-700 mt-1 list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" id="adminRegistrationForm" class="space-y-6">
            <!-- Academic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-2 text-red-500"></i>Matric Number *
                    </label>
                    <input type="text" name="matric_number" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="e.g., ADMIN001"
                        value="<?php echo $_POST['matric_number'] ?? 'ADMIN001'; ?>">
                    <p class="text-xs text-gray-500 mt-1">Use a unique identifier like ADMIN001</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-red-500"></i>Full Name *
                    </label>
                    <input type="text" name="name" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="Enter your full name"
                        value="<?php echo $_POST['name'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-graduation-cap mr-2 text-red-500"></i>Level
                    </label>
                    <select name="level" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200">
                        <option value="">Select Level</option>
                        <option value="Administrator" <?php echo ($_POST['level'] ?? '') === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="Coordinator" <?php echo ($_POST['level'] ?? '') === 'Coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                        <option value="Staff" <?php echo ($_POST['level'] ?? '') === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="Management" <?php echo ($_POST['level'] ?? '') === 'Management' ? 'selected' : ''; ?>>Management</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-building mr-2 text-red-500"></i>Department *
                    </label>
                    <input type="text" name="department" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="e.g., Evangelism Coordination"
                        value="<?php echo $_POST['department'] ?? 'Evangelism Coordination'; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book mr-2 text-red-500"></i>Course/Position
                    </label>
                    <input type="text" name="course" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="e.g., System Administrator"
                        value="<?php echo $_POST['course'] ?? 'System Administrator'; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2 text-red-500"></i>Phone Number *
                    </label>
                    <input type="tel" name="phone" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="+234 XXX XXX XXXX"
                        value="<?php echo $_POST['phone'] ?? ''; ?>">
                </div>
            </div>

            <!-- Contact Information -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-envelope mr-2 text-red-500"></i>Email Address *
                </label>
                <input type="email" name="email" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                    placeholder="admin@evangelism.com"
                    value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>

            <!-- Gender, Hostel & Room Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Gender Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-venus-mars mr-2 text-red-500"></i>Gender
                    </label>
                    <div class="flex flex-col space-y-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="male" 
                                class="h-4 w-4 text-red-500 focus:ring-red-500 border-gray-300"
                                <?php echo ($_POST['gender'] ?? '') === 'male' ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm text-gray-700">
                                <i class="fas fa-mars text-blue-500 mr-1"></i>Male
                            </span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="female"
                                class="h-4 w-4 text-red-500 focus:ring-red-500 border-gray-300"
                                <?php echo ($_POST['gender'] ?? '') === 'female' ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm text-gray-700">
                                <i class="fas fa-venus text-pink-500 mr-1"></i>Female
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Hostel Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-home mr-2 text-red-500"></i>Hostel
                    </label>
                    <input type="text" name="hostel" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="e.g., Staff Quarters"
                        value="<?php echo $_POST['hostel'] ?? ''; ?>">
                </div>

                <!-- Room Number Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-door-open mr-2 text-red-500"></i>Room Number
                    </label>
                    <input type="text" name="room_number" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="e.g., Office 101"
                        value="<?php echo $_POST['room_number'] ?? ''; ?>">
                </div>
            </div>

            <!-- Admin Accommodation Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-blue-800 text-sm font-medium">Administrator Location Information:</p>
                        <p class="text-blue-700 text-sm mt-1">
                            Providing accommodation details helps with system coordination and allows for better management 
                            of evangelism activities across different locations.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Password Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-red-500"></i>Password *
                    </label>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="Enter secure password"
                        id="password"
                        minlength="6">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters (recommended: 8+ with mix of characters)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-red-500"></i>Confirm Password *
                    </label>
                    <input type="password" name="confirm_password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-200"
                        placeholder="Confirm your password"
                        id="confirmPassword"
                        minlength="6">
                </div>
            </div>

            <!-- Security Notice -->
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-yellow-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Security Recommendation</p>
                        <p class="text-xs text-yellow-700 mt-1">
                            As a Super Administrator, please use a strong, unique password and enable two-factor authentication 
                            when available. This account has full system access.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Role Information (Read-only) -->
            <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-crown text-red-500 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-red-800">Account Type: <strong>Super Administrator</strong></p>
                        <p class="text-xs text-red-600 mt-1">This account will have full system access and privileges across all modules.</p>
                    </div>
                </div>
            </div>

            <!-- Terms and Submit -->
            <div class="flex items-start">
                <input type="checkbox" id="terms" name="terms" required 
                    class="h-4 w-4 text-red-500 focus:ring-red-500 border-gray-300 rounded mt-1">
                <label for="terms" class="ml-2 block text-sm text-gray-700">
                    I understand that I am creating a <strong>Super Administrator account</strong> with full system access 
                    and complete control over all evangelism data and user accounts. 
                    I agree to the <a href="#" class="text-red-500 hover:text-red-600 font-medium">Terms of Service</a> and 
                    <a href="#" class="text-red-500 hover:text-red-600 font-medium">Privacy Policy</a>.
                </label>
            </div>

            <button type="submit" 
                class="w-full admin-badge hover:shadow-lg text-white py-3 px-4 rounded-xl font-bold transition duration-200 transform hover:scale-105">
                <i class="fas fa-crown mr-2"></i>Create Super Admin Account
            </button>

            <div class="text-center">
                <p class="text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-red-500 hover:text-red-600 font-medium">Login here</a>
                </p>
                <p class="text-gray-500 text-sm mt-2">
                    Need a different role? 
                    <a href="register.php" class="text-blue-500 hover:text-blue-600 font-medium">Worker Registration</a> | 
                    <a href="prayer_team_register.php" class="text-green-500 hover:text-green-600 font-medium">Prayer Team Registration</a>
                </p>
            </div>
        </form>
    </div>

    <script>
        // Form validation
        document.getElementById('adminRegistrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }

            // Password strength check (optional but recommended for admin)
            if (password.length < 8) {
                if (!confirm('Your password is less than 8 characters. For a Super Admin account, we recommend using a stronger password. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }

            // Confirm super admin creation
            if (!confirm('WARNING: You are creating a SUPER ADMIN account with FULL SYSTEM ACCESS. This account will have complete control over all data and users. Continue?')) {
                e.preventDefault();
                return;
            }
        });

        // Real-time password match indicator
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.classList.remove('border-red-300');
                    this.classList.add('border-green-300');
                } else {
                    this.classList.remove('border-green-300');
                    this.classList.add('border-red-300');
                }
            } else {
                this.classList.remove('border-red-300', 'border-green-300');
            }
        });

        // Auto-format phone number
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = '+234' + value.substring(1);
            }
            e.target.value = value;
        });

        // Auto-suggest hostel based on gender for admin context
        const genderRadios = document.querySelectorAll('input[name="gender"]');
        const hostelInput = document.querySelector('input[name="hostel"]');
        
        genderRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'male' && !hostelInput.value) {
                    hostelInput.placeholder = 'e.g., Male Staff Quarters, Office Block A';
                } else if (this.value === 'female' && !hostelInput.value) {
                    hostelInput.placeholder = 'e.g., Female Staff Quarters, Office Block B';
                }
            });
        });

        // Enhanced password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.createElement('div');
            strengthIndicator.className = 'text-xs mt-1';
            
            if (password.length === 0) {
                this.parentElement.querySelector('.text-xs')?.remove();
            } else if (password.length < 6) {
                this.parentElement.querySelector('.text-xs')?.remove();
                strengthIndicator.innerHTML = '<span class="text-red-500">Weak - Minimum 6 characters required</span>';
                this.parentElement.appendChild(strengthIndicator);
            } else if (password.length < 8) {
                this.parentElement.querySelector('.text-xs')?.remove();
                strengthIndicator.innerHTML = '<span class="text-yellow-500">Moderate - Consider using 8+ characters</span>';
                this.parentElement.appendChild(strengthIndicator);
            } else {
                this.parentElement.querySelector('.text-xs')?.remove();
                strengthIndicator.innerHTML = '<span class="text-green-500">Strong - Good password length</span>';
                this.parentElement.appendChild(strengthIndicator);
            }
        });
    </script>
</body>
</html>