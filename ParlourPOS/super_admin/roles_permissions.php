<?php
// super_admin/roles_permissions.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ACTION: Edit/Manage Staff Access & Details
    if ($action === 'edit_staff') {
        $staff_id = (int)$_POST['staff_id'];
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        $status = $_POST['status'];

        try {
            // If the password field is filled out, update the password as well
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, role=?, status=?, password=? WHERE id=? AND role != 'Super Admin'");
                $stmt->bind_param("sssssi", $name, $phone, $role, $status, $password, $staff_id);
            } else {
                // Otherwise, keep the existing password
                $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, role=?, status=? WHERE id=? AND role != 'Super Admin'");
                $stmt->bind_param("ssssi", $name, $phone, $role, $status, $staff_id);
            }

            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Staff permissions and details updated.";
            } else {
                $_SESSION['flash_error'] = "Failed to update staff member.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "A database error occurred.";
        }
        header("Location: roles_permissions.php");
        exit;
    }

    // ACTION: Delete Staff & All Associated Data
    if ($action === 'delete_staff') {
        $staff_id = (int)$_POST['staff_id'];
        
        try {
            $conn->begin_transaction();
            
            // 1. Fetch file paths to delete physical files from the server
            $f_stmt = $conn->prepare("SELECT photo, document FROM users WHERE id = ? AND role != 'Super Admin'");
            $f_stmt->bind_param("i", $staff_id);
            $f_stmt->execute();
            $files = $f_stmt->get_result()->fetch_assoc();
            $f_stmt->close();

            if ($files) {
                // Delete physical files
                if (!empty($files['photo']) && file_exists('../assets/images/staff/' . $files['photo'])) {
                    unlink('../assets/images/staff/' . $files['photo']);
                }
                if (!empty($files['document']) && file_exists('../assets/documents/staff/' . $files['document'])) {
                    unlink('../assets/documents/staff/' . $files['document']);
                }
                
                // 2. Cascade delete all related database records to prevent Foreign Key constraints
                // (Using @ suppresses warnings if a specific feature's table hasn't been created yet)
                @$conn->query("DELETE FROM service_beauticians WHERE beautician_id = $staff_id");
                @$conn->query("DELETE FROM appointments WHERE staff_id = $staff_id");
                @$conn->query("DELETE FROM purchases WHERE user_id = $staff_id");
                @$conn->query("DELETE FROM refunds WHERE processor_id = $staff_id");
                @$conn->query("DELETE FROM payments WHERE cashier_id = $staff_id");
                @$conn->query("DELETE FROM invoice_items WHERE staff_assigned_id = $staff_id");
                @$conn->query("DELETE FROM invoices WHERE cashier_id = $staff_id");
                
                // 3. Delete the user record
                $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'Super Admin'");
                $del_stmt->bind_param("i", $staff_id);
                
                if ($del_stmt->execute()) {
                    $_SESSION['flash_success'] = "Staff account and all associated data deleted successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to delete staff account.";
                }
                $del_stmt->close();
                
                $conn->commit();
            } else {
                $_SESSION['flash_error'] = "Staff member not found or cannot delete Super Admin.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Error deleting staff data: " . $e->getMessage();
        }
        
        header("Location: roles_permissions.php");
        exit;
    }
}

// Fetch existing staff including the new photo and document columns
$staff_members = [];
$res = $conn->query("SELECT id, name, email, phone, role, status, photo, document FROM users WHERE role IN ('Beautician', 'Receptionist', 'Manager') ORDER BY role ASC, name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $staff_members[] = $row;
    }
}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Staff & Access Management</h3>
        <p class="text-sm text-gray-500 mt-1">Manage system access, roles, and staff records.</p>
    </div>
    <a href="add_staff.php" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
        <i class="fa-solid fa-user-plus"></i> Add New Staff
    </a>
</div>

<div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold border-b border-gray-50">
                    <th class="px-6 py-4">Staff Member</th>
                    <th class="px-6 py-4">Contact (Login ID)</th>
                    <th class="px-6 py-4">System Role</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($staff_members)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No staff members found.</td></tr>
                <?php else: ?>
                    <?php foreach ($staff_members as $staff): ?>
                        <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <?php if (!empty($staff['photo'])): ?>
                                    <img src="<?= isset($base_url) ? $base_url : '..' ?>/assets/images/staff/<?= $staff['photo'] ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-pink-50 flex items-center justify-center text-brand-magenta border border-pink-100 shadow-sm">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="font-bold text-gray-800 block"><?= htmlspecialchars($staff['name']) ?></span>
                                    <?php if (!empty($staff['document'])): ?>
                                        <a href="<?= isset($base_url) ? $base_url : '..' ?>/assets/documents/staff/<?= $staff['document'] ?>" target="_blank" class="text-[10px] font-bold text-brand-magenta hover:underline mt-0.5 inline-block"><i class="fa-solid fa-file-contract mr-1"></i>View Doc</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-900 font-medium"><?= htmlspecialchars($staff['email']) ?></div>
                                <div class="text-[10px] text-gray-500 mt-0.5"><i class="fa-solid fa-phone text-gray-300 mr-1"></i> <?= htmlspecialchars($staff['phone']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-purple-50 text-purple-600 rounded text-[10px] font-bold border border-purple-100 uppercase tracking-wider">
                                    <?= htmlspecialchars($staff['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border <?= $staff['status'] === 'Active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100' ?>">
                                    <?= $staff['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <!-- Manage / Edit Button -->
                                <button onclick='openEditModal(<?= json_encode($staff) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 hover:bg-blue-500 hover:text-white transition-smooth flex items-center justify-center" title="Manage Access">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                
                                <!-- Delete Button -->
                                <form method="POST" action="roles_permissions.php" onsubmit="return confirm('Are you sure you want to permanently delete <?= htmlspecialchars($staff['name']) ?>? ALL their associated data (appointments, invoices, purchases) will be wiped.');">
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-smooth flex items-center justify-center" title="Delete Staff">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Staff & Access Modal -->
<div id="editStaffModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('editStaffModal')"></div>
    <div class="bg-white w-full max-w-[500px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="editStaffModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div>
                <h3 class="font-bold text-gray-900 text-lg">Manage Staff Access</h3>
                <p class="text-[11px] text-gray-500 mt-1">Update roles, status, and permissions.</p>
            </div>
            <button onclick="closeModal('editStaffModal')" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="roles_permissions.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="staff_id" id="edit_staff_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Full Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">System Role</label>
                    <select name="role" id="edit_role" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer transition-all">
                        <option value="Beautician">Beautician</option>
                        <option value="Receptionist">Receptionist</option>
                        <option value="Manager">Manager</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Account Status</label>
                    <select name="status" id="edit_status" required class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer transition-all">
                        <option value="Active">Active (Can Login)</option>
                        <option value="Inactive">Inactive (Access Revoked)</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-50">
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Reset Password (Optional)</label>
                <input type="password" name="password" placeholder="Leave blank to keep current password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
            </div>

            <button type="submit" class="w-full py-3.5 mt-4 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Update Permissions</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Staff & Access Management' : null;
    
    function openEditModal(staff) {
        document.getElementById('edit_staff_id').value = staff.id;
        document.getElementById('edit_name').value = staff.name;
        document.getElementById('edit_phone').value = staff.phone;
        document.getElementById('edit_role').value = staff.role;
        document.getElementById('edit_status').value = staff.status;
        
        const modal = document.getElementById('editStaffModal');
        const content = document.getElementById('editStaffModalContent');
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