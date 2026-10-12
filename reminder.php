<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Role gate - Only super_admin and coordinator can access
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['super_admin', 'coordinator'])) {
    header("Location: dashboard.php");
    exit();
}

// Get workers with pending/in-progress follow-ups - SIMPLIFIED QUERY
function get_workers_needing_followup() {
    global $pdo;
    
    $sql = "SELECT DISTINCT
                u.id as worker_id,
                u.name as worker_name,
                u.email as worker_email,
                u.phone as worker_phone,
                u.department,
                (SELECT COUNT(*) FROM convert_assignments ca2 WHERE ca2.worker_id = u.id AND ca2.followup_status = 'pending') as pending_count,
                (SELECT COUNT(*) FROM convert_assignments ca2 WHERE ca2.worker_id = u.id AND ca2.followup_status = 'in-progress') as in_progress_count,
                (SELECT COUNT(*) FROM convert_assignments ca2 WHERE ca2.worker_id = u.id) as total_assignments,
                (SELECT MAX(updated_at) FROM convert_assignments ca2 WHERE ca2.worker_id = u.id) as last_followup,
                (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') 
                 FROM convert_assignments ca3 
                 JOIN converts c ON ca3.convert_id = c.id 
                 WHERE ca3.worker_id = u.id AND (ca3.followup_status = 'pending' OR ca3.followup_status = 'in-progress')
                ) as convert_names
            FROM users u 
            JOIN convert_assignments ca ON u.id = ca.worker_id 
            WHERE u.role = 'worker' 
            AND u.is_active = 1 
            AND (ca.followup_status = 'pending' OR ca.followup_status = 'in-progress')
            ORDER BY (pending_count + in_progress_count) DESC, last_followup ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting workers needing followup: " . $e->getMessage());
        return [];
    }
}

$workers_needing_followup = get_workers_needing_followup();

// Handle reminder sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    $worker_id = $_POST['worker_id'];
    $worker_name = $_POST['worker_name'];
    $worker_email = $_POST['worker_email'];
    $pending_count = $_POST['pending_count'];
    $in_progress_count = $_POST['in_progress_count'];
    $convert_names = $_POST['convert_names'];
    
    $subject = "Follow-up Reminder - AUC Evangelism System";
    
    // Create beautiful HTML email
    $message = create_beautiful_email($worker_name, $pending_count, $in_progress_count, $convert_names);
    
    // Send email using the same method as email.php
    $email_sent = send_reminder_email($worker_email, $subject, $message, $worker_name);
    
    if ($email_sent) {
        $_SESSION['success'] = "📧 Reminder sent successfully to {$worker_name}!";
        
        // Log activity
        log_activity($_SESSION['user_id'], 'followup_reminder_sent', 
            "Sent follow-up reminder to {$worker_name}");
    } else {
        $_SESSION['error'] = "❌ Failed to send reminder to {$worker_name}. Please check email configuration.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Function to send reminder email - USING THE SAME METHOD AS email.php
function send_reminder_email($to, $subject, $message, $name = '') {
    try {
        // Use the same PHPMailer inclusion as email.php
        require_once 'phpmailer/PHPMailer.php';
        require_once 'phpmailer/SMTP.php';
        require_once 'phpmailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings - EXACTLY THE SAME AS email.php
        $mail->isSMTP();
        $mail->Host = 'smtp.ezirimkingdom.com.ng';
        $mail->SMTPAuth = true;
        $mail->Username = 'evangelism@ezirimkingdom.com.ng';
        $mail->Password = 'Tu9b8dced';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Recipients
        $mail->setFrom('evangelism@ezirimkingdom.com.ng', 'Evangelism System');
        $mail->addAddress($to, $name);
        $mail->addReplyTo('evangelism@ezirimkingdom.com.ng', 'Evangelism System');
        
        // Content - HTML email for reminders
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message); // Plain text version
        
        // Send email
        $mail->send();
        
        // Log successful email
        log_email($_SESSION['user_id'], $to, $subject, strip_tags($message), 'sent');
        return true;
        
    } catch (Exception $e) {
        // Log failed email
        log_email($_SESSION['user_id'], $to, $subject, strip_tags($message), 'failed');
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

// Function to create beautiful email template
function create_beautiful_email($worker_name, $pending_count, $in_progress_count, $convert_names) {
    $total_needing_followup = $pending_count + $in_progress_count;
    $base_url = "https://evangelism.ezirimkingdom.com.ng";
    
    // Use text-based icons instead of emojis to avoid encoding issues
    $icons = [
        'mission' => '🎯',
        'pending' => '📋',
        'progress' => '🔄',
        'converts' => '📋',
        'rocket' => '🚀',
        'star' => '★'
    ];
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Follow-up Reminder - AUC Evangelism System</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
            body { 
                font-family: 'Inter', Arial, sans-serif; 
                line-height: 1.6; 
                color: #374151; 
                background: #f9fafb;
                margin: 0; 
                padding: 20px;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: white; 
                border-radius: 16px; 
                overflow: hidden; 
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            }
            .header { 
                background: linear-gradient(135deg, #ea580c, #dc2626);
                color: white; 
                padding: 30px; 
                text-align: center;
                position: relative;
            }
            .logo-section {
                margin-bottom: 20px;
            }
            .logo {
                max-width: 120px;
                height: auto;
                margin: 0 auto 15px;
                display: block;
                border-radius: 8px;
            }
            .content { 
                padding: 40px 30px; 
            }
            .stats-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 20px; 
                margin: 30px 0;
            }
            .stat-card { 
                background: #f8fafc; 
                padding: 25px 20px; 
                border-radius: 12px; 
                text-align: center;
                border: 2px solid #e2e8f0;
            }
            .converts-section { 
                background: #fef7ed; 
                border: 2px solid #fed7aa; 
                border-radius: 12px; 
                padding: 25px; 
                margin: 30px 0;
            }
            .button { 
                display: inline-block; 
                background: linear-gradient(135deg, #ea580c, #dc2626);
                color: white; 
                padding: 16px 40px; 
                text-decoration: none; 
                border-radius: 12px; 
                font-weight: 600; 
                font-size: 16px;
                margin: 20px 0;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(234, 88, 12, 0.3);
            }
            .verse { 
                font-style: italic; 
                color: #7c2d12; 
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                padding: 25px; 
                border-radius: 12px; 
                margin: 30px 0;
                text-align: center;
                border-left: 5px solid #d97706;
            }
            .footer { 
                background: #1f2937; 
                padding: 30px; 
                text-align: center; 
                color: #d1d5db; 
                font-size: 14px;
            }
            .mission-highlight {
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                padding: 25px;
                border-radius: 12px;
                text-align: center;
                margin: 25px 0;
                border: 2px solid #f59e0b;
            }
            .icon-text {
                display: inline-block;
                margin-right: 8px;
                font-size: 18px;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo-section'>
                    <!-- AUC Logo -->
                    <img src='{$base_url}/auc.png' alt='AUC Evangelism' class='logo' onerror=\"this.style.display='none'\">
                    <div>
                        <h1 style='margin: 10px 0 5px 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;'>Follow-up Reminder</h1>
                        <p style='margin: 0; opacity: 0.9; font-size: 16px; font-weight: 400;'>AUC Evangelism System</p>
                    </div>
                </div>
            </div>
            
            <div class='content'>
                <h2 style='color: #1f2937; margin-bottom: 15px; font-size: 24px;'>Dear {$worker_name},</h2>
                <p style='color: #6b7280; margin-bottom: 10px; font-size: 16px;'>God has entrusted you with precious souls that need your spiritual care and follow-up.</p>
                
                <div class='mission-highlight'>
                    <h3 style='color: #92400e; margin: 0 0 15px 0; font-size: 20px;'>
                        <span class='icon-text'>🎯</span> Your Follow-up Mission
                    </h3>
                    <p style='color: #92400e; margin: 0; font-size: 16px; font-weight: 600;'>You have <strong>{$total_needing_followup} convert(s)</strong> waiting for your attention</p>
                </div>

                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div style='font-size: 36px; color: #ea580c; font-weight: bold;'>{$pending_count}</div>
                        <div style='color: #6b7280; font-size: 16px; font-weight: 600;'>
                            <span class='icon-text'>📋</span> Pending Follow-ups
                        </div>
                        <div style='color: #9ca3af; font-size: 14px; margin-top: 8px;'>Need initial contact</div>
                    </div>
                    <div class='stat-card'>
                        <div style='font-size: 36px; color: #3b82f6; font-weight: bold;'>{$in_progress_count}</div>
                        <div style='color: #6b7280; font-size: 16px; font-weight: 600;'>
                            <span class='icon-text'>🔄</span> In Progress
                        </div>
                        <div style='color: #9ca3af; font-size: 14px; margin-top: 8px;'>Need continued support</div>
                    </div>
                </div>

                <div class='converts-section'>
                    <h3 style='color: #92400e; margin-top: 0; font-size: 20px;'>
                        <span class='icon-text'>📋</span> Converts Assigned to You:
                    </h3>
                    <p style='color: #78350f; background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #ea580c; font-size: 15px; line-height: 1.5; margin: 0;'>{$convert_names}</p>
                </div>

                <div class='verse'>
                    <strong style='font-size: 18px;'><span class='icon-text'>🎯</span> Matthew 28:19-20</strong><br>
                    <span style='font-size: 16px;'>&ldquo;Go therefore and make disciples of all nations, baptizing them in the name of the Father and of the Son and of the Holy Spirit, teaching them to observe all that I have commanded you.&rdquo;</span>
                </div>

                <p style='color: #374151; font-size: 16px; line-height: 1.6;'>
                    <strong><span class='icon-text'>★</span> Your Mission:</strong> Reach out, build relationships, and provide spiritual guidance to these precious souls. Your follow-up could make an eternal difference in someone's life.
                </p>
                
                <div style='text-align: center;'>
                    <a href='{$base_url}/worker_assigned_converts.php' class='button'>
                        <span class='icon-text'>🚀</span> Update Follow-up Status
                    </a>
                </div>

                <p style='color: #6b7280; font-size: 14px; text-align: center; margin-top: 25px;'>
                    <em>Every soul matters in God's kingdom. Your dedication in this ministry is greatly appreciated!</em>
                </p>
            </div>
            
            <div class='footer'>
                <p style='margin: 0 0 15px; font-size: 16px;'>
                    <strong style='color: white;'>AUC Evangelism Team</strong><br>
                    <span style='color: #d1d5db;'>Anchored in Christ, Reaching the World</span>
                </p>
                <p style='margin: 0; font-size: 12px; color: #9ca3af;'>
                    This is an automated reminder. Please do not reply to this email.<br>
                    Need help? Contact your coordinator or supervisor.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Page Wrapper -->
<div class="lg:ml-64 p-6 bg-gray-50 min-h-screen">
  <!-- Header Section -->
  <div class="max-w-7xl mx-auto mb-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell text-orange-500"></i>
          Follow-up Reminders
        </h1>
        <p class="text-gray-600 mt-1">Send reminders to workers for pending and in-progress follow-ups</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="searchWorkers" type="text" placeholder="Search workers…"
                 class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none">
        </div>
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Workers Needing Follow-up</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= count($workers_needing_followup) ?></p>
          </div>
          <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-users text-orange-500"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Pending</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= array_sum(array_column($workers_needing_followup, 'pending_count')) ?>
            </p>
          </div>
          <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-clock text-yellow-500"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total In Progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= array_sum(array_column($workers_needing_followup, 'in_progress_count')) ?>
            </p>
          </div>
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-spinner text-blue-500"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Assignments</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= array_sum(array_column($workers_needing_followup, 'total_assignments')) ?>
            </p>
          </div>
          <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-list-check text-green-500"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Workers Table -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-list text-orange-500"></i>
          Workers Needing Follow-up Reminders
        </h2>
        <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
          <?= count($workers_needing_followup) ?> worker<?= count($workers_needing_followup) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <!-- Alert Messages -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="mx-5 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center">
          <i class="fa-solid fa-check-circle text-green-500 text-lg mr-3"></i>
          <span class="text-green-700 font-medium"><?= $_SESSION['success'] ?></span>
          <?php unset($_SESSION['success']); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
        <div class="mx-5 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center">
          <i class="fa-solid fa-exclamation-triangle text-red-500 text-lg mr-3"></i>
          <span class="text-red-700 font-medium"><?= $_SESSION['error'] ?></span>
          <?php unset($_SESSION['error']); ?>
        </div>
      <?php endif; ?>

      <div class="overflow-x-auto">
        <table id="tableWorkers" class="min-w-full text-sm">
          <thead>
            <tr class="bg-orange-50 text-gray-700">
              <th class="p-3 text-left">Worker Name</th>
              <th class="p-3 text-left">Contact</th>
              <th class="p-3 text-left">Department</th>
              <th class="p-3 text-left">Pending</th>
              <th class="p-3 text-left">In Progress</th>
              <th class="p-3 text-left">Total</th>
              <th class="p-3 text-left">Last Follow-up</th>
              <th class="p-3 text-left">Converts</th>
              <th class="p-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if (empty($workers_needing_followup)): ?>
              <tr>
                <td colspan="9" class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-check-circle text-green-400 text-3xl mb-3"></i>
                  <p class="font-medium text-gray-600">All caught up!</p>
                  <p class="text-sm mt-1">No workers need follow-up reminders at this time.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($workers_needing_followup as $worker): ?>
                <?php
                  // Safe date handling
                  $last_followup = 'Never';
                  if (!empty($worker['last_followup']) && $worker['last_followup'] !== '0000-00-00 00:00:00') {
                      try {
                          $last_followup = date('M j, Y', strtotime($worker['last_followup']));
                      } catch (Exception $e) {
                          $last_followup = 'Invalid date';
                      }
                  }
                  
                  // Truncate convert names if too long
                  $convert_names = $worker['convert_names'] ?? 'No converts listed';
                  if (strlen($convert_names) > 50) {
                      $convert_names = substr($convert_names, 0, 50) . '...';
                  }
                  
                  // Ensure counts are integers
                  $pending_count = (int)($worker['pending_count'] ?? 0);
                  $in_progress_count = (int)($worker['in_progress_count'] ?? 0);
                  $total_assignments = (int)($worker['total_assignments'] ?? 0);
                ?>
                <tr class="hover:bg-gray-50">
                  <td class="p-3 font-medium">
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-user text-orange-500 text-sm"></i>
                      </div>
                      <?= htmlspecialchars($worker['worker_name']) ?>
                    </div>
                  </td>
                  <td class="p-3">
                    <div class="space-y-1">
                      <div class="flex items-center gap-1 text-xs">
                        <i class="fa-solid fa-envelope text-gray-400"></i>
                        <span class="text-gray-600"><?= htmlspecialchars($worker['worker_email']) ?></span>
                      </div>
                      <?php if (!empty($worker['worker_phone'])): ?>
                        <div class="flex items-center gap-1 text-xs">
                          <i class="fa-solid fa-phone text-gray-400"></i>
                          <span class="text-gray-600"><?= htmlspecialchars($worker['worker_phone']) ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="p-3"><?= htmlspecialchars($worker['department'] ?? 'Not specified') ?></td>
                  <td class="p-3">
                    <?php if ($pending_count > 0): ?>
                      <span class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-700 rounded-full text-xs font-medium">
                        <?= $pending_count ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="p-3">
                    <?php if ($in_progress_count > 0): ?>
                      <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                        <?= $in_progress_count ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="p-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                      <?= $total_assignments ?>
                    </span>
                  </td>
                  <td class="p-3 text-gray-500 text-xs">
                    <?= $last_followup ?>
                  </td>
                  <td class="p-3 text-xs text-gray-600 max-w-xs">
                    <div class="line-clamp-2" title="<?= htmlspecialchars($worker['convert_names'] ?? '') ?>">
                      <?= htmlspecialchars($convert_names) ?>
                    </div>
                  </td>
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <?php if ($pending_count > 0 || $in_progress_count > 0): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="worker_id" value="<?= $worker['worker_id'] ?>">
                          <input type="hidden" name="worker_name" value="<?= htmlspecialchars($worker['worker_name']) ?>">
                          <input type="hidden" name="worker_email" value="<?= htmlspecialchars($worker['worker_email']) ?>">
                          <input type="hidden" name="pending_count" value="<?= $pending_count ?>">
                          <input type="hidden" name="in_progress_count" value="<?= $in_progress_count ?>">
                          <input type="hidden" name="convert_names" value="<?= htmlspecialchars($worker['convert_names'] ?? '') ?>">
                          <button type="submit" name="send_reminder" 
                                  class="bg-orange-500 hover:bg-orange-600 text-white text-sm flex items-center gap-2 px-4 py-2 rounded-lg transition-colors shadow-sm hover:shadow-md"
                                  onclick="return confirm('Send follow-up reminder to <?= htmlspecialchars($worker['worker_name']) ?>?')">
                            <i class="fa-solid fa-paper-plane"></i>
                            Send Reminder
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="workersCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="workers" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <span id="workersPage" class="text-sm text-gray-600">1</span>
          <button data-pager="workers" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  console.log("Follow-up reminders scripts initialized ✅");

  // ===== Search functionality
  const searchInput = document.getElementById('searchWorkers');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase();
      const rows = document.querySelectorAll('#tableWorkers tbody tr');
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
      
      // Update pagination after search
      setupPagination();
    });
  }

  // ===== Pagination functionality
  function setupPagination() {
    const table = document.getElementById('tableWorkers');
    if (!table) return;
    
    const pageSpan = document.getElementById('workersPage');
    const countSpan = document.getElementById('workersCount');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const visibleRows = rows.filter(row => row.style.display !== 'none');
    const totalRows = visibleRows.length;
    const rowsPerPage = 8;
    let currentPage = 1;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    
    function showPage(page) {
      const start = (page - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      
      visibleRows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
      });
      
      if (pageSpan) pageSpan.textContent = page;
      if (countSpan) {
        const showingStart = Math.min(start + 1, totalRows);
        const showingEnd = Math.min(end, totalRows);
        countSpan.textContent = `Showing ${showingStart}-${showingEnd} of ${totalRows} workers`;
      }
    }
    
    // Show first page initially
    showPage(1);
    
    // Set up pagination buttons
    document.querySelectorAll('.pager-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const direction = btn.dataset.dir;
        if (direction === 'next' && currentPage < totalPages) {
          currentPage++;
          showPage(currentPage);
        } else if (direction === 'prev' && currentPage > 1) {
          currentPage--;
          showPage(currentPage);
        }
      });
    });
  }
  
  setupPagination();
});

// Add CSS for line clamping
const style = document.createElement('style');
style.textContent = `
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
`;
document.head.appendChild(style);
</script>

<?php include 'footer.php'; ?>