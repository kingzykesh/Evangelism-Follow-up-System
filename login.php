<?php
require_once 'functions.php';
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric_number = sanitize_input($_POST['matric_number']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE matric_number = ? AND is_active = TRUE");
    $stmt->execute([$matric_number]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_matric'] = $user['matric_number'];
        
        // Update last login
        $update = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $update->execute([$user['id']]);
        
        // Log activity
        log_activity($user['id'], 'login', 'User logged in successfully');
        
        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Invalid matric number or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Evangelism System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center gradient-bg">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl p-8 mx-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-white w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fas fa-cross text-orange-500 text-3xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Welcome Back</h2>
            <p class="text-gray-600 mt-2">Sign in to AUC Evangelism System</p>
        </div>

        <!-- Login Form -->
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-id-card mr-2 text-orange-500"></i>Matric Number
                </label>
                <input type="text" name="matric_number" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                    placeholder="Enter your matric number">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-lock mr-2 text-orange-500"></i>Password
                </label>
                <input type="password" name="password" required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                    placeholder="Enter your password">
            </div>

            <button type="submit" 
                class="w-full gradient-bg hover:shadow-lg text-white py-3 px-4 rounded-xl font-bold transition duration-200 transform hover:scale-105">
                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
            </button>
        </form>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                    <span class="text-red-700 font-medium"><?php echo $error; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-gray-50 rounded-xl">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Demo Credentials:</h4>
            <p class="text-xs text-gray-600">Matric: SUPERADMIN | Password: login123</p>
        </div>
    </div>
</body>
</html>