<?php
// staff/cashier/refunds.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']);

// Ensure refunds table exists
$conn->query("CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    processor_id INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
)");

// ==========================================================
// PRG PATTERN FOR PROCESSING REFUNDS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'process_refund') {
        $invoice_num = trim($_POST['invoice_number']);
        $amount = floatval($_POST['refund_amount']);
        $reason = trim($_POST['reason']);
        $processor_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        try {
            // Find invoice ID[cite: 2]
            $inv_stmt = $conn->prepare("SELECT id, grand_total, payment_status FROM invoices WHERE invoice_number = ? AND payment_status != 'Refunded'");
            $inv_stmt->bind_param("s", $invoice_num);
            $inv_stmt->execute();
            $inv_res = $inv_stmt->get_result()->fetch_assoc();
            
            if (!$inv_res) {
                throw new Exception("Invoice not found or already refunded.");
            }
            if ($amount > $inv_res['grand_total']) {
                throw new Exception("Refund amount cannot exceed invoice grand total.");
            }

            // Insert Refund Record
            $ref_stmt = $conn->prepare("INSERT INTO refunds (invoice_id, processor_id, refund_amount, reason) VALUES (?, ?, ?, ?)");
            $ref_stmt->bind_param("iids", $inv_res['id'], $processor_id, $amount, $reason);
            $ref_stmt->execute();
            
            // Update Invoice Status
            $upd = $conn->prepare("UPDATE invoices SET payment_status = 'Refunded' WHERE id = ?");
            $upd->bind_param("i", $inv_res['id']);
            $upd->execute();
            
            $conn->commit();
            $_SESSION['flash_success'] = "Refund processed successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    
    header("Location: refunds.php");
    exit;
}

// Fetch Recent Refunds
$refunds = [];
try {
    $r_query = "
        SELECT r.refund_amount, r.reason, DATE_FORMAT(r.created_at, '%d %b %Y, %h:%i %p') as r_date,
               i.invoice_number, u.name as processor_name
        FROM refunds r
        JOIN invoices i ON r.invoice_id = i.id
        JOIN users u ON r.processor_id = u.id
        ORDER BY r.created_at DESC LIMIT 50
    ";
    $r_res = $conn->query($r_query);
    if($r_res) while($row = $r_res->fetch_assoc()) $refunds[] = $row;
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Refunds & Returns</h3>
        <p class="text-sm text-gray-500 mt-1">Process customer refunds and view cancellation history[cite: 2]</p>
    </div>
    <button onclick="openRefundModal()" class="bg-brand-coral hover:bg-brand-coralHover text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-arrow-rotate-left"></i> Issue Refund
    </button>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date Processed</th>
                    <th class="px-6 py-4">Invoice Reference</th>
                    <th class="px-6 py-4">Reason</th>
                    <th class="px-6 py-4">Processed By</th>
                    <th class="px-6 py-4 text-right">Refund Amount</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if(empty($refunds)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No refunds have been processed yet.</td></tr>
                <?php else: ?>
                    <?php foreach($refunds as $ref): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-600"><?= $ref['r_date'] ?></td>
                            <td class="px-6 py-4 font-mono font-bold text-gray-800"><?= htmlspecialchars($ref['invoice_number']) ?></td>
                            <td class="px-6 py-4 text-gray-500 italic text-xs max-w-xs truncate"><?= htmlspecialchars($ref['reason']) ?></td>
                            <td class="px-6 py-4 text-gray-500 text-xs font-semibold uppercase"><?= htmlspecialchars($ref['processor_name']) ?></td>
                            <td class="px-6 py-4 text-right font-black text-rose-500">₹<?= number_format($ref['refund_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Issue Refund Modal -->
<div id="refundModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeRefundModal()"></div>
    <div class="bg-white w-full max-w-[450px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="refundModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-rose-50">
            <h3 class="font-bold text-rose-600 text-lg">Process Invoice Refund</h3>
            <button onclick="closeRefundModal()" class="text-gray-400 hover:text-rose-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="refunds.php" class="p-6 space-y-4" onsubmit="return confirm('Are you sure you want to process this refund? This action is irreversible.');">
            <input type="hidden" name="action" value="process_refund">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Invoice Number</label>
                <input type="text" name="invoice_number" required placeholder="INV-260906-xxxx" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono focus:outline-none focus:border-brand-coral transition-colors">
            </div>
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Refund Amount (₹)</label>
                <input type="number" step="0.01" name="refund_amount" required placeholder="0.00" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-coral transition-colors">
            </div>
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Reason for Refund</label>
                <textarea name="reason" required rows="3" placeholder="Explain why the refund is being issued..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-coral transition-colors resize-none"></textarea>
            </div>

            <button type="submit" class="w-full py-3 mt-4 bg-brand-coral text-white rounded-xl font-bold hover:bg-brand-coralHover transition-colors shadow-md text-sm">Issue Secure Refund</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Refunds';
    document.getElementById('page-subtitle').innerText = 'Transaction cancellations and cash returns';

    function openRefundModal() {
        const modal = document.getElementById('refundModal');
        const content = document.getElementById('refundModalContent');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }

    function closeRefundModal() {
        const modal = document.getElementById('refundModal');
        const content = document.getElementById('refundModalContent');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>