<?php
// manager/dashboard.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Manager', 'Super Admin']);

$today = date('Y-m-d');
$metrics = ['appointments' => 0, 'low_stock' => 0, 'sales' => 0];

try {
    $metrics['appointments'] = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = '$today'")->fetch_assoc()['c'] ?? 0;
    $metrics['low_stock'] = $conn->query("SELECT COUNT(*) as c FROM catalog_items WHERE type='Product' AND current_stock <= min_stock")->fetch_assoc()['c'] ?? 0;
    $metrics['sales'] = $conn->query("SELECT SUM(grand_total) as c FROM invoices WHERE DATE(created_at) = '$today' AND payment_status = 'Paid'")->fetch_assoc()['c'] ?? 0;
} catch (Exception $e) {}

$low_stock_items = [];
try {
    $ls_res = $conn->query("SELECT name, current_stock, min_stock FROM catalog_items WHERE type='Product' AND current_stock <= min_stock AND status='Active' LIMIT 5");
    if($ls_res) while($r = $ls_res->fetch_assoc()) $low_stock_items[] = $r;
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Operations Dashboard</h2>
        <p class="text-gray-500 mt-1">Daily overview of parlour activities</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-[#FDF0F3] text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-regular fa-calendar-check"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Bookings</p>
            <h4 class="text-2xl font-black text-gray-800"><?= $metrics['appointments'] ?></h4>
        </div>
    </div>
    
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Low Stock Alerts</p>
            <h4 class="text-2xl font-black text-rose-500"><?= $metrics['low_stock'] ?></h4>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Revenue</p>
            <h4 class="text-2xl font-black text-gray-800">&#8377;<?= number_format($metrics['sales'], 2) ?></h4>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden">
        <div class="p-6 border-b border-gray-50 flex justify-between items-center">
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Inventory Alerts</h4>
            <a href="inventory.php" class="text-xs text-brand-magenta font-bold hover:underline">Manage Stock</a>
        </div>
        <div class="p-0">
            <?php if(empty($low_stock_items)): ?>
                <div class="p-8 text-center text-gray-400 text-sm font-medium">All products are adequately stocked.</div>
            <?php else: ?>
                <ul class="divide-y divide-gray-50">
                    <?php foreach($low_stock_items as $item): ?>
                        <li class="p-4 px-6 flex justify-between items-center hover:bg-[#FDF0F3]/30 transition-colors">
                            <span class="font-bold text-gray-700 text-sm"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="px-3 py-1 bg-rose-50 text-rose-600 rounded-full text-[10px] font-bold border border-rose-100 uppercase tracking-wide">
                                <?= $item['current_stock'] ?> left (Min: <?= $item['min_stock'] ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden">
        <div class="p-6 border-b border-gray-50">
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Manager Quick Actions</h4>
        </div>
        <div class="p-6 grid grid-cols-2 gap-4">
            <a href="inventory.php" class="bg-[#FDF0F3] border border-pink-100 rounded-xl p-5 text-center hover:bg-brand-pink transition-colors group">
                <i class="fa-solid fa-truck-ramp-box text-3xl text-brand-magenta mb-3"></i>
                <h5 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Add Purchase</h5>
            </a>
            <a href="staff.php" class="bg-[#FDF0F3] border border-pink-100 rounded-xl p-5 text-center hover:bg-brand-pink transition-colors group">
                <i class="fa-solid fa-clipboard-user text-3xl text-brand-magenta mb-3"></i>
                <h5 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Staff Attendance</h5>
            </a>
            <a href="reports.php" class="bg-brand-sidebar border border-brand-sidebar rounded-xl p-5 text-center hover:bg-brand-sidebar/90 transition-colors group col-span-2 shadow-md">
                <i class="fa-solid fa-chart-pie text-3xl text-white mb-3"></i>
                <h5 class="text-xs font-bold text-white uppercase tracking-wider">View Reports</h5>
            </a>
        </div>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Operations Dashboard' : null;
</script>

<?php require_once '../includes/footer.php'; ?>