<?php
// auth/login_admin.php
session_start();

$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

require_once '../config/database.php';

// Check role and route or clear session
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Super Admin') {
        header("Location: " . $base_url . "/super_admin/dashboard.php");
        exit;
    } else {
        // Logged in as someone else - clear session to allow fresh login
        session_unset();
        session_destroy();
        session_start();
    }
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter credentials.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE email = ? AND role = 'Super Admin' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'Active') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                header("Location: " . $base_url . "/super_admin/dashboard.php");
                exit;
            } else {
                $_SESSION['login_error'] = "Account disabled.";
            }
        } else {
            $_SESSION['login_error'] = "Invalid Admin credentials.";
        }
        $stmt->close();
    }
    header("Location: login_admin.php");
    exit;
}

if (isset($_SESSION['login_error'])) {
    $error_msg = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Terminal | Crafting Styles</title>
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
            background-image: url('https://images.unsplash.com/photo-1599305090598-fe179d501227?auto=format&fit=crop&q=80');
            background-size: cover;
            background-position: center;
        }
        .text-glow { text-shadow: 2px 4px 12px rgba(0,0,0,0.8), 0 0 40px rgba(0,0,0,0.6); }
        .line-input { background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 0; transition: all 0.3s ease; }
        .line-input:focus { border-bottom-color: #f9a8d4; outline: none; box-shadow: 0 4px 6px -6px #f9a8d4; }
        .line-input::placeholder { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="relative min-h-screen font-sans antialiased text-white flex">
    <div id="loader-overlay" class="fixed inset-0 bg-[#1A1A1A] flex flex-col items-center justify-center z-[9999]">
        <div class="skeleton-pulse text-center">
            <h1 class="font-serif text-4xl tracking-[0.2em] text-[#f9a8d4] mb-2">CRAFTING STYLES</h1>
            <p class="font-sans text-xs tracking-[0.4em] text-gray-500 uppercase">System Admin</p>
        </div>
    </div>

    <div class="fixed inset-0 pointer-events-none">
        <div class="hero-bg absolute inset-0 w-full h-full opacity-60"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/40 to-transparent w-full"></div>
    </div>

    <div class="relative z-10 flex-1 hidden lg:flex flex-col justify-center px-16 xl:px-24">
        <h1 class="text-7xl xl:text-9xl text-white font-serif drop-shadow-2xl text-glow">CRAFTING<br><span class="italic text-[#f9a8d4]">Styles</span></h1>
        <p class="text-sm tracking-[0.2em] text-gray-100 font-medium mt-8 max-w-lg leading-relaxed uppercase drop-shadow-lg text-glow">
            System Admin &bull; Configure Users, Rules, and Business Settings.
        </p>
    </div>

    <div class="w-full lg:w-[480px] min-h-screen bg-[#831843]/80 backdrop-blur-2xl border-l border-white/10 flex flex-col justify-center px-8 sm:px-12 relative z-20 shadow-2xl">
        <div class="text-left mb-10">
            <div class="lg:hidden mb-10">
                <h1 class="text-4xl text-white font-serif drop-shadow-lg text-glow">CRAFTING <span class="italic text-[#f9a8d4]">Styles</span></h1>
                <p class="text-[10px] tracking-[0.3em] uppercase text-white/70 mt-2 drop-shadow-lg">System Admin</p>
            </div>
            <h2 class="font-sans font-medium tracking-widest text-2xl text-white uppercase">Admin Login</h2>
            <p class="text-xs text-gray-300 font-light mt-2 tracking-wide">Root System Access</p>
        </div>

        <form method="POST" action="login_admin.php" class="space-y-8" onsubmit="document.getElementById('loader-overlay').style.display='flex'; setTimeout(()=>document.getElementById('loader-overlay').style.opacity='1',10);">
            <?php if (!empty($error_msg)): ?>
                <div class="p-4 bg-red-500/20 border-l-2 border-red-500 text-white text-xs"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>
            
            <div class="relative">
                <i class="fa-regular fa-envelope absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                <input type="email" name="email" required placeholder="Admin Email" class="line-input w-full pl-8 pr-4 py-3 text-sm">
            </div>
            
            <div class="relative">
                <i class="fa-solid fa-lock absolute left-0 top-1/2 -translate-y-1/2 text-white/70 text-sm"></i>
                <input type="password" name="password" required placeholder="Password" class="line-input w-full pl-8 pr-10 py-3 text-sm">
            </div>
            
            <button type="submit" class="w-full mt-8 bg-[#f9a8d4] hover:bg-white text-black font-bold tracking-[0.2em] text-xs py-4 transition-all shadow-[0_0_15px_rgba(249,168,212,0.2)] uppercase">Login</button>
            
            <div class="text-center mt-6">
                <a href="../index.php" class="text-xs text-white/60 hover:text-white transition-colors">← Back to Main Menu</a>
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