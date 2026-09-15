<?php
// super_admin/appointments.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

// PRG PATTERN: Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $apt_id = (int)$_POST['appointment_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $apt_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Appointment status updated to " . htmlspecialchars($new_status);
        } else {
            $_SESSION['flash_error'] = "Failed to update status.";
        }
        $stmt->close();
    }
    header("Location: appointments.php");
    exit;
}

// Service Image Resolver Helper
function getServiceThumb($image_filename, $service_name = '', $category = '') {
    global $base_url;
    if (!empty($image_filename)) {
        $physical_path = dirname(__DIR__) . '/assets/images/services/' . $image_filename;
        if (file_exists($physical_path)) {
            return $base_url . '/assets/images/services/' . htmlspecialchars($image_filename);
        }
    }
    $keyword = strtolower($service_name . ' ' . $category);
    if (strpos($keyword, 'hair') !== false || strpos($keyword, 'cut') !== false) {
        return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=150&q=80';
    } elseif (strpos($keyword, 'bridal') !== false || strpos($keyword, 'makeup') !== false) {
        // Loads your local bridal_makeup.png file
        return $base_url . '/assets/images/bridal_makeup.png';
    } elseif (strpos($keyword, 'facial') !== false || strpos($keyword, 'skin') !== false) {
        return 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=150&q=80';
    }
    return 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=150&q=80';
}

// Fetch all appointments
$appointments = [];
try {
    $query = "
        SELECT a.id, a.appointment_date, DATE_FORMAT(a.appointment_time, '%h:%i %p') as apt_time, a.status, a.notes,
               c.name as customer_name, c.mobile,
               s.name as service_name, s.category as service_category, s.image as service_image,
               u.name as staff_name
        FROM appointments a
        LEFT JOIN customers c ON a.customer_id = c.id
        LEFT JOIN catalog_items s ON a.service_id = s.id
        LEFT JOIN users u ON a.staff_id = u.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 150
    ";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (empty($row['customer_name'])) {
                $u_stmt = $conn->prepare("SELECT name, phone FROM users WHERE id = (SELECT customer_id FROM appointments WHERE id = ?)");
                $u_stmt->bind_param("i", $row['id']);
                $u_stmt->execute();
                $u_res = $u_stmt->get_result()->fetch_assoc();
                if ($u_res) {
                    $row['customer_name'] = $u_res['name'];
                    $row['mobile'] = $u_res['phone'];
                }
                $u_stmt->close();
            }
            $row['thumb'] = getServiceThumb($row['service_image'], $row['service_name'], $row['service_category'] ?? '');
            $appointments[] = $row;
        }
    }
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<!-- Skeleton Loader -->
<div id="page-loader" class="fixed inset-0 bg-[#FAFAFA] z-[100] flex flex-col items-center justify-center transition-opacity duration-500">
    <div class="animate-pulse flex flex-col items-center">
        <div class="w-16 h-16 bg-pink-100 rounded-full mb-4 flex items-center justify-center border border-pink-200">
            <i class="fa-regular fa-calendar-check text-brand-magenta text-2xl"></i>
        </div>
        <div class="h-4 bg-gray-200 rounded w-48 mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-32"></div>
    </div>
</div>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">All Appointments</h2>
        <p class="text-gray-500 text-sm mt-1">Global view of salon bookings with assigned service photography.</p>
    </div>
    <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="appointmentSearch" placeholder="Search customer, service, or staff..." class="w-full sm:w-80 pl-10 pr-4 py-2.5 bg-white border border-pink-50 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-pink shadow-[0_2px_10px_rgba(0,0,0,0.02)] transition-smooth">
    </div>
</div>

<div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[760px]">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold border-b border-gray-50">
                    <th class="px-6 py-4">Date & Time</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Service</th>
                    <th class="px-6 py-4">Assigned Staff</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50" id="appointmentTableBody">
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="6" class="px-6 py-16 text-center text-gray-400">No appointments found in the system.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                        <tr class="hover:bg-[#FDF0F3]/30 transition-colors apt-row">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div>
                                <div class="text-[10px] font-bold text-brand-magenta uppercase tracking-wider mt-0.5"><?= $apt['apt_time'] ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-700 apt-customer"><?= htmlspecialchars($apt['customer_name'] ?? 'Unknown') ?></div>
                                <div class="text-[10px] text-gray-500 mt-0.5"><i class="fa-solid fa-phone text-gray-300 mr-1"></i><?= htmlspecialchars($apt['mobile'] ?? 'N/A') ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= $apt['thumb'] ?>" class="w-10 h-10 rounded-lg object-cover shadow-sm border border-gray-100 shrink-0" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=150&q=80'">
                                    <div>
                                        <div class="font-bold text-gray-800 apt-service"><?= htmlspecialchars($apt['service_name'] ?? 'Custom Service') ?></div>
                                        <?php if (!empty($apt['notes'])): ?>
                                            <div class="text-[10px] text-gray-400 mt-0.5 italic line-clamp-1" title="<?= htmlspecialchars($apt['notes']) ?>">
                                                <i class="fa-regular fa-comment-dots mr-1"></i><?= htmlspecialchars($apt['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-lg text-[10px] font-bold border border-purple-100 apt-staff">
                                    <?= htmlspecialchars($apt['staff_name'] ?? 'Unassigned') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $s_color = 'bg-gray-100 text-gray-600 border-gray-200';
                                    if(in_array($apt['status'], ['Confirmed', 'Checked In'])) $s_color = 'bg-blue-50 text-blue-600 border-blue-100';
                                    if($apt['status'] === 'In Progress') $s_color = 'bg-amber-50 text-amber-600 border-amber-100';
                                    if($apt['status'] === 'Completed') $s_color = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                    if(in_array($apt['status'], ['Cancelled', 'No Show'])) $s_color = 'bg-rose-50 text-rose-600 border-rose-100';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $s_color ?>">
                                    <?= $apt['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="appointments.php" class="inline-block">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg p-1.5 bg-white font-semibold text-brand-sidebar focus:outline-none focus:border-brand-magenta cursor-pointer hover:border-brand-pink transition-colors">
                                        <option value="" disabled selected>Update</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Confirmed">Confirmed</option>
                                        <option value="Checked In">Checked In</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                        <option value="No Show">No Show</option>
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

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Appointments' : null;
    
    window.addEventListener('load', () => {
        setTimeout(() => {
            const loader = document.getElementById('page-loader');
            if(loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.remove(), 500);
            }
        }, 200);
    });

    document.getElementById('appointmentSearch').addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.apt-row').forEach(row => {
            const customer = row.querySelector('.apt-customer')?.innerText.toLowerCase() || '';
            const staff = row.querySelector('.apt-staff')?.innerText.toLowerCase() || '';
            const service = row.querySelector('.apt-service')?.innerText.toLowerCase() || '';
            if (customer.includes(query) || staff.includes(query) || service.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>