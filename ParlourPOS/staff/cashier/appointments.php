<?php
// staff/cashier/appointments.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']); 

// Database initialization for appointments if not exists[cite: 2]
$conn->query("CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Checked In', 'In Progress', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ==========================================================
// PRG PATTERN FOR APPOINTMENTS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_appointment') {
        $customer_id = (int)$_POST['customer_id'];
        $service_id = (int)$_POST['service_id'];
        $staff_id = (int)$_POST['staff_id'];
        $apt_date = $_POST['appointment_date'];
        $apt_time = $_POST['appointment_time'];
        $notes = trim($_POST['notes']);

        // Basic double-booking check[cite: 2]
        $check_stmt = $conn->prepare("SELECT id FROM appointments WHERE staff_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN ('Cancelled', 'Completed', 'No Show')");
        $check_stmt->bind_param("iss", $staff_id, $apt_date, $apt_time);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Staff member is already booked at this time.";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (customer_id, service_id, staff_id, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, ?, 'Confirmed', ?)");
            $stmt->bind_param("iiisss", $customer_id, $service_id, $staff_id, $apt_date, $apt_time, $notes);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Appointment booked successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to book appointment.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    elseif ($action === 'update_status') {
        $apt_id = (int)$_POST['appointment_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $apt_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Status updated to " . htmlspecialchars($new_status);
        }
        $stmt->close();
    }

    header("Location: appointments.php");
    exit;
}

// Fetch Dropdown Data
$customers = $conn->query("SELECT id, name, mobile FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$services = $conn->query("SELECT id, name, price FROM catalog_items WHERE type='Service' AND status='Active'")->fetch_all(MYSQLI_ASSOC);
$staff = $conn->query("SELECT id, name FROM users WHERE role IN ('Beautician', 'Manager') AND status='Active'")->fetch_all(MYSQLI_ASSOC);

// Fetch Appointments for Today & Future
$appointments = [];
$query = "
    SELECT a.id, a.appointment_date, DATE_FORMAT(a.appointment_time, '%h:%i %p') as apt_time, a.status,
           c.name as customer_name, c.mobile,
           s.name as service_name,
           u.name as staff_name
    FROM appointments a
    JOIN customers c ON a.customer_id = c.id
    JOIN catalog_items s ON a.service_id = s.id
    JOIN users u ON a.staff_id = u.id
    WHERE a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
";
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) $appointments[] = $row;
}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Appointments</h3>
        <p class="text-sm text-gray-500 mt-1">Manage salon bookings and schedules[cite: 2]</p>
    </div>
    <button onclick="openModal('addAppointmentModal')" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-calendar-plus"></i> New Booking
    </button>
</div>

<div class="pos-card p-2">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-400 text-[11px] uppercase tracking-wider font-bold">
                    <th class="px-4 py-3 rounded-tl-lg">Date & Time</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Service</th>
                    <th class="px-4 py-3">Beautician</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right rounded-tr-lg">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">No upcoming appointments.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-4">
                                <div class="font-bold text-gray-800"><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div>
                                <div class="text-[11px] font-semibold text-brand-coral mt-0.5"><?= $apt['apt_time'] ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-bold text-gray-700"><?= htmlspecialchars($apt['customer_name']) ?></div>
                                <div class="text-[10px] text-gray-500"><?= htmlspecialchars($apt['mobile']) ?></div>
                            </td>
                            <td class="px-4 py-4 font-medium text-gray-600"><?= htmlspecialchars($apt['service_name']) ?></td>
                            <td class="px-4 py-4">
                                <span class="px-2 py-1 bg-indigo-50 text-indigo-600 rounded text-xs font-semibold border border-indigo-100">
                                    <i class="fa-solid fa-user-tie mr-1"></i> <?= htmlspecialchars($apt['staff_name']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <?php 
                                    $s_color = 'bg-gray-100 text-gray-600';
                                    if(in_array($apt['status'], ['Confirmed', 'Checked In'])) $s_color = 'bg-blue-50 text-blue-600';
                                    if($apt['status'] === 'In Progress') $s_color = 'bg-amber-50 text-amber-600';
                                    if($apt['status'] === 'Completed') $s_color = 'bg-emerald-50 text-emerald-600';
                                    if(in_array($apt['status'], ['Cancelled', 'No Show'])) $s_color = 'bg-rose-50 text-rose-600';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $s_color ?>">
                                    <?= $apt['status'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <!-- Status Update Dropdown Form -->
                                <form method="POST" action="appointments.php" class="inline-block">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded p-1 bg-white focus:outline-none cursor-pointer">
                                        <option value="" disabled selected>Update</option>
                                        <option value="Checked In">Check In</option>
                                        <option value="In Progress">Start Service</option>
                                        <option value="Completed">Complete</option>
                                        <option value="Cancelled">Cancel</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="addAppointmentModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addAppointmentModal')"></div>
    <div class="bg-white w-full max-w-[500px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addAppointmentModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-900 text-lg">Book Appointment</h3>
            <button onclick="closeModal('addAppointmentModal')" class="text-gray-400 hover:text-rose-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="appointments.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create_appointment">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Select Customer</label>
                <select name="customer_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
                    <option value="" disabled selected>Select Customer...</option>
                    <?php foreach($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['mobile']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Service</label>
                    <select name="service_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
                        <option value="" disabled selected>Choose Service...</option>
                        <?php foreach($services as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (₹<?= $s['price'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Staff Assignment</label>
                    <select name="staff_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
                        <option value="" disabled selected>Select Beautician...</option>
                        <?php foreach($staff as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Date</label>
                    <input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Time</label>
                    <input type="time" name="appointment_time" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Notes</label>
                <input type="text" name="notes" placeholder="Optional notes..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-brand-sidebar">
            </div>

            <button type="submit" class="w-full py-3 mt-2 bg-brand-sidebar text-white rounded-lg font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Confirm Booking</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Appointments';
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