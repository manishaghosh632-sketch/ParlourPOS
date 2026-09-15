<?php
// super_admin/products.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

$user_id = $_SESSION['user_id'];
$prod_dir = '../assets/images/products/';
if (!is_dir($prod_dir)) mkdir($prod_dir, 0777, true);

// PRG Pattern for handling form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action: Add New Product
    if ($action === 'add_product') {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $image = null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $prod_dir . $image);
        }

        try {
            $stmt = $conn->prepare("INSERT INTO catalog_items (name, category, price, type, status, stock, image) VALUES (?, ?, ?, 'Product', 'Active', 0, ?)");
            $stmt->bind_param("ssds", $name, $category, $price, $image);
            $stmt->execute();
            $_SESSION['flash_success'] = "New product added to catalog.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to add product: " . $conn->error;
        }
        header("Location: products.php");
        exit;
    }

    // Action: Edit Product
    if ($action === 'edit_product') {
        $id = (int)$_POST['product_id'];
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $status = $_POST['status'];
        
        try {
            $stmt = $conn->prepare("UPDATE catalog_items SET name=?, category=?, price=?, status=? WHERE id=? AND type='Product'");
            $stmt->bind_param("ssdsi", $name, $category, $price, $status, $id);
            $stmt->execute();
            $_SESSION['flash_success'] = "Product updated successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update product: " . $conn->error;
        }
        header("Location: products.php");
        exit;
    }

    // Action: Record Purchase and Update Stock
    if ($action === 'record_purchase') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $unit_price = (float)$_POST['unit_price'];
        $total_cost = $quantity * $unit_price;
        
        try {
            $conn->begin_transaction();
            
            // Insert into purchases
            $stmt1 = $conn->prepare("INSERT INTO purchases (product_id, user_id, quantity, unit_price, total_cost) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt1) throw new Exception("Prepare failed for purchases: " . $conn->error);
            $stmt1->bind_param("iiidd", $product_id, $user_id, $quantity, $unit_price, $total_cost);
            if (!$stmt1->execute()) throw new Exception("Execute failed for purchases: " . $stmt1->error);
            $stmt1->close();
            
            // Update product stock
            $stmt2 = $conn->prepare("UPDATE catalog_items SET stock = COALESCE(stock, 0) + ? WHERE id = ? AND type = 'Product'");
            if (!$stmt2) throw new Exception("Prepare failed for stock update: " . $conn->error);
            $stmt2->bind_param("ii", $quantity, $product_id);
            if (!$stmt2->execute()) throw new Exception("Execute failed for stock update: " . $stmt2->error);
            $stmt2->close();
            
            $conn->commit();
            $_SESSION['flash_success'] = "Purchase logged and inventory updated.";
        } catch (Exception $e) {
            $conn->rollback();
            // This will print the EXACT database error to your screen if it fails again
            $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
        }
        header("Location: products.php");
        exit;
    }

    // Action: Delete Product (and cascade purchases)
    if ($action === 'delete_product') {
        $product_id = (int)$_POST['product_id'];
        
        try {
            $conn->begin_transaction();
            @$conn->query("DELETE FROM purchases WHERE product_id = $product_id");
            
            $stmt = $conn->prepare("DELETE FROM catalog_items WHERE id = ? AND type = 'Product'");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['flash_success'] = "Product and its purchase history successfully deleted.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Failed to delete product: " . $conn->error;
        }
        header("Location: products.php");
        exit;
    }
}

// Fetch Catalog Products
$products = [];
try {
    $res = $conn->query("SELECT id, name, category, price, COALESCE(stock, 0) as stock, status, image FROM catalog_items WHERE type = 'Product' ORDER BY name ASC");
    if ($res) while ($row = $res->fetch_assoc()) $products[] = $row;
} catch (Exception $e) {}

// Fetch Purchase Log
$purchases = [];
try {
    $res2 = $conn->query("
        SELECT p.id, p.quantity, p.unit_price, p.total_cost, COALESCE(p.created_at, CURRENT_TIMESTAMP) as date_added, c.name as product_name, u.name as added_by
        FROM purchases p 
        JOIN catalog_items c ON p.product_id = c.id 
        JOIN users u ON p.user_id = u.id
        ORDER BY p.id DESC LIMIT 50
    ");
    if ($res2) while ($row = $res2->fetch_assoc()) $purchases[] = $row;
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Products & Stock Management</h2>
        <p class="text-gray-500 text-sm mt-1">Manage retail products, view live inventory, and track expenditures.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openModal('addProductModal')" class="bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition-smooth flex items-center gap-2">
            <i class="fa-solid fa-box-open text-brand-magenta"></i> Add Product
        </button>
        <button onclick="openModal('recordPurchaseModal')" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md transition-smooth flex items-center gap-2">
            <i class="fa-solid fa-plus text-brand-pink"></i> Record Purchase
        </button>
    </div>
</div>

<!-- Products Table -->
<div class="mb-8">
    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4"><i class="fa-solid fa-boxes-stacked text-brand-magenta mr-2"></i> Catalog & Live Stock</h4>
    <div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold border-b border-gray-50">
                        <th class="px-6 py-4">Product</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Stock Level</th>
                        <th class="px-6 py-4">Retail Price</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">No products found. Add one above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $prod): ?>
                            <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                                <td class="px-6 py-4 flex items-center gap-3">
                                    <?php if (!empty($prod['image'])): ?>
                                        <img src="<?= $base_url ?>/assets/images/products/<?= $prod['image'] ?>" class="w-10 h-10 rounded-lg object-cover shadow-sm">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-brand-pink shadow-sm"><i class="fa-solid fa-bottle-droplet"></i></div>
                                    <?php endif; ?>
                                    <span class="font-bold text-gray-800"><?= htmlspecialchars($prod['name']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($prod['category'] ?: 'General') ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($prod['stock'] <= 5): ?>
                                        <span class="text-rose-500 font-bold bg-rose-50 px-2 py-1 rounded-md"><i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= $prod['stock'] ?></span>
                                    <?php else: ?>
                                        <span class="text-emerald-600 font-bold bg-emerald-50 px-2 py-1 rounded-md"><?= $prod['stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-800">₹<?= number_format($prod['price'], 2) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $prod['status'] === 'Active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-gray-100 text-gray-600 border-gray-200' ?>">
                                        <?= $prod['status'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick='openEditModal(<?= json_encode($prod) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-500 hover:text-white transition-smooth inline-flex items-center justify-center" title="Edit Product">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <form method="POST" action="products.php" class="inline-block" onsubmit="return confirm('Are you sure you want to permanently delete this product? All stock and purchase records will be lost.');">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-smooth inline-flex items-center justify-center" title="Delete Product">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Purchase Log Section -->
<div class="mb-8">
    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4"><i class="fa-solid fa-clock-rotate-left text-brand-magenta mr-2"></i> Global Purchase Log</h4>
    <div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold border-b border-gray-50">
                        <th class="px-6 py-4">Date & Time</th>
                        <th class="px-6 py-4">Product Added</th>
                        <th class="px-6 py-4">Quantity</th>
                        <th class="px-6 py-4">Unit Price</th>
                        <th class="px-6 py-4">Total Cost</th>
                        <th class="px-6 py-4">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50">
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">No purchases found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $pur): ?>
                            <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                                <td class="px-6 py-4 text-gray-500 font-medium"><?= date('d M Y, h:i A', strtotime($pur['date_added'])) ?></td>
                                <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($pur['product_name']) ?></td>
                                <td class="px-6 py-4 font-bold text-emerald-600">+<?= $pur['quantity'] ?> Units</td>
                                <td class="px-6 py-4 text-gray-600">₹<?= number_format($pur['unit_price'], 2) ?></td>
                                <td class="px-6 py-4 font-black text-brand-magenta">₹<?= number_format($pur['total_cost'], 2) ?></td>
                                <td class="px-6 py-4 text-gray-500 font-medium"><?= htmlspecialchars($pur['added_by']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- 1. Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addProductModal')"></div>
    <div class="bg-white w-full max-w-[500px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addProductModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div><h3 class="font-bold text-gray-900 text-lg">Add New Product</h3></div>
            <button type="button" onclick="closeModal('addProductModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="products.php" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_product">
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Product Name</label>
                <input type="text" name="name" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Category</label>
                    <input type="text" name="category" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Retail Price (₹)</label>
                    <input type="number" step="0.01" name="price" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Image (Optional)</label>
                <input type="file" name="image" accept="image/*" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-pink-50 file:text-brand-magenta hover:file:bg-brand-magenta cursor-pointer transition-all">
            </div>
            <button type="submit" class="w-full py-3.5 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md mt-4">Add Product</button>
        </form>
    </div>
</div>

<!-- 2. Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('editProductModal')"></div>
    <div class="bg-white w-full max-w-[500px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="editProductModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div><h3 class="font-bold text-gray-900 text-lg">Edit Product</h3></div>
            <button type="button" onclick="closeModal('editProductModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="products.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" id="edit_prod_id">
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Product Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Category</label>
                    <input type="text" name="category" id="edit_category" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Retail Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                </div>
            </div>
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Status</label>
                <select name="status" id="edit_status" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" class="w-full py-3.5 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md mt-4">Save Changes</button>
        </form>
    </div>
</div>

<!-- 3. Record Purchase Modal -->
<div id="recordPurchaseModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('recordPurchaseModal')"></div>
    <div class="bg-white w-full max-w-[500px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="recordPurchaseModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div><h3 class="font-bold text-gray-900 text-lg">Record Incoming Stock</h3></div>
            <button type="button" onclick="closeModal('recordPurchaseModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="products.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="record_purchase">
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Select Product</label>
                <select name="product_id" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                    <option value="" disabled selected>Choose a product...</option>
                    <?php foreach ($products as $prod): ?>
                        <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Quantity</label>
                    <input type="number" name="quantity" min="1" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Unit Price (₹)</label>
                    <input type="number" step="0.01" name="unit_price" min="0" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                </div>
            </div>
            <button type="submit" class="w-full py-3.5 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md mt-4">Save & Update Stock</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Products & Stock Management' : null;

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

    function openEditModal(prod) {
        document.getElementById('edit_prod_id').value = prod.id;
        document.getElementById('edit_name').value = prod.name;
        document.getElementById('edit_category').value = prod.category;
        document.getElementById('edit_price').value = prod.price;
        document.getElementById('edit_status').value = prod.status;
        openModal('editProductModal');
    }
</script>

<?php require_once '../includes/footer.php'; ?>