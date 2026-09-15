// assets/js/main.js

// Handle Page Load Transitions safely with a fail-safe fallback timer
window.addEventListener('load', () => {
    revealContent();
});

// Fallback safety net: if window load already fired or delayed, force display after 600ms max
setTimeout(() => {
    revealContent();
}, 600);

function revealContent() {
    const skeleton = document.getElementById('skeleton-loader');
    const content = document.getElementById('app-content');
    
    if (skeleton && skeleton.style.display !== 'none') {
        skeleton.style.opacity = '0';
        setTimeout(() => {
            skeleton.style.display = 'none';
        }, 200);
    }
    
    if (content) {
        content.classList.add('loaded');
        content.style.opacity = '1';
        content.style.transform = 'translateY(0)';
    }
}

// Mobile Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('mobileBackdrop');
    
    if (!sidebar) return;
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
        }
    } else {
        sidebar.classList.add('-translate-x-full');
        if (backdrop) {
            backdrop.classList.add('opacity-0');
            setTimeout(() => backdrop.classList.add('hidden'), 250);
        }
    }
}

// --- NEW: Profile Dropdown Toggle Logic ---
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    const chevron = document.getElementById('profileChevron');
    
    if (menu.classList.contains('opacity-0')) {
        // Open
        menu.classList.remove('opacity-0', 'invisible', 'scale-95');
        menu.classList.add('opacity-100', 'visible', 'scale-100');
        if(chevron) chevron.classList.add('rotate-180');
    } else {
        // Close
        closeProfileMenu();
    }
}

function closeProfileMenu() {
    const menu = document.getElementById('profileMenu');
    const chevron = document.getElementById('profileChevron');
    
    if (menu && !menu.classList.contains('opacity-0')) {
        menu.classList.remove('opacity-100', 'visible', 'scale-100');
        menu.classList.add('opacity-0', 'invisible', 'scale-95');
        if(chevron) chevron.classList.remove('rotate-180');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const container = document.getElementById('profileDropdownContainer');
    if (container && !container.contains(event.target)) {
        closeProfileMenu();
    }
});
// ------------------------------------------

// Global Toast Notification System
function showToast(message, type = 'success') {
    const existing = document.getElementById('pos-toast');
    if (existing) existing.remove();

    const colors = {
        'success': 'bg-emerald-500',
        'error': 'bg-brand-coral',
        'info': 'bg-blue-500'
    };
    
    const icons = {
        'success': 'fa-circle-check',
        'error': 'fa-circle-exclamation',
        'info': 'fa-circle-info'
    };

    const color = colors[type] || colors['info'];
    const icon = icons[type] || icons['info'];

    const toast = document.createElement('div');
    toast.id = 'pos-toast';
    toast.className = `fixed bottom-6 right-6 ${color} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 transform translate-y-10 opacity-0 transition-smooth z-50 text-sm font-medium`;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
    }, 10);

    setTimeout(() => {
        toast.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => toast.remove(), 250);
    }, 3000);
}