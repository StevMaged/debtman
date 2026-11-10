<?php
// منع ظهور أي أخطاء أو تحذيرات
error_reporting(0);
ini_set('display_errors', 0);

include 'db.php'; 

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=full_report_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// إضافة الـ BOM ليدعم العربي في إكسيل
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// العناوين الأساسية
fputcsv($output, array('اسم العميل', 'نوع العميل', 'نوع العملية', 'إجمالي الفاتورة', 'تاريخ العملية', 'اسم الصنف', 'الكمية', 'سعر الوحدة', 'إجمالي الصنف'), ',', '"', '\\');

// استعلام البيانات التفصيلي للعمليات
$sql = "SELECT t.id AS transaction_id, c.name, c.customer_type, t.type AS transaction_type, 
        t.amount AS transaction_total, t.created_at, i.item_name, i.qty, i.price, i.total AS item_total 
        FROM customers c 
        JOIN transactions t ON c.id = t.customer_id 
        LEFT JOIN transaction_items i ON t.id = i.transaction_id 
        ORDER BY c.name, t.created_at DESC, t.id";

$stmt = $conn->prepare($sql);
$stmt->execute();

$last_transaction_id = null; 

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // فاصل بين كل فاتورة والأخرى
    if ($last_transaction_id !== null && $row['transaction_id'] != $last_transaction_id) {
        fputcsv($output, array('---------------', '---------------', '---------------', '---------------', '---------------', '---------------', '---------------', '---------------', '---------------'), ',', '"', '\\');
    }
    
    // دمج بيانات الفاتورة الواحدة
    if ($row['transaction_id'] == $last_transaction_id) {
        $row['transaction_total'] = ""; 
        $row['transaction_type'] = ""; 
        $row['created_at'] = "";        
    }
    
    $last_transaction_id = $row['transaction_id'];

    // ترجمة نوع العميل للعرض
    $type_display = ($row['customer_type'] == 'customer') ? 'عميل' : 'مورد';

    fputcsv($output, array(
        $row['name'],
        $type_display,
        $row['transaction_type'],
        $row['transaction_total'],
        $row['created_at'],
        $row['item_name'],
        $row['qty'],
        $row['price'],
        $row['item_total']
    ), ',', '"', '\\');
}

// --- قسم الملخص المالي (محسوب لحظياً من سجل العمليات) ---
fputcsv($output, array('', '', '', '', '', '', '', '', ''), ',', '"', '\\');
fputcsv($output, array('=== الملخص المالي النهائي (حساب فعلي من السجلات) ===', '', '', '', '', '', '', '', ''), ',', '"', '\\');

// استعلام يقوم بجمع كل "الديون" وطرح كل "المدفوعات" لكل عميل أو مورد
$summary_sql = "SELECT c.id, c.name, c.customer_type, 
                SUM(CASE WHEN t.type LIKE '%سحب%' OR t.type LIKE '%فاتورة%' OR t.type LIKE '%بضاعة%' THEN t.amount ELSE 0 END) as total_debts,
                SUM(CASE WHEN t.type LIKE '%تسديد%' OR t.type LIKE '%دفعات%' THEN t.amount ELSE 0 END) as total_credits
                FROM customers c 
                LEFT JOIN transactions t ON c.id = t.customer_id 
                GROUP BY c.id";

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->execute();

while ($sum_row = $summary_stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = $sum_row['name'];
    $type = $sum_row['customer_type'];
    
    // ترجمة نوع العميل للعرض
    $type_display = ($type == 'customer') ? 'عميل' : 'مورد';
    
    // الحساب: إجمالي السحوبات (دين) - إجمالي المدفوعات (تسديد)
    $real_balance = (float)$sum_row['total_debts'] - (float)$sum_row['total_credits'];
    
    $status_text = "";
    
    // المنطق المحاسبي (يبقى كما هو معتمداً على النوع الأصلي للعملية الحسابية):
    if ($type == 'customer') {
        if ($real_balance > 0) $status_text = "العميل عليه: " . $real_balance;
        elseif ($real_balance < 0) $status_text = "العميل له (رصيد دائن): " . abs($real_balance);
        else $status_text = "الحساب متزن (0)";
    } else {
        // المورد (Supplier)
        if ($real_balance > 0) $status_text = "علينا للمورد: " . $real_balance;
        elseif ($real_balance < 0) $status_text = "المورد عليه (رصيد مدين): " . abs($real_balance);
        else $status_text = "الحساب متزن (0)";
    }
    
    fputcsv($output, array($name, $type_display, $status_text, '', '', '', '', '', ''), ',', '"', '\\');
}

fclose($output);
?>