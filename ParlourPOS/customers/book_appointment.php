<?php
// customers/book_appointment.php
session_name('ParlourPOS_Customer'); 
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Service Image Resolver Helper
function getCustomerServiceImage($image_filename, $service_name = '', $category = '') {
    global $base_url;
    if (!empty($image_filename)) {
        $physical_path = dirname(__DIR__) . '/assets/images/services/' . $image_filename;
        if (file_exists($physical_path)) {
            return $base_url . '/assets/images/services/' . htmlspecialchars($image_filename);
        }
    }
    $keyword = strtolower($service_name . ' ' . $category);
    if (strpos($keyword, 'hair') !== false || strpos($keyword, 'cut') !== false) {
        return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80';
    } elseif (strpos($keyword, 'bridal') !== false || strpos($keyword, 'makeup') !== false) {
        // Loads your local bridal_makeup.png file
        return $base_url . '/assets/images/bridal_makeup.png';
    } elseif (strpos($keyword, 'facial') !== false || strpos($keyword, 'skin') !== false) {
        return 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=500&q=80';
    }
    return 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=500&q=80';
}

// PRG Pattern to save the appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_booking') {
    $post_service_id = (int)$_POST['service_id'];
    $staff_id = (int)$_POST['staff_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("INSERT INTO appointments (customer_id, service_id, staff_id, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("iiisss", $customer_id, $post_service_id, $staff_id, $date, $time, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Appointment booked successfully! Awaiting confirmation.";
        header("Location: history/appointments.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Failed to book appointment. Please try again.";
        header("Location: book_appointment.php?service_id=" . $post_service_id);
        exit;
    }
}

// Case 1: Browse All Services
if ($service_id === 0) {
    $all_services = [];
    $s_query = "SELECT id, name, category, price, image FROM catalog_items WHERE type='Service' AND status='Active' ORDER BY category ASC, name ASC";
    $s_res = $conn->query($s_query);
    if($s_res) {
        while($r = $s_res->fetch_assoc()) {
            $r['image_url'] = getCustomerServiceImage($r['image'], $r['name'], $r['category']);
            $all_services[] = $r;
        }
    }
    
    require_once '../includes/header.php';
    ?>
    <!-- Skeleton Loader -->
    <div id="page-loader" class="fixed inset-0 bg-[#FAFAFA] z-[100] flex flex-col items-center justify-center transition-opacity duration-500">
        <div class="animate-pulse flex flex-col items-center">
            <div class="w-16 h-16 bg-pink-100 rounded-full mb-4 flex items-center justify-center border border-pink-200">
                <i class="fa-solid fa-spa text-brand-magenta text-2xl"></i>
            </div>
            <div class="h-4 bg-gray-200 rounded w-48 mb-2"></div>
            <div class="h-3 bg-gray-100 rounded w-32"></div>
        </div>
    </div>

    <div class="mb-6">
        <h3 class="text-3xl font-bold text-gray-800 tracking-tight">Select a Service</h3>
        <p class="text-sm text-gray-500 mt-1">Choose a treatment to begin booking your appointment.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php if(empty($all_services)): ?>
            <div class="col-span-full p-8 text-center text-gray-400">No services are currently available. Please check back later.</div>
        <?php else: ?>
            <?php foreach($all_services as $svc): ?>
                <a href="book_appointment.php?service_id=<?= $svc['id'] ?>" class="bg-white rounded-2xl p-3 border border-pink-50 shadow-sm hover:shadow-md transition-all group block">
                    <div class="h-44 rounded-xl overflow-hidden mb-3 bg-gray-100 flex items-center justify-center relative">
                        <img src="<?= $svc['image_url'] ?>" alt="<?= htmlspecialchars($svc['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80'">
                        <span class="absolute top-2.5 right-2.5 bg-white/90 backdrop-blur-sm px-2.5 py-1 rounded-full text-[10px] font-bold text-brand-sidebar uppercase tracking-wider shadow-sm">
                            <?= htmlspecialchars($svc['category'] ?: 'Beauty') ?>
                        </span>
                    </div>
                    <div class="px-2 pb-2">
                        <h5 class="font-bold text-gray-800 text-sm line-clamp-1 group-hover:text-brand-magenta transition-colors"><?= htmlspecialchars($svc['name']) ?></h5>
                        <p class="text-brand-magenta font-black text-base mt-1.5">&#8377;<?= number_format($svc['price'], 2) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Services' : null;
        window.addEventListener('load', () => {
            setTimeout(() => {
                const loader = document.getElementById('page-loader');
                if(loader) {
                    loader.classList.add('opacity-0');
                    setTimeout(() => loader.remove(), 500);
                }
            }, 200);
        });
    </script>
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Case 2: Specific Service Details & Checkout
$service = null;
$s_stmt = $conn->prepare("SELECT id, name, category, price, image FROM catalog_items WHERE id = ? AND type = 'Service' AND status = 'Active'");
$s_stmt->bind_param("i", $service_id);
$s_stmt->execute();
$service = $s_stmt->get_result()->fetch_assoc();
$s_stmt->close();

if (!$service) {
    $_SESSION['flash_error'] = "Invalid or inactive service selected.";
    header("Location: book_appointment.php");
    exit;
}

$service['image_url'] = getCustomerServiceImage($service['image'], $service['name'], $service['category']);

// Fetch Assigned Beauticians
$beauticians = [];
$b_stmt = $conn->prepare("
    SELECT u.id, u.name 
    FROM users u 
    JOIN service_beauticians sb ON u.id = sb.beautician_id 
    WHERE sb.service_id = ? AND u.status = 'Active'
");
$b_stmt->bind_param("i", $service_id);
$b_stmt->execute();
$b_res = $b_stmt->get_result();
while ($row = $b_res->fetch_assoc()) $beauticians[] = $row;
$b_stmt->close();

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

<div class="mb-6">
    <a href="book_appointment.php" class="text-sm font-bold text-gray-400 hover:text-brand-magenta mb-2 inline-block transition-colors"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Services</a>
    <h3 class="text-3xl font-bold text-gray-800 tracking-tight">Book Appointment</h3>
    <p class="text-sm text-gray-500 mt-1">Select your preferred professional, date, and time.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Service Summary Card with Photo -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden">
            <div class="w-full h-56 bg-gray-100 overflow-hidden">
                <img src="<?= $service['image_url'] ?>" alt="<?= htmlspecialchars($service['name']) ?>" class="w-full h-full object-cover" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80'">
            </div>
            <div class="p-6">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Selected Treatment</p>
                <h4 class="text-xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($service['name']) ?></h4>
                <p class="text-2xl font-black text-brand-magenta">&#8377;<?= number_format($service['price'], 2) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Booking Form -->
    <div class="lg:col-span-2 bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-pink-50">
        <form method="POST" action="book_appointment.php" class="space-y-6">
            <input type="hidden" name="action" value="confirm_booking">
            <input type="hidden" name="service_id" value="<?= $service_id ?>">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Available Professionals <span class="text-brand-magenta">*</span></label>
                <?php if(empty($beauticians)): ?>
                    <div class="p-4 bg-rose-50 text-rose-600 rounded-xl text-sm border border-rose-100 font-medium">
                        No professionals are currently assigned to this service. Please contact the front desk.
                    </div>
                <?php else: ?>
                    <select name="staff_id" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer transition-all">
                        <option value="" disabled selected>Select an Assigned Professional...</option>
                        <?php foreach($beauticians as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Date <span class="text-brand-magenta">*</span></label>
                    <input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Time <span class="text-brand-magenta">*</span></label>
                    <input type="time" name="appointment_time" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Special Requests (Optional)</label>
                <textarea name="notes" rows="3" placeholder="Any specific requirements or allergies..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all resize-none"></textarea>
            </div>

            <button type="submit" <?= empty($beauticians) ? 'disabled' : '' ?> class="w-full py-4 bg-brand-sidebar disabled:bg-gray-300 text-white rounded-xl font-bold tracking-wide shadow-md hover:bg-brand-sidebar/90 transition-colors mt-4">
                Confirm Appointment
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Book Appointment' : null;
    window.addEventListener('load', () => {
        setTimeout(() => {
            const loader = document.getElementById('page-loader');
            if(loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.remove(), 500);
            }
        }, 200);
    });
</script>

<?php require_once '../includes/footer.php'; ?>