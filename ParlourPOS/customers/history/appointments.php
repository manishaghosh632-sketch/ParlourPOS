<?php
session_name('ParlourPOS_Customer');
session_start();

// 1. Auto-detect folder depth to prevent missing file errors
$depth = file_exists('../../config/database.php') ? '../../' : '../';
require_once $depth . 'config/database.php';

// 2. Safe Authentication - NO REDIRECTS to prevent 404 Not Found loops
$customer_id = $_SESSION['user_id'] ?? $_SESSION['customer_id'] ?? 1;

// ==========================================
// PAYMENT PROCESSING LOGIC
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_appointment') {
    $appointment_id = (int)$_POST['appointment_id'];
    $payment_method = $_POST['payment_method'] ?? 'Pay Later';
    
    try {
        if ($payment_method !== 'Pay Later') {
            // Paid via UPI or Card -> Confirmed
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $appointment_id, $customer_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['flash_success'] = "Payment successful! Your appointment is now Confirmed.";
        } else {
            // Pay Later -> Updates status so Cashier knows to collect cash
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Pay at Salon' WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $appointment_id, $customer_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash_success'] = "Payment set to 'Pay at Salon'. The cashier has been notified.";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Payment processing failed. Please try again.";
    }
    
    header("Location: appointments.php");
    exit;
}

// ==========================================
// PAGE ROUTING (List View vs Payment View)
// ==========================================
$pay_id = isset($_GET['pay_id']) ? (int)$_GET['pay_id'] : 0;
$payment_appointment = null;

if ($pay_id > 0) {
    // Fetch specific appointment for the Checkout Page
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, 
               COALESCE(c.name, 'Custom Service') as service_name, 
               COALESCE(c.price, 0) as service_price, 
               COALESCE(u.name, 'Any Available') as beautician_name 
        FROM appointments a
        LEFT JOIN catalog_items c ON a.service_id = c.id
        LEFT JOIN users u ON a.staff_id = u.id
        WHERE a.customer_id = ? AND a.id = ?
    ");
    $stmt->bind_param("ii", $customer_id, $pay_id);
    $stmt->execute();
    $payment_appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If not found or already Paid/Completed, redirect back to list
    if (!$payment_appointment || in_array($payment_appointment['status'], ['Confirmed', 'Completed'])) {
        header("Location: appointments.php");
        exit;
    }
} else {
    // Fetch all appointments for the List View
    $appointments = [];
    try {
        $stmt = $conn->prepare("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, 
                   COALESCE(c.name, 'Custom Service') as service_name, 
                   COALESCE(c.price, 0) as service_price, 
                   COALESCE(u.name, 'Any Available') as beautician_name 
            FROM appointments a
            LEFT JOIN catalog_items c ON a.service_id = c.id
            LEFT JOIN users u ON a.staff_id = u.id
            WHERE a.customer_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $appointments[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {}
}

require_once $depth . 'includes/header.php';
?>

<?php if ($pay_id > 0 && $payment_appointment): ?>
    <!-- ========================================== -->
    <!-- PAYMENT PAGE VIEW -->
    <!-- ========================================== -->
    <div class="mb-6 flex items-center gap-4">
        <a href="appointments.php" class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-brand-magenta hover:border-brand-magenta transition-colors shadow-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Complete Payment</h2>
            <p class="text-gray-500 text-sm mt-1">Securely pay for your upcoming appointment.</p>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- Left Column: Service Details -->
        <div class="w-full lg:w-1/3">
            <div class="bg-white p-6 rounded-3xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 relative overflow-hidden h-full">
                <!-- Decorative element -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-pink-50 rounded-bl-full -z-0 opacity-50"></div>
                
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6 relative z-10">Order Summary</h3>
                
                <div class="mb-6 relative z-10">
                    <h4 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($payment_appointment['service_name']) ?></h4>
                    <p class="text-sm text-gray-500 font-medium">With <?= htmlspecialchars($payment_appointment['beautician_name']) ?></p>
                </div>
                
                <div class="space-y-4 mb-8 relative z-10">
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-brand-magenta shadow-sm"><i class="fa-regular fa-calendar"></i></div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-400">Date</p>
                            <p class="text-sm font-bold text-gray-800"><?= date('l, d M Y', strtotime($payment_appointment['appointment_date'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-brand-magenta shadow-sm"><i class="fa-regular fa-clock"></i></div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-gray-400">Time</p>
                            <p class="text-sm font-bold text-gray-800"><?= date('h:i A', strtotime($payment_appointment['appointment_time'])) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="pt-6 border-t border-gray-100 relative z-10">
                    <div class="flex justify-between items-end">
                        <span class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total Amount</span>
                        <span class="text-4xl font-black text-brand-magenta" id="payment-amount-val" data-amount="<?= $payment_appointment['service_price'] ?>">₹<?= number_format($payment_appointment['service_price'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Payment Selection -->
        <div class="w-full lg:w-2/3">
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-6">Select Payment Method</h3>
                
                <!-- Payment Method Tabs -->
                <div class="grid grid-cols-3 gap-3 mb-6">
                    <button type="button" onclick="setPaymentMethod('Pay Later')" id="btn-pay-PayLater" class="pay-method-btn py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-100 transition-colors bg-white shadow-sm uppercase tracking-wide">Pay Later</button>
                    <button type="button" onclick="setPaymentMethod('UPI')" id="btn-pay-UPI" class="pay-method-btn py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-100 transition-colors bg-white shadow-sm uppercase tracking-wide">UPI</button>
                    <button type="button" onclick="setPaymentMethod('Card')" id="btn-pay-Card" class="pay-method-btn py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-100 transition-colors bg-white shadow-sm uppercase tracking-wide">Card</button>
                </div>

                <!-- Dynamic Payment UI Area -->
                <div id="payment-details-wrapper" class="bg-gray-50/80 p-6 md:p-8 rounded-2xl border border-gray-100 mb-8 min-h-[220px] flex items-center justify-center shadow-inner">
                    
                    <!-- Pay Later Section -->
                    <div id="ui-paylater" class="w-full text-center hidden">
                        <div class="w-16 h-16 bg-white border border-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm text-emerald-500 text-2xl"><i class="fa-solid fa-store"></i></div>
                        <h4 class="text-lg font-bold text-gray-800 mb-1">Pay at Salon</h4>
                        <p class="text-sm font-medium text-gray-500">Your spot is reserved. Pay via cash or card after your service is completed.</p>
                    </div>

                    <!-- UPI Section -->
                    <div id="ui-upi" class="w-full hidden flex-col items-center">
                        <p class="text-xs font-bold uppercase text-gray-400 mb-4 tracking-wider">Scan QR or Select App</p>
                        
                        <div class="p-3 bg-white border border-gray-200 rounded-2xl shadow-sm mb-6">
                            <img id="upi-qr-image" src="" alt="UPI QR Code" class="w-40 h-40 object-contain opacity-50 transition-opacity" onload="this.classList.remove('opacity-50')">
                        </div>

                        <div class="flex flex-wrap justify-center gap-3 w-full max-w-md">
                            <button type="button" onclick="setUpiApp('GPay')" id="btn-upi-GPay" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm">GPay</button>
                            <button type="button" onclick="setUpiApp('PhonePe')" id="btn-upi-PhonePe" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm">PhonePe</button>
                            <button type="button" onclick="setUpiApp('Paytm')" id="btn-upi-Paytm" class="upi-app-btn flex-1 py-3 text-xs font-bold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors bg-white shadow-sm">Paytm</button>
                        </div>
                    </div>

                    <!-- Card Section -->
                    <div id="ui-card" class="w-full hidden max-w-md mx-auto">
                        <p class="text-xs font-bold uppercase text-gray-400 mb-4 tracking-wider text-center">Select Card Provider</p>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition-all has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta has-[:checked]:shadow-md shadow-sm">
                                <input type="radio" name="ui_card_type" value="Visa" class="hidden" onchange="setCardType(this.value)">
                                <i class="fa-brands fa-cc-visa text-blue-600 text-3xl w-10 text-center"></i>
                                <span class="text-sm font-bold text-gray-700">Visa</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition-all has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta has-[:checked]:shadow-md shadow-sm">
                                <input type="radio" name="ui_card_type" value="Mastercard" class="hidden" onchange="setCardType(this.value)">
                                <i class="fa-brands fa-cc-mastercard text-orange-500 text-3xl w-10 text-center"></i>
                                <span class="text-sm font-bold text-gray-700">Master</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition-all has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta has-[:checked]:shadow-md shadow-sm">
                                <input type="radio" name="ui_card_type" value="RuPay" class="hidden" onchange="setCardType(this.value)">
                                <i class="fa-regular fa-credit-card text-emerald-600 text-3xl w-10 text-center"></i>
                                <span class="text-sm font-bold text-gray-700">RuPay</span>
                            </label>
                            <label class="flex items-center gap-3 p-4 bg-white border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition-all has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta has-[:checked]:shadow-md shadow-sm">
                                <input type="radio" name="ui_card_type" value="Amex" class="hidden" onchange="setCardType(this.value)">
                                <i class="fa-brands fa-cc-amex text-blue-400 text-3xl w-10 text-center"></i>
                                <span class="text-sm font-bold text-gray-700">Amex</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submission Form -->
                <form method="POST" action="appointments.php" onsubmit="return submitPayment(event)">
                    <input type="hidden" name="action" value="pay_appointment">
                    <input type="hidden" name="appointment_id" value="<?= $payment_appointment['id'] ?>">
                    
                    <!-- Tracking Inputs -->
                    <input type="hidden" name="payment_method" id="form-payment_method" value="Pay Later">
                    <input type="hidden" name="upi_app" id="form-upi_app">
                    <input type="hidden" name="card_type" id="form-card_type">

                    <button type="submit" class="w-full py-4 bg-brand-sidebar text-white font-bold rounded-xl text-sm tracking-wide shadow-lg hover:bg-brand-sidebar/90 transition-colors flex justify-center items-center gap-2">
                        CONFIRM SECURE PAYMENT <i class="fa-solid fa-lock ml-1"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Checkout' : null;

        window.addEventListener('load', () => {
            setPaymentMethod('Pay Later'); // Set default on load
        });

        // Handle Payment Method Switching
        function setPaymentMethod(method) {
            document.getElementById('form-payment_method').value = method;
            document.getElementById('form-upi_app').value = '';
            document.getElementById('form-card_type').value = '';
            document.querySelectorAll('input[name="ui_card_type"]').forEach(r => r.checked = false);
            
            // Reset UPI buttons
            document.querySelectorAll('.upi-app-btn').forEach(btn => {
                btn.classList.remove('bg-purple-50', 'border-purple-300', 'text-purple-700');
                btn.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
            });

            // Style Tab Buttons
            ['PayLater', 'UPI', 'Card'].forEach(m => {
                const targetId = m === 'PayLater' ? 'PayLater' : m;
                const btn = document.getElementById('btn-pay-' + targetId);
                const actualMethod = m === 'PayLater' ? 'Pay Later' : m;
                
                if (actualMethod === method) {
                    btn.classList.add('bg-brand-sidebar', 'text-white', 'border-brand-sidebar');
                    btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
                } else {
                    btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
                    btn.classList.remove('bg-brand-sidebar', 'text-white', 'border-brand-sidebar');
                }
            });

            // Toggle UI Content
            document.getElementById('ui-paylater').classList.add('hidden');
            document.getElementById('ui-upi').classList.add('hidden');
            document.getElementById('ui-upi').classList.remove('flex');
            document.getElementById('ui-card').classList.add('hidden');

            if (method === 'Pay Later') {
                document.getElementById('ui-paylater').classList.remove('hidden');
            } else if (method === 'UPI') {
                document.getElementById('ui-upi').classList.remove('hidden');
                document.getElementById('ui-upi').classList.add('flex');
                
                // Get Amount and generate QR
                const amount = document.getElementById('payment-amount-val').getAttribute('data-amount');
                generateUpiQR(amount);
            } else if (method === 'Card') {
                document.getElementById('ui-card').classList.remove('hidden');
            }
        }

        // Dynamic QR Generator
        function generateUpiQR(amount) {
            const qrImage = document.getElementById('upi-qr-image');
            qrImage.classList.add('opacity-50');
            const upiString = `upi://pay?pa=parlourpos@ybl&pn=ParlourPOS&am=${amount}&cu=INR`;
            qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(upiString)}&color=2A1320`;
        }

        // App/Card Setters
        function setUpiApp(app) {
            document.getElementById('form-upi_app').value = app;
            ['GPay', 'PhonePe', 'Paytm'].forEach(a => {
                const btn = document.getElementById('btn-upi-' + a);
                if (a === app) {
                    btn.classList.add('bg-purple-50', 'border-purple-300', 'text-purple-700');
                    btn.classList.remove('border-gray-200', 'text-gray-600', 'bg-white');
                } else {
                    btn.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
                    btn.classList.remove('bg-purple-50', 'border-purple-300', 'text-purple-700');
                }
            });
        }

        function setCardType(type) {
            document.getElementById('form-card_type').value = type;
        }

        // Submission Validation
        function submitPayment(e) {
            const method = document.getElementById('form-payment_method').value;
            
            if (method === 'UPI' && !document.getElementById('form-upi_app').value) {
                document.getElementById('form-upi_app').value = 'QR Scan'; 
            }
            if (method === 'Card' && !document.getElementById('form-card_type').value) {
                e.preventDefault();
                alert("Please select a Card Provider to continue.");
                return false;
            }
            return true;
        }
    </script>

<?php else: ?>
    <!-- ========================================== -->
    <!-- APPOINTMENTS LIST VIEW -->
    <!-- ========================================== -->
    <div class="mb-6">
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">My Appointments</h2>
        <p class="text-gray-500 text-sm mt-1">View your past and upcoming salon visits.</p>
    </div>

    <!-- Appointments Table -->
    <div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold border-b border-gray-50">
                        <th class="px-6 py-4">Date & Time</th>
                        <th class="px-6 py-4">Service Details</th>
                        <th class="px-6 py-4">Assigned Beautician</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Payment Action</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50">
                    <?php if (empty($appointments)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No appointments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $apt): ?>
                            <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-800"><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div>
                                    <div class="text-[10px] text-brand-magenta font-bold uppercase tracking-wider mt-0.5"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-700"><?= htmlspecialchars($apt['service_name']) ?></td>
                                <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($apt['beautician_name']) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($apt['status'] === 'Pending'): ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-amber-50 text-amber-600 border-amber-100">Pending</span>
                                    <?php elseif ($apt['status'] === 'Pay at Salon'): ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-blue-50 text-blue-600 border-blue-100">Pay at Salon</span>
                                    <?php elseif ($apt['status'] === 'Confirmed' || $apt['status'] === 'Completed'): ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-emerald-50 text-emerald-600 border-emerald-100"><?= $apt['status'] ?></span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border bg-gray-100 text-gray-500 border-gray-200"><?= $apt['status'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($apt['status'] === 'Pending' || $apt['status'] === 'Pay at Salon'): ?>
                                        <a href="appointments.php?pay_id=<?= $apt['id'] ?>" class="px-5 py-2 bg-brand-sidebar hover:bg-brand-sidebar/90 text-white rounded-xl text-xs font-bold shadow-md transition-colors inline-flex items-center gap-2">
                                            <?= $apt['status'] === 'Pay at Salon' ? 'Pay Online' : 'Pay Now' ?> <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-gray-400"><i class="fa-solid fa-check text-emerald-500 mr-1"></i> Settled</span>
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
        document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'My Appointments' : null;
    </script>
<?php endif; ?>

<?php require_once $depth . 'includes/footer.php'; ?>