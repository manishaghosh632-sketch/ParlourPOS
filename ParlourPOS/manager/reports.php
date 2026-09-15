<?php
// manager/reports.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Manager', 'Super Admin']);

// ==========================================
// REPORT EXPORT ENGINE (EXCEL & PDF)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $report_type = $_POST['report_type'] ?? 'sales';
    $export_format = $_POST['export_format'];
    $today = date('Y-m-d');
    
    $data = [];
    $columns = [];
    $title = "Business_Report";
    $display_title = "Business Report";

    // 1. Daily Collections & Sales Report
    if ($report_type === 'sales') {
        $title = "Daily_Sales_Report_" . date('Ymd');
        $display_title = "Daily Collections & Sales Report";
        $columns = ['Invoice #', 'Time', 'Subtotal (Rs)', 'Tax (Rs)', 'Grand Total (Rs)', 'Payment Status'];
        
        $stmt = $conn->prepare("SELECT invoice_number, DATE_FORMAT(created_at, '%h:%i %p') as time, subtotal, tax_amount, grand_total, payment_status FROM invoices WHERE DATE(created_at) = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                $row['invoice_number'], 
                $row['time'], 
                $row['subtotal'], 
                $row['tax_amount'], 
                $row['grand_total'], 
                $row['payment_status']
            ];
        }
        $stmt->close();
    } 
    // 2. Inventory Report
    elseif ($report_type === 'inventory') {
        $title = "Inventory_Levels_" . date('Ymd');
        $display_title = "Stock & Inventory Log";
        $columns = ['Product ID', 'Product Name', 'Category', 'Current Stock', 'Retail Price (Rs)', 'Status'];
        
        // Use current_stock as defined in manager files
        $res = $conn->query("SELECT id, name, category, COALESCE(current_stock, 0) as stock, price, status FROM catalog_items WHERE type='Product' ORDER BY name");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [
                    $row['id'], 
                    $row['name'], 
                    $row['category'], 
                    $row['stock'], 
                    number_format($row['price'], 2), 
                    $row['status']
                ];
            }
        }
    }

    // --- Output Excel (CSV) ---
    if ($export_format === 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $title . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, $columns);
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    } 
    // --- Output PDF (Printable HTML View) ---
    elseif ($export_format === 'pdf') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?= $title ?></title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 40px; color: #333; }
                .header { text-align: center; border-bottom: 2px solid #D81B60; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { margin: 0; color: #D81B60; font-size: 28px; }
                .header p { margin: 5px 0 0 0; color: #666; font-size: 14px; }
                .meta { margin-bottom: 30px; font-size: 14px; color: #555; }
                table { w-full; width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; font-size: 13px; }
                th { background-color: #FCF3F6; color: #D81B60; font-weight: bold; text-transform: uppercase; }
                tr:nth-child(even) { background-color: #fafafa; }
                .footer { text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
                @media print {
                    body { padding: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body onload="window.print()">
            <div class="header">
                <h1>ParlourPOS</h1>
                <p>Beauty &bull; Care &bull; Confidence</p>
            </div>
            
            <div class="meta">
                <strong>Report:</strong> <?= $display_title ?><br>
                <strong>Date:</strong> <?= date('d M Y') ?><br>
                <strong>Generated On:</strong> <?= date('d M Y, h:i A') ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="<?= count($columns) ?>" style="text-align:center;">No data found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?= htmlspecialchars((string)$cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="footer">
                Generated automatically by ParlourPOS System. Strictly Confidential.
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="mb-6">
    <h3 class="text-2xl font-bold text-brand-sidebar">Operational Reports</h3>
    <p class="text-sm text-gray-500 mt-1">Export daily sales and inventory logs</p>
</div>

<!-- Report Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    
    <!-- Daily Collection Report -->
    <div class="pos-card p-6 border-t-4 border-t-indigo-400 flex flex-col justify-between hover:-translate-y-1 transition-transform duration-300">
        <div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-2">Daily Collections & Sales</h4>
            <p class="text-sm text-gray-500 mb-6">Overview of all generated invoices, split by payment methods (Cash, UPI, Card), and pending dues.</p>
        </div>
        <form method="POST" action="reports.php" target="_blank" class="w-full mt-auto">
            <input type="hidden" name="report_type" value="sales">
            <input type="hidden" name="export_format" value="pdf">
            <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-indigo-50 hover:text-indigo-600 text-gray-600 font-semibold rounded-lg text-sm transition-colors border border-gray-200">
                Download PDF
            </button>
        </form>
    </div>

    <!-- Inventory Report -->
    <div class="pos-card p-6 border-t-4 border-t-emerald-400 flex flex-col justify-between hover:-translate-y-1 transition-transform duration-300">
        <div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl mb-4">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg mb-2">Stock & Inventory Log</h4>
            <p class="text-sm text-gray-500 mb-6">Current product stock levels, recent purchase influxes, and manual adjustments.</p>
        </div>
        <form method="POST" action="reports.php" target="_blank" class="w-full mt-auto">
            <input type="hidden" name="report_type" value="inventory">
            <input type="hidden" name="export_format" value="excel">
            <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 text-gray-600 font-semibold rounded-lg text-sm transition-colors border border-gray-200">
                Download Excel
            </button>
        </form>
    </div>

</div>

<!-- Real-time Quick Table (Recent End of Day metrics) -->
<div class="pos-card overflow-hidden">
    <div class="p-6 border-b border-gray-100 bg-white">
        <h4 class="font-bold text-gray-800">Last 7 Days - Quick Overview</h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 text-gray-400 text-xs uppercase tracking-wider font-bold border-b border-gray-100">
                    <th class="px-6 py-4">Date</th>
                    <th class="px-6 py-4 text-center">Invoices Generated</th>
                    <th class="px-6 py-4 text-right">Total Collection</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50">
                <?php
                // Generate last 7 days overview
                for ($i = 0; $i < 7; $i++) {
                    $target_date = date('Y-m-d', strtotime("-$i days"));
                    $display_date = date('d M Y', strtotime("-$i days"));
                    
                    $q = $conn->query("SELECT COUNT(*) as bills, SUM(grand_total) as rev FROM invoices WHERE DATE(created_at) = '$target_date' AND payment_status = 'Paid'");
                    $data = $q->fetch_assoc();
                    $bills = $data['bills'] ?? 0;
                    $rev = $data['rev'] ?? 0;
                    
                    echo "
                    <tr class='hover:bg-gray-50/50 transition-colors'>
                        <td class='px-6 py-4 font-bold text-gray-700'>{$display_date}</td>
                        <td class='px-6 py-4 text-center font-semibold text-gray-500'>{$bills}</td>
                        <td class='px-6 py-4 text-right font-black text-brand-coral'>₹" . number_format($rev, 2) . "</td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('page-title').innerText = 'Manager Reports';
</script>

<?php require_once '../includes/footer.php'; ?>