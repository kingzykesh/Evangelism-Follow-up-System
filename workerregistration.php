<?php
ob_start();
session_start();
require_once 'config.php';
require_once 'functions.php';

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
    $gender = sanitize_input($_POST['gender'] ?? '');
    $hostel = sanitize_input($_POST['hostel'] ?? '');
    $room_number = sanitize_input($_POST['room_number'] ?? '');
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
    
    // If no errors, register user as worker
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (matric_number, name, level, department, course, email, phone, gender, hostel, room_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'worker')");
        
        try {
            $stmt->execute([$matric_number, $name, $level, $department, $course, $email, $phone, $gender, $hostel, $room_number, $password_hash]);
            
            // Get the new user's ID
            $user_id = $pdo->lastInsertId();
            
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'worker';
            $_SESSION['user_matric'] = $matric_number;
            
            // Create welcome notification
            create_notification(
                $user_id,
                'Welcome to AUCEvangelismPortal!',
                "Thank you for registering as a Worker. You can now start submitting converts and tracking your evangelism activities.",
                'success',
                'dashboard',
                null
            );
            
            // Log activity
            log_activity($user_id, 'worker_registered', "New worker registered: {$name} ({$matric_number})");
            
            $_SESSION['success'] = "Registration successful! Welcome to AUCEvangelismPortal as a Worker.";
            
            // Clear output buffer before redirect
            ob_end_clean();
            header('Location: dashboard.php');
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
            error_log("Worker registration error: " . $e->getMessage());
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
    <title>Worker Registration - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
        .worker-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-12">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-8 mx-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="worker-badge w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fas fa-hands-praying text-white text-3xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Join as AUC Worker</h2>
            <p class="text-gray-600 mt-2">Register to start tracking your evangelism activities and converts</p>
            
            <!-- Worker Role Badge -->
            <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm font-medium mt-3">
                <i class="fas fa-user-shield mr-2"></i>
                Campus Fellowship Worker
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
        <form method="POST" id="registrationForm" class="space-y-6">
            <!-- Academic Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-2 text-blue-500"></i>Matric Number *
                    </label>
                    <input type="text" name="matric_number" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="e.g., U20XX00000"
                        value="<?php echo $_POST['matric_number'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-blue-500"></i>Full Name *
                    </label>
                    <input type="text" name="name" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="Enter your full name"
                        value="<?php echo $_POST['name'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-graduation-cap mr-2 text-blue-500"></i>Level *
                    </label>
                    <select name="level" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200">
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
                        <i class="fas fa-building mr-2 text-blue-500"></i>Department *
                    </label>
                    <input type="text" name="department" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="e.g., Computer Science"
                        value="<?php echo $_POST['department'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-book mr-2 text-blue-500"></i>Course of Study *
                    </label>
                    <input type="text" name="course" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="e.g., B.Sc Computer Science"
                        value="<?php echo $_POST['course'] ?? ''; ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2 text-blue-500"></i>Phone Number *
                    </label>
                    <input type="tel" name="phone" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="+234 XXX XXX XXXX"
                        value="<?php echo $_POST['phone'] ?? ''; ?>">
                </div>
            </div>

            <!-- Contact Information -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-envelope mr-2 text-blue-500"></i>Email Address *
                </label>
                <input type="email" name="email" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    placeholder="your.email@example.com"
                    value="<?php echo $_POST['email'] ?? ''; ?>">
            </div>

            <!-- Gender, Hostel & Room Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Gender Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-venus-mars mr-2 text-blue-500"></i>Gender
                    </label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="male" 
                                class="h-4 w-4 text-blue-500 focus:ring-blue-500 border-gray-300"
                                <?php echo ($_POST['gender'] ?? '') === 'male' ? 'checked' : ''; ?>>
                            <span class="ml-2 text-sm text-gray-700">
                                <i class="fas fa-mars text-blue-500 mr-1"></i>Male
                            </span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="gender" value="female"
                                class="h-4 w-4 text-blue-500 focus:ring-blue-500 border-gray-300"
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
                        <i class="fas fa-home mr-2 text-blue-500"></i>Hostel
                    </label>
                    <input type="text" name="hostel" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="e.g., Male Hostel A"
                        value="<?php echo $_POST['hostel'] ?? ''; ?>">
                </div>

                <!-- Room Number Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-door-open mr-2 text-blue-500"></i>Room Number
                    </label>
                    <input type="text" name="room_number" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="e.g., 101"
                        value="<?php echo $_POST['room_number'] ?? ''; ?>">
                </div>
            </div>

            <!-- Accommodation Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                    <div>
                        <p class="text-blue-800 text-sm font-medium">Why we ask for accommodation details:</p>
                        <p class="text-blue-700 text-sm mt-1">
                            This helps coordinators match you with converts in nearby locations for more effective follow-up. 
                        </p>
                    </div>
                </div>
            </div>

            <!-- Password Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-blue-500"></i>Password *
                    </label>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="Enter your password"
                        id="password"
                        minlength="6">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-blue-500"></i>Confirm Password *
                    </label>
                    <input type="password" name="confirm_password" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                        placeholder="Confirm your password"
                        id="confirmPassword"
                        minlength="6">
                </div>
            </div>

            <!-- Worker Responsibilities -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                    <i class="fas fa-tasks mr-2"></i>Worker Responsibilities:
                </h4>
                <ul class="text-blue-700 text-sm list-disc list-inside space-y-1">
                    <li>Submit information about new converts</li>
                    <li>Track evangelism activities and follow-ups</li>
                    <li>Report weekly evangelism progress</li>
                    <li>Participate in evangelism training sessions</li>
                    <li>Maintain accurate contact and location information</li>
                </ul>
            </div>

            <!-- Terms and Submit -->
            <div class="flex items-start space-x-3">
                <input type="checkbox" id="terms" name="terms" required 
                    class="h-4 w-4 text-blue-500 focus:ring-blue-500 border-gray-300 rounded mt-1">
                <label for="terms" class="block text-sm text-gray-700">
                    I agree to the <a href="#" class="text-blue-500 hover:text-blue-600 font-medium">Terms of Service</a> and 
                    <a href="#" class="text-blue-500 hover:text-blue-600 font-medium">Privacy Policy</a>. 
                    I understand that as a Worker, I'm committed to actively participate in evangelism activities 
                    and provide accurate information for effective follow-up coordination.
                </label>
            </div>

            <button type="submit" 
                class="w-full worker-badge hover:shadow-lg text-white py-3 px-4 rounded-xl font-bold transition duration-200 transform hover:scale-105">
                <i class="fas fa-user-plus mr-2"></i>Register as a Worker
            </button>

            <div class="text-center">
                <p class="text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-blue-500 hover:text-blue-600 font-medium">Sign in here</a>
                </p>
                <p class="text-gray-500 text-sm mt-2">
                    Want to join as Prayer Team? 
                    <a href="#" class="text-green-500 hover:text-green-600 font-medium">Register here</a>
                </p>
            </div>
        </form>
    </div>

    <script>
        // Form validation
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

        // Auto-suggest hostel based on gender
        const genderRadios = document.querySelectorAll('input[name="gender"]');
        const hostelInput = document.querySelector('input[name="hostel"]');
        
        genderRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'male' && !hostelInput.value) {
                    hostelInput.placeholder = 'e.g., Male Hostel A, Male Hostel B';
                } else if (this.value === 'female' && !hostelInput.value) {
                    hostelInput.placeholder = 'e.g., Female Hostel A, Female Hostel B';
                }
            });
        });
    </script>
</body>
</html>