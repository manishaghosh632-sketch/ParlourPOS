<?php
// customers/dashboard.php
session_name('ParlourPOS_Customer'); // CRITICAL: Isolates customer session
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Ensure user is strictly logged in as a Customer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];
$first_name = explode(' ', trim($_SESSION['name']))[0];

// ==========================================================
// SERVICE IMAGE RESOLVER FUNCTION
// ==========================================================
function getCustomerDashboardServiceImage($image_filename, $service_name = '', $category = '') {
    global $base_url;
    if (!empty($image_filename)) {
        $physical_path = dirname(__DIR__) . '/assets/images/services/' . $image_filename;
        if (file_exists($physical_path)) {
            return $base_url . '/assets/images/services/' . htmlspecialchars($image_filename);
        }
    }
    
    $keyword = strtolower($service_name . ' ' . $category);
    if (strpos($keyword, 'hair') !== false || strpos($keyword, 'cut') !== false || strpos($keyword, 'styling') !== false) {
        return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=500&q=80';
    } elseif (strpos($keyword, 'bridal') !== false || strpos($keyword, 'makeup') !== false) {
        // Loads your local bridal_makeup.png file
        return $base_url . '/assets/images/bridal_makeup.png';
    } elseif (strpos($keyword, 'facial') !== false || strpos($keyword, 'glow') !== false || strpos($keyword, 'skin') !== false) {
        return 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=500&q=80';
    } elseif (strpos($keyword, 'nail') !== false || strpos($keyword, 'manicure') !== false) {
        return 'https://images.unsplash.com/photo-1632345031435-8727f6897d53?auto=format&fit=crop&w=500&q=80';
    } elseif (strpos($keyword, 'spa') !== false || strpos($keyword, 'massage') !== false) {
        return 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=500&q=80';
    }
    return 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=500&q=80';
}

// Fetch Dashboard Metrics
$metrics = ['total_spent' => 0, 'upcoming_appointments' => 0, 'loyalty_points' => 0];
try {
    $apt_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE customer_id = ? AND appointment_date >= CURDATE() AND status IN ('Pending', 'Confirmed')");
    $apt_stmt->bind_param("i", $customer_id);
    $apt_stmt->execute();
    $metrics['upcoming_appointments'] = $apt_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $apt_stmt->close();

    $spent_stmt = $conn->prepare("SELECT SUM(grand_total) as total FROM invoices WHERE customer_id = ? AND payment_status = 'Paid'");
    $spent_stmt->bind_param("i", $customer_id);
    $spent_stmt->execute();
    $metrics['total_spent'] = $spent_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $spent_stmt->close();

    $pts_stmt = $conn->prepare("SELECT loyalty_points FROM customers WHERE id = ?");
    $pts_stmt->bind_param("i", $customer_id);
    $pts_stmt->execute();
    $metrics['loyalty_points'] = $pts_stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0;
    $pts_stmt->close();
} catch (Exception $e) {}

// Fetch Single Upcoming Appointment
$next_appointment = null;
try {
    $rec_stmt = $conn->prepare("
        SELECT a.appointment_date, DATE_FORMAT(a.appointment_time, '%h:%i %p') as time, a.status, s.name as service_name, s.category as service_category, s.image
        FROM appointments a
        JOIN catalog_items s ON a.service_id = s.id
        WHERE a.customer_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('Pending', 'Confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1
    ");
    $rec_stmt->bind_param("i", $customer_id);
    $rec_stmt->execute();
    $next_appointment = $rec_stmt->get_result()->fetch_assoc();
    $rec_stmt->close();
    
    if ($next_appointment) {
        $next_appointment['image_url'] = getCustomerDashboardServiceImage($next_appointment['image'], $next_appointment['service_name'], $next_appointment['service_category'] ?? '');
    }
} catch (Exception $e) {}

// DYNAMICALLY FETCH SERVICES ADDED BY ADMIN
$active_services = [];
$recommended_services = [];
try {
    $svc_res = $conn->query("SELECT id, name, category, price, image FROM catalog_items WHERE type='Service' AND status='Active' ORDER BY id DESC LIMIT 5");
    if ($svc_res) {
        while($row = $svc_res->fetch_assoc()) {
            $row['image_url'] = getCustomerDashboardServiceImage($row['image'], $row['name'], $row['category']);
            $active_services[] = $row;
        }
    }
    
    // Fetch 2 random services for recommendations
    $rec_svc_res = $conn->query("SELECT id, name, category, price, image FROM catalog_items WHERE type='Service' AND status='Active' ORDER BY RAND() LIMIT 2");
    if ($rec_svc_res) {
        while($row = $rec_svc_res->fetch_assoc()) {
            $row['image_url'] = getCustomerDashboardServiceImage($row['image'], $row['name'], $row['category']);
            $recommended_services[] = $row;
        }
    }
} catch (Exception $e) {}

// Includes header
require_once '../includes/header.php';
?>

<!-- Top Banner Section -->
<div class="flex flex-col lg:flex-row justify-between gap-6 mb-8">
    <div class="flex-1 flex flex-col justify-center">
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Welcome Back, <?= htmlspecialchars($first_name) ?>! ✨</h2>
        <p class="text-gray-500 text-sm mt-1">Book your beauty, because you deserve it!</p>
    </div>
    
    <!-- Decorative Banner -->
    <div class="w-full lg:w-[450px] bg-[#FDF0F3] rounded-2xl p-5 flex items-center justify-between border border-pink-100 shadow-sm relative overflow-hidden h-28">
        <div class="relative z-10 pl-2">
            <p class="font-cursive text-gray-800 text-3xl leading-none drop-shadow-sm">"Beauty<br>Looks Good<br>On You" <i class="fa-regular fa-heart text-brand-magenta text-sm ml-1"></i></p>
        </div>
        <div class="absolute right-0 top-0 bottom-0 w-48 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1616394584738-fc6e612e71c9?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'); -webkit-mask-image: linear-gradient(to right, transparent, black 40%);"></div>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Upcoming Visits -->
    <div class="bg-[#FCF3F6] p-5 rounded-2xl border border-pink-50 relative overflow-hidden group hover:shadow-md transition-all">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-pink-200 text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-regular fa-calendar-check"></i></div>
            <div class="flex-1 z-10">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Upcoming Visits</p>
                <h4 class="text-3xl font-black text-gray-800 mb-2"><?= $metrics['upcoming_appointments'] ?></h4>
                <a href="history/appointments.php" class="text-xs font-bold text-gray-600 hover:text-brand-magenta transition-colors">View Details &rarr;</a>
            </div>
        </div>
        <i class="fa-solid fa-calendar-days absolute -right-6 -bottom-4 text-7xl text-pink-200 opacity-60 transform group-hover:scale-110 transition-transform duration-300"></i>
    </div>
    
    <!-- Lifetime Spent -->
    <div class="bg-[#F4F0FE] p-5 rounded-2xl border border-purple-50 relative overflow-hidden group hover:shadow-md transition-all">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-purple-200 text-purple-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wallet"></i></div>
            <div class="flex-1 z-10">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Lifetime Spent</p>
                <h4 class="text-3xl font-black text-gray-800 mb-2">₹<?= number_format($metrics['total_spent'], 2) ?></h4>
                <a href="history/billing.php" class="text-xs font-bold text-gray-600 hover:text-purple-600 transition-colors">View Transactions &rarr;</a>
            </div>
        </div>
        <i class="fa-solid fa-coins absolute -right-4 -bottom-4 text-7xl text-purple-200 opacity-60 transform group-hover:scale-110 transition-transform duration-300"></i>
    </div>

    <!-- Loyalty Points -->
    <div class="bg-[#FFF4E6] p-5 rounded-2xl border border-orange-50 relative overflow-hidden group hover:shadow-md transition-all">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-orange-200 text-orange-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-star"></i></div>
            <div class="flex-1 z-10">
                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1">Loyalty Points</p>
                <h4 class="text-3xl font-black text-gray-800 mb-2"><?= $metrics['loyalty_points'] ?></h4>
                <a href="loyalty/points.php" class="text-xs font-bold text-gray-600 hover:text-orange-600 transition-colors">Redeem Now &rarr;</a>
            </div>
        </div>
        <i class="fa-solid fa-gift absolute -right-4 -bottom-4 text-7xl text-orange-200 opacity-60 transform group-hover:scale-110 transition-transform duration-300"></i>
    </div>
</div>

<!-- Our Services Carousel (Dynamic) -->
<div class="mb-8">
    <div class="flex justify-between items-center mb-4">
        <h4 class="font-bold text-gray-800 text-lg">Our Services</h4>
        <a href="book_appointment.php" class="text-xs font-bold text-brand-magenta hover:underline">See All &rarr;</a>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <?php if(empty($active_services)): ?>
            <div class="col-span-full text-gray-400 text-sm p-4">No services available currently.</div>
        <?php else: ?>
            <?php foreach($active_services as $svc): ?>
                <div class="bg-white rounded-2xl p-2 border border-pink-50 shadow-sm hover:shadow-md transition-all group">
                    <div class="h-32 rounded-xl overflow-hidden mb-3 bg-gray-100 flex items-center justify-center relative">
                        <img src="<?= $svc['image_url'] ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'">
                    </div>
                    <div class="px-2 pb-2 flex justify-between items-center">
                        <div>
                            <h5 class="font-bold text-gray-800 text-sm line-clamp-1"><?= htmlspecialchars($svc['name']) ?></h5>
                            <p class="text-[10px] text-gray-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($svc['category'] ?: 'Beauty') ?></p>
                        </div>
                        <a href="book_appointment.php?service_id=<?= $svc['id'] ?>" class="w-6 h-6 rounded-full bg-pink-100 text-brand-magenta flex items-center justify-center text-xs hover:bg-brand-magenta hover:text-white transition-colors shrink-0"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Grid for Upcoming & Loyalty -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">
    
    <!-- Upcoming Appointment -->
    <div class="lg:col-span-3">
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-bold text-gray-800 text-lg">Upcoming Appointment</h4>
            <a href="history/appointments.php" class="text-xs font-bold text-brand-magenta hover:underline">View All &rarr;</a>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-pink-50 shadow-sm flex flex-col sm:flex-row items-center gap-6 relative">
            <?php if(!$next_appointment): ?>
                <div class="w-full text-center py-6 text-gray-400 text-sm">No upcoming appointments. <a href="book_appointment.php" class="text-brand-magenta font-bold">Book now</a></div>
            <?php else: ?>
                <!-- Date Box -->
                <div class="bg-[#FDF0F3] rounded-xl px-6 py-4 text-center shrink-0 border border-pink-100">
                    <span class="block text-3xl font-black text-brand-magenta leading-none mb-1"><?= date('d', strtotime($next_appointment['appointment_date'])) ?></span>
                    <span class="block text-xs font-bold text-gray-800"><?= date('M Y', strtotime($next_appointment['appointment_date'])) ?></span>
                    <span class="block text-[10px] font-medium text-gray-500 mt-1 uppercase tracking-wider"><?= date('l', strtotime($next_appointment['appointment_date'])) ?></span>
                </div>
                <!-- Details -->
                <div class="flex-1 flex items-center gap-4 w-full">
                    <img src="<?= $next_appointment['image_url'] ?>" class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-sm shrink-0" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'">
                    <div>
                        <h5 class="font-bold text-gray-800 text-base"><?= htmlspecialchars($next_appointment['service_name']) ?></h5>
                        <p class="text-xs text-brand-magenta font-bold mt-1 mb-1"><i class="fa-regular fa-clock mr-1"></i> <?= $next_appointment['time'] ?></p>
                        <p class="text-xs text-gray-500"><i class="fa-solid fa-location-dot mr-1"></i> ParlourPOS</p>
                    </div>
                </div>
                <!-- Actions -->
                <div class="shrink-0 flex flex-col items-end gap-3 w-full sm:w-auto">
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-600 rounded text-[10px] font-bold uppercase tracking-wider"><?= $next_appointment['status'] ?></span>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button class="flex-1 sm:flex-none px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 transition-colors">Reschedule</button>
                        <button class="flex-1 sm:flex-none px-4 py-2 bg-brand-magenta text-white rounded-lg text-xs font-bold hover:bg-brand-coralHover transition-colors">Cancel</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Your Loyalty Journey -->
    <div class="lg:col-span-2">
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-bold text-gray-800 text-lg">Your Loyalty Journey</h4>
            <span class="text-xs font-bold text-gray-600"><?= $metrics['loyalty_points'] ?> / 500 Points</span>
        </div>
        <div class="bg-white rounded-2xl p-6 border border-pink-50 shadow-sm relative overflow-hidden h-[132px] flex flex-col justify-center">
            <div class="relative w-[70%] mb-6 mt-2">
                <div class="h-3 w-full bg-[#FDF0F3] rounded-full"></div>
                <?php $pct = min(100, ($metrics['loyalty_points'] / 500) * 100); ?>
                <div class="absolute top-0 left-0 h-3 bg-brand-magenta rounded-full" style="width: <?= $pct ?>%;"></div>
                
                <!-- Markers -->
                <div class="absolute -top-1 left-0 flex flex-col items-center transform -translate-x-1/2">
                    <div class="w-5 h-5 rounded-full bg-brand-magenta border-4 border-white shadow-sm"></div>
                    <span class="text-[10px] font-bold text-gray-500 mt-1">0</span>
                </div>
                <div class="absolute -top-1 left-[40%] flex flex-col items-center transform -translate-x-1/2">
                    <i class="fa-solid fa-gift text-brand-magenta absolute -top-5 text-sm"></i>
                    <div class="w-5 h-5 rounded-full bg-pink-200 border-4 border-white shadow-sm"></div>
                    <span class="text-[10px] font-bold text-gray-500 mt-1">200</span>
                </div>
                <div class="absolute -top-1 right-0 flex flex-col items-center transform translate-x-1/2">
                    <i class="fa-solid fa-gift text-brand-magenta absolute -top-5 text-sm"></i>
                    <div class="w-5 h-5 rounded-full bg-pink-200 border-4 border-white shadow-sm"></div>
                    <span class="text-[10px] font-bold text-gray-500 mt-1">500</span>
                </div>
            </div>
            
            <a href="loyalty/points.php" class="w-[70%] py-2 bg-brand-pink text-brand-magenta rounded-lg text-xs font-bold hover:bg-pink-300 transition-colors text-center block">Earn More Points</a>
            
            <!-- 3D Gift Graphic -->
            <i class="fa-solid fa-gift absolute -right-2 top-1/2 -translate-y-1/2 text-8xl text-pink-100 opacity-60"></i>
            <i class="fa-solid fa-sparkles absolute right-16 top-6 text-xl text-orange-300"></i>
        </div>
    </div>
</div>

<!-- Grid for Recommended & Offers (Dynamic) -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-4">
    
    <!-- Recommended For You -->
    <div class="lg:col-span-3">
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-bold text-gray-800 text-lg">Recommended for You</h4>
            <a href="book_appointment.php" class="text-xs font-bold text-brand-magenta hover:underline">See All &rarr;</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($recommended_services as $r_svc): ?>
                <div class="bg-white rounded-2xl p-3 border border-pink-50 shadow-sm flex items-center gap-4">
                    <img src="<?= $r_svc['image_url'] ?>" class="w-20 h-20 rounded-xl object-cover shrink-0 border border-gray-100" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'">
                    <div class="flex-1">
                        <h5 class="font-bold text-gray-800 text-sm line-clamp-1"><?= htmlspecialchars($r_svc['name']) ?></h5>
                        <p class="text-brand-magenta font-black text-sm mt-0.5 mb-2">₹<?= number_format($r_svc['price'], 2) ?></p>
                        <a href="book_appointment.php?service_id=<?= $r_svc['id'] ?>" class="inline-block px-4 py-1.5 bg-brand-magenta text-white rounded-md text-[10px] font-bold hover:bg-brand-coralHover transition-colors">Book Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Special Offer -->
    <div class="lg:col-span-2">
        <div class="h-[36px] mb-4"></div> <!-- Alignment spacer -->
        <div class="bg-[#FCE7EC] rounded-2xl p-6 border border-pink-100 shadow-sm relative overflow-hidden h-full flex flex-col justify-center">
            <div class="relative z-10">
                <h4 class="font-cursive text-brand-magenta text-2xl mb-1">Special Offer</h4>
                <h3 class="font-serif text-gray-800 text-2xl font-bold mb-1">Flat 20% OFF</h3>
                <p class="text-xs text-gray-600 font-medium mb-4">On Your First Booking</p>
                <a href="book_appointment.php" class="inline-block px-6 py-2.5 bg-brand-sidebar text-white rounded-lg text-xs font-bold hover:bg-gray-900 transition-colors shadow-md">Book Now</a>
            </div>
            <!-- Decorative Background -->
            <i class="fa-solid fa-paintbrush absolute -right-6 -bottom-6 text-8xl text-pink-200 opacity-50 rotate-[-15deg]"></i>
            <i class="fa-regular fa-heart absolute right-24 bottom-6 text-3xl text-brand-pink"></i>
        </div>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Dashboard' : null;
</script>

<?php require_once '../includes/footer.php'; ?>