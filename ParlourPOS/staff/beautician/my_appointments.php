<?php
// staff/beautician/my_appointments.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Beautician']); // Strictly for Beauticians[cite: 2]

$user_id = $_SESSION['user_id'];

// ==========================================================
// PRG PATTERN FOR STATUS UPDATES
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $apt_id = (int)$_POST['appointment_id'];
        $new_status = $_POST['new_status'];
        
        // Ensure the beautician owns this appointment before updating[cite: 2]
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND staff_id = ?");
        $stmt->bind_param("sii", $new_status, $apt_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['flash_success'] = "Appointment status updated to " . htmlspecialchars($new_status) . ".";
        } else {
            $_SESSION['flash_error'] = "Failed to update appointment or unauthorized access.";
        }
        $stmt->close();
    }

    header("Location: my_appointments.php");
    exit;
}

// Fetch Upcoming & Today's Appointments[cite: 2]
$appointments = [];
try {
    $query = "
        SELECT a.id, a.appointment_date, DATE_FORMAT(a.appointment_time, '%h:%i %p') as apt_time, a.status, a.notes,
               c.name as customer_name, c.mobile,
               s.name as service_name, s.price
        FROM appointments a
        JOIN customers c ON a.customer_id = c.id
        JOIN catalog_items s ON a.service_id = s.id
        WHERE a.staff_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h3 class="text-2xl font-bold text-brand-sidebar">My Appointments</h3>
    <p class="text-sm text-gray-500 mt-1">Manage your upcoming service bookings[cite: 2]</p>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-400 text-[11px] uppercase tracking-wider font-bold">
                    <th class="px-6 py-4">Date & Time</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Service Required</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Update Status</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">You have no upcoming appointments.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= date('D, d M Y', strtotime($apt['appointment_date'])) ?></div>
                                <div class="text-[11px] font-semibold text-brand-coral mt-0.5"><?= $apt['apt_time'] ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-700"><?= htmlspecialchars($apt['customer_name']) ?></div>
                                <div class="text-[10px] text-gray-500"><?= htmlspecialchars($apt['mobile']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($apt['service_name']) ?></div>
                                <?php if (!empty($apt['notes'])): ?>
                                    <div class="text-[10px] text-gray-400 mt-1 italic"><i class="fa-solid fa-note-sticky mr-1"></i><?= htmlspecialchars($apt['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $s_color = 'bg-gray-100 text-gray-600';
                                    if(in_array($apt['status'], ['Confirmed', 'Checked In'])) $s_color = 'bg-blue-50 text-blue-600';
                                    if($apt['status'] === 'In Progress') $s_color = 'bg-amber-50 text-amber-600';
                                    if($apt['status'] === 'Completed') $s_color = 'bg-emerald-50 text-emerald-600';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $s_color ?>">
                                    <?= $apt['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if (!in_array($apt['status'], ['Completed', 'Cancelled', 'No Show'])): ?>
                                    <form method="POST" action="my_appointments.php" class="inline-block">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded p-1.5 bg-white font-semibold text-brand-sidebar focus:outline-none cursor-pointer hover:border-brand-coral transition-colors">
                                            <option value="" disabled selected>Update</option>
                                            <option value="In Progress">Start Service</option>
                                            <option value="Completed">Complete</option>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs font-bold text-gray-400"><i class="fa-solid fa-lock"></i> Locked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'My Appointments';
    document.getElementById('page-subtitle').innerText = 'Manage your workflow and status updates';
</script>

<?php require_once '../../includes/footer.php'; ?>