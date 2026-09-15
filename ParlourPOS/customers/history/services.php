<?php
// customers/history/services.php
session_name('ParlourPOS_Customer'); // CRITICAL: Isolates customer session
session_start();

require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

// Ensure user is strictly logged in as a Customer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch completed services history
$services_history = [];
try {
    // Query fetches appointments that have been marked as 'Completed'
    $stmt = $conn->prepare("
        SELECT 
            a.appointment_date, 
            c.name as service_name, 
            c.category, 
            c.price, 
            u.name as beautician_name
        FROM appointments a
        JOIN catalog_items c ON a.service_id = c.id
        LEFT JOIN users u ON a.staff_id = u.id
        WHERE a.customer_id = ? AND a.status = 'Completed'
        ORDER BY a.appointment_date DESC
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $services_history[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Failsafe in case of database schema issues
}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Service History</h3>
        <p class="text-sm text-gray-500 mt-1">A complete record of treatments and services you've received.</p>
    </div>
    <a href="appointments.php" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-medium shadow-sm transition-colors flex items-center gap-2 w-full sm:w-auto justify-center">
        <i class="fa-regular fa-calendar-check"></i> View Appointments
    </a>
</div>

<div class="pos-card overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date</th>
                    <th class="px-6 py-4">Service & Category</th>
                    <th class="px-6 py-4">Beautician</th>
                    <th class="px-6 py-4 text-right">Price Paid</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($services_history)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center">
                            <div class="text-gray-300 mb-3"><i class="fa-solid fa-spa text-4xl"></i></div>
                            <p class="text-gray-400 font-medium">No completed services found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($services_history as $service): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800">
                                    <?= date('d M Y', strtotime($service['appointment_date'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900"><?= htmlspecialchars($service['service_name']) ?></div>
                                <div class="text-[10px] font-bold text-brand-coral uppercase tracking-wider mt-0.5">
                                    <?= htmlspecialchars($service['category'] ?? 'General') ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 font-medium">
                                <?= htmlspecialchars($service['beautician_name'] ?? 'Unassigned') ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="font-bold text-gray-800">₹<?= number_format($service['price'], 2) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Dynamically update the header titles via Javascript to match the screenshot layout
    document.getElementById('page-title').innerText = 'Service History';
    document.getElementById('page-subtitle').innerText = 'Past Treatments & Visits';
</script>

<?php require_once '../../includes/footer.php'; ?>