<?php
session_name('ParlourPOS_Customer');
session_start();

// Auto-detect folder depth
$depth = file_exists('../../config/database.php') ? '../../' : '../';
require_once $depth . 'config/database.php';

// Safe Authentication - No redirect loops
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'customer') {
    echo "<div style='text-align:center; padding:100px; font-family:sans-serif;'>
            <h2 style='color:#D81B60; margin-bottom:10px;'>Session Expired</h2>
            <p style='color:#666; margin-bottom:20px;'>Please log in as a Customer.</p>
            <a href='{$depth}index.php' style='padding:10px 20px; background:#2A1320; color:white; text-decoration:none; border-radius:8px;'>Return to Login</a>
          </div>";
    exit;
}

$customer_id = $_SESSION['user_id'];

// ==========================================
// HANDLE MEMBERSHIP UPGRADE PAYMENT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade_membership') {
    $plan = $_POST['plan'];
    
    try {
        // Ensure the membership_tier column exists in customers table safely
        $conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS membership_tier VARCHAR(50) DEFAULT 'Standard'");
        
        $stmt = $conn->prepare("UPDATE customers SET membership_tier = ? WHERE id = ?");
        $stmt->bind_param("si", $plan, $customer_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['flash_success'] = "Payment Successful! Welcome to the $plan tier.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Upgrade failed. Please contact support.";
    }
    
    header("Location: memberships.php");
    exit;
}

// Fetch current tier
$current_tier = 'Standard';
try {
    $res = $conn->query("SELECT membership_tier FROM customers WHERE id = $customer_id");
    if ($res && $row = $res->fetch_assoc()) {
        $current_tier = $row['membership_tier'] ?: 'Standard';
    }
} catch (Exception $e) {}

// Routing Variable
$upgrade_plan = $_GET['plan'] ?? '';
$is_checkout = in_array($upgrade_plan, ['Gold', 'Platinum']);

// Define Plan Details
$plan_details = [
    'Gold' => [
        'name' => 'Gold Member',
        'price' => 999,
        'theme_bg' => 'bg-gradient-to-br from-[#BF953F] via-[#FCF6BA] to-[#B38728]',
        'theme_text' => 'text-yellow-900',
        'theme_border' => 'border-yellow-400',
        'icon' => '<i class="fa-solid fa-crown text-yellow-600"></i>',
        'perks' => ['10% off all hair services', 'Earn 2 points per ₹100 spent', 'Priority booking access', 'Free birthday styling']
    ],
    'Platinum' => [
        'name' => 'Platinum VIP',
        'price' => 2499,
        'theme_bg' => 'bg-gradient-to-br from-[#434343] to-[#000000]',
        'theme_text' => 'text-gray-100',
        'theme_border' => 'border-gray-600',
        'icon' => '<i class="fa-solid fa-gem text-gray-300"></i>',
        'perks' => ['20% off ALL services', 'Earn 3 points per ₹100 spent', '10% off retail products', 'Free monthly head massage', 'VIP Lounge access']
    ]
];

require_once $depth . 'includes/header.php';
?>

<?php if ($is_checkout): 
    $plan_data = $plan_details[$upgrade_plan];
?>
    <!-- ========================================== -->
    <!-- PREMIUM CHECKOUT VIEW -->
    <!-- ========================================== -->
    <div class="mb-6 flex items-center gap-4">
        <a href="memberships.php" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-brand-magenta hover:border-brand-magenta transition-colors shadow-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Upgrade Membership</h2>
            <p class="text-gray-500 text-sm mt-1">Complete your payment to unlock exclusive benefits.</p>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- Left Column: Premium Plan Card -->
        <div class="w-full lg:w-1/2">
            <div class="<?= $plan_data['theme_bg'] ?> p-8 rounded-3xl shadow-2xl relative overflow-hidden h-full border <?= $plan_data['theme_border'] ?>">
                
                <!-- Decorative Glow/Shine -->
                <div class="absolute -top-20 -right-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col h-full">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center text-3xl mb-6 shadow-inner border border-white/30">
                        <?= $plan_data['icon'] ?>
                    </div>
                    
                    <h3 class="text-3xl font-black <?= $plan_data['theme_text'] ?> mb-2"><?= $plan_data['name'] ?></h3>
                    <div class="text-5xl font-bold <?= $plan_data['theme_text'] ?> tracking-tight mb-8">
                        ₹<?= number_format($plan_data['price']) ?><span class="text-lg font-medium opacity-80"> / year</span>
                    </div>

                    <div class="space-y-4 mb-8 flex-grow">
                        <?php foreach ($plan_data['perks'] as $perk): ?>
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-check mt-1 opacity-80 <?= $plan_data['theme_text'] ?>"></i>
                                <span class="font-medium <?= $plan_data['theme_text'] ?>"><?= $perk ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="pt-6 border-t border-white/20 mt-auto">
                        <p class="text-sm font-bold opacity-80 uppercase tracking-wider <?= $plan_data['theme_text'] ?>">Secure Payment Gateway</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: UPI Checkout Form -->
        <div class="w-full lg:w-1/2">
            <div class="bg-white p-6 md:p-10 rounded-3xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 h-full flex flex-col justify-center">
                
                <div class="text-center mb-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Pay via UPI</h3>
                    <p class="text-sm text-gray-500">Scan the QR code or select an app to pay instantly.</p>
                </div>

                <!-- UPI Dynamic UI -->
                <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100 flex flex-col items-center shadow-inner mb-8">
                    
                    <!-- Dynamic QR Code -->
                    <div class="p-3 bg-white border border-gray-200 rounded-2xl shadow-sm mb-6 relative">
                        <!-- Scanning line animation overlay -->
                        <div class="absolute inset-x-0 top-0 h-0.5 bg-brand-magenta/50 blur-[1px] animate-[scan_2s_ease-in-out_infinite]"></div>
                        <img id="upi-qr-image" src="" alt="UPI QR Code" class="w-48 h-48 object-contain opacity-0 transition-opacity duration-500" onload="this.classList.remove('opacity-0')">
                    </div>

                    <!-- App Selectors -->
                    <div class="flex flex-wrap justify-center gap-3 w-full max-w-sm">
                        <button type="button" onclick="setUpiApp('GPay')" id="btn-upi-GPay" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm flex items-center justify-center gap-2">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" class="w-4 h-4"> GPay
                        </button>
                        <button type="button" onclick="setUpiApp('PhonePe')" id="btn-upi-PhonePe" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm flex items-center justify-center gap-2">
                            <span class="text-purple-600 text-lg leading-none">पे</span> PhonePe
                        </button>
                        <button type="button" onclick="setUpiApp('Paytm')" id="btn-upi-Paytm" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm flex items-center justify-center gap-2">
                            <span class="text-blue-500 text-lg leading-none font-black">Paytm</span>
                        </button>
                    </div>
                </div>

                <form method="POST" action="memberships.php" onsubmit="return validatePayment()">
                    <input type="hidden" name="action" value="upgrade_membership">
                    <input type="hidden" name="plan" value="<?= htmlspecialchars($upgrade_plan) ?>">
                    <input type="hidden" name="upi_app" id="form-upi_app" value="">
                    
                    <button type="submit" class="w-full py-4 bg-brand-sidebar text-white font-bold rounded-xl text-sm tracking-widest shadow-lg hover:bg-brand-sidebar/90 transition-all hover:-translate-y-0.5 flex justify-center items-center gap-3 uppercase">
                        CONFIRM PAYMENT OF ₹<?= number_format($plan_data['price']) ?> <i class="fa-solid fa-lock text-brand-pink"></i>
                    </button>
                </form>

            </div>
        </div>
    </div>

    <style>
        @keyframes scan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
    </style>

    <script>
        document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Checkout' : null;

        // Generate QR on load
        window.addEventListener('load', () => {
            const amount = "<?= $plan_data['price'] ?>";
            const qrImage = document.getElementById('upi-qr-image');
            const upiString = `upi://pay?pa=parlourpos@ybl&pn=ParlourPOS&am=${amount}&cu=INR`;
            qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiString)}&color=2A1320`;
        });

        // App Selection Highlighter
        function setUpiApp(app) {
            document.getElementById('form-upi_app').value = app;
            ['GPay', 'PhonePe', 'Paytm'].forEach(a => {
                const btn = document.getElementById('btn-upi-' + a);
                if (a === app) {
                    btn.classList.add('bg-brand-pink/10', 'border-brand-pink', 'text-brand-magenta');
                    btn.classList.remove('border-gray-200', 'text-gray-600', 'bg-white');
                } else {
                    btn.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
                    btn.classList.remove('bg-brand-pink/10', 'border-brand-pink', 'text-brand-magenta');
                }
            });
        }

        // Validation
        function validatePayment() {
            if (!document.getElementById('form-upi_app').value) {
                document.getElementById('form-upi_app').value = 'QR Scan'; // Fallback if they just scanned
            }
            return true;
        }
    </script>

<?php else: ?>
    <!-- ========================================== -->
    <!-- MEMBERSHIP PLANS LIST VIEW -->
    <!-- ========================================== -->
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Salon Memberships</h2>
        <p class="text-gray-500 text-sm mt-1">View your current tier and unlock exclusive benefits.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Standard -->
        <div class="bg-white p-8 rounded-3xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border <?= $current_tier === 'Standard' ? 'border-gray-300 ring-2 ring-gray-100' : 'border-gray-100' ?> relative overflow-hidden flex flex-col">
            <?php if ($current_tier === 'Standard'): ?>
                <div class="absolute top-0 right-0 bg-gray-600 text-white text-[9px] font-bold uppercase tracking-wider px-3 py-1 rounded-bl-lg z-10">CURRENT PLAN</div>
            <?php endif; ?>
            
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 text-xl mb-4">
                <i class="fa-solid fa-user"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-lg">Standard</h3>
            <div class="text-2xl font-black text-gray-900 mb-6 mt-1">Free</div>
            
            <div class="space-y-3 mb-8 flex-grow">
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-emerald-500"></i> Book appointments online</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-emerald-500"></i> Earn 1 point per ₹100 spent</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-emerald-500"></i> Standard support</div>
            </div>
            
            <?php if ($current_tier === 'Standard'): ?>
                <button class="w-full py-3 bg-gray-100 text-gray-400 font-bold rounded-xl text-sm cursor-not-allowed mt-auto">Active Plan</button>
            <?php endif; ?>
        </div>

        <!-- Gold -->
        <div class="bg-white p-8 rounded-3xl shadow-[0_5px_20px_rgba(0,0,0,0.04)] border <?= $current_tier === 'Gold' ? 'border-yellow-400 ring-4 ring-yellow-50' : 'border-yellow-100' ?> relative overflow-hidden flex flex-col">
            <?php if ($current_tier === 'Gold'): ?>
                <div class="absolute top-0 right-0 bg-yellow-500 text-white text-[9px] font-bold uppercase tracking-wider px-3 py-1 rounded-bl-lg z-10">CURRENT PLAN</div>
            <?php endif; ?>
            
            <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center text-yellow-500 text-xl mb-4">
                <i class="fa-solid fa-crown"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-lg">Gold Member</h3>
            <div class="text-2xl font-black text-yellow-600 mb-6 mt-1">₹999 <span class="text-xs text-gray-400 font-medium">/ year</span></div>
            
            <div class="space-y-3 mb-8 flex-grow">
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-yellow-500"></i> 10% off all hair services</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-yellow-500"></i> Earn 2 points per ₹100 spent</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-yellow-500"></i> Priority booking access</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-yellow-500"></i> Free birthday styling</div>
            </div>
            
            <?php if ($current_tier === 'Gold'): ?>
                <button class="w-full py-3 bg-yellow-50 text-yellow-600 font-bold rounded-xl text-sm cursor-not-allowed mt-auto">Active Plan</button>
            <?php elseif ($current_tier === 'Platinum'): ?>
                <button class="w-full py-3 bg-gray-50 text-gray-400 font-bold rounded-xl text-sm cursor-not-allowed mt-auto">Included in VIP</button>
            <?php else: ?>
                <a href="memberships.php?plan=Gold" class="block text-center w-full py-3 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 font-bold rounded-xl text-sm transition-colors mt-auto">Upgrade to Gold</a>
            <?php endif; ?>
        </div>

        <!-- Platinum VIP -->
        <div class="bg-white p-8 rounded-3xl shadow-[0_10px_30px_rgba(0,0,0,0.06)] border <?= $current_tier === 'Platinum' ? 'border-gray-800 ring-4 ring-gray-100' : 'border-gray-100' ?> relative overflow-hidden flex flex-col">
            <?php if ($current_tier === 'Platinum'): ?>
                <div class="absolute top-0 right-0 bg-gray-900 text-white text-[9px] font-bold uppercase tracking-wider px-3 py-1 rounded-bl-lg z-10">CURRENT PLAN</div>
            <?php endif; ?>
            
            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-gray-800 text-xl mb-4">
                <i class="fa-solid fa-gem"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-lg">Platinum VIP</h3>
            <div class="text-2xl font-black text-gray-900 mb-6 mt-1">₹2499 <span class="text-xs text-gray-400 font-medium">/ year</span></div>
            
            <div class="space-y-3 mb-8 flex-grow">
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-gray-800"></i> 20% off ALL services</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-gray-800"></i> Earn 3 points per ₹100 spent</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-gray-800"></i> 10% off retail products</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-gray-800"></i> Free monthly head massage</div>
                <div class="flex items-center gap-3 text-sm text-gray-600"><i class="fa-solid fa-check text-gray-800"></i> VIP Lounge access</div>
            </div>
            
            <?php if ($current_tier === 'Platinum'): ?>
                <button class="w-full py-3 bg-gray-100 text-gray-800 font-bold rounded-xl text-sm cursor-not-allowed mt-auto border border-gray-200">Active Plan</button>
            <?php else: ?>
                <a href="memberships.php?plan=Platinum" class="block text-center w-full py-3 bg-[#2A1320] hover:bg-black text-white font-bold rounded-xl text-sm shadow-md transition-colors mt-auto border border-[#2A1320]">Upgrade to VIP</a>
            <?php endif; ?>
        </div>

    </div>
    
    <script>
        document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Memberships' : null;
    </script>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>