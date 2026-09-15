<?php 
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? '';

$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

// Helper to determine active class
function isActive($page, $dir = null) {
    global $current_page, $current_dir;
    
    $is_active = false;
    if ($dir !== null) {
        $is_active = ($current_page === $page && $current_dir === $dir);
    } else {
        $is_active = ($current_page === $page);
    }
    
    if ($is_active) {
        return 'bg-brand-pink text-brand-sidebar font-bold shadow-sm';
    }
    return 'text-[#E2C7D2] hover:text-white hover:bg-white/5 font-medium';
}

$logout_path = ($role === 'Customer') ? $base_url . '/customers/logout.php' : $base_url . '/auth/logout.php';
?>

<aside id="app-sidebar" class="fixed inset-y-0 left-0 w-[260px] bg-brand-sidebar text-white shadow-2xl flex flex-col z-50 transform -translate-x-full lg:translate-x-0 lg:relative transition-smooth duration-300">
    
    <!-- Branding -->
    <div class="pt-8 pb-6 flex flex-col items-center border-b border-white/5 shrink-0 relative overflow-hidden">
        <i class="fa-solid fa-crown text-brand-pink text-xl mb-1 relative z-10"></i>
        <div class="w-16 h-16 rounded-full bg-brand-pink/10 flex items-center justify-center mb-3 relative z-10 border border-brand-pink/30">
            <i class="fa-solid fa-spa text-brand-pink text-3xl"></i>
        </div>
        <h1 class="font-serif text-2xl font-bold text-white tracking-wide relative z-10">ParlourPOS</h1>
        <p class="text-[9px] text-[#E2C7D2] uppercase tracking-[0.15em] mt-1.5 relative z-10">Beauty &bull; Care &bull; Confidence</p>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-1 no-scrollbar">
        
        <?php if ($role === 'Super Admin'): ?>
            <a href="<?= $base_url ?>/super_admin/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('dashboard.php', 'super_admin') ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
            </a>
            <a href="<?= $base_url ?>/super_admin/appointments.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('appointments.php') ?>">
                <i class="fa-regular fa-calendar-check w-5 text-center"></i> Appointments
            </a>
            <a href="<?= $base_url ?>/super_admin/roles_permissions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('roles_permissions.php') ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> Staff / Team
            </a>
            <a href="<?= $base_url ?>/super_admin/services.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('services.php') ?>">
                <i class="fa-solid fa-spa w-5 text-center"></i> Services
            </a>
            <a href="<?= $base_url ?>/super_admin/products.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('products.php') ?>">
                <i class="fa-solid fa-bottle-droplet w-5 text-center"></i> Products
            </a>
            <a href="<?= $base_url ?>/super_admin/commissions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('commissions.php') ?>">
                <i class="fa-solid fa-wallet w-5 text-center"></i> Commissions
            </a>
            <a href="<?= $base_url ?>/super_admin/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('reports.php') ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Reports
            </a>
            <a href="<?= $base_url ?>/super_admin/feedback.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('feedback.php') ?>">
                <i class="fa-solid fa-star-half-stroke w-5 text-center"></i> Reviews & Feedback
            </a>
            <a href="<?= $base_url ?>/super_admin/settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('settings.php') ?>">
                <i class="fa-solid fa-gear w-5 text-center"></i> Settings
            </a>

        <?php elseif ($role === 'Manager'): ?>
            <a href="<?= $base_url ?>/manager/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('dashboard.php', 'manager') ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
            </a>
            <a href="<?= $base_url ?>/manager/staff.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('staff.php') ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> Staff
            </a>
            <a href="<?= $base_url ?>/manager/inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('inventory.php') ?>">
                <i class="fa-solid fa-boxes-stacked w-5 text-center"></i> Inventory
            </a>
            <a href="<?= $base_url ?>/manager/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('reports.php', 'manager') ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i> Reports
            </a>
            <a href="<?= $base_url ?>/manager/feedback.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('feedback.php') ?>">
                <i class="fa-solid fa-star-half-stroke w-5 text-center"></i> Reviews & Feedback
            </a>

        <?php elseif ($role === 'Receptionist'): ?>
            <a href="<?= $base_url ?>/staff/cashier/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('dashboard.php', 'cashier') ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
            </a>
            <a href="<?= $base_url ?>/staff/cashier/pos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('pos.php') ?>">
                <i class="fa-solid fa-cash-register w-5 text-center"></i> POS Billing
            </a>
            <a href="<?= $base_url ?>/staff/cashier/appointments.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('appointments.php') ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center"></i> Appointments
            </a>
            <a href="<?= $base_url ?>/staff/cashier/customers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('customers.php') ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> Customers
            </a>
            <a href="<?= $base_url ?>/staff/cashier/payments.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('payments.php') ?>">
                <i class="fa-solid fa-hand-holding-dollar w-5 text-center"></i> Payments
            </a>
            <a href="<?= $base_url ?>/staff/cashier/invoices.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('invoices.php') ?>">
                <i class="fa-solid fa-file-invoice w-5 text-center"></i> Invoices
            </a>
            <a href="<?= $base_url ?>/staff/cashier/refunds.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('refunds.php') ?>">
                <i class="fa-solid fa-arrow-rotate-left w-5 text-center"></i> Refunds
            </a>

        <?php elseif ($role === 'Beautician'): ?>
            <a href="<?= $base_url ?>/staff/beautician/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('dashboard.php', 'beautician') ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
            </a>
            <a href="<?= $base_url ?>/staff/beautician/my_appointments.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('my_appointments.php') ?>">
                <i class="fa-solid fa-calendar-day w-5 text-center"></i> Appointments
            </a>
            <a href="<?= $base_url ?>/staff/beautician/my_commisions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('my_commisions.php') ?>">
                <i class="fa-solid fa-wallet w-5 text-center"></i> Commissions
            </a>

        <?php elseif ($role === 'Customer'): ?>
            <a href="<?= $base_url ?>/customers/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('dashboard.php', 'customers') ?>">
                <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
            </a>
            <a href="<?= $base_url ?>/customers/history/appointments.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('appointments.php') ?>">
                <i class="fa-regular fa-calendar-check w-5 text-center"></i> Appointments
            </a>
            <a href="<?= $base_url ?>/customers/book_appointment.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('book_appointment.php') ?>">
                <i class="fa-solid fa-spa w-5 text-center"></i> Services
            </a>
            <a href="<?= $base_url ?>/customers/history/services.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('services.php') ?>">
                <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i> Service History
            </a>
            <a href="<?= $base_url ?>/customers/loyalty/memberships.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('memberships.php') ?>">
                <i class="fa-solid fa-id-card w-5 text-center"></i> Memberships
            </a>
            <a href="<?= $base_url ?>/customers/loyalty/points.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('points.php') ?>">
                <i class="fa-solid fa-star w-5 text-center"></i> My Points
            </a>
            <a href="<?= $base_url ?>/customers/feedback.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('feedback.php') ?>">
                <i class="fa-solid fa-star-half-stroke w-5 text-center"></i> Give Feedback
            </a>
            <a href="<?= $base_url ?>/customers/profile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-smooth <?= isActive('profile.php') ?>">
                <i class="fa-solid fa-user w-5 text-center"></i> Profile Settings
            </a>
        <?php endif; ?>

    </nav>

    <!-- Bottom Decorative Text -->
    <div class="p-6 pt-0 shrink-0 text-center relative overflow-hidden">
        <p class="font-cursive text-brand-pink text-3xl leading-tight relative z-10 drop-shadow-md">Self care<br>is always<br>a good idea <i class="fa-regular fa-heart text-sm"></i></p>
        <i class="fa-solid fa-leaf text-8xl text-white/5 absolute -bottom-4 -left-4 z-0 rotate-45"></i>
    </div>

    <!-- Logout Button -->
    <div class="px-6 pb-6 shrink-0 pt-2 relative z-20">
        <a href="<?= $logout_path ?>" class="flex items-center gap-3 text-sm font-medium text-[#E2C7D2] hover:text-white transition-smooth group">
            <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center group-hover:text-brand-pink transition-colors"></i> Logout
        </a>
    </div>
</aside>