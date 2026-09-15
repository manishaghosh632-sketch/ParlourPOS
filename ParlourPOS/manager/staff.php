<?php
// manager/staff.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Manager', 'Super Admin']);

// Fetch Staff & Today's Performance
$staff_list = [];
$today = date('Y-m-d');

try {
    $query = "
        SELECT 
            u.id, u.name, u.email, u.role, u.status,
            (SELECT COUNT(*) FROM appointments WHERE staff_id = u.id AND appointment_date = '$today') as today_appointments,
            (SELECT COUNT(*) FROM appointments WHERE staff_id = u.id AND appointment_date = '$today' AND status = 'Completed') as completed_today
        FROM users u
        WHERE u.role IN ('Beautician', 'Receptionist')
        ORDER BY u.role ASC, u.name ASC
    ";
    $res = $conn->query($query);
    if($res) while($r = $res->fetch_assoc()) $staff_list[] = $r;
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Staff Directory</h3>
        <p class="text-sm text-gray-500 mt-1">Monitor daily staff assignments and performance[cite: 2]</p>
    </div>
    <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="staffSearch" placeholder="Search staff..." class="w-full sm:w-64 pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-sidebar shadow-sm transition-smooth">
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="staffGrid">
    <?php if(empty($staff_list)): ?>
        <div class="col-span-full py-12 text-center text-gray-400 font-medium bg-white rounded-2xl border border-gray-100">No staff members found.</div>
    <?php else: ?>
        <?php foreach($staff_list as $staff): ?>
            <div class="pos-card p-6 flex flex-col staff-card transition-smooth hover:-translate-y-1 hover:shadow-lg hover:border-brand-sidebar/20" data-name="<?= strtolower($staff['name']) ?>">
                
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-4">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($staff['name']) ?>&background=e2e8f0&color=1a1b41&rounded=true" class="w-12 h-12 rounded-full border-2 border-white shadow-sm" alt="Avatar">
                        <div>
                            <h4 class="font-bold text-gray-900 text-lg leading-tight staff-name"><?= htmlspecialchars($staff['name']) ?></h4>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-1"><?= htmlspecialchars($staff['role']) ?></p>
                        </div>
                    </div>
                    <div>
                        <span class="w-3 h-3 rounded-full inline-block <?= $staff['status'] === 'Active' ? 'bg-emerald-500' : 'bg-rose-500' ?>" title="<?= $staff['status'] ?>"></span>
                    </div>
                </div>

                <div class="text-sm text-gray-500 mb-6 flex-1">
                    <p><i class="fa-solid fa-envelope w-5 text-gray-400"></i> <?= htmlspecialchars($staff['email']) ?></p>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 flex justify-between items-center">
                    <div class="text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Tasks</p>
                        <p class="font-black text-gray-800"><?= $staff['today_appointments'] ?></p>
                    </div>
                    <div class="w-px h-8 bg-gray-200"></div>
                    <div class="text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Completed</p>
                        <p class="font-black text-emerald-500"><?= $staff['completed_today'] ?></p>
                    </div>
                </div>
                
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    document.getElementById('page-title').innerText = 'Staff Directory';
    
    document.getElementById('staffSearch').addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.staff-card').forEach(card => {
            const name = card.getAttribute('data-name');
            card.style.display = name.includes(query) ? 'flex' : 'none';
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>