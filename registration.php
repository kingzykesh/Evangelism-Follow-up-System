<?php
ob_start();
session_start();
require_once 'config.php';

// Redirect to dashboard if already logged in
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
    $role = sanitize_input($_POST['role']); // New role field
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    // Check if matric number already exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE matric_number = ?");
    $check_stmt->execute([$matric_number]);
    if ($check_stmt->rowCount() > 0) {
        $errors[] = "Matric number already exists!";
    }
    
    // Check if email already exists
    if (!empty($email)) {
        $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->rowCount() > 0) {
            $errors[] = "Email already exists!";
        }
    }
    
    // Password validation
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long!";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    // Validate role
    $allowed_roles = ['worker', 'prayer_team'];
    if (!in_array($role, $allowed_roles)) {
        $errors[] = "Please select a valid user type!";
    }
    
    // If no errors, register user
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (matric_number, name, level, department, course, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$matric_number, $name, $level, $department, $course, $email, $phone, $password_hash, $role]);
            
            // Get the new user's ID
            $user_id = $pdo->lastInsertId();
            
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_matric'] = $matric_number;
            
            $_SESSION['success'] = "Registration successful! Welcome to EvangelismTracker.";
            
            ob_end_clean();
            header('Location: dashboard.php');
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-12">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8 mx-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-orange-500 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-cross text-white text-2xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Join EvangelismTracker</h2>
            <p class="text-gray-600 mt-2">Create your account to start tracking evangelism activities</p>
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
        <form method="POST" id="registrationForm" class="space-y-6">
            <!-- Academic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-2 text-orange-500"></i>Matric Number *
                    </label>
                    <input type="text" name="matric_number" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="e.g., U20XX00000"
                        value="<?php echo $_POST['matric_number'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-orange-500"></i>Full Name *
                    </label>
                    <input type="text" name="name" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="Enter your full name"
                        value="<?php echo $_POST['name'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-tag mr-2 text-orange-500"></i>User Type *
                    </label>
                    <select name="role" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                        <option value="">Select User Type</option>
                        <option value="worker" <?php echo ($_POST['role'] ?? '') === 'worker' ? 'selected' : ''; ?>>Evangelism Worker</option>
                        <option value="prayer_team" <?php echo ($_POST['role'] ?? '') === 'prayer_team' ? 'selected' : ''; ?>>Prayer Team Member</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-graduation-cap mr-2 text-orange-500"></i>Level
                    </label>
                    <select name="level" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                        <option value="">Select Level</option>
                        <option value="100" <?php echo ($_POST['level'] ?? '') === '100' ? 'selected' : ''; ?>>100 Level</option>
                        <option value="200" <?php echo ($_POST['level'] ?? '') === '200' ? 'selected' : ''; ?>>200 Level</option>
                        <option value="300" <?php echo ($_POST['level'] ?? '') === '300' ? 'selected' : ''; ?>>300 Level</option>
                        <option value="400" <?php echo ($_POST['level'] ?? '') === '400' ? 'selected' : ''; ?>>400 Level</option>
                        <option value="500" <?php echo ($_POST['level'] ?? '') === '500' ? 'selected' : ''; ?>>500 Level</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-building mr-2 text-orange-500"></i>Department
                    </label>
                    <input type="text" name="department" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="e.g., Computer Science"
                        value="<?php echo $_POST['department'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book mr-2 text-orange-500"></i>Course of Study
                    </label>
                    <input type="text" name="course" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="e.g., B.Sc Computer Science"
                        value="<?php echo $_POST['course'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2 text-orange-500"></i>Phone Number
                    </label>
                    <input type="tel" name="phone" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="+234 XXX XXX XXXX"
                        value="<?php echo $_POST['phone'] ?? ''; ?>">
                </div>
            </div>

            <!-- Contact Information -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-envelope mr-2 text-orange-500"></i>Email Address
                </label>
                <input type="email" name="email" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                    placeholder="your.email@example.com"
                    value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>

            <!-- Password Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-orange-500"></i>Password *
                    </label>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="Enter your password"
                        id="password"
                        minlength="6">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-orange-500"></i>Confirm Password *
                    </label>
                    <input type="password" name="confirm_password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                        placeholder="Confirm your password"
                        id="confirmPassword"
                        minlength="6">
                </div>
            </div>

            <!-- Terms and Submit -->
            <div class="flex items-center">
                <input type="checkbox" id="terms" name="terms" required 
                    class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300 rounded">
                <label for="terms" class="ml-2 block text-sm text-gray-700">
                    I agree to the <a href="#" class="text-orange-500 hover:text-orange-600 font-medium">Terms of Service</a> and <a href="#" class="text-orange-500 hover:text-orange-600 font-medium">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" 
                class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 px-4 rounded-xl font-bold transition duration-200 transform hover:scale-105">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </button>

            <div class="text-center">
                <p class="text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-orange-500 hover:text-orange-600 font-medium">Sign in here</a>
                </p>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
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
        });
    </script>
</body>
</html>