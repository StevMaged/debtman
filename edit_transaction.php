<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php'; // تأكد أن هذا الملف يحتوي على اتصال PDO في $conn

// قراءة البيانات المرسلة
$raw_data = file_get_contents("php://input");
$data = json_decode($raw_data, true);

if (!$data) {
    echo json_encode(["status" => false, "message" => "بيانات غير صالحة"], JSON_UNESCAPED_UNICODE);
    exit;
}

$transaction_id = isset($data['transaction_id']) ? intval($data['transaction_id']) : 0;
$total_amount   = isset($data['total_amount']) ? floatval($data['total_amount']) : 0.0;
$items          = isset($data['items']) ? $data['items'] : [];
$modified_by = isset($data['user_id']) ? intval($data['user_id']) : 0;
// متغير جديد للتمييز بين فاتورة البضاعة وسند السداد
$is_payment     = isset($data['is_payment']) ? (bool)$data['is_payment'] : false;

if ($transaction_id <= 0) {
    echo json_encode(["status" => false, "message" => "رقم المعاملة غير صحيح"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. تحديث إجمالي المبلغ في الجدول الرئيسي
    $sql = "UPDATE transactions SET amount = :amount, last_modified_by = :uid WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':amount' => $total_amount, 
        ':uid'    => $modified_by, 
        ':id'     => $transaction_id
    ]);
        
        // تحقق مما إذا تم تحديث أي صف فعلاً
    if ($stmt->rowCount() === 0) {
        // قد يكون المبلغ لم يتغير، لذا لن يغير MySQL القيمة. 
        // الحل: أجبر التحديث حتى لو لم تتغير القيم
        $stmt_force = $conn->prepare("UPDATE transactions SET last_modified_by = :uid WHERE id = :id");
        $stmt_force->execute([':uid' => $modified_by, ':id' => $transaction_id]);
    }

    // 2. معالجة الأصناف فقط إذا لم تكن المعاملة "سداد"
    if (!$is_payment) {
        // حذف الأصناف القديمة
        $stmt_del = $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = :tid");
        $stmt_del->execute(['tid' => $transaction_id]);

        // إضافة الأصناف الجديدة
        $sql = "INSERT INTO transaction_items (transaction_id, item_name, qty, price, total) VALUES (?, ?, ?, ?, ?)";
        $stmt_ins = $conn->prepare($sql);

        foreach ($items as $item) {
            $name  = $item['name'];
            $qty   = floatval($item['qty']);
            $price = floatval($item['price']);
            $total = $qty * $price;
            
            $stmt_ins->execute([$transaction_id, $name, $qty, $price, $total]);
        }
    }

    $conn->commit();
    echo json_encode(["status" => true, "message" => "تم تعديل الفاتورة بنجاح"], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "خطأ في السيرفر: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>