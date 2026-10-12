<?php
// Get unread notifications count
$notification_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$notification_stmt->execute([$_SESSION['user_id']]);
$unread_count = $notification_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!-- Header -->
<header class="bg-white shadow-sm border-b border-gray-200 fixed top-0 right-0 left-0 lg:left-64 z-40">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Mobile menu button -->
            <div class="lg:hidden">
                <button type="button" onclick="toggleSidebar()" 
                    class="bg-white p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <!-- Search Bar -->
            <div class="flex-1 max-w-2xl">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" id="globalSearch" 
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-orange-500 focus:border-orange-500 sm:text-sm"
                        placeholder="Search converts, users, reports...">
                </div>
            </div>

            <!-- Right Section -->
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <button type="button" onclick="toggleNotifications()" 
                        class="bg-white p-2 rounded-xl text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition duration-200 relative">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-96 bg-white rounded-2xl shadow-xl border border-gray-200 z-50 transform origin-top-right">
                        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="font-bold text-gray-900">Notifications</h3>
                            <?php if ($unread_count > 0): ?>
                            <button onclick="markAllAsRead()" class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                                Mark all as read
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <?php 
                            $notifications = get_user_notifications($_SESSION['user_id'], false);
                            if (empty($notifications)): ?>
                                <div class="p-6 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-3xl mb-3 text-gray-300"></i>
                                    <p class="font-medium">No notifications yet</p>
                                    <p class="text-sm mt-1">We'll notify you when something arrives</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50 transition duration-150 <?php echo !$notification['is_read'] ? 'bg-orange-50' : ''; ?>">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 pt-1">
                                                <div class="w-8 h-8 rounded-full bg-<?php 
                                                    switch($notification['type']) {
                                                        case 'success': echo 'green-100'; break;
                                                        case 'warning': echo 'yellow-100'; break;
                                                        case 'error': echo 'red-100'; break;
                                                        default: echo 'blue-100';
                                                    }
                                                ?> flex items-center justify-center">
                                                    <i class="fas fa-<?php 
                                                        switch($notification['type']) {
                                                            case 'success': echo 'check text-green-600'; break;
                                                            case 'warning': echo 'exclamation-triangle text-yellow-600'; break;
                                                            case 'error': echo 'times text-red-600'; break;
                                                            default: echo 'info text-blue-600';
                                                        }
                                                    ?> text-sm"></i>
                                                </div>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <p class="text-sm font-medium text-gray-900"><?php echo $notification['title']; ?></p>
                                                <p class="text-sm text-gray-600 mt-1"><?php echo $notification['message']; ?></p>
                                                <p class="text-xs text-gray-400 mt-2">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?php 
                                                    $time = strtotime($notification['created_at']);
                                                    $now = time();
                                                    $diff = $now - $time;
                                                    
                                                    if ($diff < 3600) {
                                                        echo ceil($diff / 60) . ' min ago';
                                                    } elseif ($diff < 86400) {
                                                        echo ceil($diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M j, g:i A', $time);
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                            <?php if (!$notification['is_read']): ?>
                                            <div class="flex-shrink-0">
                                                <span class="w-2 h-2 bg-orange-500 rounded-full"></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                            <a href="notifications.php" class="block text-center text-orange-500 hover:text-orange-600 font-medium text-sm">
                                View all notifications
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="relative">
                    <button type="button" onclick="toggleProfileMenu()" 
                        class="flex items-center space-x-3 bg-white p-2 rounded-xl hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition duration-200">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-medium text-gray-900"><?php echo $_SESSION['user_name']; ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?php echo str_replace('_', ' ', $_SESSION['user_role']); ?></p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-200 z-50 transform origin-top-right">
                        <div class="p-4 border-b border-gray-200">
                            <p class="text-sm font-medium text-gray-900"><?php echo $_SESSION['user_name']; ?></p>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $_SESSION['user_matric']; ?></p>
                        </div>
                        <div class="p-2">
                            <a href="profile.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition duration-150">
                                <i class="fas fa-user-circle mr-3 text-gray-400"></i>
                                My Profile
                            </a>
                            <a href="settings.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition duration-150">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Settings
                            </a>
                            <a href="activity.php" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition duration-150">
                                <i class="fas fa-history mr-3 text-gray-400"></i>
                                Activity Log
                            </a>
                        </div>
                        <div class="p-2 border-t border-gray-200">
                            <a href="logout.php" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition duration-150">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Global Search Results -->
<div id="searchResults" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Search Results</h3>
                    <button type="button" onclick="closeSearch()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="searchResultsContent" class="max-h-96 overflow-y-auto">
                    <!-- Search results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle functions
function toggleNotifications() {
    const dropdown = document.getElementById('notificationsDropdown');
    dropdown.classList.toggle('hidden');
    // Close profile menu if open
    document.getElementById('profileMenu').classList.add('hidden');
}

function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');
    // Close notifications if open
    document.getElementById('notificationsDropdown').classList.add('hidden');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
}

function closeSearch() {
    document.getElementById('searchResults').classList.add('hidden');
}

// Mark all notifications as read
function markAllAsRead() {
    fetch('notifications.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
}

// Global search functionality
let searchTimeout;
document.getElementById('globalSearch').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
        closeSearch();
        return;
    }
    
    searchTimeout = setTimeout(() => {
        performGlobalSearch(query);
    }, 500);
});

function performGlobalSearch(query) {
    fetch(`search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            const resultsContainer = document.getElementById('searchResultsContent');
            const modal = document.getElementById('searchResults');
            
            if (data.results && data.results.length > 0) {
                let html = '<div class="space-y-3">';
                data.results.forEach(result => {
                    html += `
                        <a href="${result.link}" class="flex items-center p-3 hover:bg-gray-50 rounded-xl transition duration-150 border border-gray-100">
                            <div class="flex-shrink-0 w-10 h-10 bg-${result.color}-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-${result.icon} text-${result.color}-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">${result.title}</p>
                                <p class="text-xs text-gray-500">${result.subtitle}</p>
                            </div>
                        </a>
                    `;
                });
                html += '</div>';
                resultsContainer.innerHTML = html;
            } else {
                resultsContainer.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-search text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No results found for "${query}"</p>
                    </div>
                `;
            }
            
            modal.classList.remove('hidden');
        });
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const notifications = document.getElementById('notificationsDropdown');
    const profile = document.getElementById('profileMenu');
    const notifBtn = event.target.closest('button[onclick="toggleNotifications()"]');
    const profileBtn = event.target.closest('button[onclick="toggleProfileMenu()"]');
    
    if (!notifications.contains(event.target) && !notifBtn) {
        notifications.classList.add('hidden');
    }
    
    if (!profile.contains(event.target) && !profileBtn) {
        profile.classList.add('hidden');
    }
});
</script>