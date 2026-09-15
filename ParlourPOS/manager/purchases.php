<?php
// manager/purchases.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Manager', 'Super Admin']);

// Ensure purchases table exists
$conn->query("CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES catalog_items(id)
)");

// ==========================================================
// PRG PATTERN FOR ADDING PURCHASES
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_purchase') {
        $product_id = (int)$_POST['product_id'];
        $qty = (int)$_POST['quantity'];
        $price = floatval($_POST['purchase_price']);
        $total = $qty * $price;
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        try {
            // 1. Record the purchase[cite: 2]
            $stmt = $conn->prepare("INSERT INTO purchases (product_id, user_id, quantity, purchase_price, total_amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidd", $product_id, $user_id, $qty, $price, $total);
            $stmt->execute();
            
            // 2. Increase inventory automatically[cite: 2]
            $upd = $conn->prepare("UPDATE catalog_items SET current_stock = current_stock + ? WHERE id = ?");
            $upd->bind_param("ii", $qty, $product_id);
            $upd->execute();
            
            $conn->commit();
            $_SESSION['flash_success'] = "Purchase recorded and inventory updated.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Failed to record purchase.";
        }
    }
    
    header("Location: purchases.php");
    exit;
}

// Fetch Dropdown & History
$products = [];
$res_prod = $conn->query("SELECT id, name FROM catalog_items WHERE type='Product' AND status='Active' ORDER BY name ASC");
if($res_prod) while($r = $res_prod->fetch_assoc()) $products[] = $r;

$purchases = [];
try {
    $p_query = "
        SELECT p.id, p.quantity, p.purchase_price, p.total_amount, DATE_FORMAT(p.created_at, '%d %b %Y, %h:%i %p') as p_date,
               c.name as product_name, u.name as added_by
        FROM purchases p
        JOIN catalog_items c ON p.product_id = c.id
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC LIMIT 50
    ";
    $p_res = $conn->query($p_query);
    if($p_res) while($r = $p_res->fetch_assoc()) $purchases[] = $r;
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Purchase Log</h3>
        <p class="text-sm text-gray-500 mt-1">Record incoming supplier stock and expenditures[cite: 2]</p>
    </div>
    <button onclick="openPurchaseModal()" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Record Purchase
    </button>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date Added</th>
                    <th class="px-6 py-4">Product</th>
                    <th class="px-6 py-4 text-center">Quantity</th>
                    <th class="px-6 py-4 text-right">Unit Price</th>
                    <th class="px-6 py-4 text-right">Total Cost</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if(empty($purchases)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No purchase records found.</td></tr>
                <?php else: ?>
                    <?php foreach($purchases as $pur): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= $pur['p_date'] ?></div>
                                <div class="text-[10px] text-gray-400 font-semibold uppercase mt-0.5">By: <?= htmlspecialchars($pur['added_by']) ?></div>
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-700"><?= htmlspecialchars($pur['product_name']) ?></td>
                            <td class="px-6 py-4 text-center font-bold text-brand-sidebar">
                                <span class="bg-indigo-50 text-indigo-600 px-2 py-1 rounded">+<?= $pur['quantity'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-500">₹<?= number_format($pur['purchase_price'], 2) ?></td>
                            <td class="px-6 py-4 text-right font-black text-rose-500">₹<?= number_format($pur['total_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Purchase Modal -->
<div id="addPurchaseModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closePurchaseModal()"></div>
    <div class="bg-white w-full max-w-[450px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addPurchaseModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-900 text-lg">Record Incoming Stock</h3>
            <button onclick="closePurchaseModal()" class="text-gray-400 hover:text-rose-500"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="purchases.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_purchase">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Select Product</label>
                <select name="product_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar cursor-pointer">
                    <option value="" disabled selected>Choose a product...</option>
                    <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Quantity</label>
                    <input type="number" name="quantity" min="1" required placeholder="Units received" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Unit Buy Price (₹)</label>
                    <input type="number" step="0.01" name="purchase_price" required placeholder="0.00" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-brand-sidebar">
                </div>
            </div>

            <button type="submit" class="w-full py-3 mt-4 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Confirm Purchase</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Purchases';
    document.getElementById('page-subtitle').innerText = 'Inventory influx and vendor logging';

    function openPurchaseModal() {
        const modal = document.getElementById('addPurchaseModal');
        const content = document.getElementById('addPurchaseModalContent');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }

    function closePurchaseModal() {
        const modal = document.getElementById('addPurchaseModal');
        const content = document.getElementById('addPurchaseModalContent');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }
</script>

<?php require_once '../includes/footer.php'; ?>