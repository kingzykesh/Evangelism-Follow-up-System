<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EvangelismSystem - Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #dc2626 100%);
        }
        .hero-pattern {
            background-image: radial-gradient(circle at 25px 25px, rgba(255,255,255,0.2) 2%, transparent 40%), radial-gradient(circle at 75px 75px, rgba(255,255,255,0.1) 2%, transparent 40%);
            background-size: 100px 100px;
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <!-- Navigation -->
    <nav class="bg-white/10 backdrop-blur-md border-b border-white/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="bg-white p-2 rounded-xl shadow-lg">
                        <i class="fas fa-cross text-orange-500 text-2xl"></i>
                    </div>
                    <span class="ml-3 text-white text-xl font-bold">AUC<span class="text-orange-300">Evangelism</span></span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-white hover:text-orange-200 transition duration-200 font-medium">
                        Sign In
                    </a>
                    <a href="login.php" class="bg-white text-orange-600 hover:bg-orange-50 px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105">
                        Get Started
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-pattern">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 text-center">
            <!-- Badge -->
            <div class="inline-flex items-center px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 mb-8">
                <i class="fas fa-star text-yellow-300 mr-2"></i>
                <span class="text-white text-sm font-medium">Currently being used by the Evangelism Team</span>
            </div>

            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
                Transform Your
                <span class="bg-gradient-to-r from-orange-200 to-yellow-200 bg-clip-text text-transparent">Evangelism</span>
                <br>Management
            </h1>
            
            <p class="text-xl md:text-2xl text-orange-100 mb-8 max-w-3xl mx-auto leading-relaxed">
                Streamline convert tracking, follow-up coordination, and team management with our comprehensive evangelism platform.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                <a href="login.php" class="bg-white text-orange-600 hover:bg-orange-50 px-8 py-4 rounded-2xl font-bold text-lg transition duration-200 transform hover:scale-105 shadow-2xl">
                    <i class="fas fa-rocket mr-2"></i>Launch Dashboard
                </a>
                <a href="#features" class="border-2 border-white text-white hover:bg-white/10 px-8 py-4 rounded-2xl font-bold text-lg transition duration-200">
                    <i class="fas fa-play-circle mr-2"></i>Watch Demo
                </a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                <div class="text-center">
                    <div class="text-3xl font-bold text-white mb-2">500+</div>
                    <div class="text-orange-200">Converts Tracked</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-white mb-2">500+</div>
                    <div class="text-orange-200">Active Workers</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-white mb-2">99%</div>
                    <div class="text-orange-200">Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section id="features" class="bg-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Everything Needed for Effective Evangelism
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Powerful tools designed specifically for evangelism coordination and follow-up management.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-gradient-to-br from-orange-50 to-red-50 p-6 rounded-2xl border border-orange-100 hover:shadow-lg transition duration-300">
                    <div class="bg-orange-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Convert Management</h3>
                    <p class="text-gray-600 mb-4">
                        Track every convert with detailed profiles, contact information, and spiritual journey progress.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Smart search & filtering
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Status tracking
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Bulk operations
                        </li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-2xl border border-blue-100 hover:shadow-lg transition duration-300">
                    <div class="bg-blue-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-hands-praying text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Follow-up System</h3>
                    <p class="text-gray-600 mb-4">
                        Coordinate prayer teams and track follow-up activities with detailed timelines and notes.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Prayer team coordination
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Activity timeline
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Progress tracking
                        </li>
                    </ul>
                </div>

                <!-- Feature 3 -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 p-6 rounded-2xl border border-green-100 hover:shadow-lg transition duration-300">
                    <div class="bg-green-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-sms text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Digital Follow-up</h3>
                    <p class="text-gray-600 mb-4">
                        Send personalized SMS messages and maintain digital contact with converts automatically.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Bulk SMS messaging
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Message templates
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Delivery tracking
                        </li>
                    </ul>
                </div>

                <!-- Feature 4 -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 p-6 rounded-2xl border border-purple-100 hover:shadow-lg transition duration-300">
                    <div class="bg-purple-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-user-shield text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Role Management</h3>
                    <p class="text-gray-600 mb-4">
                        Manage different user roles with appropriate permissions and access levels.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Multi-level access
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Activity monitoring
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Bulk user management
                        </li>
                    </ul>
                </div>

                <!-- Feature 5 -->
                <div class="bg-gradient-to-br from-yellow-50 to-amber-50 p-6 rounded-2xl border border-yellow-100 hover:shadow-lg transition duration-300">
                    <div class="bg-yellow-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-chart-bar text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Analytics & Reports</h3>
                    <p class="text-gray-600 mb-4">
                        Gain insights with comprehensive analytics and export reports for leadership review.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Visual dashboards
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Performance metrics
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Export capabilities
                        </li>
                    </ul>
                </div>

                <!-- Feature 6 -->
                <div class="bg-gradient-to-br from-red-50 to-rose-50 p-6 rounded-2xl border border-red-100 hover:shadow-lg transition duration-300">
                    <div class="bg-red-500 w-12 h-12 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-mobile-alt text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Mobile Responsive</h3>
                    <p class="text-gray-600 mb-4">
                        Access your evangelism data anywhere, anytime with our fully responsive design.
                    </p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Mobile-first design
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Touch-friendly interface
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Offline capabilities
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="gradient-bg py-20">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">
                Ready to Transform Your Evangelism Efforts?
            </h2>
            <p class="text-xl text-orange-100 mb-8">
                Join hundreds of evangelism teams already using our platform to make disciples effectively.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="login.php" class="bg-white text-orange-600 hover:bg-orange-50 px-8 py-4 rounded-2xl font-bold text-lg transition duration-200 transform hover:scale-105 shadow-2xl">
                    <i class="fas fa-play mr-2"></i>Get Started Now
                </a>
                <a href="#features" class="border-2 border-white text-white hover:bg-white/10 px-8 py-4 rounded-2xl font-bold text-lg transition duration-200">
                    <i class="fas fa-book-open mr-2"></i>View Documentation
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="bg-orange-500 p-2 rounded-lg">
                            <i class="fas fa-cross text-white text-lg"></i>
                        </div>
                        <span class="ml-3 text-xl font-bold">AUC<span class="text-orange-400"> Evangelism</span></span>
                    </div>
                    <p class="text-gray-400 text-sm">
                        Empowering the evangelism team with powerful management tools for effective discipleship.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">Platform</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition duration-200">Features</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Pricing</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Documentation</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">API</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">Support</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition duration-200">Help Center</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Community</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Contact Us</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Status</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">Company</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition duration-200">About</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Blog</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition duration-200">Privacy</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-400">
                <p>&copy; 2024 EvangelismTracker. All rights reserved. Built with ❤️ for the Great Commission.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll animation for features
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        document.querySelectorAll('.bg-gradient-to-br').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>