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
$points = 0;

try {
    // Fetch total loyalty points
    $pts_stmt = $conn->prepare("SELECT loyalty_points FROM customers WHERE id = ?");
    $pts_stmt->bind_param("i", $customer_id);
    $pts_stmt->execute();
    $points = $pts_stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0;
    $pts_stmt->close();
} catch (Exception $e) {
    // Fallback if column doesn't exist
}

// Fetch dummy/actual points history based on invoices
$history = [];
try {
    // In a real system you'd query a `points_ledger` table. 
    // Here we estimate points based on past paid invoices (e.g., 1 point per 100 spent).
    $query = "
        SELECT invoice_number, grand_total, 
               DATE_FORMAT(created_at, '%d %b %Y') as p_date
        FROM invoices 
        WHERE customer_id = ? AND payment_status = 'Paid'
        ORDER BY created_at DESC LIMIT 10
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $earned = floor($row['grand_total'] / 100);
        if ($earned > 0) {
            $history[] = [
                'date' => $row['p_date'],
                'description' => "Earned from Invoice " . $row['invoice_number'],
                'points' => '+' . $earned,
                'type' => 'Earned'
            ];
        }
    }
    $stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">My Reward Points</h3>
        <p class="text-sm text-gray-500 mt-1">Track your points balance and history.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Balance Card -->
    <div class="pos-card p-8 bg-gradient-to-br from-amber-400 to-amber-600 text-white flex flex-col justify-center items-center text-center shadow-lg shadow-amber-500/30 lg:col-span-1">
        <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-3xl mb-4">
            <i class="fa-solid fa-star"></i>
        </div>
        <p class="text-xs font-bold text-white/80 uppercase tracking-widest mb-2">Available Balance</p>
        <h2 class="text-5xl font-black mb-2"><?= number_format($points) ?></h2>
        <p class="text-sm font-medium text-white/90">Points never expire!</p>
    </div>

    <!-- How to earn/redeem -->
    <div class="pos-card p-6 border border-gray-100 lg:col-span-2 flex flex-col justify-center">
        <h4 class="font-bold text-gray-800 mb-4 text-lg">How it works</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="flex gap-4 items-start">
                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-lg shrink-0">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <h5 class="font-bold text-gray-800 text-sm">Earn Points</h5>
                    <p class="text-xs text-gray-500 mt-1">Earn 1 point for every ₹100 spent on services and products.</p>
                </div>
            </div>
            <div class="flex gap-4 items-start">
                <div class="w-10 h-10 rounded-full bg-brand-coral/10 text-brand-coral flex items-center justify-center text-lg shrink-0">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <div>
                    <h5 class="font-bold text-gray-800 text-sm">Redeem Rewards</h5>
                    <p class="text-xs text-gray-500 mt-1">Redeem 100 points for ₹100 off your next appointment at checkout.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Points History Table -->
<div class="pos-card overflow-hidden">
    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
        <h4 class="font-bold text-gray-800">Points Activity</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date</th>
                    <th class="px-6 py-4">Description</th>
                    <th class="px-6 py-4 text-right">Points</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center text-gray-400">No points history found yet. Book a service to start earning!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $record): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-600">
                                <?= $record['date'] ?>
                            </td>
                            <td class="px-6 py-4 text-gray-800 font-medium">
                                <?= htmlspecialchars($record['description']) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="font-black text-emerald-500 bg-emerald-50 px-3 py-1 rounded-full text-xs">
                                    <?= $record['points'] ?>
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
    document.getElementById('page-title').innerText = 'My Points';
    document.getElementById('page-subtitle').innerText = 'Loyalty Rewards Balance';
</script>

<?php require_once '../../includes/footer.php'; ?>