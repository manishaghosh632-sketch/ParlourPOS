<?php
// staff/beautician/my_commisions.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Beautician']); // Strictly for Beauticians[cite: 2]

$user_id = $_SESSION['user_id'];

// Fetch Commission Details for the logged-in user
$commissions = [];
$totals = ['services' => 0, 'revenue' => 0, 'earnings' => 0];

try {
    $query = "
        SELECT 
            i.invoice_number, i.payment_status,
            DATE_FORMAT(i.created_at, '%d %b %Y, %h:%i %p') as date_time,
            c.name as service_name,
            ii.total_price, ii.staff_commission
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        JOIN catalog_items c ON ii.catalog_id = c.id
        WHERE ii.staff_assigned_id = ? AND i.payment_status = 'Paid'
        ORDER BY i.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $commissions[] = $row;
        $totals['services']++;
        $totals['revenue'] += $row['total_price'];
        $totals['earnings'] += $row['staff_commission'];
    }
    $stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h3 class="text-2xl font-bold text-brand-sidebar">My Commissions</h3>
    <p class="text-sm text-gray-500 mt-1">Review your generated revenue and commission earnings[cite: 2]</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="pos-card p-5 bg-white border border-gray-100 shadow-sm">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Services Done</p>
        <h4 class="text-2xl font-bold text-gray-800"><?= $totals['services'] ?></h4>
    </div>
    <div class="pos-card p-5 bg-white border border-gray-100 shadow-sm">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Revenue Generated</p>
        <h4 class="text-2xl font-bold text-gray-800">₹<?= number_format($totals['revenue'], 2) ?></h4>
    </div>
    <div class="pos-card p-5 bg-brand-sidebar text-white shadow-md shadow-brand-sidebar/20">
        <p class="text-[10px] font-bold text-white/60 uppercase tracking-wider mb-1">Total Commission Earned</p>
        <h4 class="text-2xl font-bold text-brand-coral">₹<?= number_format($totals['earnings'], 2) ?></h4>
    </div>
</div>

<div class="pos-card overflow-hidden">
    <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
        <h4 class="font-bold text-gray-800">Earning History</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date & Time</th>
                    <th class="px-6 py-4">Service</th>
                    <th class="px-6 py-4">Invoice #</th>
                    <th class="px-6 py-4 text-right">Service Value</th>
                    <th class="px-6 py-4 text-right">My Commission</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($commissions)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No completed commission records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($commissions as $comm): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-600"><?= $comm['date_time'] ?></td>
                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($comm['service_name']) ?></td>
                            <td class="px-6 py-4 text-xs font-mono text-gray-500"><?= htmlspecialchars($comm['invoice_number']) ?></td>
                            <td class="px-6 py-4 text-right font-medium text-gray-500">₹<?= number_format($comm['total_price'], 2) ?></td>
                            <td class="px-6 py-4 text-right font-black text-emerald-500">₹<?= number_format($comm['staff_commission'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'My Commissions';
    document.getElementById('page-subtitle').innerText = 'Earnings breakdown per service';
</script>

<?php require_once '../../includes/footer.php'; ?>