<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php'; 

// 1. جلب العمليات
$query = "SELECT customer_id,
    SUM(CASE WHEN type LIKE '%سحب%' THEN amount ELSE 0 END) - SUM(CASE WHEN type LIKE '%تسديد حساب%' THEN amount ELSE 0 END) AS net_customer,
    SUM(CASE WHEN type LIKE '%فاتورة%' THEN amount ELSE 0 END) - SUM(CASE WHEN type LIKE '%سددنا%' THEN amount ELSE 0 END) AS net_supplier
    FROM transactions GROUP BY customer_id";

$stmt = $conn->prepare($query);
$stmt->execute();
$all_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. جلب أنواع العملاء (مورد أم عميل)
$stmtTypes = $conn->query("SELECT id, customer_type FROM customers");
$types = $stmtTypes->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. تهيئة المتغيرات
$total_leena = 0.0; $total_aleena = 0.0;
$count_leena = 0; $count_aleena = 0; 
$c_debtor = 0; $c_creditor = 0; $c_zero = 0; 
$s_debtor = 0; $s_creditor = 0; $s_zero = 0; 

// 4. حلقة معالجة واحدة شاملة
foreach ($all_data as $row) {
    $id = $row['customer_id'];
    $type = $types[$id] ?? 'customer';
    $net_cust = (double)$row['net_customer'];
    $net_supp = (double)$row['net_supplier'];
    
    // الصافي الكلي للشخص (عميل + مورد)
    $net_total = $net_cust + $net_supp;

    // حساب المجاميع المالية العامة
    if ($net_cust > 0) $total_leena += $net_cust;
    else $total_aleena += abs($net_cust);

    if ($net_supp > 0) $total_aleena += $net_supp;
    else $total_leena += abs($net_supp);

    // حساب التصنيفات للوحة التحكم
    if ($type == 'customer') {
        if ($net_total > 0) { $c_debtor++; $count_leena++; }
        elseif ($net_total < 0) { $c_creditor++; $count_aleena++; }
        else $c_zero++;
    } else {
        if ($net_total > 0) { $s_debtor++; $count_aleena++; }
        elseif ($net_total < 0) { $s_creditor++; $count_leena++; }
        else $s_zero++;
    }
}

// 5. جلب إجمالي الأعداد من جدول العملاء
$stmtCount = $conn->query("SELECT 
    SUM(CASE WHEN customer_type = 'customer' THEN 1 ELSE 0 END) as total_c,
    SUM(CASE WHEN customer_type = 'supplier' THEN 1 ELSE 0 END) as total_s 
    FROM customers");
$counts = $stmtCount->fetch(PDO::FETCH_ASSOC);

// 🌟 6. حساب مجموع العمليات التي تقع تحت تصنيف "غير ذلك" (التي لا تطابق الكلمات المعرفة)
$stmtOther = $conn->query("SELECT SUM(amount) as other_total FROM transactions 
    WHERE type NOT LIKE '%سحب%' 
      AND type NOT LIKE '%تسديد حساب%' 
      AND type NOT LIKE '%فاتورة%' 
      AND type NOT LIKE '%سددنا%'");
$otherResult = $stmtOther->fetch(PDO::FETCH_ASSOC);
$other_total = (double)($otherResult['other_total'] ?? 0.0);

// 7. إخراج النتيجة (مع إبقاء كل ما طلبته وإضافة المتغير الجديد دونه مساس بالباقي)
echo json_encode([
    "status" => "success",
    "total_leena" => $total_leena, 
    "total_aleena" => $total_aleena,
    "count_leena" => $count_leena,
    "count_aleena" => $count_aleena,
    "total_customers" => (int)$counts['total_c'],
    "total_suppliers" => (int)$counts['total_s'],
    "c_stats" => ["debtor" => $c_debtor, "creditor" => $c_creditor, "zero" => $c_zero],
    "s_stats" => ["debtor" => $s_debtor, "creditor" => $s_creditor, "zero" => $s_zero],
    "other_transactions_total" => $other_total // المتغير الجديد المضاف بجانب البيانات الأساسية
], JSON_UNESCAPED_UNICODE);
?>