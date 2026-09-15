<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['name'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

// ==========================================
// DYNAMIC NOTIFICATION ENGINE
// ==========================================
$notifications = [];
if (isset($conn)) {
    try {
        // 1. Admins & Managers: Low Stock Alerts
        if (in_array($role, ['Super Admin', 'Manager'])) {
            $ns = $conn->query("SELECT name, stock FROM catalog_items WHERE type='Product' AND stock <= 5 LIMIT 3");
            if ($ns) {
                while ($r = $ns->fetch_assoc()) {
                    $notifications[] = [
                        'icon' => 'fa-triangle-exclamation text-rose-500 bg-rose-50', 
                        'title' => 'Low Stock Alert', 
                        'desc' => "{$r['name']} has only {$r['stock']} units left.", 
                        'time' => 'Action Required'
                    ];
                }
            }
        }
        
        // 2. Admins & Receptionists: Pending Appointments
        if (in_array($role, ['Super Admin', 'Receptionist'])) {
            $ns = $conn->query("SELECT appointment_date, appointment_time FROM appointments WHERE status='Pending' ORDER BY created_at DESC LIMIT 3");
            if ($ns) {
                while ($r = $ns->fetch_assoc()) {
                    $notifications[] = [
                        'icon' => 'fa-calendar-plus text-blue-500 bg-blue-50', 
                        'title' => 'New Appointment Request', 
                        'desc' => "Pending request for " . date('d M', strtotime($r['appointment_date'])), 
                        'time' => date('h:i A', strtotime($r['appointment_time']))
                    ];
                }
            }
        }
        
        // 3. Beauticians: Upcoming Assigned Services
        if ($role === 'Beautician') {
            $ns = $conn->query("SELECT appointment_date, appointment_time FROM appointments WHERE staff_id=$user_id AND status='Confirmed' AND appointment_date >= CURDATE() ORDER BY appointment_date ASC LIMIT 4");
            if ($ns) {
                while ($r = $ns->fetch_assoc()) {
                    $notifications[] = [
                        'icon' => 'fa-spa text-emerald-500 bg-emerald-50', 
                        'title' => 'Upcoming Service', 
                        'desc' => "You have a confirmed booking on " . date('d M', strtotime($r['appointment_date'])), 
                        'time' => date('h:i A', strtotime($r['appointment_time']))
                    ];
                }
            }
        }
        
        // 4. Customers: Status Updates on their Appointments
        if ($role === 'Customer') {
            $ns = $conn->query("SELECT status, appointment_date FROM appointments WHERE customer_id=$user_id ORDER BY id DESC LIMIT 3");
            if ($ns) {
                while ($r = $ns->fetch_assoc()) {
                    $icon = $r['status'] == 'Confirmed' ? 'fa-check text-emerald-500 bg-emerald-50' : ($r['status'] == 'Pending' ? 'fa-clock text-amber-500 bg-amber-50' : 'fa-bell text-brand-magenta bg-pink-50');
                    $notifications[] = [
                        'icon' => $icon, 
                        'title' => 'Appointment Status', 
                        'desc' => "Your visit on " . date('d M', strtotime($r['appointment_date'])) . " is " . $r['status'], 
                        'time' => 'System Update'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silently ignore DB errors if a table doesn't exist yet
    }
}
$notif_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">ParlourPOS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;900&family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Montserrat', 'sans-serif'], 
                        serif: ['"Playfair Display"', 'serif'], 
                        cursive: ['"Great Vibes"', 'cursive'] 
                    },
                    colors: { 
                        'brand-sidebar': '#2A1320', 
                        'brand-pink': '#F4D8E2', 
                        'brand-magenta': '#D81B60', 
                        'brand-coral': '#E07A5F' 
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #FAFAFA; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .transition-smooth { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="flex h-screen overflow-hidden font-sans text-gray-800 antialiased">
    
    <!-- Sidebar -->
    <?php require_once 'sidebar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-[#FAFAFA] relative">
        
        <!-- TOP HEADER BAR -->
        <header class="h-[80px] bg-[#FAFAFA] flex items-center justify-between px-6 lg:px-10 shrink-0 z-40 relative">
            
            <!-- Left: Mobile Toggle & Flash Messages -->
            <div class="flex items-center gap-4">
                <button id="mobile-menu-btn" class="lg:hidden text-gray-500 hover:text-brand-magenta transition-colors">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div id="flash-msg" class="hidden md:flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-600 rounded-full text-xs font-bold border border-emerald-100 animate-[pulse_2s_ease-in-out_infinite]">
                        <i class="fa-solid fa-circle-check"></i> <?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div id="flash-msg" class="hidden md:flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-600 rounded-full text-xs font-bold border border-rose-100 animate-[pulse_2s_ease-in-out_infinite]">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Notifications & Profile -->
            <div class="flex items-center gap-5">
                
                <!-- NOTIFICATION BELL -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-brand-magenta hover:border-brand-magenta transition-colors shadow-sm relative">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($notif_count > 0): ?>
                            <span class="absolute top-0 right-0 w-3 h-3 bg-rose-500 border-2 border-white rounded-full animate-pulse"></span>
                        <?php endif; ?>
                    </button>

                    <!-- NOTIFICATION DROPDOWN -->
                    <div id="notif-dropdown" class="absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-[0_10px_40px_rgba(0,0,0,0.08)] border border-gray-100 hidden flex-col overflow-hidden origin-top-right transition-all duration-200 scale-95 opacity-0">
                        <div class="px-5 py-4 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                            <h3 class="font-bold text-gray-800 text-sm">Notifications</h3>
                            <?php if ($notif_count > 0): ?>
                                <span class="bg-brand-magenta text-white text-[9px] font-bold px-2 py-0.5 rounded-full"><?= $notif_count ?> New</span>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-[320px] overflow-y-auto no-scrollbar">
                            <?php if (empty($notifications)): ?>
                                <div class="p-8 text-center text-gray-400 text-sm">
                                    <i class="fa-regular fa-bell-slash text-3xl mb-3 opacity-50"></i>
                                    <p class="font-medium">You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="px-5 py-4 border-b border-gray-50 hover:bg-[#FDF0F3]/50 transition-colors flex gap-4 items-start cursor-default">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border border-gray-100 <?= $n['icon'] ?>"></div>
                                        <div>
                                            <h4 class="text-xs font-bold text-gray-800 mb-0.5"><?= $n['title'] ?></h4>
                                            <p class="text-[11px] text-gray-500 leading-snug"><?= $n['desc'] ?></p>
                                            <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider mt-1.5 block"><?= $n['time'] ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- USER PROFILE MENU -->
                <div class="flex items-center gap-3 relative cursor-pointer" onclick="toggleProfileDropdown()">
                    <div class="w-10 h-10 rounded-full bg-brand-magenta text-white flex items-center justify-center font-serif font-bold text-lg shadow-md border-2 border-pink-100">
                        <?= strtoupper(substr($user_name, 0, 2)) ?>
                    </div>
                    <div class="hidden md:block">
                        <div class="text-sm font-bold text-gray-800 leading-tight"><?= htmlspecialchars($user_name) ?></div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wider font-bold"><?= htmlspecialchars($role) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down text-xs text-gray-400 ml-1 hidden md:block"></i>

                    <!-- Dropdown Content -->
                    <div id="profile-dropdown" class="absolute top-full right-0 mt-3 w-48 bg-white rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.08)] border border-gray-100 hidden flex-col overflow-hidden origin-top-right transition-all duration-200 scale-95 opacity-0">
                        <?php if ($role === 'Customer'): ?>
                            <a href="<?= $base_url ?>/customers/profile.php" class="px-4 py-3 text-sm text-gray-600 hover:bg-pink-50 hover:text-brand-magenta font-medium flex items-center gap-2 border-b border-gray-50"><i class="fa-regular fa-user w-4 text-center"></i> Profile Settings</a>
                        <?php else: ?>
                            <div class="px-4 py-3 text-[10px] text-gray-400 font-bold border-b border-gray-50 uppercase tracking-wider bg-gray-50/50">Staff Account</div>
                        <?php endif; ?>
                        <a href="<?= $role === 'Customer' ? $base_url.'/customers/logout.php' : $base_url.'/auth/logout.php' ?>" class="px-4 py-3 text-sm text-rose-500 hover:bg-rose-50 font-bold flex items-center gap-2"><i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i> Sign Out</a>
                    </div>
                </div>

            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="flex-1 overflow-y-auto no-scrollbar p-6 lg:p-10 pb-24 relative z-10">
            
            <script>
                // Dropdown Toggle Logic
                function toggleNotifications() {
                    const drop = document.getElementById('notif-dropdown');
                    const prof = document.getElementById('profile-dropdown');
                    
                    if(!prof.classList.contains('hidden')) {
                        prof.classList.remove('scale-100', 'opacity-100');
                        prof.classList.add('scale-95', 'opacity-0');
                        setTimeout(() => prof.classList.add('hidden'), 150);
                    }

                    if (drop.classList.contains('hidden')) {
                        drop.classList.remove('hidden');
                        setTimeout(() => { drop.classList.remove('scale-95', 'opacity-0'); drop.classList.add('scale-100', 'opacity-100'); }, 10);
                    } else {
                        drop.classList.remove('scale-100', 'opacity-100');
                        drop.classList.add('scale-95', 'opacity-0');
                        setTimeout(() => drop.classList.add('hidden'), 200);
                    }
                }

                function toggleProfileDropdown() {
                    const drop = document.getElementById('profile-dropdown');
                    const notif = document.getElementById('notif-dropdown');

                    if(!notif.classList.contains('hidden')) {
                        notif.classList.remove('scale-100', 'opacity-100');
                        notif.classList.add('scale-95', 'opacity-0');
                        setTimeout(() => notif.classList.add('hidden'), 150);
                    }

                    if (drop.classList.contains('hidden')) {
                        drop.classList.remove('hidden');
                        setTimeout(() => { drop.classList.remove('scale-95', 'opacity-0'); drop.classList.add('scale-100', 'opacity-100'); }, 10);
                    } else {
                        drop.classList.remove('scale-100', 'opacity-100');
                        drop.classList.add('scale-95', 'opacity-0');
                        setTimeout(() => drop.classList.add('hidden'), 200);
                    }
                }

                // Close dropdowns when clicking outside
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.relative')) {
                        const notifDrop = document.getElementById('notif-dropdown');
                        const profDrop = document.getElementById('profile-dropdown');
                        if (!notifDrop.classList.contains('hidden')) {
                            notifDrop.classList.remove('scale-100', 'opacity-100');
                            notifDrop.classList.add('scale-95', 'opacity-0');
                            setTimeout(() => notifDrop.classList.add('hidden'), 200);
                        }
                        if (!profDrop.classList.contains('hidden')) {
                            profDrop.classList.remove('scale-100', 'opacity-100');
                            profDrop.classList.add('scale-95', 'opacity-0');
                            setTimeout(() => profDrop.classList.add('hidden'), 200);
                        }
                    }
                });

                // Mobile Menu Logic
                const mobileBtn = document.getElementById('mobile-menu-btn');
                if (mobileBtn) {
                    mobileBtn.addEventListener('click', () => {
                        const sidebar = document.getElementById('app-sidebar');
                        if (sidebar) {
                            if (sidebar.classList.contains('-translate-x-full')) {
                                sidebar.classList.remove('-translate-x-full');
                            } else {
                                sidebar.classList.add('-translate-x-full');
                            }
                        }
                    });
                }

                // Flash Message Auto-Hide
                const flashMsg = document.getElementById('flash-msg');
                if (flashMsg) {
                    setTimeout(() => {
                        flashMsg.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                        setTimeout(() => flashMsg.remove(), 500);
                    }, 4000);
                }
            </script>