<?php
// customers/register.php
session_start();
$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

require_once '../config/database.php';

// PHPMailer requires
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure table supports necessary columns
$conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL");

// ==========================================================
// PRG PATTERN FOR REGISTRATION & OTP VIA PHPMAILER
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $mobile = trim($_POST['mobile']);
        $password = $_POST['password'];

        // Check if email or mobile exists
        $check = $conn->prepare("SELECT id FROM customers WHERE email = ? OR mobile = ? LIMIT 1");
        $check->bind_param("ss", $email, $mobile);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = "An account with this email or mobile number already exists.";
        } else {
            // Generate OTP
            $otp = sprintf("%06d", random_int(100000, 999999));
            $_SESSION['reg_otp'] = $otp;
            $_SESSION['reg_data'] = [
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT)
            ];
            
            // Send OTP using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                
                // Updated Email Address
                $mail->Username   = 'manishaghosh632@gmail.com';
                
                // PASTE YOUR 16-DIGIT APP PASSWORD HERE (Replace the text inside the quotes)
                $mail->Password   = 'ztzb muey maop eyrn'; 
                
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // XAMPP Localhost SSL Bypass (Prevents certificate verification failures on Windows)
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Updated From Address
                $mail->setFrom('manishaghosh632@gmail.com', 'Crafting Styles Admin');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Account - Crafting Styles';
                $mail->Body    = "
                    <h3>Account Verification</h3>
                    <p>Hello $name,</p>
                    <p>Thank you for registering at Crafting Styles.</p>
                    <p>Your Verification OTP is: <b style='font-size: 20px; color: #be185d; letter-spacing: 2px;'>$otp</b></p>
                    <p>If you did not request this, please ignore this email.</p>
                    <br>
                    <p>Regards,<br>Crafting Styles Team</p>
                ";

                $mail->send();
                $_SESSION['flash_success'] = "Verification OTP sent to $email.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to send OTP email. Mailer Error: {$mail->ErrorInfo}";
                unset($_SESSION['reg_otp']);
                unset($_SESSION['reg_data']);
            }
        }
        $check->close();
    } 
    elseif ($action === 'verify_otp') {
        $entered_otp = trim($_POST['otp']);
        
        if (isset($_SESSION['reg_otp']) && (string)$_SESSION['reg_otp'] === $entered_otp) {
            $data = $_SESSION['reg_data'];
            
            $stmt = $conn->prepare("INSERT INTO customers (name, email, mobile, password_hash, status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->bind_param("ssss", $data['name'], $data['email'], $data['mobile'], $data['password_hash']);
            
            if ($stmt->execute()) {
                $_SESSION['customer_id'] = $conn->insert_id;
                $_SESSION['customer_name'] = $data['name'];
                
                unset($_SESSION['reg_otp']);
                unset($_SESSION['reg_data']);
                
                header("Location: dashboard.php");
                exit;
            } else {
                $_SESSION['flash_error'] = "Database error during registration.";
            }
            $stmt->close();
        } else {
            $_SESSION['flash_error'] = "Invalid OTP. Please try again.";
        }
    }

    header("Location: register.php");
    exit;
}

$step = isset($_SESSION['reg_otp']) ? 'verify' : 'details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Crafting Styles</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700;900&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Montserrat', 'sans-serif'], serif: ['"Playfair Display"', 'serif'] } } } }
    </script>
    <style>
        body { background-color: #000; overflow-x: hidden; }
        #loader-overlay { transition: opacity 0.6s ease-out; z-index: 9999; }
        .hero-bg { background-image: url('<?= $base_url ?>/assets/images/login-bg.png'); background-size: cover; background-position: left center; }
        .line-input { background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 0; transition: all 0.3s ease; }
        .line-input:focus { border-bottom-color: #fbcfe8; outline: none; box-shadow: 0 4px 6px -6px #fbcfe8; }
        .line-input::placeholder { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="relative min-h-screen font-sans antialiased text-white flex">
    
    <!-- Skeleton Loader Overlay -->
    <div id="loader-overlay" class="fixed inset-0 bg-[#1A1A1A] flex flex-col items-center justify-center z-[9999]">
        <div class="h-8 w-64 bg-gray-700 rounded mb-4 animate-pulse"></div>
    </div>

    <div class="fixed inset-0 pointer-events-none">
        <div class="hero-bg absolute inset-0 w-full h-full opacity-60"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-transparent w-full"></div>
    </div>

    <div class="relative z-10 flex-1 hidden lg:flex flex-col justify-center px-16 xl:px-24">
        <h1 class="text-7xl xl:text-9xl text-white font-serif drop-shadow-2xl">Create<br><span class="italic text-[#fbcfe8]">Account</span></h1>
    </div>

    <div class="w-full lg:w-[480px] min-h-screen bg-[#be185d]/90 backdrop-blur-2xl border-l border-white/10 flex flex-col justify-center px-8 sm:px-12 relative z-20 shadow-2xl overflow-y-auto py-12">
        <div class="text-left mb-8">
            <h2 class="font-sans font-medium tracking-widest text-2xl text-white uppercase">Sign Up</h2>
            <p class="text-xs text-gray-300 font-light mt-2 tracking-wide">Join our exclusive client portal.</p>
        </div>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="mb-6 p-4 bg-red-500/20 border-l-2 border-red-500 text-white text-xs">
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
                <?php unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="mb-6 p-4 bg-emerald-500/20 border-l-2 border-emerald-500 text-white text-xs">
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
                <?php unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'details'): ?>
            <form method="POST" action="register.php" class="space-y-6" id="registerForm" onsubmit="document.getElementById('loader-overlay').style.display='flex'; setTimeout(()=>document.getElementById('loader-overlay').style.opacity='1',10);">
                <input type="hidden" name="action" value="send_otp">
                
                <div class="relative">
                    <i class="fa-regular fa-user absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                    <input type="text" name="name" required placeholder="Full Name" class="line-input w-full pl-8 pr-4 py-3 text-sm">
                </div>
                
                <div class="relative">
                    <i class="fa-solid fa-mobile-screen absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                    <input type="tel" name="mobile" required placeholder="Phone Number" class="line-input w-full pl-8 pr-4 py-3 text-sm">
                </div>

                <div class="relative">
                    <i class="fa-regular fa-envelope absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                    <input type="email" name="email" required placeholder="Email Address" class="line-input w-full pl-8 pr-4 py-3 text-sm">
                </div>
                
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                    <input type="password" name="password" id="regPassword" required pattern="(?=.*\d)(?=.*[A-Z]).{8,}" title="Minimum 8 characters, at least 1 uppercase letter, and 1 number" placeholder="Password" class="line-input w-full pl-8 pr-10 py-3 text-sm">
                </div>
                
                <ul class="text-[10px] text-white/60 space-y-1 mt-2" id="pwdRules">
                    <li id="ruleLength"><i class="fa-solid fa-xmark text-red-400 w-3"></i> Minimum 8 characters</li>
                    <li id="ruleUpper"><i class="fa-solid fa-xmark text-red-400 w-3"></i> At least 1 uppercase letter</li>
                    <li id="ruleNumber"><i class="fa-solid fa-xmark text-red-400 w-3"></i> At least 1 number</li>
                </ul>

                <button type="submit" id="btnSendOtp" class="w-full mt-6 bg-[#fbcfe8] hover:bg-white text-black font-bold tracking-[0.2em] text-xs py-4 transition-all uppercase">Send Verification OTP</button>
            </form>
        <?php else: ?>
            <form method="POST" action="register.php" class="space-y-6" onsubmit="document.getElementById('loader-overlay').style.display='flex'; setTimeout(()=>document.getElementById('loader-overlay').style.opacity='1',10);">
                <input type="hidden" name="action" value="verify_otp">
                
                <p class="text-sm text-white/80 mb-4">We've sent a 6-digit code to <strong><?= htmlspecialchars($_SESSION['reg_data']['email']) ?></strong>.</p>
                
                <div class="relative">
                    <i class="fa-solid fa-shield-halved absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                    <input type="text" name="otp" required maxlength="6" placeholder="Enter 6-digit OTP" class="line-input w-full pl-8 pr-4 py-3 text-sm tracking-widest font-bold">
                </div>

                <button type="submit" class="w-full mt-6 bg-[#fbcfe8] hover:bg-white text-black font-bold tracking-[0.2em] text-xs py-4 transition-all uppercase">Verify & Create Account</button>
                
                <div class="text-center mt-4">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('cancelForm').submit();" class="text-[10px] text-white/60 hover:text-white uppercase tracking-wider">Cancel Registration</a>
                </div>
            </form>
            <form id="cancelForm" method="POST" action="register.php" class="hidden">
                <input type="hidden" name="action" value="cancel_reg">
            </form>
            <?php 
                if (isset($_POST['action']) && $_POST['action'] === 'cancel_reg') {
                    unset($_SESSION['reg_otp']);
                    unset($_SESSION['reg_data']);
                    header("Location: register.php");
                    exit;
                }
            ?>
        <?php endif; ?>

        <div class="text-center mt-8">
            <p class="text-xs text-white/60 tracking-wide">Already have an account? <a href="login.php" class="text-white font-bold hover:text-[#fbcfe8] transition-colors ml-1">Sign In</a></p>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => {
                const overlay = document.getElementById('loader-overlay');
                overlay.style.opacity = '0';
                setTimeout(() => { overlay.style.display = 'none'; }, 600);
            }, 300);
        });

        // Visual Password Checklist
        const pwdInput = document.getElementById('regPassword');
        
        if (pwdInput) {
            const validatePassword = function() {
                const val = pwdInput.value;
                const hasLen = val.length >= 8;
                const hasUpper = /[A-Z]/.test(val);
                const hasNum = /[0-9]/.test(val);

                updateRule('ruleLength', hasLen);
                updateRule('ruleUpper', hasUpper);
                updateRule('ruleNumber', hasNum);
            };

            pwdInput.addEventListener('input', validatePassword);
            setTimeout(validatePassword, 100);
        }

        function updateRule(id, isValid) {
            const el = document.getElementById(id);
            if (el) {
                if (isValid) {
                    el.innerHTML = '<i class="fa-solid fa-check text-emerald-400 w-3"></i> ' + el.innerText.replace(/<[^>]*>?/gm, '').trim();
                    el.classList.replace('text-white/60', 'text-emerald-400');
                } else {
                    el.innerHTML = '<i class="fa-solid fa-xmark text-red-400 w-3"></i> ' + el.innerText.replace(/<[^>]*>?/gm, '').trim();
                    el.classList.replace('text-emerald-400', 'text-white/60');
                }
            }
        }
    </script>
</body>
</html>