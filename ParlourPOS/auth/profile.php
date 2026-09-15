<?php
// auth/profile.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_login();

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'general';

// ==========================================================
// PRG PATTERN FOR PROFILE UPDATES
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update General Details
    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);

        // Check if email belongs to another user
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "Email address is already in use.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
            if ($stmt->execute()) {
                $_SESSION['name'] = $name; // Update session name
                $_SESSION['flash_success'] = "Profile updated successfully.";
            } else {
                $_SESSION['flash_error'] = "Failed to update profile.";
            }
            $stmt->close();
        }
        $check->close();
        header("Location: profile.php?tab=general");
        exit;
    }

    // Change Password
    if ($action === 'change_password') {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // Fetch current hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || !password_verify($current_pass, $user['password_hash'])) {
            $_SESSION['flash_error'] = "Current password is incorrect.";
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash_error'] = "New passwords do not match.";
        } elseif (strlen($new_pass) < 6) {
            $_SESSION['flash_error'] = "Password must be at least 6 characters.";
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->bind_param("si", $new_hash, $user_id);
            if ($upd->execute()) {
                $_SESSION['flash_success'] = "Password changed securely.";
            } else {
                $_SESSION['flash_error'] = "Failed to update password.";
            }
            $upd->close();
        }
        $stmt->close();
        header("Location: profile.php?tab=security");
        exit;
    }
}

// Fetch Current User Details
$stmt = $conn->prepare("SELECT name, email, role, commission_type, commission_value FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once '../includes/header.php';
?>

<div class="mb-8">
    <h3 class="text-2xl font-bold text-brand-sidebar">My Profile</h3>
    <p class="text-sm text-gray-500 mt-1">Manage your personal settings and security credentials</p>
</div>

<div class="flex flex-col lg:flex-row gap-8">
    
    <!-- Profile Sidebar Navigation -->
    <div class="w-full lg:w-1/4">
        <div class="pos-card overflow-hidden">
            <div class="p-6 text-center border-b border-gray-100 bg-gray-50/50">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_data['name']) ?>&background=e2e8f0&color=1a1b41&rounded=true&bold=true&size=128" class="w-24 h-24 rounded-full border-4 border-white shadow-lg mx-auto mb-4" alt="Avatar">
                <h4 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($user_data['name']) ?></h4>
                <span class="inline-block mt-2 px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold uppercase tracking-wider border border-indigo-100">
                    <?= htmlspecialchars($user_data['role']) ?>
                </span>
            </div>
            <div class="p-3 space-y-1">
                <a href="?tab=general" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-colors <?= $active_tab === 'general' ? 'bg-brand-sidebar text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-sidebar' ?>">
                    <i class="fa-regular fa-id-card w-5"></i> General Info
                </a>
                <a href="?tab=security" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-colors <?= $active_tab === 'security' ? 'bg-brand-sidebar text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-sidebar' ?>">
                    <i class="fa-solid fa-shield-halved w-5"></i> Security
                </a>
            </div>
        </div>

        <?php if($user_data['commission_type'] !== 'None'): ?>
            <div class="pos-card p-6 mt-6 border-t-4 border-t-emerald-400">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">My Commission Rule</p>
                <div class="font-bold text-gray-800 text-lg">
                    <?= $user_data['commission_type'] ?>: 
                    <span class="text-emerald-500">
                        <?= $user_data['commission_type'] === 'Percentage' ? $user_data['commission_value'] . '%' : '₹' . number_format($user_data['commission_value'], 2) ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content Area -->
    <div class="w-full lg:w-3/4">
        
        <?php if ($active_tab === 'general'): ?>
            <!-- General Info Form -->
            <div class="pos-card p-8">
                <h4 class="font-bold text-gray-900 text-lg mb-6 border-b border-gray-100 pb-4">Personal Details</h4>
                <form method="POST" action="profile.php?tab=general" class="max-w-xl space-y-5">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user_data['name']) ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
                    </div>
                    
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
                    </div>

                    <button type="submit" class="mt-4 bg-brand-sidebar hover:bg-brand-sidebar/90 text-white px-6 py-3 rounded-xl text-sm font-bold shadow-md shadow-brand-sidebar/20 transition-smooth">
                        Save Changes
                    </button>
                </form>
            </div>

        <?php elseif ($active_tab === 'security'): ?>
            <!-- Password Form -->
            <div class="pos-card p-8">
                <h4 class="font-bold text-gray-900 text-lg mb-6 border-b border-gray-100 pb-4">Change Password</h4>
                <form method="POST" action="profile.php?tab=security" class="max-w-xl space-y-5">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Current Password</label>
                        <input type="password" name="current_password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
                    </div>
                    
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">New Password</label>
                        <input type="password" name="new_password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
                        <p class="text-[10px] text-gray-400 mt-1.5"><i class="fa-solid fa-circle-info mr-1"></i>Must be at least 6 characters long.</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:outline-none focus:border-brand-sidebar focus:bg-white transition-colors">
                    </div>

                    <button type="submit" class="mt-4 bg-brand-coral hover:bg-brand-coralHover text-white px-6 py-3 rounded-xl text-sm font-bold shadow-md shadow-brand-coral/20 transition-smooth">
                        Update Password
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Settings';
    document.getElementById('page-subtitle').innerText = 'Account preferences';
</script>

<?php require_once '../includes/footer.php'; ?>