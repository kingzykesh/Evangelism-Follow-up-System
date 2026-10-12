<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ✅ Use the correct session key 'user_role' and validate properly
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'worker') {
  header("Location: dashboard.php");
  exit();
}

$assigned_converts = get_assigned_converts($_SESSION['user_id']);

// Helper functions
function safe($v) { return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function getGenderIcon($gender) {
    return $gender === 'male' ? 'mars' : ($gender === 'female' ? 'venus' : 'genderless');
}
function getGenderColor($gender) {
    return $gender === 'male' ? 'text-blue-500' : ($gender === 'female' ? 'text-pink-500' : 'text-gray-500');
}
function getStatusBadge($status) {
    switch($status) {
        case 'completed': return 'bg-green-100 text-green-700';
        case 'in-progress': return 'bg-blue-100 text-blue-700';
        default: return 'bg-yellow-100 text-yellow-700';
    }
}

// Safe date formatting function
function formatDateSafe($dateString) {
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00' || $dateString === '0000-00-00') {
        return 'Not updated';
    }
    try {
        $date = new DateTime($dateString);
        return $date->format('M j, Y');
    } catch (Exception $e) {
        return 'Invalid date';
    }
}
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Page Wrapper -->
<div class="lg:ml-64 p-6 bg-gray-50 min-h-screen mt-16">
  <!-- Header Section -->
  <div class="max-w-7xl mx-auto mb-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i>
          My Assigned Converts
        </h1>
        <p class="text-gray-600 mt-1">Manage and update follow-up progress for your assigned converts</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input id="searchConverts" type="text" placeholder="Search converts…"
                 class="pl-9 pr-3 py-2 rounded-lg bg-gray-50 border border-gray-200 text-sm focus:border-blue-400 focus:ring-0 outline-none">
        </div>
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Assigned</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= count($assigned_converts) ?></p>
          </div>
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-users text-blue-500"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Completed</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= count(array_filter($assigned_converts, fn($c) => $c['followup_status'] === 'completed')) ?>
            </p>
          </div>
          <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-check text-green-500"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">In Progress</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= count(array_filter($assigned_converts, fn($c) => $c['followup_status'] === 'in-progress')) ?>
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
            <p class="text-sm font-medium text-gray-600">Pending</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">
              <?= count(array_filter($assigned_converts, fn($c) => $c['followup_status'] === 'pending')) ?>
            </p>
          </div>
          <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
            <i class="fa-solid fa-clock text-yellow-500"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Converts Table -->
    <section class="bg-white shadow rounded-xl overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-list text-blue-500"></i>
          Assigned Converts List
        </h2>
        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
          <?= count($assigned_converts) ?> convert<?= count($assigned_converts) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <div class="overflow-x-auto">
        <table id="tableConverts" class="min-w-full text-sm">
          <thead>
            <tr class="bg-blue-50 text-gray-700">
              <th class="p-3 text-left">Name</th>
              <th class="p-3 text-left">Phone</th>
              <th class="p-3 text-left">Gender</th>
              <th class="p-3 text-left">Hostel & Room</th>
              <th class="p-3 text-left">Status</th>
              <th class="p-3 text-left">Last Updated</th>
              <th class="p-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if (empty($assigned_converts)): ?>
              <tr>
                <td colspan="7" class="p-8 text-center text-gray-500">
                  <i class="fa-solid fa-user-plus text-gray-400 text-3xl mb-3"></i>
                  <p class="font-medium text-gray-600">No converts assigned yet</p>
                  <p class="text-sm mt-1">You will see converts here once they are assigned to you by a coordinator.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($assigned_converts as $c): ?>
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
                  
                  // Safe date handling
                  $last_updated = formatDateSafe($c['updated_at'] ?? '');
                ?>
                <tr class="hover:bg-gray-50">
                  <td class="p-3 font-medium"><?= safe($c['name']) ?></td>
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <i class="fa-solid fa-phone text-gray-400"></i>
                      <?= safe($c['phone_number']) ?>
                    </div>
                  </td>
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
                    <span class="px-2 py-1 rounded text-xs <?= getStatusBadge($c['followup_status']) ?>">
                      <?= ucfirst($c['followup_status']) ?>
                    </span>
                  </td>
                  <td class="p-3 text-gray-500 text-xs">
                    <?= $last_updated ?>
                  </td>
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <button 
                        onclick="openViewModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)"
                        class="text-blue-600 hover:text-blue-700 text-sm flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                        <i class="fa-solid fa-eye"></i> View
                      </button>
                      <button 
                        onclick="openFollowupModal(<?= $c['id'] ?>)"
                        class="text-green-600 hover:text-green-700 text-sm flex items-center gap-1 px-3 py-1 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
                        <i class="fa-solid fa-edit"></i> Update
                      </button>
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
        <span id="convertsCount" class="text-xs text-gray-500"></span>
        <div class="flex items-center gap-2">
          <button data-pager="converts" data-dir="prev" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <span id="convertsPage" class="text-sm text-gray-600">1</span>
          <button data-pager="converts" data-dir="next" class="pager-btn px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- ===== Modal: View Convert Details ===== -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4">
  <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-user text-blue-500"></i> Convert Details
      </h3>
      <button id="closeViewModal" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="px-6 py-5 space-y-4 text-sm">
      <div class="flex items-center gap-4">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
          <i class="fa-solid fa-user text-blue-500 text-xl"></i>
        </div>
        <div>
          <h4 id="viewName" class="font-semibold text-gray-800 text-lg">—</h4>
          <p id="viewPhone" class="text-gray-600">—</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-gray-500">Gender</p>
          <p id="viewGender" class="font-medium text-gray-800 flex items-center gap-1">—</p>
        </div>
        <div>
          <p class="text-gray-500">Status</p>
          <p id="viewStatus" class="font-medium">—</p>
        </div>
        <div>
          <p class="text-gray-500">Hostel</p>
          <p id="viewHostel" class="font-medium text-gray-800">—</p>
        </div>
        <div>
          <p class="text-gray-500">Room</p>
          <p id="viewRoom" class="font-medium text-gray-800">—</p>
        </div>
      </div>

      <div>
        <p class="text-gray-500 mb-1">Follow-up Progress</p>
        <div class="bg-gray-50 rounded-lg p-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-600">Current Status:</span>
            <span id="viewFollowupStatus" class="px-2 py-1 rounded text-xs bg-gray-100">—</span>
          </div>
          <div class="text-xs text-gray-500">
            Last updated: <span id="viewUpdatedAt">—</span>
          </div>
        </div>
      </div>

      <div id="viewNotesSection" class="hidden">
        <p class="text-gray-500 mb-1">Worker Notes</p>
        <div id="viewNotes" class="rounded-lg bg-blue-50 border border-blue-100 p-3 min-h-[48px] text-gray-700"></div>
      </div>
    </div>

    <div class="px-6 pb-6">
      <button id="okView" class="w-full px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-sm">
        Close
      </button>
    </div>
  </div>
</div>

<!-- ===== Modal: Update Follow-up ===== -->
<div id="followupModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60] p-4">
  <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-edit text-green-500"></i> Update Follow-up Status
      </h3>
      <button id="closeFollowupModal" class="text-gray-400 hover:text-gray-600">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <form action="process_followup.php" method="POST" class="px-6 py-5 space-y-4">
      <input type="hidden" name="convert_id" id="convert_id">

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-phone text-blue-500"></i>
          Did you contact the convert?
        </label>
        <select name="contacted" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none">
          <option value="yes">Yes, successfully contacted</option>
          <option value="no">No, unable to reach</option>
        </select>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-comment text-green-500"></i>
          Did they respond positively?
        </label>
        <select name="responded" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none">
          <option value="yes">Yes, positive response</option>
          <option value="no">No response or negative</option>
        </select>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-church text-purple-500"></i>
          Have they attended a fellowship/service?
        </label>
        <select name="attended" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none">
          <option value="yes">Yes, attended</option>
          <option value="no">Not yet</option>
        </select>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-pray text-yellow-500"></i>
          Do they need further prayers or visits?
        </label>
        <textarea name="prayer_need" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none" rows="2" placeholder="Specify any prayer needs or visit requests..."></textarea>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-note-sticky text-gray-500"></i>
          Additional Notes
        </label>
        <textarea name="notes" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none" rows="3" placeholder="Add any additional observations or comments..."></textarea>
      </div>

      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 flex items-center gap-2">
          <i class="fa-solid fa-flag text-orange-500"></i>
          Follow-up Status
        </label>
        <select name="followup_status" class="w-full border border-gray-200 rounded-lg p-2 text-sm focus:border-blue-400 focus:ring-0 outline-none">
          <option value="pending">Pending - Not started</option>
          <option value="in-progress">In Progress - Ongoing follow-up</option>
          <option value="completed">Completed - Successfully followed up</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-3 pt-4">
        <button type="button" id="cancelFollowup" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white text-sm flex items-center gap-2">
          <i class="fa-solid fa-paper-plane"></i> Update Status
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  console.log("Worker assigned converts scripts initialized ✅");

  // ===== View Modal functionality
  const viewModal = document.getElementById('viewModal');
  const closeViewModal = document.getElementById('closeViewModal');
  const okView = document.getElementById('okView');

  window.openViewModal = function(convert) {
    // Fill modal with convert data
    document.getElementById('viewName').textContent = convert.name || '—';
    document.getElementById('viewPhone').textContent = convert.phone_number || '—';
    
    // Gender with icon
    const genderEl = document.getElementById('viewGender');
    if (convert.gender) {
      const icon = convert.gender === 'male' ? 'mars text-blue-500' : 
                   convert.gender === 'female' ? 'venus text-pink-500' : 'genderless text-gray-500';
      genderEl.innerHTML = `<i class="fa-solid fa-${icon}"></i> ${convert.gender.charAt(0).toUpperCase() + convert.gender.slice(1)}`;
    } else {
      genderEl.textContent = '—';
    }
    
    document.getElementById('viewHostel').textContent = convert.hostel || 'Not specified';
    document.getElementById('viewRoom').textContent = convert.room_number || 'Not specified';
    
    // Status
    const status = convert.followup_status || 'pending';
    const statusMap = {
      'completed': 'bg-green-100 text-green-700',
      'in-progress': 'bg-blue-100 text-blue-700',
      'pending': 'bg-yellow-100 text-yellow-700'
    };
    document.getElementById('viewStatus').innerHTML = 
      `<span class="px-2 py-1 rounded text-xs ${statusMap[status] || 'bg-gray-100'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
    
    document.getElementById('viewFollowupStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
    document.getElementById('viewFollowupStatus').className = `px-2 py-1 rounded text-xs ${statusMap[status] || 'bg-gray-100'}`;
    
    // Safe date handling in JavaScript
    const updatedAt = convert.updated_at;
    let displayDate = 'Not updated';
    if (updatedAt && updatedAt !== '0000-00-00 00:00:00' && updatedAt !== '0000-00-00') {
      try {
        const date = new Date(updatedAt);
        if (!isNaN(date.getTime())) {
          displayDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
      } catch (e) {
        console.error('Date parsing error:', e);
      }
    }
    document.getElementById('viewUpdatedAt').textContent = displayDate;
    
    // Notes section
    const notesSection = document.getElementById('viewNotesSection');
    const notesEl = document.getElementById('viewNotes');
    if (convert.notes) {
      notesEl.textContent = convert.notes;
      notesSection.classList.remove('hidden');
    } else {
      notesSection.classList.add('hidden');
    }
    
    viewModal.classList.remove('hidden');
    viewModal.classList.add('flex');
  };

  [closeViewModal, okView].forEach(btn => 
    btn?.addEventListener('click', () => {
      viewModal.classList.add('hidden');
      viewModal.classList.remove('flex');
    })
  );

  // ===== Follow-up Modal functionality
  const followupModal = document.getElementById('followupModal');
  const closeFollowupModal = document.getElementById('closeFollowupModal');
  const cancelFollowup = document.getElementById('cancelFollowup');

  window.openFollowupModal = function(id) {
    document.getElementById('convert_id').value = id;
    followupModal.classList.remove('hidden');
    followupModal.classList.add('flex');
  };

  [closeFollowupModal, cancelFollowup].forEach(btn => 
    btn?.addEventListener('click', () => {
      followupModal.classList.add('hidden');
      followupModal.classList.remove('flex');
    })
  );

  // Close modals when clicking outside
  [viewModal, followupModal].forEach(modal => {
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }
    });
  });

  // ===== Search functionality
  const searchInput = document.getElementById('searchConverts');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.toLowerCase();
      const rows = document.querySelectorAll('#tableConverts tbody tr');
      
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
    const table = document.getElementById('tableConverts');
    if (!table) return;
    
    const pageSpan = document.getElementById('convertsPage');
    const countSpan = document.getElementById('convertsCount');
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
        countSpan.textContent = `Showing ${showingStart}-${showingEnd} of ${totalRows} converts`;
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
</script>

<?php include 'footer.php'; ?>