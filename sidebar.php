<?php
// Define navigation items based on user role
$nav_items = [
    'dashboard' => [
        'icon' => 'gauge-high',
        'label' => 'Dashboard',
        'link' => 'dashboard.php',
        'roles' => ['super_admin', 'coordinator', 'worker', 'prayer_team']
    ],
    'converts' => [
        'icon' => 'users',
        'label' => 'Converts Management',
        'link' => 'converts.php',
        'roles' => ['super_admin', 'coordinator', 'worker', 'prayer_team'],
        'badge' => 'converts'
    ],
    'followups' => [
        'icon' => 'hands-praying',
        'label' => 'Follow-up System',
        'link' => 'followups.php',
        'roles' => ['super_admin', 'coordinator', 'prayer_team'],
        'badge' => 'followups'
    ],
    'email' => [
        'icon' => 'sms',
        'label' => 'Digital Follow-up',
        'link' => 'email.php',
        'roles' => ['super_admin', 'coordinator', 'prayer_team']
    ],
    'users' => [
        'icon' => 'user-gear',
        'label' => 'User Management',
        'link' => 'users.php',
        'roles' => ['super_admin']
    ],
    'exports' => [
        'icon' => 'file-export',
        'label' => 'Reports & Exports',
        'link' => 'exports.php',
        'roles' => ['super_admin', 'coordinator']
    ],
    'analytics' => [
        'icon' => 'chart-line',
        'label' => 'Analytics',
        'link' => 'analytics.php',
        'roles' => ['super_admin', 'coordinator']
    ],

    // ✅ NEW SECTION: Coordinator Exclusive
    'coordinator_dashboard' => [
        'icon' => 'users-cog',
        'label' => 'Converts Assignment',
        'link' => 'coordinator_dashboard.php',
        'roles' => ['super_admin', 'coordinator']
    ],
      // Woekers Reminder
    'reminder' => [
        'icon' => 'bell',
        'label' => 'Workers Reminder',
        'link' => 'reminder.php',
        'roles' => ['super_admin', 'coordinator']
    ],

    // ✅ NEW SECTION: Worker Exclusive
    'assigned_converts' => [
        'icon' => 'user-check',
        'label' => 'Assigned Converts',
        'link' => 'worker_assigned_converts.php',
        'roles' => ['worker']
    ]
 
];

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div id="sidebar" class="bg-gradient-to-b from-gray-900 to-black fixed inset-y-0 left-0 z-50 w-64 transform lg:translate-x-0 transition duration-300 ease-in-out -translate-x-full">
    <!-- Logo -->
    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-800">
        <div class="flex items-center">
            <div class="bg-orange-500 p-2 rounded-xl">
                <i class="fas fa-cross text-white text-lg"></i>
            </div>
            <div class="ml-3">
                <h1 class="text-white font-bold text-lg">AUC<span class="text-orange-500">Evangelism</span></h1>
                <p class="text-gray-400 text-xs">Management System</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="lg:hidden text-gray-400 hover:text-white">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- User Profile Summary -->
    <div class="p-4 border-b border-gray-800">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg">
                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white font-medium truncate"><?php echo $_SESSION['user_name']; ?></p>
                <p class="text-orange-400 text-sm font-medium capitalize"><?php echo str_replace('_', ' ', $_SESSION['user_role']); ?></p>
                <p class="text-gray-400 text-xs truncate"><?php echo $_SESSION['user_matric']; ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
        <?php foreach ($nav_items as $key => $item): ?>
            <?php if (has_permission($item['roles'])): ?>
                <?php
                $is_active = ($current_page === $item['link']);
                $badge_count = 0;

                // Dynamic badge counters (mocked - replace with real queries)
                if (isset($item['badge'])) {
                    switch ($item['badge']) {
                        case 'converts':
                            $badge_count = rand(5, 20);
                            break;
                        case 'followups':
                            $badge_count = rand(3, 15);
                            break;
                    }
                }
                ?>
                <a href="<?php echo $item['link']; ?>" 
                   class="flex items-center px-3 py-3 text-sm font-medium rounded-xl transition-all duration-200 group relative <?php echo $is_active ? 'bg-orange-500 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
                    <!-- Icon -->
                    <i class="fas fa-<?php echo $item['icon']; ?> w-5 text-center mr-3 <?php echo $is_active ? 'text-white' : 'text-gray-400 group-hover:text-white'; ?>"></i>

                    <!-- Label -->
                    <span class="flex-1"><?php echo $item['label']; ?></span>

                    <!-- Badge -->
                    <?php if ($badge_count > 0): ?>
                        <span class="bg-<?php echo $is_active ? 'white text-orange-500' : 'orange-500 text-white'; ?> text-xs font-bold px-2 py-1 rounded-full min-w-6 text-center transform group-hover:scale-110 transition duration-200">
                            <?php echo $badge_count; ?>
                        </span>
                    <?php endif; ?>

                    <!-- Active indicator -->
                    <?php if ($is_active): ?>
                        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                            <div class="w-2 h-2 bg-white rounded-full"></div>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Quick Stats -->
    <div class="p-4 border-t border-gray-800">
        <div class="space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-400">Online Workers</span>
                <span class="text-green-400 font-semibold"><?php echo rand(5, 15); ?></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-400">This Week</span>
                <span class="text-orange-400 font-semibold">+<?php echo rand(10, 25); ?> converts</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-400">Follow-ups</span>
                <span class="text-blue-400 font-semibold"><?php echo rand(20, 50); ?> pending</span>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center justify-between text-xs">
            <span class="text-gray-400">System Status</span>
            <div class="flex items-center">
                <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                <span class="text-green-400 font-semibold">Online</span>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>

<style>
#sidebar {
    scrollbar-width: thin;
    scrollbar-color: #4B5563 #1F2937;
}

#sidebar::-webkit-scrollbar {
    width: 6px;
}

#sidebar::-webkit-scrollbar-track {
    background: #1F2937;
}

#sidebar::-webkit-scrollbar-thumb {
    background: #4B5563;
    border-radius: 3px;
}

#sidebar::-webkit-scrollbar-thumb:hover {
    background: #6B7280;
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Close sidebar when clicking on a link in mobile view
document.querySelectorAll('#sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
            toggleSidebar();
        }
    });
});
</script>
