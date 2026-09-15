<?php
// super_admin/commissions.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']); // Strict access control[cite: 2]

// Fetch Aggregated Commission Data
$commissions = [];
try {
    $query = "
        SELECT 
            u.id, 
            u.name, 
            u.role, 
            u.commission_type, 
            u.commission_value,
            COUNT(ii.id) as services_completed,
            COALESCE(SUM(ii.total_price), 0) as revenue_generated,
            COALESCE(SUM(ii.staff_commission), 0) as total_commission_earned
        FROM users u
        LEFT JOIN invoice_items ii ON u.id = ii.staff_assigned_id
        WHERE u.role IN ('Beautician', 'Manager') AND u.commission_type != 'None'
        GROUP BY u.id
        ORDER BY total_commission_earned DESC
    ";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $commissions[] = $row;
        }
    }
} catch (Exception $e) {
    // Graceful fallback if invoice tables aren't fully populated yet
}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Staff Commissions</h3>
        <p class="text-sm text-gray-500 mt-1">Track staff performance and calculated payouts[cite: 2]</p>
    </div>
    <button onclick="window.print()" class="bg-white border border-gray-200 text-gray-600 hover:text-brand-sidebar px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-print"></i> Print Report
    </button>
</div>

<div class="pos-card overflow-hidden">
    <div class="p-6 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
        <h4 class="font-bold text-gray-800">Current Earnings Overview</h4>
        <span class="text-xs font-semibold bg-brand-bg text-gray-500 px-3 py-1 rounded-full border border-gray-200">All Time</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Staff Member</th>
                    <th class="px-6 py-4">Commission Rule</th>
                    <th class="px-6 py-4 text-center">Services Done</th>
                    <th class="px-6 py-4 text-right">Revenue Generated</th>
                    <th class="px-6 py-4 text-right">Commission Earned</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($commissions)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">No commission data found. Ensure staff have rules assigned and invoices are processed.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($commissions as $comm): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($comm['name']) ?></div>
                                <div class="text-[11px] text-gray-400 mt-0.5 uppercase tracking-wide"><?= htmlspecialchars($comm['role']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                <span class="font-semibold text-gray-700"><?= $comm['commission_type'] ?>:</span> 
                                <?= $comm['commission_type'] === 'Percentage' ? $comm['commission_value'] . '%' : '₹' . number_format($comm['commission_value'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-gray-600">
                                <?= $comm['services_completed'] ?>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-500">
                                ₹<?= number_format($comm['revenue_generated'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-brand-coral text-base">
                                ₹<?= number_format($comm['total_commission_earned'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Commissions';
    document.getElementById('page-subtitle').innerText = 'Staff earnings and performance tracking';
</script>

<?php require_once '../includes/footer.php'; ?>