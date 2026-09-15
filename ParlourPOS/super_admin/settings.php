<?php
// super_admin/settings.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

// Ensure the settings table exists to prevent database errors
$conn->query("CREATE TABLE IF NOT EXISTS business_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
)");

// ==========================================
// HANDLE FORM SUBMISSION (PRG PATTERN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only capture the editable fields
    $updates = [
        'email' => trim($_POST['email'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'whatsapp_number' => trim($_POST['whatsapp_number'] ?? ''),
        'social_media' => trim($_POST['social_media'] ?? '')
    ];

    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("INSERT INTO business_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($updates as $key => $value) {
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        $_SESSION['flash_success'] = "Business settings updated successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = "Failed to update settings.";
    }
    
    header("Location: settings.php");
    exit;
}

// ==========================================
// FETCH CURRENT SETTINGS
// ==========================================
// Define fixed values
$fixed_settings = [
    'parlour_name' => 'Crafting Styles',
    'address' => 'Kolkata, West Bengal, India'
];

// Define defaults for editable fields
$settings = [
    'email' => '',
    'contact_number' => '',
    'whatsapp_number' => '',
    'social_media' => ''
];

try {
    $res = $conn->query("SELECT setting_key, setting_value FROM business_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800 tracking-tight">System Settings</h2>
    <p class="text-gray-500 text-sm mt-1">Configure global business contact details and social links.</p>
</div>

<div class="bg-white rounded-3xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 overflow-hidden max-w-4xl">
    
    <div class="p-6 border-b border-gray-50 bg-gray-50/50">
        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
            <i class="fa-solid fa-store text-brand-magenta"></i> Business Profile
        </h3>
    </div>

    <form method="POST" action="settings.php" class="p-6 md:p-8">
        
        <!-- FIXED FIELDS -->
        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Primary Identity (Fixed)</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Parlour Name</label>
                <div class="relative">
                    <i class="fa-solid fa-crown absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" value="<?= htmlspecialchars($fixed_settings['parlour_name']) ?>" readonly class="w-full pl-10 pr-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-sm text-gray-500 font-bold cursor-not-allowed focus:outline-none select-none">
                </div>
                <p class="text-[10px] text-gray-400 mt-1">Contact system administrator to change the registered business name.</p>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Registered Address</label>
                <div class="relative">
                    <i class="fa-solid fa-map-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" value="<?= htmlspecialchars($fixed_settings['address']) ?>" readonly class="w-full pl-10 pr-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-sm text-gray-500 font-bold cursor-not-allowed focus:outline-none select-none">
                </div>
            </div>
        </div>

        <!-- EDITABLE FIELDS -->
        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Contact & Social Information</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Email Address</label>
                <div class="relative">
                    <i class="fa-regular fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($settings['email']) ?>" placeholder="contact@craftingstyles.com" class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Contact Number</label>
                <div class="relative">
                    <i class="fa-solid fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($settings['contact_number']) ?>" placeholder="+91 00000 00000" class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">WhatsApp Number</label>
                <div class="relative">
                    <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500"></i>
                    <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number']) ?>" placeholder="+91 00000 00000" class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-300 focus:ring-2 focus:ring-emerald-300/50 transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Social Media Link (Insta/FB)</label>
                <div class="relative">
                    <i class="fa-solid fa-link absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="social_media" value="<?= htmlspecialchars($settings['social_media']) ?>" placeholder="https://instagram.com/craftingstyles" class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="px-8 py-3.5 bg-brand-sidebar text-white font-bold rounded-xl text-sm tracking-wide shadow-md hover:bg-brand-sidebar/90 transition-all hover:-translate-y-0.5 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Save Settings
            </button>
        </div>
        
    </form>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'System Settings' : null;
</script>

<?php require_once '../includes/footer.php'; ?>