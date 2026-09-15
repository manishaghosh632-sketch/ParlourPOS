<?php
// super_admin/reports.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';
require_role(['Super Admin']);

// ==========================================
// REPORT EXPORT ENGINE (EXCEL & PDF)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_format'])) {
    $report_type = $_POST['report_type'] ?? 'sales';
    $export_format = $_POST['export_format'];
    
    // Default to current month if dates are not provided
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');

    $data = [];
    $columns = [];
    $title = "Business_Report";
    $display_title = "Business Report";

    // 1. Inventory Report Logic (Ignores Dates)
    if ($report_type === 'inventory') {
        $title = "Inventory_Levels_" . date('Ymd');
        $display_title = "Inventory Stock Levels";
        $columns = ['Product ID', 'Product Name', 'Category', 'Current Stock', 'Retail Price (Rs)', 'Status'];
        
        $res = $conn->query("SELECT id, name, category, COALESCE(stock, 0) as stock, price, status FROM catalog_items WHERE type='Product' ORDER BY name");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = [$row['id'], $row['name'], $row['category'], $row['stock'], number_format($row['price'], 2), $row['status']];
            }
        }
    } 
    // 2. Sales & Revenue Logic (Fixed: Removed 'status' column requirement)
    elseif ($report_type === 'sales') {
        $title = "Sales_Revenue_{$start_date}_to_{$end_date}";
        $display_title = "Sales & Revenue Report";
        $columns = ['Invoice #', 'Date', 'Cashier ID', 'Subtotal (Rs)', 'Tax (Rs)', 'Grand Total (Rs)'];
        
        $stmt = $conn->prepare("SELECT invoice_number, DATE(created_at) as date, cashier_id, subtotal, tax_amount, grand_total FROM invoices WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $data[] = [$row['invoice_number'], $row['date'], $row['cashier_id'], $row['subtotal'], $row['tax_amount'], $row['grand_total']];
        }
        $stmt->close();
    } 
    // 3. Profit & Loss / Expense Logic (Fixed: Removed 'status' column requirement)
    elseif ($report_type === 'profit_loss' || $report_type === 'expenses') {
        $title = "Profit_Loss_{$start_date}_to_{$end_date}";
        $display_title = "Profit & Loss Statement";
        $columns = ['Metric', 'Amount (Rs)'];
        
        // Calculate Total Revenue (All Invoices)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total), 0) as total FROM invoices WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc()['total'];
        
        // Calculate Total Expenses (Purchases)
        $stmt2 = $conn->prepare("SELECT COALESCE(SUM(total_cost), 0) as total FROM purchases WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt2->bind_param("ss", $start_date, $end_date);
        $stmt2->execute();
        $expenses = $stmt2->get_result()->fetch_assoc()['total'];
        
        $data[] = ['Total Sales Revenue', number_format($revenue, 2)];
        $data[] = ['Total Inventory Expenses', number_format($expenses, 2)];
        $data[] = ['-------------------', '-------------------'];
        $net = $revenue - $expenses;
        $data[] = ['NET PROFIT / LOSS', number_format($net, 2)];
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
                <?php if ($report_type !== 'inventory'): ?>
                    <strong>Date Range:</strong> <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?><br>
                <?php endif; ?>
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
                                    <td style="<?= strpos((string)$cell, 'NET PROFIT') !== false ? 'font-weight:bold;color:#D81B60;' : '' ?>">
                                        <?= htmlspecialchars((string)$cell) ?>
                                    </td>
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

// Set default dates for the UI inputs
$default_start = date('Y-m-01');
$default_end = date('Y-m-d');
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Business Reports</h2>
    <p class="text-gray-500 text-sm mt-1">Export and analyze salon performance metrics.</p>
</div>

<!-- Quick Action Cards (Generates PDF instantly for current month) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Sales & Revenue -->
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 flex flex-col h-full">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <h3 class="font-bold text-gray-800 text-lg mb-2">Sales & Revenue</h3>
        <p class="text-sm text-gray-500 mb-6 flex-grow">Daily, weekly, and monthly sales reports separated by services and products.</p>
        <form method="POST" action="reports.php" target="_blank">
            <input type="hidden" name="report_type" value="sales">
            <input type="hidden" name="export_format" value="pdf">
            <input type="hidden" name="start_date" value="<?= $default_start ?>">
            <input type="hidden" name="end_date" value="<?= $default_end ?>">
            <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold rounded-xl text-sm border border-gray-200 transition-colors">
                Generate Report
            </button>
        </form>
    </div>

    <!-- Inventory Levels -->
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 flex flex-col h-full">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <h3 class="font-bold text-gray-800 text-lg mb-2">Inventory Levels</h3>
        <p class="text-sm text-gray-500 mb-6 flex-grow">Current stock, low stock alerts, and historical stock movement logs.</p>
        <form method="POST" action="reports.php" target="_blank">
            <input type="hidden" name="report_type" value="inventory">
            <input type="hidden" name="export_format" value="pdf">
            <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold rounded-xl text-sm border border-gray-200 transition-colors">
                Generate Report
            </button>
        </form>
    </div>

    <!-- Profit & Loss -->
    <div class="bg-white p-6 rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50 flex flex-col h-full">
        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-scale-balanced"></i>
        </div>
        <h3 class="font-bold text-gray-800 text-lg mb-2">Profit & Loss</h3>
        <p class="text-sm text-gray-500 mb-6 flex-grow">Estimated business profit calculated against product costs and operational expenses.</p>
        <form method="POST" action="reports.php" target="_blank">
            <input type="hidden" name="report_type" value="profit_loss">
            <input type="hidden" name="export_format" value="pdf">
            <input type="hidden" name="start_date" value="<?= $default_start ?>">
            <input type="hidden" name="end_date" value="<?= $default_end ?>">
            <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold rounded-xl text-sm border border-gray-200 transition-colors">
                Generate Report
            </button>
        </form>
    </div>
</div>

<!-- Custom Export Tool -->
<div class="bg-white rounded-2xl p-6 md:p-8 shadow-[0_2px_10px_rgba(0,0,0,0.02)] border border-pink-50">
    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-6 flex items-center gap-2">
        <i class="fa-solid fa-download text-brand-magenta"></i> Custom Export Tool
    </h4>
    
    <form method="POST" action="reports.php" target="_blank" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
        
        <div class="md:col-span-1">
            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-2">Report Type</label>
            <select name="report_type" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
                <option value="sales">Sales & Revenue</option>
                <option value="inventory">Inventory Status</option>
                <option value="profit_loss">Profit & Loss (Expenses)</option>
            </select>
        </div>

        <div class="md:col-span-1">
            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-2">Start Date</label>
            <input type="date" name="start_date" value="<?= $default_start ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
        </div>

        <div class="md:col-span-1">
            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-2">End Date</label>
            <input type="date" name="end_date" value="<?= $default_end ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-brand-pink transition-all">
        </div>

        <div class="md:col-span-1 flex flex-col sm:flex-row gap-3">
            <button type="submit" name="export_format" value="excel" class="flex-1 py-3 bg-[#10b981] hover:bg-[#059669] text-white rounded-xl font-bold text-sm transition-colors shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Excel
            </button>
            <button type="submit" name="export_format" value="pdf" class="flex-1 py-3 bg-brand-magenta hover:bg-[#BE124F] text-white rounded-xl font-bold text-sm transition-colors shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </button>
        </div>
    </form>
</div>

<script>
    document.getElementById('page-title') ? document.getElementById('page-title').innerText = 'Business Reports' : null;
</script>

<?php require_once '../includes/footer.php'; ?>