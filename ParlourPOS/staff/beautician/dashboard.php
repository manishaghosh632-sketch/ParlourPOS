<?php
// staff/beautician/dashboard.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Beautician']);

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$metrics = ['total_appointments' => 0, 'completed' => 0, 'commission' => 0];

try {
    $apt_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed FROM appointments WHERE staff_id = ? AND appointment_date = ?");
    $apt_stmt->bind_param("is", $user_id, $today);
    $apt_stmt->execute();
    $apt_res = $apt_stmt->get_result()->fetch_assoc();
    $metrics['total_appointments'] = $apt_res['total'] ?? 0;
    $metrics['completed'] = $apt_res['completed'] ?? 0;
    $apt_stmt->close();

    $comm_stmt = $conn->prepare("
        SELECT SUM(ii.staff_commission) as today_commission 
        FROM invoice_items ii 
        JOIN invoices i ON ii.invoice_id = i.id 
        WHERE ii.staff_assigned_id = ? AND DATE(i.created_at) = ? AND i.payment_status = 'Paid'
    ");
    $comm_stmt->bind_param("is", $user_id, $today);
    $comm_stmt->execute();
    $metrics['commission'] = $comm_stmt->get_result()->fetch_assoc()['today_commission'] ?? 0;
    $comm_stmt->close();
} catch (Exception $e) {}

$schedule = [];
try {
    $sch_stmt = $conn->prepare("
        SELECT a.id, DATE_FORMAT(a.appointment_time, '%h:%i %p') as time, a.status, c.name as customer_name, s.name as service_name
        FROM appointments a
        JOIN customers c ON a.customer_id = c.id
        JOIN catalog_items s ON a.service_id = s.id
        WHERE a.staff_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ");
    $sch_stmt->bind_param("is", $user_id, $today);
    $sch_stmt->execute();
    $sch_res = $sch_stmt->get_result();
    while ($row = $sch_res->fetch_assoc()) $schedule[] = $row;
    $sch_stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 tracking-tight">My Dashboard</h2>
    <p class="text-gray-500 mt-1">Hello, <?= htmlspecialchars($_SESSION['name']) ?>. Here is your schedule for today.</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-[#FDF0F3] text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-regular fa-calendar-check"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Schedule</p>
            <h4 class="text-2xl font-black text-gray-800"><?= $metrics['total_appointments'] ?></h4>
        </div>
    </div>
    
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-check-double"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Completed Services</p>
            <h4 class="text-2xl font-black text-gray-800"><?= $metrics['completed'] ?></h4>
        </div>
    </div>

    <div class="bg-brand-sidebar p-5 rounded-2xl shadow-md border border-brand-sidebar flex items-center gap-4 text-white">
        <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wallet"></i></div>
        <div>
            <p class="text-[10px] font-bold text-white/60 uppercase tracking-wider mb-1">Commission Earned</p>
            <h4 class="text-2xl font-black text-white">₹<?= number_format($metrics['commission'], 2) ?></h4>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden max-w-4xl">
    <div class="p-6 border-b border-gray-50">
        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">My Appointments (<?= date('M d, Y') ?>)</h4>
    </div>
    <div class="p-0">
        <?php if (empty($schedule)): ?>
            <div class="p-12 text-center">
                <i class="fa-regular fa-calendar-xmark text-4xl text-gray-200 mb-3"></i>
                <p class="text-gray-400 font-medium">You have no appointments scheduled for today.</p>
            </div>
        <?php else: ?>
            <ul class="divide-y divide-gray-50">
                <?php foreach ($schedule as $apt): ?>
                    <li class="p-6 hover:bg-[#FDF0F3]/30 transition-colors flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div class="text-center shrink-0 w-24">
                                <span class="block text-xl font-black text-brand-sidebar"><?= explode(' ', $apt['time'])[0] ?></span>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase"><?= explode(' ', $apt['time'])[1] ?></span>
                            </div>
                            <div class="w-px h-10 bg-gray-100 hidden sm:block"></div>
                            <div>
                                <h5 class="font-bold text-gray-900 text-base"><?= htmlspecialchars($apt['customer_name']) ?></h5>
                                <p class="text-xs font-bold text-brand-magenta mt-1 uppercase tracking-wider"><i class="fa-solid fa-spa mr-1 opacity-70"></i> <?= htmlspecialchars($apt['service_name']) ?></p>
                            </div>
                        </div>
                        <div>
                            <?php 
                                $s_color = 'bg-gray-100 text-gray-600 border-gray-200';
                                if($apt['status'] === 'Confirmed') $s_color = 'bg-blue-50 text-blue-600 border-blue-100';
                                if($apt['status'] === 'In Progress') $s_color = 'bg-amber-50 text-amber-600 border-amber-100';
                                if($apt['status'] === 'Completed') $s_color = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                            ?>
                            <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $s_color ?>">
                                <?= $apt['status'] ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>