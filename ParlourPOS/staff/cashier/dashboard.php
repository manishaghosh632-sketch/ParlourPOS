<?php
// staff/cashier/dashboard.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']);

$today = date('Y-m-d');
$metrics = ['sales' => 0, 'bills' => 0, 'pending' => 0];

try {
    $query = "
        SELECT 
            COUNT(id) as total_bills,
            SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END) as total_sales,
            SUM(CASE WHEN payment_status != 'Paid' THEN grand_total ELSE 0 END) as pending_payments
        FROM invoices 
        WHERE DATE(created_at) = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $metrics['bills'] = $res['total_bills'] ?? 0;
    $metrics['sales'] = $res['total_sales'] ?? 0;
    $metrics['pending'] = $res['pending_payments'] ?? 0;
    $stmt->close();
} catch (Exception $e) {}

$recent_transactions = [];
try {
    $t_query = "
        SELECT i.invoice_number, i.grand_total, i.payment_method, i.payment_status, DATE_FORMAT(i.created_at, '%h:%i %p') as time, c.name as customer_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE DATE(i.created_at) = ?
        ORDER BY i.created_at DESC LIMIT 5
    ";
    $stmt = $conn->prepare($t_query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $t_res = $stmt->get_result();
    while($row = $t_res->fetch_assoc()) $recent_transactions[] = $row;
    $stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Front Desk Dashboard</h2>
    <p class="text-gray-500 mt-1">Point of Sale operations & daily drawer</p>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <a href="pos.php" class="bg-brand-sidebar text-white rounded-2xl p-6 hover:bg-brand-sidebar/90 transition-all flex items-center justify-center gap-3 shadow-md border border-brand-sidebar">
        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-xl"><i class="fa-solid fa-cash-register"></i></div>
        <span class="text-sm font-bold uppercase tracking-wider">New Sale</span>
    </a>
    <a href="appointments.php" class="bg-white rounded-2xl p-6 border border-pink-100 hover:border-brand-magenta hover:shadow-sm transition-all flex items-center justify-center gap-3 group">
        <div class="w-10 h-10 rounded-full bg-[#FDF0F3] text-brand-magenta flex items-center justify-center text-xl"><i class="fa-regular fa-calendar-plus"></i></div>
        <span class="text-sm font-bold text-gray-700 uppercase tracking-wider">New Appointment</span>
    </a>
    <a href="../../customers/register.php" class="bg-white rounded-2xl p-6 border border-pink-100 hover:border-brand-magenta hover:shadow-sm transition-all flex items-center justify-center gap-3 group">
        <div class="w-10 h-10 rounded-full bg-[#FDF0F3] text-brand-magenta flex items-center justify-center text-xl"><i class="fa-solid fa-user-plus"></i></div>
        <span class="text-sm font-bold text-gray-700 uppercase tracking-wider">Add Customer</span>
    </a>
</div>

<!-- Daily KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Collection</p>
            <h4 class="text-2xl font-black text-gray-800">₹<?= number_format($metrics['sales'], 2) ?></h4>
        </div>
    </div>
    
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-[#FDF0F3] text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-receipt"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Bills Generated</p>
            <h4 class="text-2xl font-black text-gray-800"><?= $metrics['bills'] ?></h4>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-pink-50 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-clock"></i></div>
        <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pending Payments</p>
            <h4 class="text-2xl font-black text-rose-500">₹<?= number_format($metrics['pending'], 2) ?></h4>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden">
    <div class="p-6 border-b border-gray-50 flex justify-between items-center">
        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Recent Transactions</h4>
        <a href="invoices.php" class="text-xs font-bold text-brand-magenta hover:underline">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold">
                    <th class="px-6 py-4">Invoice & Time</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Amount</th>
                    <th class="px-6 py-4">Method</th>
                    <th class="px-6 py-4 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if(empty($recent_transactions)): ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No transactions recorded today.</td></tr>
                <?php else: ?>
                    <?php foreach($recent_transactions as $txn): ?>
                        <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($txn['invoice_number']) ?></div>
                                <div class="text-[10px] text-gray-400 mt-0.5"><i class="fa-regular fa-clock"></i> <?= $txn['time'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 font-medium"><?= htmlspecialchars($txn['customer_name'] ?? 'Walk-in Customer') ?></td>
                            <td class="px-6 py-4 font-black text-gray-800">₹<?= number_format($txn['grand_total'], 2) ?></td>
                            <td class="px-6 py-4 text-gray-500 text-[10px] uppercase tracking-wider font-bold"><?= htmlspecialchars($txn['payment_method']) ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider <?= $txn['payment_status'] === 'Paid' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100' ?>">
                                    <?= $txn['payment_status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>