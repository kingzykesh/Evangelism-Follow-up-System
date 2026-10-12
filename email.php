<?php
require_once 'functions.php';
check_auth();

// Check permissions
if (!has_permission(['super_admin', 'coordinator', 'prayer_team'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle Email submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_email'])) {
        $recipients = $_POST['recipients'] ?? [];
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);
        $message_type = sanitize_input($_POST['message_type']);
        
        if (empty($recipients)) {
            $_SESSION['error'] = "Please select at least one recipient.";
        } elseif (empty($subject)) {
            $_SESSION['error'] = "Please enter a subject.";
        } elseif (empty($message)) {
            $_SESSION['error'] = "Please enter a message.";
        } else {
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($recipients as $recipient_data) {
                list($email, $name) = explode('|', $recipient_data);
                
                // Personalize message
                $personalized_message = str_replace('{name}', $name, $message);
                
                // Send Email (using actual email function)
                $success = send_email($email, $subject, $personalized_message, $name);
                
                if ($success) {
                    $success_count++;
                    
                    // Log activity
                    log_activity($_SESSION['user_id'], 'email_sent', "Sent email to {$name} ({$email})");
                } else {
                    $failed_count++;
                }
            }
            
            if ($success_count > 0) {
                $_SESSION['success'] = "Successfully sent {$success_count} emails" . 
                    ($failed_count > 0 ? " ({$failed_count} failed)" : "");
                
                // Notify coordinators about bulk emails
                if ($message_type === 'bulk' && $success_count > 5) {
                    $coordinators = $pdo->prepare("SELECT id FROM users WHERE role = 'coordinator'");
                    $coordinators->execute();
                    foreach ($coordinators->fetchAll(PDO::FETCH_ASSOC) as $coordinator) {
                        create_notification(
                            $coordinator['id'],
                            'Bulk Email Sent',
                            "{$_SESSION['user_name']} sent {$success_count} bulk emails",
                            'info',
                            'email',
                            null
                        );
                    }
                }
            } else {
                $_SESSION['error'] = "Failed to send all emails. Please try again.";
            }
        }
    }
}

// Get filter parameters for converts
$filters = [
    'status' => $_GET['status'] ?? 'converted',
    'search' => $_GET['search'] ?? '',
    'has_followup' => $_GET['has_followup'] ?? ''
];

// Get converts for Email
$converts = search_converts($filters);

// Get Email templates
$email_templates = [
    'welcome' => [
        'name' => 'Welcome Email',
        'subject' => 'Welcome to Our Christian Family!',
        'message' => 'Hello {name}!

Welcome to our Christian family! We\'re excited to journey with you in faith and support you as you grow in your relationship with Christ.

We believe God has amazing plans for your life, and we\'re honored to be part of your spiritual journey.

Blessings,
Your Christian Family'
    ],
    'followup' => [
        'name' => 'Follow-up Check-in',
        'subject' => 'Checking In On You',
        'message' => 'Hi {name}!

Just wanted to check in and see how you\'re doing. Remember that we\'re here to pray for you and support you in any way we can.

If you have any prayer requests or need someone to talk to, please don\'t hesitate to reach out.

"For I know the plans I have for you," declares the Lord, "plans to prosper you and not to harm you, plans to give you hope and a future." - Jeremiah 29:11

Blessings,'
    ],
    'prayer' => [
        'name' => 'Prayer Encouragement',
        'subject' => 'We\'re Praying For You Today',
        'message' => 'Hello {name}!

This is just a reminder that we\'re praying for you today. We\'ve been lifting you up in prayer and asking God to bless you, guide you, and fill you with His peace.

"Do not be anxious about anything, but in every situation, by prayer and petition, with thanksgiving, present your requests to God." - Philippians 4:6

Know that you are loved and supported in prayer.

Blessings,'
    ],
    'event' => [
        'name' => 'Event Invitation',
        'subject' => 'You\'re Invited to Our Fellowship',
        'message' => 'Hi {name}!

You\'re invited to our fellowship meeting this week! It\'s a great opportunity to connect with other believers, worship together, and grow in faith.

Details:
- When: [Date & Time]
- Where: [Location]
- What to bring: Just yourself and an open heart!

We\'d love to see you there and get to know you better. Let us know if you can make it!

Blessings,'
    ],
    'encouragement' => [
        'name' => 'Encouragement Email',
        'subject' => 'A Word of Encouragement',
        'message' => 'Hello {name}!

We wanted to send you some encouragement today:

"The Lord himself goes before you and will be with you; he will never leave you nor forsake you. Do not be afraid; do not be discouraged." - Deuteronomy 31:8

No matter what you\'re facing today, remember that God is with you, He loves you, and He has a purpose for your life.

You are not alone in this journey!

Blessings,'
    ]
];

// Get Email statistics
$email_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sent,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        COUNT(DISTINCT DATE(sent_at)) as active_days
    FROM email_logs 
    WHERE sender_id = ?
");
$email_stats_stmt->execute([$_SESSION['user_id']]);
$email_stats = $email_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent email logs
$recent_emails_stmt = $pdo->prepare("
    SELECT e.*, c.name as recipient_name 
    FROM email_logs e 
    LEFT JOIN converts c ON e.recipient_email = c.email 
    WHERE e.sender_id = ? 
    ORDER BY e.sent_at DESC 
    LIMIT 10
");
$recent_emails_stmt->execute([$_SESSION['user_id']]);
$recent_emails = $recent_emails_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Follow-up - Evangelism System</title>
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
                        <h1 class="text-2xl font-bold text-gray-900">Digital Follow-up</h1>
                        <p class="text-gray-600 mt-1">Send email messages to converts for follow-up and encouragement</p>
                    </div>
                    <div class="flex items-center space-x-3 mt-4 lg:mt-0">
                        <button onclick="openQuickEmail()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-envelope mr-2"></i>Quick Email
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

            <!-- Email Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-paper-plane text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Sent</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $email_stats['total_sent'] ?? 0; ?></h3>
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
                            <i class="fas fa-calendar-alt text-purple-500 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Days</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $email_stats['active_days'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Email Composer -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Compose Email</h3>

                        <form method="POST" id="emailForm">
                            <!-- Message Type -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Message Type</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="relative flex cursor-pointer">
                                        <input type="radio" name="message_type" value="individual" checked 
                                            class="sr-only peer">
                                        <div class="w-full p-4 border border-gray-300 rounded-xl peer-checked:border-orange-500 peer-checked:bg-orange-50 transition duration-200">
                                            <div class="flex items-center">
                                                <i class="fas fa-user text-orange-500 mr-3"></i>
                                                <div>
                                                    <div class="font-medium text-gray-900">Individual</div>
                                                    <div class="text-sm text-gray-500">Send to specific converts</div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="relative flex cursor-pointer">
                                        <input type="radio" name="message_type" value="bulk" 
                                            class="sr-only peer">
                                        <div class="w-full p-4 border border-gray-300 rounded-xl peer-checked:border-orange-500 peer-checked:bg-orange-50 transition duration-200">
                                            <div class="flex items-center">
                                                <i class="fas fa-users text-orange-500 mr-3"></i>
                                                <div>
                                                    <div class="font-medium text-gray-900">Bulk</div>
                                                    <div class="text-sm text-gray-500">Send to multiple converts</div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Recipients Selection -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-700">Select Recipients</label>
                                    <div class="flex items-center space-x-2">
                                        <button type="button" onclick="selectAllConverts()" 
                                            class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                                            Select All
                                        </button>
                                        <button type="button" onclick="clearSelection()" 
                                            class="text-sm text-gray-500 hover:text-gray-700 font-medium">
                                            Clear
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Recipients List -->
                                <div class="border border-gray-300 rounded-xl max-h-60 overflow-y-auto custom-scrollbar">
                                    <?php if (empty($converts)): ?>
                                        <div class="p-4 text-center text-gray-500">
                                            <i class="fas fa-users text-2xl text-gray-300 mb-2"></i>
                                            <p>No converts found</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($converts as $convert): ?>
                                            <?php if (!empty($convert['email'])): ?>
                                        <label class="flex items-center p-3 border-b border-gray-100 hover:bg-gray-50 transition duration-150 last:border-b-0">
                                            <input type="checkbox" name="recipients[]" 
                                                value="<?php echo $convert['email'] . '|' . $convert['name']; ?>"
                                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-300 rounded convert-checkbox">
                                            <div class="ml-3 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($convert['name']); ?></span>
                                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($convert['email']); ?></span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?php echo htmlspecialchars($convert['hostel']); ?> - Room <?php echo htmlspecialchars($convert['room_number']); ?>
                                                    <?php if ($convert['follow_up_count'] > 0): ?>
                                                        <span class="ml-2 text-blue-500">
                                                            <i class="fas fa-hands-praying mr-1"></i><?php echo $convert['follow_up_count']; ?> follow-ups
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 text-sm text-gray-500" id="selectedCount">
                                    0 recipients selected
                                </div>
                            </div>

                            <!-- Email Template -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-gray-700">Email Template</label>
                                    <button type="button" onclick="showTemplates()" 
                                        class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                                        <i class="fas fa-lightbulb mr-1"></i>Show Templates
                                    </button>
                                </div>
                                <select id="templateSelect" onchange="loadTemplate(this.value)" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200">
                                    <option value="">Choose a template...</option>
                                    <?php foreach ($email_templates as $key => $template): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $template['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Subject -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                <input type="text" name="subject" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                                    placeholder="Enter email subject"
                                    id="emailSubject">
                            </div>

                            <!-- Message Content -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Message Content</label>
                                <textarea name="message" rows="8" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200 resize-none"
                                    placeholder="Type your message here... Use {name} to personalize for each recipient."
                                    oninput="updateCharCount(this)"></textarea>
                                <div class="flex items-center justify-between mt-2 text-sm text-gray-500">
                                    <div>
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <span id="charCount">0</span> characters
                                    </div>
                                    <div id="messageCount" class="text-orange-500 font-medium">
                                        0 emails will be sent
                                    </div>
                                </div>
                            </div>

                            <!-- Send Button -->
                            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-envelope mr-2"></i>
                                    From: <span class="font-semibold text-green-500">evangelism@ezirimkingdom.com.ng</span>
                                </div>
                                <button type="submit" name="send_email" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-8 py-3 rounded-xl font-medium transition duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    id="sendButton">
                                    <i class="fas fa-paper-plane mr-2"></i>Send Emails
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity & Templates -->
                <div class="space-y-6">
                    <!-- Quick Templates -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Templates</h3>
                        <div class="space-y-3">
                            <?php foreach ($email_templates as $key => $template): ?>
                            <button onclick="loadTemplate('<?php echo $key; ?>')" 
                                class="w-full text-left p-3 border border-gray-200 rounded-xl hover:border-orange-300 hover:bg-orange-50 transition duration-200 group">
                                <div class="font-medium text-gray-900 group-hover:text-orange-600"><?php echo $template['name']; ?></div>
                                <div class="text-sm text-gray-500 mt-1 line-clamp-2"><?php echo $template['subject']; ?></div>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Email Activity -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                            <a href="email_logs.php" class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                                View All
                            </a>
                        </div>
                        <div class="space-y-3">
                            <?php if (empty($recent_emails)): ?>
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-envelope-open text-2xl text-gray-300 mb-2"></i>
                                    <p class="text-sm">No recent emails</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_emails as $email): ?>
                                <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-8 h-8 rounded-full bg-<?php echo $email['status'] === 'sent' ? 'green' : 'red'; ?>-100 flex items-center justify-center">
                                            <i class="fas fa-<?php echo $email['status'] === 'sent' ? 'check' : 'times'; ?> text-<?php echo $email['status'] === 'sent' ? 'green' : 'red'; ?>-500 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo $email['recipient_name'] ?: $email['recipient_email']; ?>
                                        </p>
                                        <p class="text-sm text-gray-600 font-medium"><?php echo $email['subject']; ?></p>
                                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo $email['message']; ?></p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?php echo date('M j, g:i A', strtotime($email['sent_at'])); ?>
                                        </p>
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

    <!-- Quick Email Modal -->
    <div id="quickEmailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Quick Email</h3>
                <button onclick="closeQuickEmail()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="quickEmailForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="quickEmail" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter email address">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                        <input type="text" id="quickSubject" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200"
                            placeholder="Enter email subject">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                        <textarea id="quickMessage" rows="4" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition duration-200 resize-none"
                            placeholder="Type your message..."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeQuickEmail()" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition duration-200">
                        Cancel
                    </button>
                    <button type="button" onclick="sendQuickEmail()" 
                        class="px-4 py-2 bg-orange-500 text-white rounded-xl font-medium hover:bg-orange-600 transition duration-200">
                        <i class="fas fa-paper-plane mr-2"></i>Send
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Email Composer Functions
        function updateCharCount(textarea) {
            const charCount = textarea.value.length;
            document.getElementById('charCount').textContent = charCount;
            
            // Update message count
            const selectedCount = document.querySelectorAll('.convert-checkbox:checked').length;
            updateMessageCount(selectedCount);
        }

        function updateMessageCount(count) {
            const messageCount = document.getElementById('messageCount');
            messageCount.textContent = `${count} email${count !== 1 ? 's' : ''} will be sent`;
            
            // Enable/disable send button
            const sendButton = document.getElementById('sendButton');
            sendButton.disabled = count === 0;
        }

        function selectAllConverts() {
            const checkboxes = document.querySelectorAll('.convert-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectionCount();
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.convert-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectionCount();
        }

        function updateSelectionCount() {
            const selectedCount = document.querySelectorAll('.convert-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = `${selectedCount} recipient${selectedCount !== 1 ? 's' : ''} selected`;
            updateMessageCount(selectedCount);
        }

        function loadTemplate(templateKey) {
            const templates = <?php echo json_encode($email_templates); ?>;
            if (templates[templateKey]) {
                document.querySelector('textarea[name="message"]').value = templates[templateKey].message;
                document.getElementById('emailSubject').value = templates[templateKey].subject;
                updateCharCount(document.querySelector('textarea[name="message"]'));
            }
        }

        function showTemplates() {
            // Could implement a modal with template previews
            alert('Template library feature coming soon!');
        }

        // Quick Email Modal Functions
        function openQuickEmail() {
            document.getElementById('quickEmailModal').classList.remove('hidden');
        }

        function closeQuickEmail() {
            document.getElementById('quickEmailModal').classList.add('hidden');
        }

        function sendQuickEmail() {
            const email = document.getElementById('quickEmail').value;
            const subject = document.getElementById('quickSubject').value;
            const message = document.getElementById('quickMessage').value;
            
            if (!email || !subject || !message) {
                alert('Please fill in all fields.');
                return;
            }
            
            // Here you would typically make an AJAX call to send the email
            alert(`Email to ${email}:\n\nSubject: ${subject}\n\n${message}\n\n(This would send via Email API)`);
            closeQuickEmail();
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update selection count when checkboxes change
            document.querySelectorAll('.convert-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectionCount);
            });
            
            // Initialize counts
            updateSelectionCount();
            updateCharCount(document.querySelector('textarea[name="message"]'));
        });

        // Auto-close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQuickEmail();
            }
        });
    </script>

    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Custom scrollbar for recipients list */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</body>
</html>