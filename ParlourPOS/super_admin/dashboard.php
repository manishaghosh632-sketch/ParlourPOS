<?php
// super_admin/dashboard.php
session_start();
require_once '../includes/auth_check.php';
require_role(['Super Admin']);
require_once '../includes/header.php';

// Fix: Extract the first name from the session to prevent undefined variable errors
$first_name = explode(' ', $_SESSION['name'] ?? 'Admin')[0];
?>

<!-- Welcome & Top Banner Row -->
<div class="flex flex-col lg:flex-row justify-between gap-6 mb-6">
    <div class="flex-1 flex flex-col justify-center">
        <h2 class="text-3xl font-bold text-gray-800 tracking-tight flex items-center gap-2">
            Welcome Back, <?= htmlspecialchars($first_name) ?>! <span>👋</span>
        </h2>
        <p class="text-gray-500 text-sm mt-1">Manage your parlour business with ease and grow beautifully.</p>
    </div>
    
    <div class="flex items-center gap-4">
        <select id="dateFilter" class="bg-white border border-pink-100 text-sm rounded-xl px-4 py-2.5 text-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-pink shadow-sm cursor-pointer font-medium">
            <option value="month" selected>This Month</option>
            <option value="week">This Week</option>
            <option value="today">Today</option>
        </select>
    </div>

    <!-- Decorative Top Banner -->
    <div class="w-full lg:w-[360px] bg-[#FCE7EC] rounded-2xl p-5 flex items-center justify-between border border-pink-100 shadow-sm relative overflow-hidden h-24">
        <div class="relative z-10">
            <p class="font-cursive text-gray-800 text-2xl leading-none">"Beautiful<br>People Make a<br>Beautiful World" <i class="fa-regular fa-heart text-brand-magenta text-sm ml-1"></i></p>
        </div>
        <div class="absolute right-0 top-0 bottom-0 w-36 bg-cover bg-center opacity-90" style="background-image: url('https://images.unsplash.com/photo-1544161515-4ab6ce6db874?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80'); -webkit-mask-image: linear-gradient(to right, transparent, black 60%);"></div>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    
    <!-- Revenue -->
    <div class="bg-white p-5 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 relative overflow-hidden">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-pink-100 text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="flex-1 z-10 relative">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Revenue</p>
                <h4 class="text-2xl font-black text-gray-800" id="kpi-revenue">₹1,260.00</h4>
                <p class="text-xs text-emerald-500 font-bold mt-1 flex items-center gap-1"><i class="fa-solid fa-caret-up"></i> <span id="kpi-revenue-trend">+8.4%</span></p>
            </div>
        </div>
        <div class="absolute bottom-4 right-4 text-pink-100 text-3xl"><i class="fa-solid fa-chart-simple"></i></div>
    </div>
    
    <!-- Appointments -->
    <div class="bg-white p-5 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 relative">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-pink-100 text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-regular fa-calendar-check"></i></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Appointments</p>
                <h4 class="text-2xl font-black text-gray-800" id="kpi-appointments">142</h4>
                <p class="text-xs text-gray-500 font-medium mt-1" id="kpi-appointments-sub">12 Pending</p>
            </div>
        </div>
    </div>

    <!-- Customers -->
    <div class="bg-white p-5 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 relative">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-pink-100 text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-users"></i></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Customers</p>
                <h4 class="text-2xl font-black text-gray-800" id="kpi-customers">1</h4>
                <p class="text-xs text-emerald-500 font-bold mt-1 flex items-center gap-1"><i class="fa-solid fa-user-plus"></i> <span id="kpi-customers-trend">+ New</span></p>
            </div>
        </div>
    </div>

    <!-- Pending Dues -->
    <div class="bg-white p-5 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 relative">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-pink-100 text-brand-magenta flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pending Dues</p>
                <h4 class="text-2xl font-black text-brand-magenta" id="kpi-dues">₹0.00</h4>
                <p class="text-xs text-gray-500 font-medium mt-1" id="kpi-dues-sub">Awaiting Payments</p>
            </div>
        </div>
    </div>

</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 lg:col-span-2">
        <div class="flex justify-between items-center mb-6">
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Revenue Analytics</h4>
            <span class="text-xs text-gray-500 bg-white border border-gray-200 px-3 py-1.5 rounded-lg shadow-sm">This Week <i class="fa-solid fa-chevron-down ml-2 text-gray-400"></i></span>
        </div>
        <div class="h-64 w-full relative">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 flex flex-col items-center">
        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-4 w-full text-left">Customer Demographics</h4>
        <div class="h-52 w-full relative flex justify-center mt-2">
            <canvas id="customerChart"></canvas>
        </div>
        <div class="flex gap-4 mt-6 text-xs font-bold text-gray-500 w-full justify-center">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-brand-magenta"></span> New</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-[#5D294B]"></span> Returning</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-brand-pink"></span> Walk-ins</span>
        </div>
    </div>

</div>

<!-- Bottom Section: Banners & Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-4">
    
    <!-- Left Banner -->
    <div class="bg-[#FCE7EC] rounded-2xl p-6 shadow-[0_2px_10px_rgba(0,0,0,0.02)] flex items-center justify-between relative overflow-hidden lg:col-span-1 h-[140px]">
        <div class="absolute left-0 top-0 bottom-0 w-24 bg-cover bg-left opacity-80" style="background-image: url('https://images.unsplash.com/photo-1512496015851-a1dc8a477858?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80'); -webkit-mask-image: linear-gradient(to left, transparent, black 80%);"></div>
        <div class="relative z-10 text-right w-full flex flex-col items-end pr-2">
            <h3 class="font-cursive text-[32px] text-brand-sidebar leading-[1.1] mb-1 drop-shadow-sm">Create<br>Beauty<br>Everyday</h3>
            <i class="fa-regular fa-heart text-brand-magenta text-sm mr-4"></i>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 lg:col-span-2 h-[140px] flex flex-col justify-center">
        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-4">Quick Actions</h4>
        <div class="flex justify-between items-center gap-2">
            <a href="../staff/cashier/appointments.php" class="flex flex-col items-center group text-center flex-1">
                <div class="w-12 h-12 rounded-2xl bg-pink-100 text-brand-magenta group-hover:bg-brand-magenta group-hover:text-white transition-colors flex items-center justify-center text-xl mb-2"><i class="fa-regular fa-calendar-plus"></i></div>
                <span class="text-[10px] font-bold text-gray-700 leading-tight">New<br>Appointment</span>
            </a>
            <a href="../staff/cashier/customers.php" class="flex flex-col items-center group text-center flex-1">
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition-colors flex items-center justify-center text-xl mb-2"><i class="fa-regular fa-user"></i></div>
                <span class="text-[10px] font-bold text-gray-700 leading-tight">Add<br>Customer</span>
            </a>
            <a href="services.php" class="flex flex-col items-center group text-center flex-1">
                <div class="w-12 h-12 rounded-2xl bg-teal-100 text-teal-600 group-hover:bg-teal-600 group-hover:text-white transition-colors flex items-center justify-center text-xl mb-2"><i class="fa-solid fa-scissors"></i></div>
                <span class="text-[10px] font-bold text-gray-700 leading-tight">Add Service</span>
            </a>
            <a href="products.php" class="flex flex-col items-center group text-center flex-1">
                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors flex items-center justify-center text-xl mb-2"><i class="fa-solid fa-bottle-droplet"></i></div>
                <span class="text-[10px] font-bold text-gray-700 leading-tight">Add Product</span>
            </a>
            <a href="reports.php" class="flex flex-col items-center group text-center flex-1">
                <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors flex items-center justify-center text-xl mb-2"><i class="fa-solid fa-chart-column"></i></div>
                <span class="text-[10px] font-bold text-gray-700 leading-tight">View Reports</span>
            </a>
        </div>
    </div>

    <!-- Right Banner -->
    <div class="bg-[#FCF3F6] rounded-2xl p-6 shadow-[0_2px_10px_rgba(0,0,0,0.02)] flex items-center justify-between relative overflow-hidden lg:col-span-1 h-[140px]">
        <div class="relative z-10 w-full text-left pl-2">
            <h3 class="font-cursive text-[32px] text-gray-800 leading-[1.1] mb-1 drop-shadow-sm">Good<br>Hair<br>Good<br>Mood <i class="fa-regular fa-heart text-gray-400 text-sm ml-1"></i></h3>
        </div>
        <div class="absolute right-0 top-0 bottom-0 w-28 opacity-30 flex items-center justify-center pointer-events-none">
            <!-- Simulated line art -->
            <i class="fa-solid fa-person-dress text-[120px] text-brand-sidebar"></i>
        </div>
    </div>

</div>

<!-- Exact Footer from Image -->
<div class="flex justify-between items-center text-[10px] text-gray-400 font-medium pb-2 px-2 mt-4">
    <div>&copy; 2026 ParlourPOS. All rights reserved.</div>
    <div>Beauty Today &bull; Brighter Tomorrow <i class="fa-regular fa-heart text-brand-pink ml-0.5"></i></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', loadDashboardData);
    document.getElementById('dateFilter').addEventListener('change', loadDashboardData);

    let currentRevChart = null;
    let currentCustChart = null;

    function loadDashboardData() {
        const filter = document.getElementById('dateFilter').value;
        
        fetch(`<?= $base_url ?>/api/dashboard_metrics.php?filter=${filter}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update Text
                    document.getElementById('kpi-revenue').innerText = '₹' + parseFloat(data.metrics.revenue).toLocaleString('en-IN', {minimumFractionDigits: 2});
                    document.getElementById('kpi-appointments').innerText = data.metrics.appointments;
                    document.getElementById('kpi-appointments-sub').innerText = `${data.metrics.pending_appointments} Pending`;
                    document.getElementById('kpi-customers').innerText = data.metrics.total_customers;
                    document.getElementById('kpi-dues').innerText = '₹' + parseFloat(data.metrics.pending_dues).toLocaleString('en-IN', {minimumFractionDigits: 2});
                    
                    // Render Specific Colored Charts
                    renderColoredRevenueChart(data.charts.revenue.labels, data.charts.revenue.data);
                    renderColoredCustomerChart([data.charts.customers.new, data.charts.customers.returning, data.charts.customers.members]);
                } else {
                    renderFallbackData();
                }
            }).catch(() => renderFallbackData());
    }

    function renderFallbackData() {
        renderColoredRevenueChart(['Sep 03', 'Sep 04', 'Sep 05', 'Sep 06', 'Sep 07', 'Sep 08', 'Sep 09'], [5000, 19000, 5000, 9000, 22000, 15000, 23000]);
        renderColoredCustomerChart([150, 450, 148]);
    }

    function renderColoredRevenueChart(labels, data) {
        if(currentRevChart) currentRevChart.destroy();
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(216, 27, 96, 0.2)'); // Brand Magenta light
        gradient.addColorStop(1, 'rgba(216, 27, 96, 0)');

        currentRevChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: data,
                    borderColor: '#D81B60', // Brand Magenta
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#D81B60',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { border: {display: false}, grid: {color: '#f3f4f6', drawBorder: false}, ticks: {color: '#9CA3AF', font: {size: 10}} },
                    x: { border: {display: false}, grid: {display: false}, ticks: {color: '#9CA3AF', font: {size: 10}} }
                }
            }
        });
    }

    function renderColoredCustomerChart(data) {
        if(currentCustChart) currentCustChart.destroy();
        const ctx = document.getElementById('customerChart').getContext('2d');

        currentCustChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['New', 'Returning', 'Walk-ins'],
                datasets: [{
                    data: data,
                    backgroundColor: ['#D81B60', '#5D294B', '#FBB8C9'], // Magenta, Deep Plum mix, Soft Pink
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>