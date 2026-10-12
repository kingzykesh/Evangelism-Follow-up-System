<?php
require_once 'functions.php';
check_auth();

// Check permissions
if (!has_permission(['super_admin', 'coordinator', 'prayer_team'])) {
    header('Location: dashboard.php');
    exit();
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for email logs
$query = "
    SELECT el.*, u.name as sender_name, c.name as recipient_name 
    FROM email_logs el 
    LEFT JOIN users u ON el.sender_id = u.id 
    LEFT JOIN converts c ON el.recipient_email = c.email 
    WHERE 1=1
";

$params = [];

// Add filters
if (!empty($date_from)) {
    $query .= " AND DATE(el.sent_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(el.sent_at) <= ?";
    $params[] = $date_to;
}

if (!empty($status) && in_array($status, ['sent', 'failed'])) {
    $query .= " AND el.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (el.recipient_email LIKE ? OR el.subject LIKE ? OR c.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Order by most recent first
$query .= " ORDER BY el.sent_at DESC";

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ($query) as total");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();

// Setup pagination
$per_page = 20;
$total_pages = ceil($total_logs / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Execute main query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$email_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get email statistics for the header
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_emails,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        MIN(sent_at) as first_sent,
        MAX(sent_at) as last_sent
    FROM email_logs
    WHERE sender_id = ?
");
$stats_stmt->execute([$_SESSION['user_id']]);
$email_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Logs - Evangelism System</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">Email Logs</h1>
                        <p class="text-gray-600 mt-1">View and manage all sent email messages</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <a href="email.php" 
                            class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Composer
                        </a>
                    </div>
                </div>
            </div>

            <!-- Email Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-envelope text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Emails</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $email_stats['total_emails'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-check-circle text-green-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Successful</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $email_stats['successful'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-xl">
                            <i class="fas fa-times-circle text-red-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Failed</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $email_stats['failed'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-calendar-plus text-purple-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">First Sent</p>
                            <h3 class="text-lg font-bold text-gray-900">
                                <?php echo $email_stats['first_sent'] ? date('M j, Y', strtotime($email_stats['first_sent'])) : 'Never'; ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-xl">
                            <i class="fas fa-calendar-check text-orange-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Last Sent</p>
                            <h3 class="text-lg font-bold text-gray-900">
                                <?php echo $email_stats['last_sent'] ? date('M j, Y', strtotime($email_stats['last_sent'])) : 'Never'; ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Logs</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">All Status</option>
                            <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Email, subject, or name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" 
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 px-4 rounded-lg font-medium transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Email Logs Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Email History</h3>
                        <span class="text-sm text-gray-500">
                            Showing <?php echo count($email_logs); ?> of <?php echo $total_logs; ?> emails
                        </span>
                    </div>
                </div>

                <?php if (empty($email_logs)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-envelope-open text-4xl text-gray-300 mb-4"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">No email logs found</h4>
                        <p class="text-gray-500 mb-4">No emails have been sent yet or match your filters.</p>
                        <a href="email.php" class="text-orange-500 hover:text-orange-600 font-medium">
                            <i class="fas fa-paper-plane mr-2"></i>Send your first email
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($email_logs as $log): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-orange-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-orange-500"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($log['recipient_name'] ?: 'Unknown'); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($log['recipient_email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 max-w-xs truncate">
                                            <?php echo htmlspecialchars($log['subject']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-md truncate">
                                            <?php echo htmlspecialchars($log['message']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $log['status'] === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas fa-<?php echo $log['status'] === 'sent' ? 'check' : 'times'; ?> mr-1"></i>
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($log['sent_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewEmail(<?php echo $log['id']; ?>)" 
                                            class="text-orange-500 hover:text-orange-700 mr-3">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        <button onclick="resendEmail(<?php echo $log['id']; ?>)" 
                                            class="text-green-500 hover:text-green-700">
                                            <i class="fas fa-redo mr-1"></i>Resend
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($current_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                                        class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                        class="px-3 py-1 border rounded-lg text-sm font-medium 
                                        <?php echo $i == $current_page ? 'bg-orange-500 text-white border-orange-500' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                                        class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Email View Modal -->
    <div id="emailViewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Email Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="emailDetails" class="space-y-4">
                <!-- Email details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function viewEmail(emailId) {
            // In a real implementation, you'd fetch the email details via AJAX
            // For now, we'll show a simple alert
            alert('Viewing email ID: ' + emailId + '\n\nIn a full implementation, this would show the complete email details in a modal.');
        }

        function resendEmail(emailId) {
            if (confirm('Are you sure you want to resend this email?')) {
                // In a real implementation, you'd make an AJAX call to resend the email
                alert('Resending email ID: ' + emailId + '\n\nThis would resend the email via AJAX.');
            }
        }

        function closeViewModal() {
            document.getElementById('emailViewModal').classList.add('hidden');
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeViewModal();
            }
        });
    </script>
</body>
</html>