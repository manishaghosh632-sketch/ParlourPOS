<?php
// staff/cashier/pos.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

// Allow Receptionists and Managers to access POS
if (!in_array($_SESSION['role'], ['Receptionist', 'Manager', 'Super Admin'])) {
    header("Location: ../../index.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Service Image Resolver Helper
function getPosItemImage($type, $image_filename, $name = '', $category = '') {
    global $base_url;
    $folder = ($type === 'Service') ? 'services' : 'products';
    if (!empty($image_filename)) {
        $physical_path = dirname(dirname(__DIR__)) . "/assets/images/$folder/" . $image_filename;
        if (file_exists($physical_path)) {
            return $base_url . "/assets/images/$folder/" . htmlspecialchars($image_filename);
        }
    }
    // Fallback based on item type & keyword
    $keyword = strtolower($name . ' ' . $category);
    if ($type === 'Service') {
        if (strpos($keyword, 'hair') !== false || strpos($keyword, 'cut') !== false) {
            return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80';
        } elseif (strpos($keyword, 'bridal') !== false || strpos($keyword, 'makeup') !== false) {
            // Loads your local bridal_makeup.png file
            return $base_url . '/assets/images/bridal_makeup.png';
        } elseif (strpos($keyword, 'facial') !== false || strpos($keyword, 'skin') !== false) {
            return 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=400&q=80';
        }
        return 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=400&q=80';
    } else {
        return 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=400&q=80';
    }
}

// PRG PATTERN FOR CHECKOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_checkout') {
    try {
        $conn->begin_transaction();
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $payment_details = '';
        
        if ($payment_method === 'UPI') {
            $payment_details = $_POST['upi_app'] ?? 'UPI Scan';
        } elseif ($payment_method === 'Card') {
            $payment_details = $_POST['card_type'] ?? 'Card';
        }
        
        $subtotal = (float)$_POST['subtotal'];
        $tax = (float)$_POST['tax'];
        $discount = (float)$_POST['discount'];
        $grand_total = (float)$_POST['grand_total'];
        
        $cart_items = json_decode($_POST['cart_data'], true);
        if (empty($cart_items)) {
            throw new Exception("Cart is empty.");
        }
        
        // 1. Create Invoice
        $invoice_number = 'INV-' . strtoupper(uniqid());
        $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, customer_id, cashier_id, subtotal, tax_amount, discount_amount, grand_total, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Paid')");
        $stmt->bind_param("siidddd", $invoice_number, $customer_id, $user_id, $subtotal, $tax, $discount, $grand_total);
        $stmt->execute();
        $invoice_id = $stmt->insert_id;
        $stmt->close();
        
        // 2. Insert Invoice Items and Update Stock
        $stmt_item = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_id, item_type, beautician_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_stock = $conn->prepare("UPDATE catalog_items SET stock = stock - ? WHERE id = ? AND type = 'Product'");
        
        foreach ($cart_items as $item) {
            $b_id = !empty($item['beautician_id']) ? (int)$item['beautician_id'] : null;
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            $total = $qty * $price;
            
            $stmt_item->bind_param("iisiidd", $invoice_id, $item['id'], $item['type'], $b_id, $qty, $price, $total);
            $stmt_item->execute();
            
            if ($item['type'] === 'Product') {
                $stmt_stock->bind_param("ii", $qty, $item['id']);
                $stmt_stock->execute();
            }
        }
        $stmt_item->close();
        $stmt_stock->close();
        
        // 3. Record Payment
        $stmt_pay = $conn->prepare("INSERT INTO payments (invoice_id, cashier_id, payment_method, amount, reference_number, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
        $stmt_pay->bind_param("iisds", $invoice_id, $user_id, $payment_method, $grand_total, $payment_details);
        $stmt_pay->execute();
        $stmt_pay->close();
        
        $conn->commit();
        $_SESSION['flash_success'] = "Checkout completed successfully! Invoice: $invoice_number";
        header("Location: pos.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = "Checkout failed: " . $e->getMessage();
        header("Location: pos.php");
        exit;
    }
}

// Fetch Catalog Items
$catalog = [];
try {
    $res = $conn->query("SELECT id, name, type, category, price, image FROM catalog_items WHERE status = 'Active' ORDER BY type ASC, name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['img_url'] = getPosItemImage($row['type'], $row['image'], $row['name'], $row['category']);
            $catalog[] = $row;
        }
    }
} catch (Exception $e) {}

// Fetch Customers
$customers = [];
try {
    $res = $conn->query("SELECT * FROM customers ORDER BY name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $contact = $row['phone'] ?? $row['email'] ?? 'No Contact';
            $customers[] = ['id' => $row['id'], 'name' => $row['name'], 'contact' => $contact];
        }
    }
} catch (Exception $e) {}

// Fetch Beauticians
$beauticians = [];
try {
    $res = $conn->query("SELECT id, name FROM users WHERE role = 'Beautician' AND status = 'Active' ORDER BY name ASC");
    if ($res) while ($row = $res->fetch_assoc()) $beauticians[] = $row;
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<!-- Skeleton Loader Overlay -->
<div id="pos-loader" class="fixed inset-0 bg-[#FAFAFA] z-[100] flex flex-col items-center justify-center transition-opacity duration-500">
    <div class="animate-pulse flex flex-col items-center">
        <div class="w-16 h-16 bg-pink-100 rounded-full mb-4 flex items-center justify-center border border-pink-200">
            <i class="fa-solid fa-cash-register text-brand-magenta text-2xl"></i>
        </div>
        <div class="h-4 bg-gray-200 rounded w-48 mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-32"></div>
    </div>
</div>

<div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-100px)]">
    
    <!-- Left Area: Catalog Grid -->
    <div class="w-full lg:w-2/3 flex flex-col h-full bg-transparent">
        <div class="relative mb-6">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="pos-search" placeholder="Search services or products..." class="w-full pl-12 pr-4 py-3.5 bg-white border border-gray-100 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] focus:outline-none focus:ring-2 focus:ring-brand-pink/50 focus:border-brand-pink transition-all text-sm">
        </div>
        
        <div class="flex-1 overflow-y-auto no-scrollbar pr-2 pb-20 lg:pb-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" id="catalog-grid">
                <?php foreach ($catalog as $item): ?>
                    <div class="catalog-card bg-white p-3 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 hover:border-brand-pink/50 cursor-pointer transition-all hover:-translate-y-1 flex flex-col group"
                         onclick="handleItemClick(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>, '<?= $item['type'] ?>')"
                         data-name="<?= strtolower($item['name']) ?>">
                        
                        <div class="h-32 w-full bg-gray-50 rounded-xl mb-3 flex items-center justify-center overflow-hidden shrink-0 relative">
                            <span class="absolute top-2 left-2 z-10 text-[9px] font-bold uppercase tracking-wider <?= $item['type'] === 'Service' ? 'text-indigo-600 bg-indigo-50 border border-indigo-100' : 'text-emerald-600 bg-emerald-50 border border-emerald-100' ?> px-2 py-0.5 rounded shadow-sm">
                                <?= $item['type'] ?>
                            </span>
                            <img src="<?= $item['img_url'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'">
                        </div>

                        <div class="px-1 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-gray-800 text-sm leading-snug line-clamp-2 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                            <div class="text-lg font-black text-gray-900">&#8377;<?= number_format($item['price'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Area: Cart & Checkout -->
    <div class="w-full lg:w-1/3 bg-white rounded-3xl shadow-[0_5px_30px_rgba(0,0,0,0.03)] border border-pink-50 flex flex-col h-[600px] lg:h-full overflow-hidden flex-shrink-0">
        
        <div class="p-5 border-b border-gray-50 bg-gray-50/30 shrink-0">
            <select id="cart-customer" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all cursor-pointer">
                <option value="">Walk-in Customer</option>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['contact']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="flex-1 overflow-y-auto p-5 no-scrollbar bg-gray-50/20" id="cart-items-container">
            <div id="empty-cart-msg" class="h-full flex flex-col items-center justify-center text-gray-400 opacity-50">
                <i class="fa-solid fa-cart-shopping text-4xl mb-3"></i>
                <p class="text-sm font-medium">Cart is empty</p>
            </div>
        </div>
        
        <div class="p-5 bg-white border-t border-gray-100 shrink-0 z-10 shadow-[0_-5px_20px_rgba(0,0,0,0.02)]">
            <div class="space-y-2 mb-4">
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Subtotal</span>
                    <span id="cart-subtotal" class="font-bold text-gray-800">&#8377;0.00</span>
                </div>
                <div class="flex justify-between text-sm text-gray-500 items-center">
                    <span>Discount (&#8377;)</span>
                    <input type="number" id="cart-discount" value="0" min="0" class="w-20 px-2 py-1 text-right bg-gray-50 border border-gray-200 rounded-md text-xs focus:outline-none focus:border-brand-pink" oninput="calculateTotals()">
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Tax (5%)</span>
                    <span id="cart-tax" class="font-bold text-gray-800">&#8377;0.00</span>
                </div>
                <div class="flex justify-between text-lg pt-2 border-t border-gray-100 mt-2">
                    <span class="font-bold text-gray-800 uppercase text-xs self-center tracking-wider">Total</span>
                    <span id="cart-total" class="font-black text-brand-magenta text-2xl">&#8377;0.00</span>
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-2 mb-3">
                <button type="button" onclick="setPaymentMethod('Cash')" id="btn-pay-Cash" class="pay-method-btn py-2 text-xs font-bold rounded-lg border border-gray-200 text-gray-600 bg-white shadow-sm">CASH</button>
                <button type="button" onclick="setPaymentMethod('UPI')" id="btn-pay-UPI" class="pay-method-btn py-2 text-xs font-bold rounded-lg border border-gray-200 text-gray-600 bg-white shadow-sm">UPI</button>
                <button type="button" onclick="setPaymentMethod('Card')" id="btn-pay-Card" class="pay-method-btn py-2 text-xs font-bold rounded-lg border border-gray-200 text-gray-600 bg-white shadow-sm">CARD</button>
            </div>
            
            <div id="payment-details-wrapper" class="bg-gray-50 p-3 rounded-xl border border-gray-100 mb-4 min-h-[60px] flex items-center justify-center">
                <div id="ui-cash" class="w-full text-center hidden">
                    <p class="text-xs font-bold text-gray-500"><i class="fa-solid fa-money-bill-wave text-emerald-500 mr-1"></i> Collect cash from customer.</p>
                </div>
                
                <div id="ui-upi" class="w-full hidden flex-col items-center">
                    <p class="text-[10px] font-bold uppercase text-gray-400 mb-2 tracking-wider">Scan to Pay</p>
                    <div class="p-1.5 bg-white border border-gray-200 rounded-lg shadow-sm mb-3">
                        <img id="upi-qr-image" src="" alt="UPI QR Code" class="w-24 h-24 object-contain opacity-50 transition-opacity" onload="this.classList.remove('opacity-50')">
                    </div>
                    <div class="flex gap-2 w-full">
                        <button type="button" onclick="setUpiApp('GPay')" id="btn-upi-GPay" class="upi-app-btn flex-1 py-1.5 text-[10px] font-bold rounded-md border border-gray-200 text-gray-600 bg-white">GPay</button>
                        <button type="button" onclick="setUpiApp('PhonePe')" id="btn-upi-PhonePe" class="upi-app-btn flex-1 py-1.5 text-[10px] font-bold rounded-md border border-gray-200 text-gray-600 bg-white">PhonePe</button>
                        <button type="button" onclick="setUpiApp('Paytm')" id="btn-upi-Paytm" class="upi-app-btn flex-1 py-1.5 text-[10px] font-bold rounded-md border border-gray-200 text-gray-600 bg-white">Paytm</button>
                    </div>
                </div>
                
                <div id="ui-card" class="w-full hidden">
                    <p class="text-[10px] font-bold uppercase text-gray-400 mb-2 tracking-wider text-center">Select Card Type</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta">
                            <input type="radio" name="ui_card_type" value="Visa" class="hidden" onchange="setCardType(this.value)">
                            <i class="fa-brands fa-cc-visa text-blue-600 text-lg"></i>
                            <span class="text-xs font-bold text-gray-700">Visa</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta">
                            <input type="radio" name="ui_card_type" value="Mastercard" class="hidden" onchange="setCardType(this.value)">
                            <i class="fa-brands fa-cc-mastercard text-orange-500 text-lg"></i>
                            <span class="text-xs font-bold text-gray-700">Master</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta">
                            <input type="radio" name="ui_card_type" value="RuPay" class="hidden" onchange="setCardType(this.value)">
                            <i class="fa-regular fa-credit-card text-emerald-600 text-lg"></i>
                            <span class="text-xs font-bold text-gray-700">RuPay</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-pink-50 has-[:checked]:border-brand-magenta">
                            <input type="radio" name="ui_card_type" value="Amex" class="hidden" onchange="setCardType(this.value)">
                            <i class="fa-brands fa-cc-amex text-blue-400 text-lg"></i>
                            <span class="text-xs font-bold text-gray-700">Amex</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <form id="checkout-form" method="POST" action="pos.php" onsubmit="return submitCheckout(event)">
                <input type="hidden" name="action" value="complete_checkout">
                <input type="hidden" name="customer_id" id="form-customer_id">
                <input type="hidden" name="subtotal" id="form-subtotal">
                <input type="hidden" name="tax" id="form-tax">
                <input type="hidden" name="discount" id="form-discount">
                <input type="hidden" name="grand_total" id="form-grand_total">
                <input type="hidden" name="cart_data" id="form-cart_data">
                <input type="hidden" name="payment_method" id="form-payment_method" value="Cash">
                <input type="hidden" name="upi_app" id="form-upi_app" value="">
                <input type="hidden" name="card_type" id="form-card_type" value="">
                
                <div class="flex gap-2">
                    <button type="button" onclick="clearCart()" class="w-12 shrink-0 bg-white border border-gray-200 text-gray-400 hover:text-rose-500 rounded-xl flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                    <button type="submit" class="flex-1 py-3.5 bg-brand-sidebar text-white font-bold rounded-xl text-sm tracking-wide shadow-md hover:bg-brand-sidebar/90 transition-colors flex justify-center items-center gap-2">
                        COMPLETE CHECKOUT <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Select Beautician Modal -->
<div id="beauticianModal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('beauticianModal')"></div>
    <div class="bg-white w-full max-w-[400px] rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="beauticianModalContent">
        <div class="p-5 border-b border-gray-100 bg-gray-50/50">
            <h3 class="font-bold text-gray-900">Assign Professional</h3>
            <p class="text-[10px] text-gray-500 mt-0.5" id="modal-service-name"></p>
        </div>
        <div class="p-5 space-y-3 max-h-[50vh] overflow-y-auto no-scrollbar">
            <?php foreach ($beauticians as $b): ?>
                <button type="button" onclick="confirmServiceAddToCart(<?= $b['id'] ?>, '<?= htmlspecialchars($b['name']) ?>')" class="w-full text-left px-4 py-3 border border-gray-200 bg-white rounded-xl hover:border-brand-pink hover:bg-pink-50 hover:text-brand-magenta font-bold text-sm text-gray-700 transition-colors flex justify-between items-center group shadow-sm hover:shadow-md">
                    <?= htmlspecialchars($b['name']) ?>
                    <i class="fa-solid fa-circle-plus opacity-0 group-hover:opacity-100 transition-opacity"></i>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'POS Billing' : null;
    let cart = [];
    let currentPendingItem = null;
    const TAX_RATE = 0.05;

    window.addEventListener('load', () => {
        setTimeout(() => {
            const loader = document.getElementById('pos-loader');
            if(loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.remove(), 500);
            }
        }, 200);
        setPaymentMethod('Cash');
    });

    document.getElementById('pos-search').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.catalog-card').forEach(card => {
            if (card.dataset.name.includes(term)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    function handleItemClick(id, name, price, type) {
        if (type === 'Service') {
            currentPendingItem = { id, name, price, type, qty: 1 };
            document.getElementById('modal-service-name').innerText = name;
            openModal('beauticianModal');
        } else {
            addToCart({ id, name, price, type, qty: 1, beautician_id: null, beautician_name: null });
        }
    }

    function confirmServiceAddToCart(bId, bName) {
        if (currentPendingItem) {
            currentPendingItem.beautician_id = bId;
            currentPendingItem.beautician_name = bName;
            addToCart(currentPendingItem);
            currentPendingItem = null;
            closeModal('beauticianModal');
        }
    }

    function addToCart(item) {
        const existing = cart.find(i => i.id === item.id && i.beautician_id === item.beautician_id);
        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({...item, unique_key: Date.now() + Math.random()});
        }
        updateCartUI();
    }

    function removeFromCart(uniqueKey) {
        cart = cart.filter(i => i.unique_key !== uniqueKey);
        updateCartUI();
    }

    function updateCartUI() {
        const container = document.getElementById('cart-items-container');
        const emptyMsg = document.getElementById('empty-cart-msg');
        
        if (cart.length === 0) {
            container.innerHTML = '';
            container.appendChild(emptyMsg);
            emptyMsg.style.display = 'flex';
        } else {
            emptyMsg.style.display = 'none';
            let html = '';
            cart.forEach(item => {
                const bText = item.beautician_name ? `<span class="text-[9px] text-brand-magenta font-bold ml-1 bg-pink-50 px-1 rounded">By: ${item.beautician_name}</span>` : '';
                html += `
                    <div class="flex justify-between items-start mb-3 bg-white p-3 border border-gray-100 rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
                        <div class="flex-1 pr-2">
                            <h4 class="text-xs font-bold text-gray-800 leading-tight mb-1">${item.name}</h4>
                            <div class="text-[10px] text-gray-500 font-medium">&#8377;${item.price.toFixed(2)} x ${item.qty} ${bText}</div>
                        </div>
                        <div class="flex flex-col items-end shrink-0">
                            <div class="text-sm font-black text-gray-900 mb-2">&#8377;${(item.price * item.qty).toFixed(2)}</div>
                            <button type="button" onclick="removeFromCart(${item.unique_key})" class="text-gray-300 hover:text-rose-500 transition-colors">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
        calculateTotals();
    }

    function calculateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        let discount = parseFloat(document.getElementById('cart-discount').value) || 0;
        
        if (discount > subtotal) {
            discount = subtotal;
            document.getElementById('cart-discount').value = discount;
        }
        
        const afterDiscount = subtotal - discount;
        const tax = afterDiscount * TAX_RATE;
        const grandTotal = afterDiscount + tax;
        
        document.getElementById('cart-subtotal').innerText = '₹' + subtotal.toFixed(2);
        document.getElementById('cart-tax').innerText = '₹' + tax.toFixed(2);
        document.getElementById('cart-total').innerText = '₹' + grandTotal.toFixed(2);
        
        document.getElementById('form-subtotal').value = subtotal.toFixed(2);
        document.getElementById('form-tax').value = tax.toFixed(2);
        document.getElementById('form-discount').value = discount.toFixed(2);
        document.getElementById('form-grand_total').value = grandTotal.toFixed(2);
        document.getElementById('form-cart_data').value = JSON.stringify(cart);
        
        if (document.getElementById('form-payment_method').value === 'UPI') {
            generateUpiQR(grandTotal.toFixed(2));
        }
    }

    function clearCart() {
        cart = [];
        document.getElementById('cart-discount').value = 0;
        updateCartUI();
    }

    function setPaymentMethod(method) {
        document.getElementById('form-payment_method').value = method;
        document.getElementById('form-upi_app').value = '';
        document.getElementById('form-card_type').value = '';
        document.querySelectorAll('input[name="ui_card_type"]').forEach(r => r.checked = false);
        
        document.querySelectorAll('.upi-app-btn').forEach(btn => {
            btn.classList.remove('bg-purple-50', 'border-purple-300', 'text-purple-700');
            btn.classList.add('border-gray-200', 'text-gray-600', 'bg-white');
        });
        
        const methods = ['Cash', 'UPI', 'Card'];
        methods.forEach(m => {
            const btn = document.getElementById('btn-pay-' + m);
            if (m === method) {
                btn.classList.add('bg-brand-sidebar', 'text-white', 'border-brand-sidebar');
                btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
            } else {
                btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
                btn.classList.remove('bg-brand-sidebar', 'text-white', 'border-brand-sidebar');
            }
        });
        
        document.getElementById('ui-cash').classList.add('hidden');
        document.getElementById('ui-upi').classList.add('hidden');
        document.getElementById('ui-card').classList.add('hidden');
        document.getElementById('ui-upi').classList.remove('flex');
        
        if (method === 'Cash') {
            document.getElementById('ui-cash').classList.remove('hidden');
        } else if (method === 'UPI') {
            document.getElementById('ui-upi').classList.remove('hidden');
            document.getElementById('ui-upi').classList.add('flex');
            generateUpiQR(document.getElementById('form-grand_total').value || "0.00");
        } else if (method === 'Card') {
            document.getElementById('ui-card').classList.remove('hidden');
        }
    }

    function generateUpiQR(amount) {
        const qrImage = document.getElementById('upi-qr-image');
        qrImage.classList.add('opacity-50');
        const upiString = `upi://pay?pa=parlourpos@ybl&pn=ParlourPOS&am=${amount}&cu=INR`;
        qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(upiString)}&color=2A1320`;
    }

    function setUpiApp(app) {
        document.getElementById('form-upi_app').value = app;
        const apps = ['GPay', 'PhonePe', 'Paytm'];
        apps.forEach(a => {
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

    function submitCheckout(e) {
        if (cart.length === 0) {
            e.preventDefault();
            alert("Cart is empty!");
            return false;
        }
        
        const method = document.getElementById('form-payment_method').value;
        if (method === 'UPI' && !document.getElementById('form-upi_app').value) {
            document.getElementById('form-upi_app').value = 'UPI QR Scan';
        }
        if (method === 'Card' && !document.getElementById('form-card_type').value) {
            e.preventDefault();
            alert("Please select a Card Type.");
            return false;
        }
        document.getElementById('form-customer_id').value = document.getElementById('cart-customer').value;
        return true;
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        modal.classList.remove('hidden'); 
        modal.classList.add('flex');
        setTimeout(() => { 
            content.classList.remove('scale-95', 'opacity-0'); 
            content.classList.add('scale-100', 'opacity-100'); 
        }, 10);
    }
    
    function closeModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        content.classList.remove('scale-100', 'opacity-100'); 
        content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { 
            modal.classList.add('hidden'); 
            modal.classList.remove('flex'); 
        }, 200);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>