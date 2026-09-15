<?php
// customers/login.php
session_name('ParlourPOS_Customer');
session_start();

$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

require_once '../config/database.php';

// Prevent redirect loops by validating exact session requirements
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Customer') {
        header("Location: dashboard.php");
        exit;
    } else {
        // Destroy stale/poisoned session variables causing the loop
        session_unset();
        session_destroy();
        session_start(); // Restart cleanly
    }
}

// ==========================================================
// PRG PATTERN FOR STANDARD LOGIN
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $_SESSION['flash_error'] = "Please enter both email and password.";
    } else {
        // CORRECTED: Querying the 'customers' table and checking 'password_hash'
        $stmt = $conn->prepare("SELECT id, name, password_hash, status FROM customers WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Use password_verify with the password_hash column
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'Active') {
                session_regenerate_id(true);
                // Set the exact variables dashboard.php expects
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = 'Customer';
                header("Location: dashboard.php");
                exit;
            } else {
                $_SESSION['flash_error'] = "Your account has been deactivated.";
            }
        } else {
            $_SESSION['flash_error'] = "Invalid email or password.";
        }
        $stmt->close();
    }
    
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login | Crafting Styles</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;700;900&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Montserrat', 'sans-serif'], serif: ['"Playfair Display"', 'serif'] } } } }
    </script>
    <style>
        body { background-color: #000; overflow-x: hidden; }
        #loader-overlay { transition: opacity 0.6s ease-out; z-index: 9999; }
        .skeleton-pulse { animation: pulse-op 1.5s ease-in-out infinite; }
        @keyframes pulse-op { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .hero-bg { background-image: url('<?= $base_url ?>/assets/images/login-bg.png'); background-size: cover; background-position: left center; }
        .text-glow { text-shadow: 2px 4px 12px rgba(0,0,0,0.7), 0 0 40px rgba(0,0,0,0.5); }
        .line-input { background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 0; transition: all 0.3s ease; }
        .line-input:focus { border-bottom-color: #fbcfe8; outline: none; box-shadow: 0 4px 6px -6px #fbcfe8; }
        .line-input::placeholder { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="relative min-h-screen font-sans antialiased text-white flex">
    
    <div id="loader-overlay" class="fixed inset-0 bg-[#1A1A1A] flex flex-col items-center justify-center z-[9999]">
        <div class="skeleton-pulse text-center">
            <h1 class="font-serif text-4xl tracking-[0.2em] text-[#fbcfe8] mb-2">CRAFTING STYLES</h1>
            <p class="font-sans text-xs tracking-[0.4em] text-gray-500 uppercase">Customer Portal</p>
        </div>
    </div>

    <div class="fixed inset-0 pointer-events-none">
        <div class="hero-bg absolute inset-0 w-full h-full opacity-70"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-transparent w-full"></div>
    </div>

    <div class="relative z-10 flex-1 hidden lg:flex flex-col justify-center px-16 xl:px-24">
        <h1 class="text-7xl xl:text-9xl text-white font-serif drop-shadow-2xl text-glow">CRAFTING<br><span class="italic text-[#fbcfe8]">Styles</span></h1>
        <p class="text-sm tracking-[0.2em] text-gray-100 font-medium mt-8 max-w-lg leading-relaxed uppercase drop-shadow-lg text-glow">
            Customer Portal &bull; Bookings, Loyalty, and History.
        </p>
    </div>

    <div class="w-full lg:w-[480px] min-h-screen bg-[#be185d]/80 backdrop-blur-2xl border-l border-white/10 flex flex-col justify-center px-8 sm:px-12 relative z-20 shadow-2xl overflow-y-auto">
        <div class="text-left mb-8 mt-8">
            <h2 class="font-sans font-medium tracking-widest text-2xl text-white uppercase">Welcome Back</h2>
            <p class="text-xs text-gray-300 font-light mt-2 tracking-wide">Sign in to your account.</p>
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
        
        <form method="POST" action="login.php" class="space-y-6" onsubmit="document.getElementById('loader-overlay').style.display='flex'; setTimeout(()=>document.getElementById('loader-overlay').style.opacity='1',10);">
            <div class="relative">
                <i class="fa-regular fa-envelope absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                <input type="email" name="email" required placeholder="Email Address" class="line-input w-full pl-8 pr-4 py-3 text-sm">
            </div>
            
            <div class="relative">
                <i class="fa-solid fa-lock absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                <input type="password" name="password" required placeholder="Password" class="line-input w-full pl-8 pr-10 py-3 text-sm">
            </div>

            <div class="flex justify-end">
                <a href="forgot_password.php" class="text-[10px] text-white/70 hover:text-white uppercase tracking-wider transition-colors">Forgot Password?</a>
            </div>
            
            <button type="submit" class="w-full mt-4 bg-[#fbcfe8] hover:bg-white text-black font-bold tracking-[0.2em] text-xs py-4 transition-all shadow-[0_0_15px_rgba(251,207,232,0.2)] uppercase">Sign In</button>
        </form>

        <div class="text-center mb-8 mt-8">
            <p class="text-xs text-white/60 tracking-wide">New here? <a href="register.php" class="text-white font-bold hover:text-[#fbcfe8] transition-colors ml-1">Create an Account</a></p>
        </div>
        
        <div class="text-center mt-6 pt-4 border-t border-white/10">
            <a href="../index.php" class="text-[10px] text-white/40 hover:text-white transition-colors uppercase tracking-widest">← Back to Main Menu</a>
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
    </script>
</body>
</html>