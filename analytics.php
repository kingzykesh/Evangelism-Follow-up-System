<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Role gate
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['super_admin', 'coordinator'])) {
    header("Location: dashboard.php");
    exit();
}

// Get unassigned converts
$unassigned_converts = get_unassigned_converts();

// Get workers with no assignments
$unassigned_workers = get_workers_with_no_assignments();

// Get worker assignment statistics
$worker_stats = get_worker_assignment_stats();

// Get all workers for the modal
$all_workers = get_workers();

// Helpers
function safe($v) { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function getGenderIcon($gender) {
    return $gender === 'male' ? 'mars' : ($gender === 'female' ? 'venus' : 'genderless');
}
function getGenderColor($gender) {
    return $gender === 'male' ? 'text-blue-500' : ($gender === 'female' ? 'text-pink-500' : 'text-gray-500');
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Page Wrapper -->
<div class="lg:ml-64 p-6 pt-20 bg-gray-50 min-h-screen">

  <!-- Topbar / Title -->
  <div class="max-w-7xl mx-auto mb-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Assignment Management</h1>
        <p class="text-gray-600 mt-1">Track unassigned converts and worker assignments</p>
      </div>
      <div class="flex items-center gap-3">
        <a href="coordinator_dashboard.php" 
           class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto space-y-8">

    <!-- ============ Summary Cards ============ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Unassigned Converts Card -->
      <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Unassigned Converts</p>
            <p class="text-3xl font-bold text-gray-900 mt-2"><?= count($unassigned_converts) ?></p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-users text-blue-500 text-xl"></i>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">Converts waiting for worker assignment</p>
      </div>

      <!-- Unassigned Workers Card -->
      <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Available Workers</p>
            <p class="text-3xl font-bold text-gray-900 mt-2"><?= count($unassigned_workers) ?></p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-user-check text-green-500 text-xl"></i>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">Workers with no current assignments</p>
      </div>

      <!-- Total Workers Card -->
      <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Workers</p>
            <p class="text-3xl font-bold text-gray-900 mt-2"><?= count($worker_stats) ?></p>
          </div>
          <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-people-group text-orange-500 text-xl"></i>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">All active workers in system</p>
      </div>
    </div>

    <!-- ============ Unassigned Converts ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i>
          Unassigned Converts
          <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?= count($unassigned_converts) ?></span>
        </h2>
        <div class="flex items-center gap-3">
          <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input id="searchUnassignedConverts" type="text" placeholder="Search unassigned converts…"
                   class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-blue-400 focus:ring-0 outline-none">
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableUnassignedConverts" class="min-w-full text-sm">
          <thead>
            <tr class="bg-blue-50 text-gray-700">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Phone</th>
              <th class="p-3 text-left">Gender</th>
              <th class="p-3 text-left">Hostel & Room</th>
              <th class="p-3 text-left">Status</th>
              <th class="p-3 text-left">Date Added</th>
              <th class="p-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if (empty($unassigned_converts)): ?>
              <tr>
                <td colspan="7" class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-check-circle text-green-400 text-2xl mb-2"></i>
                  <p class="font-medium">All converts have been assigned!</p>
                  <p class="text-sm mt-1">No unassigned converts found in the system.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($unassigned_converts as $c): ?>
                <?php
                  $hostel_display = '';
                  if (!empty($c['hostel']) && !empty($c['room_number'])) {
                      $hostel_display = safe($c['hostel']) . ' - Room ' . safe($c['room_number']);
                  } elseif (!empty($c['hostel'])) {
                      $hostel_display = safe($c['hostel']);
                  } elseif (!empty($c['room_number'])) {
                      $hostel_display = 'Room ' . safe($c['room_number']);
                  } else {
                      $hostel_display = '<span class="text-gray-400 italic">Not specified</span>';
                  }
                ?>
                <tr class="hover:bg-gray-50">
                  <td class="p-3 font-medium"><?= safe($c['name']) ?></td>
                  <td class="p-3"><?= safe($c['phone_number']) ?></td>
                  <td class="p-3">
                    <?php if (!empty($c['gender'])): ?>
                      <div class="flex items-center gap-1">
                        <i class="fa-solid fa-<?= getGenderIcon($c['gender']) ?> <?= getGenderColor($c['gender']) ?>"></i>
                        <span class="text-gray-700"><?= ucfirst($c['gender']) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 italic">Not set</span>
                    <?php endif; ?>
                  </td>
                  <td class="p-3"><?= $hostel_display ?></td>
                  <td class="p-3">
                    <?php $badge = ($c['status'] === 'converted') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>
                    <span class="px-2 py-1 rounded text-xs <?= $badge ?>"><?= ucfirst(safe($c['status'])) ?></span>
                  </td>
                  <td class="p-3 text-gray-500">
                    <?= $c['date_submitted'] ? date('M j, Y', strtotime($c['date_submitted'])) : '—' ?>
                  </td>
                  <td class="p-3">
                    <button 
                      data-convert-id="<?= $c['id'] ?>"
                      data-convert-name="<?= safe($c['name']) ?>"
                      data-convert-phone="<?= safe($c['phone_number']) ?>"
                      data-convert-gender="<?= safe($c['gender'] ?? '') ?>"
                      data-convert-hostel="<?= safe($c['hostel'] ?? '') ?>"
                      data-convert-room="<?= safe($c['room_number'] ?? '') ?>"
                      data-convert-status="<?= safe($c['status'] ?? '') ?>"
                      class="open-assign-modal text-blue-600 hover:text-blue-700 text-sm flex items-center gap-2">
                      <i class="fa-solid fa-user-plus"></i> Assign
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="unassignedConvertsCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="unassignedConverts" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="unassignedConvertsPage" class="text-sm text-gray-600">1</span>
          <button data-pager="unassignedConverts" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

    <!-- ============ Available Workers ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-check text-green-500"></i>
          Available Workers (No Assignments)
          <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"><?= count($unassigned_workers) ?></span>
        </h2>
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="searchAvailableWorkers" type="text" placeholder="Search available workers…"
                 class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-green-400 focus:ring-0 outline-none">
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableAvailableWorkers" class="min-w-full text-sm">
          <thead>
            <tr class="bg-green-50 text-gray-700">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Phone</th>
              <th class="p-3 text-left">Gender</th>
              <th class="p-3 text-left">Hostel & Room</th>
              <th class="p-3 text-left">Department</th>
              <th class="p-3 text-left">Level</th>
              <th class="p-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if (empty($unassigned_workers)): ?>
              <tr>
                <td colspan="7" class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-user-clock text-orange-400 text-2xl mb-2"></i>
                  <p class="font-medium">All workers have assignments!</p>
                  <p class="text-sm mt-1">No available workers without assignments.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($unassigned_workers as $w): ?>
                <?php
                  $hostel_display = '';
                  if (!empty($w['hostel']) && !empty($w['room_number'])) {
                      $hostel_display = safe($w['hostel']) . ' - Room ' . safe($w['room_number']);
                  } elseif (!empty($w['hostel'])) {
                      $hostel_display = safe($w['hostel']);
                  } elseif (!empty($w['room_number'])) {
                      $hostel_display = 'Room ' . safe($w['room_number']);
                  } else {
                      $hostel_display = '<span class="text-gray-400 italic">Not specified</span>';
                  }
                ?>
                <tr class="hover:bg-gray-50">
                  <td class="p-3 font-medium"><?= safe($w['name']) ?></td>
                  <td class="p-3"><?= safe($w['phone']) ?></td>
                  <td class="p-3">
                    <?php if (!empty($w['gender'])): ?>
                      <div class="flex items-center gap-1">
                        <i class="fa-solid fa-<?= getGenderIcon($w['gender']) ?> <?= getGenderColor($w['gender']) ?>"></i>
                        <span class="text-gray-700"><?= ucfirst($w['gender']) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 italic">Not set</span>
                    <?php endif; ?>
                  </td>
                  <td class="p-3"><?= $hostel_display ?></td>
                  <td class="p-3"><?= safe($w['department']) ?></td>
                  <td class="p-3"><?= safe($w['level']) ?></td>
                  <td class="p-3">
                    <button 
                      data-worker-id="<?= $w['id'] ?>"
                      data-worker-name="<?= safe($w['name']) ?>"
                      data-worker-phone="<?= safe($w['phone']) ?>"
                      data-worker-gender="<?= safe($w['gender'] ?? '') ?>"
                      data-worker-hostel="<?= safe($w['hostel'] ?? '') ?>"
                      data-worker-room="<?= safe($w['room_number'] ?? '') ?>"
                      data-worker-department="<?= safe($w['department'] ?? '') ?>"
                      data-worker-level="<?= safe($w['level'] ?? '') ?>"
                      class="open-assign-modal text-green-600 hover:text-green-700 text-sm flex items-center gap-2">
                      <i class="fa-solid fa-user-plus"></i> Assign Convert
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="availableWorkersCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="availableWorkers" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="availableWorkersPage" class="text-sm text-gray-600">1</span>
          <button data-pager="availableWorkers" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

    <!-- ============ Worker Assignment Statistics ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-orange-500"></i>
          Worker Assignment Statistics
        </h2>
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="searchWorkerStats" type="text" placeholder="Search workers…"
                 class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none">
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableWorkerStats" class="min-w-full text-sm">
          <thead>
            <tr class="bg-orange-50 text-gray-700">
              <th class="p-3 text-left">Worker Name</th>
              <th class="p-3 text-left">Department</th>
              <th class="p-3 text-left">Total Assigned</th>
              <th class="p-3 text-left">Completed</th>
              <th class="p-3 text-left">In Progress</th>
              <th class="p-3 text-left">Pending</th>
              <th class="p-3 text-left">Last Assignment</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($worker_stats as $stat): ?>
              <tr class="hover:bg-gray-50">
                <td class="p-3 font-medium"><?= safe($stat['worker_name']) ?></td>
                <td class="p-3"><?= safe($stat['department']) ?></td>
                <td class="p-3">
                  <span class="inline-flex items-center justify-center w-8 h-8 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">
                    <?= $stat['total_assignments'] ?>
                  </span>
                </td>
                <td class="p-3">
                  <span class="inline-flex items-center justify-center w-8 h-8 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                    <?= $stat['completed_count'] ?>
                  </span>
                </td>
                <td class="p-3">
                  <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                    <?= $stat['in_progress_count'] ?>
                  </span>
                </td>
                <td class="p-3">
                  <span class="inline-flex items-center justify-center w-8 h-8 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">
                    <?= $stat['pending_count'] ?>
                  </span>
                </td>
                <td class="p-3 text-gray-500 text-xs">
                  <?= $stat['last_assignment'] ? date('M j, Y', strtotime($stat['last_assignment'])) : 'No assignments' ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="workerStatsCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="workerStats" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="workerStatsPage" class="text-sm text-gray-600">1</span>
          <button data-pager="workerStats" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

  </div>
</div>

<!-- ========== Modal: Assign Convert ========== -->
<div id="assignModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4">
  <div class="bg-white rounded-2xl w-full max-w-2xl shadow-xl">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-user-plus text-orange-500"></i> Assign Convert to Worker
      </h3>
      <button id="closeAssignModal" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <form action="process_assign.php" method="POST" class="px-6 py-5 space-y-5">
      <!-- Convert Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Convert</label>
        <div class="relative">
          <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="filterConvert" type="text" placeholder="Type to filter converts…" class="pl-9 pr-3 py-2 w-full rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none mb-2">
        </div>
        <select name="convert_id" id="selectConvert" required class="w-full border rounded-lg p-2 bg-white">
          <option value="">— Select Convert —</option>
          <?php foreach ($unassigned_converts as $c): ?>
            <?php
              $convert_details = safe($c['name']) . ' — ' . safe($c['phone_number']);
              if (!empty($c['gender'])) {
                  $convert_details .= ' • ' . ucfirst($c['gender']);
              }
              if (!empty($c['hostel']) || !empty($c['room_number'])) {
                  $hostel_info = '';
                  if (!empty($c['hostel']) && !empty($c['room_number'])) {
                      $hostel_info = safe($c['hostel']) . ' - Room ' . safe($c['room_number']);
                  } elseif (!empty($c['hostel'])) {
                      $hostel_info = safe($c['hostel']);
                  } else {
                      $hostel_info = 'Room ' . safe($c['room_number']);
                  }
                  $convert_details .= ' • ' . $hostel_info;
              }
            ?>
            <option value="<?= (int)$c['id'] ?>" data-gender="<?= safe($c['gender'] ?? '') ?>" data-hostel="<?= safe($c['hostel'] ?? '') ?>" data-room="<?= safe($c['room_number'] ?? '') ?>" data-status="<?= safe($c['status'] ?? '') ?>">
              <?= $convert_details ?>
            </option>
          <?php endforeach; ?>
        </select>
        
        <!-- Convert Details Preview -->
        <div id="convertDetails" class="mt-3 p-3 bg-blue-50 rounded-lg hidden">
          <h4 class="font-medium text-gray-800 mb-2 flex items-center gap-2">
            <i class="fa-solid fa-user text-blue-500"></i> Convert Details
          </h4>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-600">Gender:</span> <span id="convertGender" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Hostel:</span> <span id="convertHostel" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Room:</span> <span id="convertRoom" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Status:</span> <span id="convertStatus" class="font-medium">—</span></div>
          </div>
        </div>
      </div>

      <!-- Worker Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Worker</label>
        <div class="relative">
          <i class="fa-solid fa-id-badge absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="filterWorker" type="text" placeholder="Type to filter workers…" class="pl-9 pr-3 py-2 w-full rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none mb-2">
        </div>
        <select name="worker_id" id="selectWorker" required class="w-full border rounded-lg p-2 bg-white">
          <option value="">— Select Worker —</option>
          <!-- First show unassigned workers -->
          <optgroup label="Available Workers (No Assignments)">
            <?php foreach ($unassigned_workers as $w): ?>
              <?php
                $worker_details = safe($w['name']) . ' — ' . safe($w['department']);
                if (!empty($w['gender'])) {
                    $worker_details .= ' • ' . ucfirst($w['gender']);
                }
                if (!empty($w['hostel']) || !empty($w['room_number'])) {
                    $hostel_info = '';
                    if (!empty($w['hostel']) && !empty($w['room_number'])) {
                        $hostel_info = safe($w['hostel']) . ' - Room ' . safe($w['room_number']);
                    } elseif (!empty($w['hostel'])) {
                        $hostel_info = safe($w['hostel']);
                    } else {
                        $hostel_info = 'Room ' . safe($w['room_number']);
                    }
                    $worker_details .= ' • ' . $hostel_info;
                }
              ?>
              <option value="<?= (int)$w['id'] ?>" data-gender="<?= safe($w['gender'] ?? '') ?>" data-hostel="<?= safe($w['hostel'] ?? '') ?>" data-room="<?= safe($w['room_number'] ?? '') ?>" data-phone="<?= safe($w['phone'] ?? '') ?>" data-available="true">
                <?= $worker_details ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
          
          <!-- Then show other workers -->
          <optgroup label="Other Workers">
            <?php 
            $assigned_workers_ids = array_column($unassigned_workers, 'id');
            foreach ($all_workers as $w): 
              if (!in_array($w['id'], $assigned_workers_ids)):
            ?>
              <?php
                $worker_details = safe($w['name']) . ' — ' . safe($w['department']);
                if (!empty($w['gender'])) {
                    $worker_details .= ' • ' . ucfirst($w['gender']);
                }
                if (!empty($w['hostel']) || !empty($w['room_number'])) {
                    $hostel_info = '';
                    if (!empty($w['hostel']) && !empty($w['room_number'])) {
                        $hostel_info = safe($w['hostel']) . ' - Room ' . safe($w['room_number']);
                    } elseif (!empty($w['hostel'])) {
                        $hostel_info = safe($w['hostel']);
                    } else {
                        $hostel_info = 'Room ' . safe($w['room_number']);
                    }
                    $worker_details .= ' • ' . $hostel_info;
                }
              ?>
              <option value="<?= (int)$w['id'] ?>" data-gender="<?= safe($w['gender'] ?? '') ?>" data-hostel="<?= safe($w['hostel'] ?? '') ?>" data-room="<?= safe($w['room_number'] ?? '') ?>" data-phone="<?= safe($w['phone'] ?? '') ?>" data-available="false">
                <?= $worker_details ?>
              </option>
            <?php 
              endif;
            endforeach; 
            ?>
          </optgroup>
        </select>
        
        <!-- Worker Details Preview -->
        <div id="workerDetails" class="mt-3 p-3 bg-green-50 rounded-lg hidden">
          <h4 class="font-medium text-gray-800 mb-2 flex items-center gap-2">
            <i class="fa-solid fa-id-badge text-green-500"></i> Worker Details
          </h4>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-600">Gender:</span> <span id="workerGender" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Hostel:</span> <span id="workerHostel" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Room:</span> <span id="workerRoom" class="font-medium">—</span></div>
            <div><span class="text-gray-600">Phone:</span> <span id="workerPhone" class="font-medium">—</span></div>
          </div>
        </div>
      </div>

      <!-- Compatibility Notice -->
      <div id="compatibilityNotice" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
        <div class="flex items-start gap-3">
          <i class="fa-solid fa-lightbulb text-yellow-500 mt-0.5"></i>
          <div>
            <p class="text-sm font-medium text-yellow-800">Matching Suggestions</p>
            <p id="compatibilityText" class="text-xs text-yellow-700 mt-1"></p>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button type="button" id="cancelAssign" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm">
          <i class="fa-solid fa-paper-plane mr-1"></i> Assign
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  console.log("Assignment management scripts initialized ✅");

  // ===== Search functionality for all tables
  function makeSearch(tableId, searchId) {
    const table = document.getElementById(tableId);
    const search = document.getElementById(searchId);
    const rows = Array.from(table?.querySelectorAll('tbody tr') || []);
    
    if (!search || !table) return;
    
    search.addEventListener('input', () => {
      const term = search.value.toLowerCase();
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
      });
    });
  }

  // Initialize search for all tables
  makeSearch('tableUnassignedConverts', 'searchUnassignedConverts');
  makeSearch('tableAvailableWorkers', 'searchAvailableWorkers');
  makeSearch('tableWorkerStats', 'searchWorkerStats');

  // ===== Assign Modal handlers
  const assignModal = document.getElementById('assignModal');
  const closeAssign = document.getElementById('closeAssignModal');
  const cancelAssign = document.getElementById('cancelAssign');
  
  // Open modal when clicking assign buttons
  document.querySelectorAll('.open-assign-modal').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      
      // Check if this is from a convert or worker row
      const convertId = btn.dataset.convertId;
      const workerId = btn.dataset.workerId;
      
      // Show modal
      assignModal.classList.remove('hidden');
      assignModal.classList.add('flex');
      
      // Pre-select if coming from specific row
      if (convertId) {
        document.getElementById('selectConvert').value = convertId;
        updateConvertDetails();
      }
      
      if (workerId) {
        document.getElementById('selectWorker').value = workerId;
        updateWorkerDetails();
      }
    });
  });
  
  // Close modal
  [closeAssign, cancelAssign].forEach(btn => {
    if (!btn) return;
    btn.addEventListener('click', () => {
      assignModal.classList.add('hidden');
      assignModal.classList.remove('flex');
      // Reset details when modal closes
      document.getElementById('convertDetails').classList.add('hidden');
      document.getElementById('workerDetails').classList.add('hidden');
      document.getElementById('compatibilityNotice').classList.add('hidden');
    });
  });

  // ===== Filter selects inside assign modal
  function filterSelect(inputId, selectId) {
    const q = document.getElementById(inputId);
    const sel = document.getElementById(selectId);
    if (!q || !sel) return;
    q.addEventListener('input', () => {
      const term = q.value.toLowerCase();
      Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        const txt = opt.textContent.toLowerCase();
        opt.style.display = txt.includes(term) ? '' : 'none';
      });
      
      // Handle optgroups
      Array.from(sel.querySelectorAll('optgroup')).forEach(group => {
        const visibleOptions = Array.from(group.querySelectorAll('option')).filter(opt => 
          opt.style.display !== 'none'
        );
        group.style.display = visibleOptions.length > 0 ? '' : 'none';
      });
    });
  }
  filterSelect('filterConvert', 'selectConvert');
  filterSelect('filterWorker', 'selectWorker');

  // ===== Show details when convert/worker is selected
  function updateConvertDetails() {
    const select = document.getElementById('selectConvert');
    const selected = select.options[select.selectedIndex];
    const detailsDiv = document.getElementById('convertDetails');
    
    if (selected && selected.value) {
      const gender = selected.dataset.gender || 'Not specified';
      const hostel = selected.dataset.hostel || 'Not specified';
      const room = selected.dataset.room || 'Not specified';
      const status = selected.dataset.status || 'Not specified';
      
      document.getElementById('convertGender').textContent = gender;
      document.getElementById('convertHostel').textContent = hostel;
      document.getElementById('convertRoom').textContent = room;
      document.getElementById('convertStatus').textContent = status;
      
      detailsDiv.classList.remove('hidden');
    } else {
      detailsDiv.classList.add('hidden');
    }
    checkCompatibility();
  }

  function updateWorkerDetails() {
    const select = document.getElementById('selectWorker');
    const selected = select.options[select.selectedIndex];
    const detailsDiv = document.getElementById('workerDetails');
    
    if (selected && selected.value) {
      const gender = selected.dataset.gender || 'Not specified';
      const hostel = selected.dataset.hostel || 'Not specified';
      const room = selected.dataset.room || 'Not specified';
      const phone = selected.dataset.phone || 'Not specified';
      
      document.getElementById('workerGender').textContent = gender;
      document.getElementById('workerHostel').textContent = hostel;
      document.getElementById('workerRoom').textContent = room;
      document.getElementById('workerPhone').textContent = phone;
      
      detailsDiv.classList.remove('hidden');
    } else {
      detailsDiv.classList.add('hidden');
    }
    checkCompatibility();
  }

  // ===== Check compatibility between selected convert and worker
  function checkCompatibility() {
    const convertSelect = document.getElementById('selectConvert');
    const workerSelect = document.getElementById('selectWorker');
    const notice = document.getElementById('compatibilityNotice');
    const text = document.getElementById('compatibilityText');
    
    const convert = convertSelect.options[convertSelect.selectedIndex];
    const worker = workerSelect.options[workerSelect.selectedIndex];
    
    if (!convert || !convert.value || !worker || !worker.value) {
      notice.classList.add('hidden');
      return;
    }
    
    const convertGender = convert.dataset.gender;
    const workerGender = worker.dataset.gender;
    const convertHostel = convert.dataset.hostel;
    const workerHostel = worker.dataset.hostel;
    const isWorkerAvailable = worker.dataset.available === 'true';
    
    let suggestions = [];
    
    // Gender compatibility
    if (convertGender && workerGender) {
      if (convertGender === 'female' && workerGender === 'male') {
        suggestions.push('Consider assigning a female worker for this female convert');
      }
    }
    
    // Hostel proximity
    if (convertHostel && workerHostel && convertHostel === workerHostel) {
      suggestions.push('Both are in the same hostel - good for follow-up visits');
    }
    
    // Worker availability
    if (!isWorkerAvailable) {
      suggestions.push('This worker already has assignments');
    }
    
    // Show notice if we have suggestions
    if (suggestions.length > 0) {
      text.innerHTML = suggestions.map(s => `• ${s}`).join('<br>');
      notice.classList.remove('hidden');
    } else {
      notice.classList.add('hidden');
    }
  }

  // ===== Event listeners for select changes
  document.getElementById('selectConvert')?.addEventListener('change', updateConvertDetails);
  document.getElementById('selectWorker')?.addEventListener('change', updateWorkerDetails);

  // ===== Pagination functionality
  function setupPagination(tableId, pageId, countId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const pageSpan = document.getElementById(pageId);
    const countSpan = document.getElementById(countId);
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const visibleRows = rows.filter(row => row.style.display !== 'none');
    const totalRows = visibleRows.length;
    const rowsPerPage = 10;
    let currentPage = 1;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    
    // Update count display
    if (countSpan) {
      countSpan.textContent = `Showing ${Math.min(rowsPerPage, totalRows)} of ${totalRows} items`;
    }
    
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
        countSpan.textContent = `Showing ${showingStart}-${showingEnd} of ${totalRows} items`;
      }
    }
    
    // Show first page initially
    showPage(1);
    
    // Return navigation function
    return function navigate(direction) {
      if (direction === 'next' && currentPage < totalPages) {
        currentPage++;
        showPage(currentPage);
      } else if (direction === 'prev' && currentPage > 1) {
        currentPage--;
        showPage(currentPage);
      }
    };
  }
  
  // Initialize pagination for all tables
  const pagers = {
    unassignedConverts: setupPagination('tableUnassignedConverts', 'unassignedConvertsPage', 'unassignedConvertsCount'),
    availableWorkers: setupPagination('tableAvailableWorkers', 'availableWorkersPage', 'availableWorkersCount'),
    workerStats: setupPagination('tableWorkerStats', 'workerStatsPage', 'workerStatsCount')
  };
  
  // Add event listeners to pagination buttons
  document.querySelectorAll('.pager-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const pager = btn.dataset.pager;
      const direction = btn.dataset.dir;
      if (pagers[pager]) {
        pagers[pager](direction);
      }
    });
  });
});
</script>

<?php include 'footer.php'; ?>