<?php
// staff/cashier/customers.php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';
require_role(['Receptionist', 'Manager', 'Super Admin']);

// Handle adding a new customer manually from the desk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    // Default their web login password to their phone number for easy onboarding
    $password = password_hash($phone, PASSWORD_DEFAULT); 
    
    $conn->begin_transaction();
    try {
        // 1. Add to users table
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'Customer', 'Active')");
        $stmt->bind_param("ssss", $name, $email, $phone, $password);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // 2. Initialize loyalty points in customers table
        try {
            $c_stmt = $conn->prepare("INSERT INTO customers (id, loyalty_points) VALUES (?, 0)");
            $c_stmt->bind_param("i", $user_id);
            $c_stmt->execute();
            $c_stmt->close();
        } catch(Exception $e) {
            // Ignore if table doesn't exist yet
        }

        $conn->commit();
        $_SESSION['flash_success'] = "Client added successfully. Default password is their phone number.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = "Failed to add client. Email or phone may already exist.";
    }
    header("Location: customers.php");
    exit;
}

// Fetch all customers with their lifetime statistics
// FIXED: Renamed the subquery alias to 'client_points' to prevent MariaDB forward reference error
$customers = [];
$query = "
    SELECT 
        u.id, u.name, u.email, u.phone, u.status, u.created_at,
        (SELECT COUNT(*) FROM appointments WHERE customer_id = u.id AND status IN ('Completed', 'Checked In')) as total_visits,
        (SELECT MAX(appointment_date) FROM appointments WHERE customer_id = u.id AND status IN ('Completed', 'Checked In')) as last_visit,
        (SELECT SUM(grand_total) FROM invoices WHERE customer_id = u.id AND payment_status = 'Paid') as total_spent,
        (SELECT loyalty_points FROM customers WHERE id = u.id LIMIT 1) as client_points
    FROM users u
    WHERE u.role = 'Customer'
    ORDER BY u.id DESC
";
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $customers[] = $row;
    }
}

require_once '../../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-2xl font-bold text-brand-sidebar">Client Directory</h3>
        <p class="text-sm text-gray-500 mt-1">Manage customer records, view lifetime spend, and track loyalty points.</p>
    </div>
    <div class="flex items-center gap-3">
        <!-- Live Search Bar -->
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="clientSearch" placeholder="Search by name or phone..." class="pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-sidebar w-64 shadow-sm" onkeyup="filterTable()">
        </div>
        
        <button onclick="openModal('addClientModal')" class="bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-5 py-2.5 rounded-xl text-sm font-medium shadow-md transition-smooth flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Add Client
        </button>
    </div>
</div>

<div class="pos-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="clientsTable">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Client Details</th>
                    <th class="px-6 py-4">Lifetime Spend</th>
                    <th class="px-6 py-4">Visits & History</th>
                    <th class="px-6 py-4 text-center">Loyalty Points</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            <i class="fa-regular fa-address-book text-3xl mb-3 opacity-50 block"></i>
                            No clients found in the directory.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors client-row">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-brand-bg flex items-center justify-center text-brand-sidebar font-bold border border-gray-200 shrink-0">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 client-name"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="text-[11px] text-gray-500 mt-0.5 client-contact flex items-center gap-2">
                                            <span><i class="fa-solid fa-phone text-gray-400"></i> <?= htmlspecialchars($c['phone']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if($c['total_spent'] > 0): ?>
                                    <span class="font-bold text-brand-coral">₹<?= number_format($c['total_spent'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400 font-medium">₹0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= $c['total_visits'] ?? 0 ?> Visits</div>
                                <?php if($c['last_visit']): ?>
                                    <div class="text-[10px] text-gray-500 mt-0.5">Last: <?= date('d M, Y', strtotime($c['last_visit'])) ?></div>
                                <?php else: ?>
                                    <div class="text-[10px] text-gray-400 mt-0.5">No visits yet</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-600 border border-amber-100 rounded-full text-xs font-bold inline-flex items-center gap-1">
                                    <i class="fa-solid fa-star text-[10px]"></i> <?= $c['client_points'] ?? 0 ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $c['status'] === 'Active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                    <?= $c['status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Client Modal -->
<div id="addClientModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="closeModal('addClientModal')"></div>
    <div class="bg-white w-full max-w-[450px] mx-4 rounded-2xl shadow-2xl z-10 overflow-hidden transform scale-95 opacity-0 transition-all duration-200" id="addClientModalContent">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div>
                <h3 class="font-bold text-gray-900 text-lg">Register New Client</h3>
                <p class="text-[11px] text-gray-500 mt-1">Default web password will be their phone number.</p>
            </div>
            <button onclick="closeModal('addClientModal')" class="text-gray-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="POST" action="customers.php" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_customer">
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Full Name <span class="text-brand-coral">*</span></label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
            </div>
            
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Phone Number <span class="text-brand-coral">*</span></label>
                <input type="text" name="phone" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Email Address (Optional)</label>
                <input type="email" name="email" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
            </div>

            <button type="submit" class="w-full py-3 mt-4 bg-brand-sidebar text-white rounded-xl font-bold hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">Save Client Record</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Customers';
    document.getElementById('page-subtitle').innerText = 'Client Directory & History';
    
    // Live Search Functionality
    function filterTable() {
        let input = document.getElementById("clientSearch");
        let filter = input.value.toLowerCase();
        let trs = document.querySelectorAll('.client-row');

        trs.forEach(tr => {
            let name = tr.querySelector('.client-name').textContent.toLowerCase();
            let contact = tr.querySelector('.client-contact').textContent.toLowerCase();
            
            if (name.includes(filter) || contact.includes(filter)) {
                tr.style.display = "";
            } else {
                tr.style.display = "none";
            }
        });
    }

    // Modal Controls
    function openModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => { content.classList.remove('scale-95', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    
    function closeModal(id) {
        const modal = document.getElementById(id);
        const content = document.getElementById(id + 'Content');
        content.classList.remove('scale-100', 'opacity-100'); content.classList.add('scale-95', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>