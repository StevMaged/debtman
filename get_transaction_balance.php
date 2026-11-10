<?php
header("Content-Type: application/json");
include "db.php";

$transaction_id = $_GET['transaction_id'];

// التعديل هنا: أضفنا كلمة "سداد" في البحث لضمان جلب الدفعات فقط
// الاستعلام سيبحث عن أي نص يحتوي على "سداد للفاتورة رقم: 176"
$sql = "SELECT amount, note, created_at 
        FROM transactions 
        WHERE note LIKE ?";

// ننشئ النمط الدقيق للبحث
$search_pattern = "%سداد للفاتورة رقم: " . $transaction_id . "%";

$stmt = $conn->prepare($sql);
$stmt->execute([$search_pattern]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الإجمالي برمجياً
$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += (double)$payment['amount'];
}

// إرجاع النتائج
echo json_encode([
    "status" => true,
    "total_paid" => (double)$total_paid,
    "payments" => $payments 
]);
?>