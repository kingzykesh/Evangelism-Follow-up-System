<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Role gate
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['super_admin', 'coordinator'])) {
    header("Location: dashboard.php");
    exit();
}
$workers = get_workers();             // id, name, phone, department, gender, hostel, room_number
$converts = get_all_converts();       // id, name, phone_number, gender, hostel, room_number, status
$reports = get_followup_reports();    // ca.*, convert_name, worker_name, followup_questions, notes, followup_status, updated_at

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

<!-- Page Wrapper (keeps content beside the fixed sidebar) -->
<div class="lg:ml-64 p-6 bg-gray-50 pt-20 min-h-screen">
  <!-- Topbar / Title -->
  <div class="max-w-7xl mx-auto mb-6">
    <?php if (isset($_GET['assigned']) && $_GET['assigned'] == 1): ?>
      <div id="flashAssigned" class="mb-4 rounded-lg bg-green-100 text-green-800 px-4 py-3 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        <span>Convert successfully assigned to worker.</span>
      </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Coordinator Dashboard</h1>
      <!-- Global search (optional hook) -->
      <div class="hidden lg:flex items-center gap-2 bg-white rounded-full px-4 py-2 shadow">
        <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
        <input type="text" class="outline-none text-sm w-72" placeholder="Search converts, users, reports…">
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto space-y-8">

    <!-- ============ Workers ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-people-group text-orange-500"></i>
          Workers
        </h2>
        <div class="flex items-center gap-3">
          <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input id="searchWorkers" type="text" placeholder="Search workers…"
                   class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none">
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableWorkers" class="min-w-full text-sm">
          <thead>
            <tr class="bg-orange-50 text-gray-700">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Phone</th>
              <th class="p-3 text-left">Gender</th>
              <th class="p-3 text-left">Hostel & Room</th>
              <th class="p-3 text-left">Department</th>
              <th class="p-3 text-left">Assigned Converts</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($workers as $w): ?>
              <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM convert_assignments WHERE worker_id = ?");
                $stmt->execute([$w['id']]);
                $assigned_count = (int)$stmt->fetchColumn();
                
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
                <td class="p-3">
                  <span class="inline-flex items-center justify-center w-6 h-6 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                    <?= $assigned_count ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="workersCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="workers" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="workersPage" class="text-sm text-gray-600">1</span>
          <button data-pager="workers" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

    <!-- ============ Converts ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-orange-500"></i>
          All Converts
        </h2>
        <div class="flex items-center gap-3">
          <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input id="searchConverts" type="text" placeholder="Search converts…"
                   class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none">
          </div>
          <button id="openAssignModal"
                  class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Assign Convert
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableConverts" class="min-w-full text-sm">
          <thead>
            <tr class="bg-orange-50 text-gray-700">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Phone</th>
              <th class="p-3 text-left">Gender</th>
              <th class="p-3 text-left">Hostel & Room</th>
              <th class="p-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($converts as $c): ?>
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
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="convertsCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="converts" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="convertsPage" class="text-sm text-gray-600">1</span>
          <button data-pager="converts" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </section>

    <!-- ============ Follow-up Reports ============ -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-orange-500"></i>
          Follow-up Reports
        </h2>
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="searchReports" type="text" placeholder="Search reports…"
                 class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-orange-400 focus:ring-0 outline-none">
        </div>
      </div>

      <div class="overflow-x-auto">
        <table id="tableReports" class="min-w-full text-sm">
          <thead>
            <tr class="bg-orange-50 text-gray-700">
              <th class="p-3 text-left">Convert</th>
              <th class="p-3 text-left">Worker</th>
              <th class="p-3 text-left">Status</th>
              <th class="p-3 text-left">Notes</th>
              <th class="p-3 text-left">Updated</th>
              <th class="p-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($reports as $r): ?>
              <?php
                $status = $r['followup_status'] ?? 'pending';
                $badge = $status === 'completed' ? 'bg-green-100 text-green-700' : ($status === 'in-progress' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700');
                $qjson = $r['followup_questions'] ?? '{}';
                // Make sure JSON is safe to embed as data-attr
                $qattr = htmlspecialchars($qjson, ENT_QUOTES, 'UTF-8');
              ?>
              <tr class="hover:bg-gray-50">
                <td class="p-3"><?= safe($r['convert_name']) ?></td>
                <td class="p-3"><?= safe($r['worker_name']) ?></td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded text-xs <?= $badge ?>"><?= ucfirst(safe($status)) ?></span>
                </td>
                <td class="p-3">
                  <?= $r['notes'] ? safe($r['notes']) : '<span class="text-gray-400 italic">No notes yet</span>' ?>
                </td>
                <td class="p-3"><?= $r['updated_at'] ? date('M d, Y h:i A', strtotime($r['updated_at'])) : '' ?></td>
                <td class="p-3">
                  <button
                    class="view-details text-orange-600 hover:text-orange-700 text-sm flex items-center gap-2"
                    data-convert="<?= safe($r['convert_name']) ?>"
                    data-worker="<?= safe($r['worker_name']) ?>"
                    data-status="<?= safe($status) ?>"
                    data-notes="<?= safe($r['notes'] ?? '') ?>"
                    data-updated="<?= $r['updated_at'] ? date('M d, Y h:i A', strtotime($r['updated_at'])) : '' ?>"
                    data-questions='<?= $qattr ?>'
                  >
                    <i class="fa-solid fa-eye"></i> View
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 flex items-center justify-between border-t border-gray-100">
        <span id="reportsCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="reports" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-left"></i></button>
          <span id="reportsPage" class="text-sm text-gray-600">1</span>
          <button data-pager="reports" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm"><i class="fa-solid fa-chevron-right"></i></button>
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
          <?php foreach ($converts as $c): ?>
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
            <option value="<?= (int)$c['id'] ?>" data-gender="<?= safe($c['gender'] ?? '') ?>" data-hostel="<?= safe($c['hostel'] ?? '') ?>" data-room="<?= safe($c['room_number'] ?? '') ?>">
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
          <?php foreach ($workers as $w): ?>
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
            <option value="<?= (int)$w['id'] ?>" data-gender="<?= safe($w['gender'] ?? '') ?>" data-hostel="<?= safe($w['hostel'] ?? '') ?>" data-room="<?= safe($w['room_number'] ?? '') ?>" data-phone="<?= safe($w['phone'] ?? '') ?>">
              <?= $worker_details ?>
            </option>
          <?php endforeach; ?>
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

<!-- ========== Modal: View Follow-up Details ========== -->
<div id="detailsModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4">
  <div class="bg-white rounded-2xl w-full max-w-xl shadow-xl">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-eye text-orange-500"></i> Follow-up Details
      </h3>
      <button id="closeDetailsModal" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="px-6 py-5 space-y-4 text-sm">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-gray-500">Convert</p>
          <p id="dConvert" class="font-medium text-gray-800">—</p>
        </div>
        <div>
          <p class="text-gray-500">Worker</p>
          <p id="dWorker" class="font-medium text-gray-800">—</p>
        </div>
        <div>
          <p class="text-gray-500">Status</p>
          <p id="dStatus" class="font-medium"><span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">—</span></p>
        </div>
        <div>
          <p class="text-gray-500">Updated</p>
          <p id="dUpdated" class="font-medium text-gray-800">—</p>
        </div>
      </div>

      <div>
        <p class="text-gray-500 mb-1">Notes</p>
        <div id="dNotes" class="rounded-lg bg-gray-50 border border-gray-100 p-3 min-h-[48px]"></div>
      </div>

      <div>
        <p class="text-gray-500 mb-2">Worker Responses</p>
        <div id="dQA" class="grid grid-cols-1 gap-2">
          <!-- Filled dynamically -->
        </div>
      </div>
    </div>

    <div class="px-6 pb-6">
      <button id="okDetails" class="ml-auto block px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm">
        Close
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  console.log("Coordinator dashboard scripts initialized ✅");

  // ===== Flash success auto-hide
  const flashAssigned = document.getElementById('flashAssigned');
  if (flashAssigned) setTimeout(() => flashAssigned.remove(), 3500);

  // ===== Assign Modal handlers
  const assignModal = document.getElementById('assignModal');
  const openAssign = document.getElementById('openAssignModal');
  const closeAssign = document.getElementById('closeAssignModal');
  const cancelAssign = document.getElementById('cancelAssign');
  [openAssign, closeAssign, cancelAssign].forEach(btn => {
    if (!btn) return;
    btn.addEventListener('click', () => {
      assignModal.classList.toggle('hidden');
      assignModal.classList.toggle('flex');
      // Reset details when modal opens/closes
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
      
      document.getElementById('convertGender').textContent = gender;
      document.getElementById('convertHostel').textContent = hostel;
      document.getElementById('convertRoom').textContent = room;
      document.getElementById('convertStatus').textContent = 'Active';
      
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

  function checkCompatibility() {
    const convertSelect = document.getElementById('selectConvert');
    const workerSelect = document.getElementById('selectWorker');
    const noticeDiv = document.getElementById('compatibilityNotice');
    const noticeText = document.getElementById('compatibilityText');
    
    const convert = convertSelect.options[convertSelect.selectedIndex];
    const worker = workerSelect.options[workerSelect.selectedIndex];
    
    if (!convert || !convert.value || !worker || !worker.value) {
      noticeDiv.classList.add('hidden');
      return;
    }
    
    const convertGender = convert.dataset.gender;
    const workerGender = worker.dataset.gender;
    const convertHostel = convert.dataset.hostel;
    const workerHostel = worker.dataset.hostel;
    
    let suggestions = [];
    
    // Gender compatibility
    if (convertGender && workerGender) {
      if (convertGender === workerGender) {
        suggestions.push("✓ Same gender - good for personal follow-up");
      } else {
        suggestions.push("⚠ Different genders - consider cultural sensitivity");
      }
    }
    
    // Hostel proximity
    if (convertHostel && workerHostel && convertHostel !== 'Not specified' && workerHostel !== 'Not specified') {
      if (convertHostel === workerHostel) {
        suggestions.push("✓ Same hostel - easy access for visits");
      } else {
        suggestions.push("📍 Different hostels - plan meeting location");
      }
    }
    
    if (suggestions.length > 0) {
      noticeText.innerHTML = suggestions.join('<br>');
      noticeDiv.classList.remove('hidden');
    } else {
      noticeDiv.classList.add('hidden');
    }
  }

  document.getElementById('selectConvert').addEventListener('change', updateConvertDetails);
  document.getElementById('selectWorker').addEventListener('change', updateWorkerDetails);

  // ===== View Details Modal
  const detailsModal = document.getElementById('detailsModal');
  const closeDetailsModal = document.getElementById('closeDetailsModal');
  const okDetails = document.getElementById('okDetails');
  [closeDetailsModal, okDetails].forEach(btn =>
    btn?.addEventListener('click', () => {
      detailsModal.classList.add('hidden');
      detailsModal.classList.remove('flex');
    })
  );

  document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', () => {
      const convert = btn.dataset.convert || '';
      const worker = btn.dataset.worker || '';
      const status = btn.dataset.status || 'pending';
      const notes = btn.dataset.notes || '';
      const updated = btn.dataset.updated || '';
      const qjson = btn.dataset.questions || '{}';

      // Fill modal fields
      document.getElementById('dConvert').textContent = convert;
      document.getElementById('dWorker').textContent = worker;
      document.getElementById('dUpdated').textContent = updated;

      const map = {
        'completed': 'bg-green-100 text-green-700',
        'in-progress': 'bg-blue-100 text-blue-700',
        'pending': 'bg-yellow-100 text-yellow-700'
      };
      document.getElementById('dStatus').innerHTML = 
        `<span class="px-2 py-1 rounded text-xs ${map[status] ?? 'bg-gray-100 text-gray-700'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;

      document.getElementById('dNotes').textContent = notes || '—';
      const qa = document.getElementById('dQA');
      qa.innerHTML = '';

      let parsed = {};
      try { parsed = JSON.parse(qjson || '{}'); } catch (e) { parsed = {}; }
      const rows = [
        ['Contacted?', parsed.contacted || '—'],
        ['Responded?', parsed.responded || '—'],
        ['Attended Service?', parsed.attended || '—'],
        ['Prayer/Visit Need', parsed.prayer_need || '—']
      ];
      rows.forEach(([label, val]) => {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between bg-gray-50 border border-gray-100 rounded-md px-3 py-2';
        div.innerHTML = `<span class="text-gray-600">${label}</span><span class="font-medium text-gray-800">${val}</span>`;
        qa.appendChild(div);
      });

      detailsModal.classList.remove('hidden');
      detailsModal.classList.add('flex');
    });
  });

  // ===== Search + Pagination (client-side)
  function makePager(tableId, searchId, countId, pageId, pageSize = 5) {
    const table = document.getElementById(tableId);
    const tbody = table?.querySelector('tbody');
    const rows = Array.from(tbody?.querySelectorAll('tr') || []);
    const search = document.getElementById(searchId);
    const count = document.getElementById(countId);
    const pageEl = document.getElementById(pageId);
    let filtered = rows.slice();
    let page = 1;

    function render() {
      rows.forEach(r => (r.style.display = 'none'));
      const total = filtered.length;
      const pages = Math.max(1, Math.ceil(total / pageSize));
      if (page > pages) page = pages;
      filtered.slice((page - 1) * pageSize, (page - 1) * pageSize + pageSize).forEach(r => (r.style.display = ''));
      if (count) count.textContent = `${total} result${total !== 1 ? 's' : ''} • page ${page} of ${pages}`;
      if (pageEl) pageEl.textContent = page;
    }

    function applyFilter() {
      const term = (search?.value || '').toLowerCase();
      filtered = rows.filter(r => r.textContent.toLowerCase().includes(term));
      page = 1;
      render();
    }

    search?.addEventListener('input', applyFilter);
    render();
    return { next() { page++; render(); }, prev() { page = Math.max(1, page - 1); render(); } };
  }

  const pagers = {
    workers: makePager('tableWorkers', 'searchWorkers', 'workersCount', 'workersPage', 5),
    converts: makePager('tableConverts', 'searchConverts', 'convertsCount', 'convertsPage', 5),
    reports: makePager('tableReports', 'searchReports', 'reportsCount', 'reportsPage', 5),
  };

  document.querySelectorAll('.pager-btn').forEach(btn => {
    const which = btn.dataset.pager;
    const dir = btn.dataset.dir;
    btn.addEventListener('click', () => {
      const p = pagers[which];
      if (!p) return;
      if (dir === 'prev') p.prev();
      if (dir === 'next') p.next();
    });
  });
});
</script>
<?php include 'footer.php'; ?>