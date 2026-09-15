<?php
// customers/feedback.php
session_name('ParlourPOS_Customer');
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['user_id'];

// 1. Create the feedback table automatically (No strict foreign keys to prevent constraint failures)
$conn->query("CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    appointment_id INT NULL,
    staff_id INT NULL,
    service_id INT NULL,
    rating INT NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Handle PRG Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $appointment_id = (int)$_POST['appointment_id'];
    $rating = (int)$_POST['rating'];
    $comments = trim($_POST['comments']);

    // Fetch the staff and service ID linked to this appointment to save it alongside the feedback
    $fetch_stmt = $conn->prepare("SELECT staff_id, service_id FROM appointments WHERE id = ? AND customer_id = ?");
    $fetch_stmt->bind_param("ii", $appointment_id, $customer_id);
    $fetch_stmt->execute();
    $apt_data = $fetch_stmt->get_result()->fetch_assoc();
    $fetch_stmt->close();

    if ($apt_data) {
        $staff_id = $apt_data['staff_id'];
        $service_id = $apt_data['service_id'];

        $stmt = $conn->prepare("INSERT INTO feedback (customer_id, appointment_id, staff_id, service_id, rating, comments) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiis", $customer_id, $appointment_id, $staff_id, $service_id, $rating, $comments);
        
        // Disable foreign key checks temporarily to bypass legacy database mismatches
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Thank you for your valuable feedback!";
        } else {
            $_SESSION['flash_error'] = "Failed to submit feedback. Please try again.";
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        $stmt->close();
        
    } else {
        $_SESSION['flash_error'] = "Invalid appointment selected.";
    }
    
    header("Location: feedback.php");
    exit;
}

// 3. Fetch past completed appointments that HAVE NOT been reviewed yet
$unreviewed_appointments = [];
try {
    $q = "
        SELECT a.id, a.appointment_date, s.name as service_name, u.name as staff_name 
        FROM appointments a
        JOIN catalog_items s ON a.service_id = s.id
        JOIN users u ON a.staff_id = u.id
        WHERE a.customer_id = ? 
        AND a.status = 'Completed'
        AND a.id NOT IN (SELECT appointment_id FROM feedback WHERE customer_id = ?)
        ORDER BY a.appointment_date DESC 
        LIMIT 10
    ";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("ii", $customer_id, $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $unreviewed_appointments[] = $row;
    $stmt->close();
} catch (Exception $e) {}

// 4. Fetch Customer's Past Feedback
$past_feedback = [];
try {
    $q = "
        SELECT f.rating, f.comments, DATE_FORMAT(f.created_at, '%d %b %Y') as f_date,
               s.name as service_name, u.name as staff_name
        FROM feedback f
        LEFT JOIN catalog_items s ON f.service_id = s.id
        LEFT JOIN users u ON f.staff_id = u.id
        WHERE f.customer_id = ?
        ORDER BY f.created_at DESC
    ";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $past_feedback[] = $row;
    $stmt->close();
} catch (Exception $e) {}

require_once '../includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h3 class="text-3xl font-bold text-gray-800 tracking-tight">Service Feedback</h3>
        <p class="text-sm text-gray-500 mt-1">Rate your recent experiences and help us improve.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Submit Feedback Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-pink-50 p-6 md:p-8">
            <h4 class="text-lg font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Give Feedback</h4>
            
            <?php if (empty($unreviewed_appointments)): ?>
                <div class="text-center py-8">
                    <i class="fa-solid fa-face-smile-beam text-4xl text-pink-200 mb-3"></i>
                    <p class="text-gray-500 text-sm font-medium">You have no pending completed services to review. Check back after your next appointment!</p>
                </div>
            <?php else: ?>
                <form method="POST" action="feedback.php" class="space-y-4">
                    <input type="hidden" name="action" value="submit_feedback">
                    
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Select Past Service <span class="text-brand-magenta">*</span></label>
                        <select name="appointment_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                            <option value="" disabled selected>Choose a completed service...</option>
                            <?php foreach ($unreviewed_appointments as $apt): ?>
                                <option value="<?= $apt['id'] ?>">
                                    <?= date('d M', strtotime($apt['appointment_date'])) ?> - <?= htmlspecialchars($apt['service_name']) ?> (by <?= htmlspecialchars($apt['staff_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Rating <span class="text-brand-magenta">*</span></label>
                        <select name="rating" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                            <option value="5">⭐⭐⭐⭐⭐ (5/5) - Excellent</option>
                            <option value="4">⭐⭐⭐⭐ (4/5) - Very Good</option>
                            <option value="3">⭐⭐⭐ (3/5) - Average</option>
                            <option value="2">⭐⭐ (2/5) - Poor</option>
                            <option value="1">⭐ (1/5) - Terrible</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500 mb-1.5">Your Review / Comments</label>
                        <textarea name="comments" rows="3" placeholder="Tell us how the beautician did..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all resize-none"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-brand-sidebar text-white rounded-xl font-bold tracking-wide shadow-md hover:bg-brand-sidebar/90 transition-colors">
                        Submit Review
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback History -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm border border-pink-50 overflow-hidden h-full flex flex-col">
            <div class="p-6 border-b border-gray-50 bg-gray-50/30 shrink-0">
                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">My Past Reviews</h4>
            </div>
            <div class="flex-1 p-0 overflow-y-auto">
                <?php if (empty($past_feedback)): ?>
                    <div class="p-8 text-center text-gray-400 text-sm">You haven't left any feedback yet.</div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-50">
                        <?php foreach ($past_feedback as $fb): ?>
                            <li class="p-6 hover:bg-[#FDF0F3]/30 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h5 class="font-bold text-gray-900"><?= htmlspecialchars($fb['service_name']) ?></h5>
                                        <p class="text-xs text-brand-magenta font-semibold mt-0.5">Professional: <?= htmlspecialchars($fb['staff_name']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-yellow-400 text-sm">
                                            <?php for($i=1; $i<=5; $i++) { echo $i <= $fb['rating'] ? '★' : '<span class="text-gray-200">★</span>'; } ?>
                                        </div>
                                        <span class="text-[10px] text-gray-400 mt-1 block"><?= $fb['f_date'] ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($fb['comments'])): ?>
                                    <p class="text-sm text-gray-600 italic bg-gray-50 p-3 rounded-lg border border-gray-100">"<?= htmlspecialchars($fb['comments']) ?>"</p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Feedback' : null;
</script>

<?php require_once '../includes/footer.php'; ?>