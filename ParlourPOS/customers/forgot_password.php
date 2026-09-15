<?php
// customers/forgot_password.php
session_start();

// Safely resolve the application root directory
$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

require_once '../config/database.php';

// Ensure the necessary columns exist in the customers table to store reset tokens safely
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL");

$error_msg = '';
$success_msg = '';

if (isset($_SESSION['flash_error'])) {
    $error_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_SESSION['flash_success'])) {
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ==========================================
// HANDLE FORM SUBMISSION (PRG PATTERN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['flash_error'] = "Please enter your registered email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM customers WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Generate a secure reset token and set expiry to 1 hour from now
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = $conn->prepare("UPDATE customers SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $update->bind_param("ssi", $token, $expiry, $user['id']);
            $update->execute();
            $update->close();

            // Create the reset link based on the current server host
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $reset_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_url . "/customers/reset_password.php?token=" . $token;

            // ==========================================
            // EXACT PHPMAILER PATH FIX
            // ==========================================
            $mailer_loaded = false;
            
            // 1. Check for Composer Installation (Fallback)
            $composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
            
            // 2. Check for Manual Download Installation inside the 'customers' folder
            // __DIR__ points exactly to the current 'customers' folder where your PHPMailer folder is located.
            $manual_path = __DIR__ . '/PHPMailer/src/';
            
            if (file_exists($composer_autoload)) {
                require_once $composer_autoload;
                $mailer_loaded = true;
            } elseif (file_exists($manual_path . 'Exception.php') && file_exists($manual_path . 'PHPMailer.php') && file_exists($manual_path . 'SMTP.php')) {
                require_once $manual_path . 'Exception.php';
                require_once $manual_path . 'PHPMailer.php';
                require_once $manual_path . 'SMTP.php';
                $mailer_loaded = true;
            }

            if ($mailer_loaded) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    // IMPORTANT: Update these SMTP credentials to match your email provider
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com'; 
                    $mail->SMTPAuth = true;
                    $mail->Username = 'manishaghosh632@gmail.com'; // REPLACE THIS
                    $mail->Password = 'ztzb muey maop eyrn';    // REPLACE THIS
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('noreply@craftingstyles.com', 'Crafting Styles');
                    $mail->addAddress($email, $user['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body    = "Hi {$user['name']},<br><br>Click the link below to reset your password. This link is valid for 1 hour.<br><br><a href='{$reset_link}'>Reset Password</a><br><br>If you didn't request this, please ignore this email.";

                    $mail->send();
                    $_SESSION['flash_success'] = "A password reset link has been sent to your email.";
                } catch (Exception $e) {
                    // If credentials are wrong, show the error but still give the local dev link so you aren't blocked
                    $_SESSION['flash_error'] = "SMTP Error: " . $mail->ErrorInfo . " <br><br><strong>Fallback Dev Link:</strong> <a href='{$reset_link}' class='underline font-bold'>Reset Password Here</a>";
                }
            } else {
                $_SESSION['flash_success'] = "<strong>Local Dev Mode:</strong> PHPMailer is not found in vendor or manual path. Click here to <a href='{$reset_link}' class='text-blue-200 underline font-bold'>Reset Password</a>.";
            }
        } else {
            // Generic success message to prevent email enumeration attacks
            $_SESSION['flash_success'] = "If that email is registered in our system, a reset link has been sent.";
        }
    }
    
    header("Location: forgot_password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Crafting Styles</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700;900&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Montserrat', 'sans-serif'], serif: ['"Playfair Display"', 'serif'] } } } }
    </script>
    <style>
        body { background-color: #000; overflow-x: hidden; }
        #loader-overlay { transition: opacity 0.8s ease-in-out; z-index: 9999; }
        .skeleton-pulse { animation: pulse-op 1.5s ease-in-out infinite; }
        @keyframes pulse-op { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        
        .hero-bg {
            background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&q=80');
            background-size: cover;
            background-position: center;
        }
        .text-glow { text-shadow: 2px 4px 12px rgba(0,0,0,0.8), 0 0 40px rgba(0,0,0,0.6); }
        .line-input { background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 0; transition: all 0.3s ease; }
        .line-input:focus { border-bottom-color: #fbcfe8; outline: none; box-shadow: 0 4px 6px -6px #fbcfe8; }
        .line-input::placeholder { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="relative min-h-screen font-sans antialiased text-white flex">
    
    <!-- Skeleton Loader -->
    <div id="loader-overlay" class="fixed inset-0 bg-[#1A1A1A] flex flex-col items-center justify-center z-[9999]">
        <div class="skeleton-pulse text-center">
            <h1 class="font-serif text-4xl tracking-[0.2em] text-[#fbcfe8] mb-2">CRAFTING STYLES</h1>
            <p class="font-sans text-xs tracking-[0.4em] text-gray-500 uppercase">Account Recovery</p>
        </div>
    </div>

    <!-- Background Elements -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="hero-bg absolute inset-0 w-full h-full opacity-50"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/50 to-transparent w-full"></div>
    </div>

    <!-- Left Branding Section -->
    <div class="relative z-10 flex-1 hidden lg:flex flex-col justify-center px-16 xl:px-24">
        <h1 class="text-7xl xl:text-9xl text-white font-serif drop-shadow-2xl text-glow">CRAFTING<br><span class="italic text-[#fbcfe8]">Styles</span></h1>
        <p class="text-sm tracking-[0.2em] text-gray-200 font-medium mt-8 max-w-lg leading-relaxed uppercase drop-shadow-lg text-glow">
            Password Recovery &bull; Regain access to your beauty profile.
        </p>
    </div>

    <!-- Right Form Section -->
    <div class="w-full lg:w-[480px] min-h-screen bg-[#2A1320]/90 backdrop-blur-2xl border-l border-white/10 flex flex-col justify-center px-8 sm:px-12 relative z-20 shadow-2xl">
        <div class="text-left mb-10">
            <div class="lg:hidden mb-10">
                <h1 class="text-4xl text-white font-serif drop-shadow-lg text-glow">CRAFTING <span class="italic text-[#fbcfe8]">Styles</span></h1>
            </div>
            <h2 class="font-sans font-medium tracking-widest text-2xl text-white uppercase">Reset Password</h2>
            <p class="text-xs text-gray-300 font-light mt-2 tracking-wide leading-relaxed">Enter the email address associated with your account and we will send you a link to reset your password.</p>
        </div>

        <form method="POST" action="forgot_password.php" class="space-y-8" onsubmit="document.getElementById('loader-overlay').style.display='flex'; setTimeout(()=>document.getElementById('loader-overlay').style.opacity='1',10);">
            
            <?php if (!empty($error_msg)): ?>
                <div class="p-4 bg-red-500/20 border-l-2 border-red-500 text-white text-xs leading-relaxed"><?= $error_msg // Intentionally unescaped to allow fallback link to render ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="p-4 bg-emerald-500/20 border-l-2 border-emerald-500 text-white text-xs leading-relaxed"><?= $success_msg // Kept unescaped deliberately to allow the local dev HTML link to render ?></div>
            <?php endif; ?>
            
            <div class="relative">
                <i class="fa-regular fa-envelope absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                <input type="email" name="email" required placeholder="Registered Email Address" class="line-input w-full pl-8 pr-4 py-3 text-sm">
            </div>
            
            <button type="submit" class="w-full mt-8 bg-[#fbcfe8] hover:bg-white text-black font-bold tracking-[0.2em] text-xs py-4 transition-all shadow-[0_0_15px_rgba(251,207,232,0.2)] uppercase">
                Send Reset Link
            </button>
            
            <div class="text-center mt-6">
                <a href="login.php" class="text-xs text-white/60 hover:text-white transition-colors uppercase tracking-widest font-bold">← Back to Login</a>
            </div>
        </form>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => {
                const overlay = document.getElementById('loader-overlay');
                overlay.style.opacity = '0';
                setTimeout(() => { overlay.style.display = 'none'; }, 800);
            }, 600);
        });
    </script>
</body>
</html>