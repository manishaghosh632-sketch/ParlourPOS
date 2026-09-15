<?php
// api/dashboard_metrics.php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Ensure user is authenticated (AJAX requests should also be secured)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$filter = $_GET['filter'] ?? 'month';
$date_condition = "";

// Set SQL date condition based on filter
switch ($filter) {
    case 'today':
        $date_condition = "DATE(created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
    default:
        $date_condition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        break;
}

try {
    // 1. Fetch Revenue
    $rev_query = $conn->query("SELECT SUM(grand_total) as total_rev FROM invoices WHERE $date_condition");
    $revenue = $rev_query->fetch_assoc()['total_rev'] ?? 0;

    // 2. Fetch Appointments (Dummy table check, assuming appointments table exists in future phase)
    // $appt_query = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending FROM appointments WHERE $date_condition");
    // $appointments = $appt_query->fetch_assoc();
    $appointments = ['total' => 142, 'pending' => 12]; // Placeholder until appointments module is built[cite: 2]

    // 3. Fetch Customers
    $cust_query = $conn->query("SELECT COUNT(*) as total FROM customers");
    $total_customers = $cust_query->fetch_assoc()['total'] ?? 0;
    $new_cust_query = $conn->query("SELECT COUNT(*) as new_cust FROM customers WHERE $date_condition");
    $new_customers = $new_cust_query->fetch_assoc()['new_cust'] ?? 0;

    // 4. Fetch Pending Dues
    $due_query = $conn->query("SELECT SUM(grand_total) as due_amount, COUNT(*) as due_count FROM invoices WHERE payment_status != 'Paid'");
    $dues = $due_query->fetch_assoc();

    // 5. Chart Data: 7-Day Revenue Trend (Mock logic for structure)
    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('M d', strtotime("-$i days"));
        $chart_labels[] = $date;
        $chart_data[] = rand(5000, 25000); // Replace with actual GROUP BY DATE() query later
    }

    // Assemble JSON response[cite: 2]
    echo json_encode([
        'status' => 'success',
        'metrics' => [
            'revenue' => $revenue,
            'appointments' => $appointments['total'],
            'pending_appointments' => $appointments['pending'],
            'total_customers' => $total_customers,
            'new_customers' => $new_customers,
            'pending_dues' => $dues['due_amount'] ?? 0,
            'due_invoices' => $dues['due_count'] ?? 0
        ],
        'charts' => [
            'revenue' => [
                'labels' => $chart_labels,
                'data' => $chart_data
            ],
            'customers' => [
                'new' => $new_customers,
                'returning' => $total_customers > $new_customers ? ($total_customers - $new_customers) : 0,
                'members' => rand(10, 50) // Placeholder for membership logic
            ]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>