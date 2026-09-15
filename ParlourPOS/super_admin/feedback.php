<?php
// super_admin/feedback.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

// Fetch all feedback with fallback for both Users and Customers tables
$feedbacks = [];
try {
    $q = "
        SELECT f.rating, f.comments, DATE_FORMAT(f.created_at, '%d %b %Y, %h:%i %p') as f_date,
               COALESCE(u_cust.name, c.name, 'Unknown Customer') as customer_name, 
               COALESCE(u_cust.phone, c.mobile, 'No Phone') as customer_phone,
               s.name as service_name, 
               u_staff.name as staff_name
        FROM feedback f
        LEFT JOIN users u_cust ON f.customer_id = u_cust.id
        LEFT JOIN customers c ON f.customer_id = c.id
        LEFT JOIN catalog_items s ON f.service_id = s.id
        LEFT JOIN users u_staff ON f.staff_id = u_staff.id
        ORDER BY f.created_at DESC
    ";
    $res = $conn->query($q);
    if($res) {
        while ($row = $res->fetch_assoc()) $feedbacks[] = $row;
    }
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-3xl font-bold text-gray-800 tracking-tight">Customer Reviews</h3>
        <p class="text-sm text-gray-500 mt-1">Monitor client satisfaction and staff performance ratings.</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Service & Staff</th>
                    <th class="px-6 py-4">Rating</th>
                    <th class="px-6 py-4">Review / Comments</th>
                    <th class="px-6 py-4 text-right">Date</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php if (empty($feedbacks)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400">No feedback submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($feedbacks as $fb): ?>
                        <tr class="hover:bg-[#FDF0F3]/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($fb['customer_name']) ?></div>
                                <div class="text-[10px] text-gray-500 mt-0.5"><i class="fa-solid fa-phone mr-1"></i> <?= htmlspecialchars($fb['customer_phone']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($fb['service_name'] ?? 'General') ?></div>
                                <div class="text-[10px] text-brand-magenta font-bold mt-0.5 uppercase tracking-wider">By: <?= htmlspecialchars($fb['staff_name'] ?? 'Unknown') ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-yellow-400 text-sm">
                                    <?php for($i=1; $i<=5; $i++) { echo $i <= $fb['rating'] ? '★' : '<span class="text-gray-200">★</span>'; } ?>
                                </div>
                                <span class="text-[10px] text-gray-400 font-bold ml-1"><?= $fb['rating'] ?>.0</span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($fb['comments'])): ?>
                                    <p class="text-xs text-gray-600 italic line-clamp-2" title="<?= htmlspecialchars($fb['comments']) ?>">"<?= htmlspecialchars($fb['comments']) ?>"</p>
                                <?php else: ?>
                                    <span class="text-xs text-gray-300 italic">No comments</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-xs text-gray-500 font-medium">
                                <?= $fb['f_date'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Feedback & Reviews' : null;
</script>

<?php require_once '../includes/footer.php'; ?>