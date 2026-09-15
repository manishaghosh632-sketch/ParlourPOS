<?php
// staff/cashier/payments.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']);

// Ensure payments table exists to support multiple payment methods per bill
$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    cashier_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'UPI', 'Card', 'Bank Transfer', 'Other') NOT NULL,
    reference_number VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (cashier_id) REFERENCES users(id)
)");

// ==========================================================
// PRG PATTERN FOR ADDING SPLIT PAYMENTS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_payment') {
        $invoice_id = (int)$_POST['invoice_id'];
        $amount = floatval($_POST['amount']);
        $method = $_POST['payment_method'];
        $reference = trim($_POST['reference_number']);
        $cashier_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        try {
            // Insert Payment
            $stmt = $conn->prepare("INSERT INTO payments (invoice_id, cashier_id, amount, payment_method, reference_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidss", $invoice_id, $cashier_id, $amount, $method, $reference);
            $stmt->execute();
            
            // Check total paid against invoice grand total
            $check = $conn->prepare("SELECT grand_total, (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = invoices.id) as total_paid FROM invoices WHERE id = ?");
            $check->bind_param("i", $invoice_id);
            $check->execute();
            $inv_data = $check->get_result()->fetch_assoc();
            
            if ($inv_data) {
                $status = 'Due';
                if ($inv_data['total_paid'] >= $inv_data['grand_total']) {
                    $status = 'Paid';
                } elseif ($inv_data['total_paid'] > 0) {
                    $status = 'Partial';
                }
                
                $upd = $conn->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
                $upd->bind_param("si", $status, $invoice_id);
                $upd->execute();
            }
            
            $conn->commit();
            $_SESSION['flash_success'] = "Payment recorded successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Failed to record payment.";
        }
    }
    header("Location: payments.php");
    exit;
}

// Fetch Today's Payment Summary
$today = date('Y-m-d');
$summary = ['Cash' => 0, 'UPI' => 0, 'Card' => 0, 'Total' => 0];
$sum_res = $conn->query("SELECT payment_method, SUM(amount) as total FROM payments WHERE DATE(created_at) = '$today' GROUP BY payment_method");
if ($sum_res) {
    while ($row = $sum_res->fetch_assoc()) {
        $summary[$row['payment_method']] = $row['total'];
        $summary['Total'] += $row['total'];
    }
}

// Fetch Payment Ledger
$payments = [];
try {
    $p_query = "
        SELECT p.id, p.amount, p.payment_method, p.reference_number, DATE_FORMAT(p.created_at, '%d %b %Y, %h:%i %p') as pay_time,
               i.invoice_number, u.name as cashier_name
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        JOIN users u ON p.cashier_id = u.id
        ORDER BY p.created_at DESC LIMIT 100
    ";
    $p_res = $conn->query($p_query);
    if($p_res) while($row = $p_res->fetch_assoc()) $payments[] = $row;
} catch (Exception $e) {}

// Fetch Pending Invoices for Modal Dropdown
$pending_invoices = [];
$inv_res = $conn->query("SELECT id, invoice_number, grand_total FROM invoices WHERE payment_status != 'Paid' ORDER BY created_at DESC");
if($inv_res) while($r = $inv_res->fetch_assoc()) $pending_invoices[] = $r;

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Payment Ledger</h3>
        <p class="text-sm text-gray-500 mt-1">Daily collection summary and transaction records[cite: 2]</p>
    </div>
    <button onclick="openModal('addPaymentModal')" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-hand-holding-dollar"></i> Collect Payment
    </button>
</div>

<!-- Daily Collection Summary -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="pos-card p-4 text-center border-t-4 border-t-emerald-500">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Cash Collected</p>
        <h4 class="text-xl font-bold text-emerald-600">₹<?= number_format($summary['Cash'], 2) ?></h4>
    </div>
    <div class="pos-card p-4 text-center border-t-4 border-t-indigo-500">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">UPI Collected</p>
        <h4 class="text-xl font-bold text-indigo-600">₹<?= number_format($summary['UPI'], 2) ?></h4>
    </div>
    <div class="pos-card p-4 text-center border-t-4 border-t-blue-500">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Card Collected</p>
        <h4 class="text-xl font-bold text-blue-600">₹<?= number_format($summary['Card'], 2) ?></h4>
    </div>
    <div class="pos-card p-4 text-center bg-brand-sidebar text-white shadow-md">
        <p class="text-[10px] font-bold text-white/60 uppercase tracking-wider mb-1">Total Today</p>
        <h4 class="text-xl font-bold text-white">₹<?= number_format($summary['Total'], 2) ?></h4>
    </div>
</div>

<!-- Payment History Table -->
<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Transaction Time</th>
                    <th class="px-6 py-4">Invoice No.</th>
                    <th class="px-6 py-4">Method & Ref</th>
                    <th class="px-6 py-4">Processed By</th>
                    <th class="px-6 py-4 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if(empty($payments)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No payment records found.</td></tr>
                <?php else: ?>
                    <?php foreach($payments as $pay): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-600"><?= $pay['pay_time'] ?></td>
                            <td class="px-6 py-4 font-mono font-bold text-gray-800"><?= htmlspecialchars($pay['invoice_number']) ?></td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-brand-sidebar uppercase text-[10px] tracking-wider"><?= htmlspecialchars($pay['payment_method']) ?></span>
                                <?php if($pay['reference_number']): ?>
                                    <div class="text-[10px] text-gray-400 mt-0.5">Ref: <?= htmlspecialchars($pay['reference_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs font-semibold uppercase"><?= htmlspecialchars($pay['cashier_name']) ?></td>
                            <td class="px-6 py-4 text-right font-black text-brand-coral">₹<?= number_format($pay['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Payment Modal -->
<div id="addPaymentModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addPaymentModal')"></div>
    <div class="bg-white w-full max-w-[450px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addPaymentModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-900 text-lg">Record Pending Payment</h3>
            <button onclick="closeModal('addPaymentModal')" class="text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="payments.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_payment">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Select Pending Invoice</label>
                <select name="invoice_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar cursor-pointer">
                    <option value="" disabled selected>Choose invoice...</option>
                    <?php foreach($pending_invoices as $inv): ?>
                        <option value="<?= $inv['id'] ?>"><?= $inv['invoice_number'] ?> (Total: ₹<?= $inv['grand_total'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Payment Method</label>
                    <select name="payment_method" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar cursor-pointer">
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Card">Debit/Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Reference Number (Optional)</label>
                <input type="text" name="reference_number" placeholder="Txn ID or Receipt No." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar">
            </div>

            <button type="submit" class="w-full py-3 mt-4 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Save Payment</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Payments';
    function openModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>