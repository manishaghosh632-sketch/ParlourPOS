<?php
// super_admin/services.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

// Ensure upload directory exists
$upload_dir = '../assets/images/services/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// ==========================================================
// SERVICE IMAGE RESOLVER FUNCTION
// ==========================================================
function getServiceImageUrl($image_filename, $service_name = '', $category = '') {
    global $base_url;
    // 1. Check if uploaded file actually exists on server
    if (!empty($image_filename)) {
        $physical_path = dirname(__DIR__) . '/assets/images/services/' . $image_filename;
        if (file_exists($physical_path)) {
            return $base_url . '/assets/images/services/' . htmlspecialchars($image_filename);
        }
    }
    // 2. High-quality contextual beauty photography based on title/category
    $keyword = strtolower($service_name . ' ' . $category);
    if (strpos($keyword, 'hair') !== false || strpos($keyword, 'cut') !== false || strpos($keyword, 'styling') !== false) {
        return 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80';
    } elseif (strpos($keyword, 'bridal') !== false || strpos($keyword, 'makeup') !== false) {
        // Loads your local bridal_makeup.png file
        return $base_url . '/assets/images/bridal_makeup.png';
    } elseif (strpos($keyword, 'facial') !== false || strpos($keyword, 'glow') !== false || strpos($keyword, 'skin') !== false) {
        return 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=400&q=80';
    } elseif (strpos($keyword, 'nail') !== false || strpos($keyword, 'manicure') !== false || strpos($keyword, 'pedicure') !== false) {
        return 'https://images.unsplash.com/photo-1632345031435-8727f6897d53?auto=format&fit=crop&w=400&q=80';
    } elseif (strpos($keyword, 'spa') !== false || strpos($keyword, 'massage') !== false) {
        return 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=400&q=80';
    }
    return 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=400&q=80';
}

// ==========================================================
// PRG PATTERN: HANDLE FORM SUBMISSIONS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ACTION: ADD NEW SERVICE
    if ($action === 'add_service') {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = floatval($_POST['price']);
        $beauticians = $_POST['beauticians'] ?? [];
        
        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid('srv_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO catalog_items (type, category, name, image, price, status) VALUES ('Service', ?, ?, ?, ?, 'Active')");
            $stmt->bind_param("sssd", $category, $name, $image_name, $price);
            $stmt->execute();
            $service_id = $conn->insert_id;
            $stmt->close();
            
            if (!empty($beauticians)) {
                $link_stmt = $conn->prepare("INSERT INTO service_beauticians (service_id, beautician_id) VALUES (?, ?)");
                foreach ($beauticians as $b_id) {
                    $link_stmt->bind_param("ii", $service_id, $b_id);
                    $link_stmt->execute();
                }
                $link_stmt->close();
            }
            
            $conn->commit();
            $_SESSION['flash_success'] = "Service added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Error adding service: " . $e->getMessage();
        }
    } 
    // ACTION: EDIT EXISTING SERVICE & UPDATE BEAUTICIANS
    elseif ($action === 'edit_service') {
        $id = (int)$_POST['service_id'];
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = floatval($_POST['price']);
        $beauticians = $_POST['beauticians'] ?? [];
        
        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = uniqid('srv_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
        
        $conn->begin_transaction();
        try {
            if ($image_name) {
                $stmt = $conn->prepare("UPDATE catalog_items SET name=?, category=?, price=?, image=? WHERE id=? AND type='Service'");
                $stmt->bind_param("ssdsi", $name, $category, $price, $image_name, $id);
            } else {
                $stmt = $conn->prepare("UPDATE catalog_items SET name=?, category=?, price=? WHERE id=? AND type='Service'");
                $stmt->bind_param("ssdi", $name, $category, $price, $id);
            }
            $stmt->execute();
            $stmt->close();
            
            $conn->query("DELETE FROM service_beauticians WHERE service_id = $id");
            if (!empty($beauticians)) {
                $link_stmt = $conn->prepare("INSERT INTO service_beauticians (service_id, beautician_id) VALUES (?, ?)");
                foreach ($beauticians as $b_id) {
                    $link_stmt->bind_param("ii", $id, $b_id);
                    $link_stmt->execute();
                }
                $link_stmt->close();
            }
            
            $conn->commit();
            $_SESSION['flash_success'] = "Service updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Error updating service: " . $e->getMessage();
        }
    }
    // ACTION: DELETE SERVICE
    elseif ($action === 'delete_service') {
        $id = (int)$_POST['service_id'];
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM service_beauticians WHERE service_id = $id");
            $stmt = $conn->prepare("DELETE FROM catalog_items WHERE id=? AND type='Service'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            $_SESSION['flash_success'] = "Service deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Error deleting service.";
        }
    }
    
    header("Location: services.php");
    exit;
}

// Fetch Active Beauticians
$staff_list = [];
$s_res = $conn->query("SELECT id, name FROM users WHERE role IN ('Beautician', 'Manager') AND status = 'Active'");
if ($s_res) while ($r = $s_res->fetch_assoc()) $staff_list[] = $r;

// Fetch Existing Services
$services = [];
$res = $conn->query("SELECT id, name, image, category, price, status FROM catalog_items WHERE type = 'Service' ORDER BY category ASC, name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $assigned = [];
        $b_res = $conn->query("SELECT beautician_id FROM service_beauticians WHERE service_id = " . $row['id']);
        if ($b_res) while($b_row = $b_res->fetch_assoc()) $assigned[] = $b_row['beautician_id'];
        
        $row['assigned_beauticians'] = $assigned;
        $row['staff_count'] = count($assigned);
        $row['image_url'] = getServiceImageUrl($row['image'], $row['name'], $row['category']);
        $services[] = $row;
    }
}

require_once '../includes/header.php';
?>

<!-- Skeleton Loader Overlay -->
<div id="page-loader" class="fixed inset-0 bg-[#FAFAFA] z-[100] flex flex-col items-center justify-center transition-opacity duration-500">
    <div class="animate-pulse flex flex-col items-center">
        <div class="w-16 h-16 bg-pink-100 rounded-full mb-4 flex items-center justify-center border border-pink-200">
            <i class="fa-solid fa-spa text-brand-magenta text-2xl"></i>
        </div>
        <div class="h-4 bg-gray-200 rounded w-48 mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-32"></div>
    </div>
</div>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Services</h3>
        <p class="text-sm text-gray-500 mt-1">Manage parlour services, assigned staff, and visual service photos.</p>
    </div>
    <button onclick="openModal('addServiceModal')" class="bg-brand-sidebar text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md flex items-center gap-2 hover:bg-brand-sidebar/90 transition-all">
        <i class="fa-solid fa-plus text-brand-pink"></i> Add Service
    </button>
</div>

<div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[700px]">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Service</th>
                    <th class="px-6 py-4">Category</th>
                    <th class="px-6 py-4">Staff Assigned</th>
                    <th class="px-6 py-4">Price</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($services)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No services found. Click "Add Service" above.</td></tr>
                <?php else: ?>
                    <?php foreach ($services as $srv): ?>
                        <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-4">
                                <div class="w-14 h-14 rounded-xl overflow-hidden shadow-sm border border-gray-100 shrink-0 relative group bg-gray-50">
                                    <img src="<?= $srv['image_url'] ?>" alt="<?= htmlspecialchars($srv['name']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=400&q=80'">
                                </div>
                                <div>
                                    <span class="font-bold text-gray-800 text-sm block"><?= htmlspecialchars($srv['name']) ?></span>
                                    <span class="text-[10px] text-gray-400 uppercase tracking-wider font-medium"><?= htmlspecialchars($srv['category']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 font-medium"><?= htmlspecialchars($srv['category']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-lg text-xs font-bold border border-purple-100">
                                    <?= $srv['staff_count'] ?> Beauticians
                                </span>
                            </td>
                            <td class="px-6 py-4 font-black text-brand-sidebar text-base">&#8377;<?= number_format($srv['price'], 2) ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openEditModal(<?= json_encode($srv) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-500 hover:text-white transition-smooth flex items-center justify-center" title="Edit Service">
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <form method="POST" action="services.php" class="inline-block" onsubmit="return confirm('Are you sure you want to permanently delete this service?');">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= $srv['id'] ?>">
                                        <button type="submit" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-smooth flex items-center justify-center" title="Delete Service">
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

<!-- Add Service Modal -->
<div id="addServiceModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addServiceModal')"></div>
    <div class="bg-white w-full max-w-[520px] rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addServiceModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-900 text-lg">Add New Service</h3>
            <button type="button" onclick="closeModal('addServiceModal')" class="text-gray-400 hover:text-rose-500 transition-colors w-8 h-8 rounded-full flex items-center justify-center hover:bg-rose-50"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="services.php" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto no-scrollbar">
            <input type="hidden" name="action" value="add_service">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Service Name <span class="text-brand-magenta">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Gold Glow Facial" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Category <span class="text-brand-magenta">*</span></label>
                    <input type="text" name="category" required placeholder="e.g. Facial, Hair" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Price (&#8377;) <span class="text-brand-magenta">*</span></label>
                    <input type="number" step="0.01" name="price" required placeholder="2500.00" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Service Picture</label>
                <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-pink-50 file:text-brand-magenta hover:file:bg-brand-magenta hover:file:text-white transition-all">
                <p class="text-[10px] text-gray-400 mt-1">If omitted, an automatic curated high-resolution beauty photo will be assigned.</p>
            </div>

            <div class="pt-2 border-t border-gray-100">
                <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Assign Beauticians to this Service</label>
                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1">
                    <?php foreach($staff_list as $staff): ?>
                        <label class="flex items-center gap-2 p-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-pink-50/50 hover:border-brand-pink transition-all">
                            <input type="checkbox" name="beauticians[]" value="<?= $staff['id'] ?>" class="rounded text-brand-magenta focus:ring-brand-pink h-4 w-4">
                            <span class="text-xs font-semibold text-gray-700"><?= htmlspecialchars($staff['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="w-full py-3.5 mt-4 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">
                <i class="fa-solid fa-plus mr-2 text-brand-pink"></i> Add Service
            </button>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="editServiceModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('editServiceModal')"></div>
    <div class="bg-white w-full max-w-[520px] rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="editServiceModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="font-bold text-gray-900 text-lg">Edit Service</h3>
            <button type="button" onclick="closeModal('editServiceModal')" class="text-gray-400 hover:text-rose-500 transition-colors w-8 h-8 rounded-full flex items-center justify-center hover:bg-rose-50"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="services.php" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto no-scrollbar">
            <input type="hidden" name="action" value="edit_service">
            <input type="hidden" name="service_id" id="edit_service_id">
            
            <!-- Current Image Preview -->
            <div class="flex items-center gap-4 p-3 bg-pink-50/50 border border-pink-100 rounded-xl">
                <img id="edit_image_preview" src="" alt="Service Preview" class="w-16 h-16 rounded-lg object-cover border border-pink-200 shadow-sm">
                <div>
                    <span class="text-xs font-bold text-gray-800 block">Current Photo</span>
                    <span class="text-[10px] text-gray-500">Upload a new file below to replace it.</span>
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Service Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Category</label>
                    <input type="text" name="category" id="edit_category" required class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Price (&#8377;)</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Replace Picture (Optional)</label>
                <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-600 hover:file:bg-purple-600 hover:file:text-white transition-all">
            </div>

            <div class="pt-2 border-t border-gray-100">
                <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Assigned Beauticians</label>
                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto pr-1" id="edit_beauticians_container">
                    <?php foreach($staff_list as $staff): ?>
                        <label class="flex items-center gap-2 p-2.5 border border-gray-200 rounded-xl cursor-pointer hover:bg-pink-50/50 hover:border-brand-pink transition-all">
                            <input type="checkbox" name="beauticians[]" value="<?= $staff['id'] ?>" class="edit-staff-checkbox rounded text-brand-magenta focus:ring-brand-pink h-4 w-4" data-staff-id="<?= $staff['id'] ?>">
                            <span class="text-xs font-semibold text-gray-700"><?= htmlspecialchars($staff['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="w-full py-3.5 mt-4 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Save Changes</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Services' : null;
    
    // Skeleton Loader Dismissal
    window.addEventListener('load', () => {
        setTimeout(() => {
            const loader = document.getElementById('page-loader');
            if(loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.remove(), 500);
            }
        }, 200);
    });

    function openEditModal(service) {
        document.getElementById('edit_service_id').value = service.id;
        document.getElementById('edit_name').value = service.name;
        document.getElementById('edit_category').value = service.category;
        document.getElementById('edit_price').value = service.price;
        document.getElementById('edit_image_preview').src = service.image_url;
        
        document.querySelectorAll('.edit-staff-checkbox').forEach(cb => cb.checked = false);
        service.assigned_beauticians.forEach(b_id => {
            const checkbox = document.querySelector(`.edit-staff-checkbox[data-staff-id="${b_id}"]`);
            if(checkbox) checkbox.checked = true;
        });
        
        openModal('editServiceModal');
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

<?php require_once '../includes/footer.php'; ?>