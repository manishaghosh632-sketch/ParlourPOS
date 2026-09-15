<?php
// super_admin/add_staff.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']); // Strict access control

// Safely resolve the absolute path to PHPMailer to prevent inclusion errors
$manual_path = dirname(__DIR__) . '/customers/PHPMailer/src/';
require_once $manual_path . 'Exception.php';
require_once $manual_path . 'PHPMailer.php';
require_once $manual_path . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure upload directories exist
$photo_dir = '../assets/images/staff/';
$doc_dir = '../assets/documents/staff/';
if (!is_dir($photo_dir)) mkdir($photo_dir, 0777, true);
if (!is_dir($doc_dir)) mkdir($doc_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    // Auto-generate a secure 10-character random password
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $raw_password = substr(str_shuffle($alphabet), 0, 10);
    
    // Hash the generated password for database storage
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    $photo_name = null;
    $doc_name = null;

    // Handle Photo Upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_name = uniqid('staff_photo_') . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $photo_name);
    }

    // Handle Document Upload
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $doc_name = uniqid('staff_doc_') . '.' . $ext;
        move_uploaded_file($_FILES['document']['tmp_name'], $doc_dir . $doc_name);
    }

    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, photo, document) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)");
    $stmt->bind_param("sssssss", $name, $email, $phone, $password, $role, $photo_name, $doc_name);
    
    if ($stmt->execute()) {
        $staff_added = true;
        $stmt->close(); // Close DB connection early to free resources
        
        // ==========================================
        // SEND WELCOME EMAIL WITH CREDENTIALS
        // ==========================================
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'manishaghosh632@gmail.com';     
            $mail->Password   = 'ztzb muey maop eyrn';            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('manishaghosh632@gmail.com', 'ParlourPOS'); 
            $mail->addAddress($email, $name);

            // Determine Login Link based on role
            global $base_url;
            $login_url = "http://" . $_SERVER['HTTP_HOST'] . $base_url;
            if ($role === 'Manager') {
                $login_url .= "/auth/login_manager.php";
            } else {
                $login_url .= "/auth/login_staff.php";
            }

            // Beautiful HTML Email Template
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to ParlourPOS! Your Login Details';
            $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-w: 600px; margin: 0 auto; border: 1px solid #FBB8C9; border-radius: 16px; overflow: hidden;'>
                <div style='background-color: #2A1320; padding: 30px; text-align: center;'>
                    <h1 style='color: #FBB8C9; margin: 0; font-size: 28px;'>ParlourPOS</h1>
                    <p style='color: #E2C7D2; margin-top: 5px; font-size: 12px; letter-spacing: 2px; text-transform: uppercase;'>Beauty &bull; Care &bull; Confidence</p>
                </div>
                <div style='padding: 30px; background-color: #FCF3F6; color: #333;'>
                    <h2 style='color: #2A1320; margin-top: 0;'>Welcome to the team, $name! 👋</h2>
                    <p>Your <b>$role</b> account has been successfully created. You can now log into the ParlourPOS management system.</p>
                    
                    <div style='background-color: #fff; padding: 20px; border-radius: 12px; border: 1px solid #FBB8C9; margin: 20px 0;'>
                        <p style='margin: 0 0 10px 0;'><b style='color: #D81B60;'>Login URL:</b> <a href='$login_url' style='color: #2A1320;'>$login_url</a></p>
                        <p style='margin: 0 0 10px 0;'><b style='color: #D81B60;'>Email (ID):</b> $email</p>
                        <p style='margin: 0;'><b style='color: #D81B60;'>Password:</b> $raw_password</p>
                    </div>
                    
                    <p style='font-size: 14px; color: #666;'>For security reasons, we highly recommend updating your password once you log in via the Profile Settings page.</p>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='$login_url' style='background-color: #D81B60; color: #fff; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; display: inline-block;'>Login to Dashboard</a>
                    </div>
                </div>
            </div>";

            $mail->send();
            
            // FIX: Cleanly close the SMTP connection immediately so Gmail allows subsequent emails
            $mail->clearAllRecipients();
            $mail->smtpClose();

            $_SESSION['flash_success'] = "New staff member added and credentials emailed successfully.";
        } catch (Exception $e) {
            // If email fails, the staff is still added, so we just show an info message
            $_SESSION['flash_success'] = "Staff member added, but email failed to send. Error: {$mail->ErrorInfo}";
        }
        // ==========================================
        
        header("Location: roles_permissions.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Failed to add staff. Email or phone might already exist.";
        $stmt->close();
    }
}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <a href="roles_permissions.php" class="text-sm font-bold text-gray-400 hover:text-brand-magenta mb-2 inline-block transition-colors"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Staff List</a>
        <h3 class="text-3xl font-bold text-gray-800 tracking-tight">Onboard New Staff</h3>
        <p class="text-sm text-gray-500 mt-1">Add details, upload documents, and manage system access.</p>
    </div>
</div>

<div class="bg-white rounded-2xl p-6 md:p-8 shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 max-w-4xl relative overflow-hidden">
    
    <!-- Decorative Blob -->
    <div class="absolute -top-10 -right-10 w-40 h-40 bg-pink-100 rounded-full blur-3xl opacity-50 pointer-events-none"></div>

    <form method="POST" action="add_staff.php" enctype="multipart/form-data" class="space-y-8 relative z-10" onsubmit="showToast('Processing & Sending Email...', 'info')">
        
        <!-- Section: Personal Details -->
        <div>
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 border-b border-gray-50 pb-2"><i class="fa-solid fa-user text-brand-magenta mr-2"></i> Personal Information</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Full Name <span class="text-brand-magenta">*</span></label>
                    <input type="text" name="name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Phone Number <span class="text-brand-magenta">*</span></label>
                    <input type="text" name="phone" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
            </div>
        </div>

        <!-- Section: Account & Access -->
        <div>
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 border-b border-gray-50 pb-2"><i class="fa-solid fa-shield-halved text-brand-magenta mr-2"></i> Role & Access Permissions</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Email (Login ID) <span class="text-brand-magenta">*</span></label>
                    <input type="email" name="email" required placeholder="name@parlourpos.com" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">System Role <span class="text-brand-magenta">*</span></label>
                    <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink focus:ring-2 focus:ring-brand-pink/50 cursor-pointer transition-all">
                        <option value="" disabled selected>Select Role...</option>
                        <option value="Beautician">Beautician (Dashboard & Appointments)</option>
                        <option value="Receptionist">Receptionist (POS & Customers)</option>
                        <option value="Manager">Manager (Inventory, POS, Staff)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Initial Password <span class="text-brand-magenta">*</span></label>
                    <div class="w-full px-4 py-3 bg-pink-50 border border-pink-100 text-brand-magenta rounded-xl text-sm font-bold flex items-center justify-between cursor-not-allowed">
                        <span>Auto-Generated</span>
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2"><i class="fa-solid fa-envelope mr-1"></i> An automated email containing the login URL, Email ID, and securely generated Password will be sent to the staff member upon creation.</p>
        </div>

        <!-- Section: Documents & Media -->
        <div>
            <h4 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 border-b border-gray-50 pb-2"><i class="fa-solid fa-file-arrow-up text-brand-magenta mr-2"></i> Uploads & Documents</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Profile Photo (Optional)</label>
                    <input type="file" name="photo" accept="image/*" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-pink-100 file:text-brand-magenta hover:file:bg-brand-magenta hover:file:text-white cursor-pointer transition-all">
                </div>
                <div>
                    <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Identity / Contract Document (Optional)</label>
                    <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.png" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-600 hover:file:text-white cursor-pointer transition-all">
                </div>
            </div>
        </div>

        <div class="pt-6 flex justify-end gap-3 border-t border-gray-50 mt-8">
            <a href="roles_permissions.php" class="px-6 py-3.5 rounded-xl font-bold text-gray-500 hover:bg-gray-100 transition-colors text-sm">Cancel</a>
            <button type="submit" class="px-8 py-3.5 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide hover:bg-brand-sidebar/90 transition-colors shadow-md text-sm">
                <i class="fa-solid fa-paper-plane mr-2 text-brand-pink"></i> Save & Send Email
            </button>
        </div>

    </form>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Add Staff' : null;
</script>

<?php require_once '../includes/footer.php'; ?>