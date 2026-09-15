<?php
// customers/profile.php
session_name('ParlourPOS_Customer'); // CRITICAL: Isolates customer session so it doesn't redirect
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Ensure user is strictly logged in as a Customer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    try {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Try updating users table
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'Customer'");
            if ($stmt) {
                $stmt->bind_param("sssi", $name, $email, $password, $customer_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Fallback: Try updating legacy customers table
            $stmt2 = $conn->prepare("UPDATE customers SET name = ?, email = ?, password = ? WHERE id = ?");
            if ($stmt2) {
                $stmt2->bind_param("sssi", $name, $email, $password, $customer_id);
                $stmt2->execute();
                $stmt2->close();
            }
        } else {
            // Try updating users table
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'Customer'");
            if ($stmt) {
                $stmt->bind_param("ssi", $name, $email, $customer_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Fallback: Try updating legacy customers table
            $stmt2 = $conn->prepare("UPDATE customers SET name = ?, email = ? WHERE id = ?");
            if ($stmt2) {
                $stmt2->bind_param("ssi", $name, $email, $customer_id);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        
        $_SESSION['name'] = $name; // Update active session name
        $_SESSION['flash_success'] = "Profile updated successfully!";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Failed to update profile. Email might be in use.";
    }
    
    header("Location: profile.php");
    exit;
}

// 1. Establish a default fallback array to PREVENT the "type null" PHP warnings
$user = [
    'name' => $_SESSION['name'] ?? 'Customer',
    'email' => '',
    'phone' => 'Not Provided'
];

// 2. Fetch current user details dynamically
try {
    // First try the unified users table
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ? AND role = 'Customer'");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        $user = array_merge($user, array_filter($res));
    } else {
        // Fallback to legacy customers table
        $stmt2 = $conn->prepare("SELECT name, email, mobile as phone FROM customers WHERE id = ?");
        if ($stmt2) {
            $stmt2->bind_param("i", $customer_id);
            $stmt2->execute();
            $res2 = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            
            if ($res2) {
                $user = array_merge($user, array_filter($res2));
            }
        }
    }
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Profile Settings</h2>
    <p class="text-gray-500 text-sm mt-1">Manage your account details and security preferences.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Profile Card (Left) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl p-8 border border-pink-50 shadow-[0_2px_10px_rgba(0,0,0,0.02)] flex flex-col items-center text-center relative overflow-hidden">
            <!-- Decorative background blob -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-pink-100 rounded-full blur-2xl opacity-50"></div>
            
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=D81B60&color=fff&rounded=true&bold=true&size=128" class="w-24 h-24 rounded-full shadow-md mb-4 relative z-10" alt="Avatar">
            <h3 class="text-xl font-bold text-gray-900 relative z-10"><?= htmlspecialchars($user['name']) ?></h3>
            <p class="text-xs font-bold text-brand-magenta uppercase tracking-wider mt-1 relative z-10">VIP Member</p>
            
            <div class="w-full border-t border-gray-100 my-6"></div>
            
            <div class="w-full text-left space-y-4">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Registered Phone</p>
                    <p class="text-sm font-semibold text-gray-700 mt-0.5"><i class="fa-solid fa-phone text-gray-300 mr-2"></i><?= htmlspecialchars($user['phone']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Form (Right) -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl p-6 md:p-8 border border-pink-50 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-6 border-b border-gray-50 pb-4">Personal Information</h4>
            
            <form method="POST" action="profile.php" class="space-y-6">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                    </div>
                </div>

                <div class="pt-4 mt-2 border-t border-gray-50">
                    <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4">Security</h4>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">New Password (Optional)</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                        <p class="text-[10px] text-gray-400 mt-2"><i class="fa-solid fa-shield-halved mr-1"></i> Your password will be securely encrypted.</p>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="px-8 py-3.5 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide shadow-md hover:bg-brand-sidebar/90 transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Profile Settings' : null;
</script>

<?php require_once '../includes/footer.php'; ?>