<?php
session_name('ParlourPOS_Customer');
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

$invoices = [];
try {
    $query = "
        SELECT invoice_number, grand_total, payment_method, payment_status, 
               DATE_FORMAT(created_at, '%d %b %Y, %h:%i %p') as date_time
        FROM invoices 
        WHERE customer_id = ?
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $invoices[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Billing & Invoices</h3>
        <p class="text-sm text-gray-500 mt-1">Review your past transactions and receipts.</p>
    </div>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Invoice No. & Date</th>
                    <th class="px-6 py-4">Payment Method</th>
                    <th class="px-6 py-4">Total Amount</th>
                    <th class="px-6 py-4 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-400">No billing history found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                                <div class="text-[11px] text-gray-400 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i> <?= $inv['date_time'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs uppercase tracking-wider font-bold">
                                <?= htmlspecialchars($inv['payment_method']) ?>
                            </td>
                            <td class="px-6 py-4 font-black text-brand-sidebar text-base">
                                ₹<?= number_format($inv['grand_total'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php 
                                    $badge = 'bg-emerald-50 text-emerald-600';
                                    if ($inv['payment_status'] === 'Due') $badge = 'bg-rose-50 text-rose-600';
                                    elseif ($inv['payment_status'] === 'Partial') $badge = 'bg-amber-50 text-amber-600';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $badge ?>">
                                    <?= $inv['payment_status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Billing';
    document.getElementById('page-subtitle').innerText = 'Invoices & Payments';
</script>

<?php require_once '../../includes/footer.php'; ?>