<?php
// staff/cashier/invoices.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']); // Allow higher roles

// ==========================================================
// PRG PATTERN FOR INVOICE ACTIONS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_paid') {
        $invoice_id = (int)$_POST['invoice_id'];
        $stmt = $conn->prepare("UPDATE invoices SET payment_status = 'Paid' WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Invoice marked as paid.";
        } else {
            $_SESSION['flash_error'] = "Failed to update invoice.";
        }
        $stmt->close();
    }

    header("Location: invoices.php");
    exit;
}

// Fetch Invoices
$invoices = [];
try {
    $query = "
        SELECT 
            i.id, i.invoice_number, i.grand_total, i.payment_method, i.payment_status, 
            DATE_FORMAT(i.created_at, '%d %b %Y, %h:%i %p') as date_time, 
            c.name as customer_name, c.mobile
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        ORDER BY i.created_at DESC
        LIMIT 50
    ";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $invoices[] = $row;
        }
    }
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Invoices & Billing History</h3>
        <p class="text-sm text-gray-500 mt-1">Review past transactions and manage pending dues[cite: 2]</p>
    </div>
    <div class="flex gap-2">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" id="invoiceSearch" placeholder="Search invoice or phone..." class="w-64 pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar transition-smooth shadow-sm">
        </div>
    </div>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Invoice Details</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Amount & Method</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50" id="invoiceTableBody">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">No invoices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors invoice-row">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800 invoice-number"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                                <div class="text-[11px] text-gray-400 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i> <?= $inv['date_time'] ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-700"><?= htmlspecialchars($inv['customer_name'] ?? 'Walk-in Guest') ?></div>
                                <?php if ($inv['mobile']): ?>
                                    <div class="text-[11px] text-gray-500 mt-0.5 invoice-mobile"><?= htmlspecialchars($inv['mobile']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-black text-brand-sidebar text-base">₹<?= number_format($inv['grand_total'], 2) ?></div>
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-0.5"><?= htmlspecialchars($inv['payment_method']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $badge = 'bg-emerald-50 text-emerald-600';
                                    if ($inv['payment_status'] === 'Due') $badge = 'bg-rose-50 text-rose-600';
                                    elseif ($inv['payment_status'] === 'Partial') $badge = 'bg-amber-50 text-amber-600';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $badge ?>">
                                    <?= $inv['payment_status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <?php if ($inv['payment_status'] !== 'Paid'): ?>
                                    <form method="POST" action="invoices.php" onsubmit="return confirm('Mark this invoice as fully paid?');">
                                        <input type="hidden" name="action" value="mark_paid">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <button type="submit" title="Mark as Paid" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-smooth flex items-center justify-center">
                                            <i class="fa-solid fa-check text-xs"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button onclick="window.print()" title="Print Receipt" class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:bg-brand-sidebar hover:text-white transition-smooth flex items-center justify-center">
                                    <i class="fa-solid fa-print text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Invoices';
    document.getElementById('page-subtitle').innerText = 'Transaction history and receipts';

    // Simple Client-side Search Filter
    document.getElementById('invoiceSearch').addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.invoice-row').forEach(row => {
            const invNum = row.querySelector('.invoice-number').innerText.toLowerCase();
            const mobile = row.querySelector('.invoice-mobile') ? row.querySelector('.invoice-mobile').innerText.toLowerCase() : '';
            if (invNum.includes(query) || mobile.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>