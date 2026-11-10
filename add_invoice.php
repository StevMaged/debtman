<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "بيانات غير صحيحة"]);
    exit();
}

$customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
$total_amount = isset($data['total_amount']) ? floatval($data['total_amount']) : 0.0;
$paid_amount = isset($data['paid_amount']) ? floatval($data['paid_amount']) : 0.0;
$transaction_type = isset($data['transaction_type']) ? $data['transaction_type'] : '';
$description = isset($data['description']) ? $data['description'] : '';
$items = isset($data['items']) ? $data['items'] : [];
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

if ($user_id == 0) {
    echo json_encode(["status" => false, "message" => "Debug: user_id is missing. Data received: " . json_encode($data)]);
    exit();
}

// تحديد أنواع العمليات
$is_other_type = (strpos($transaction_type, 'غير ذلك') !== false);
$is_payment_only = (strpos($transaction_type, 'تسديد') !== false || strpos($transaction_type, 'سدد') !== false);

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    if ($is_other_type) {
        // --- حالة: غير ذلك (سجل أرشيفي بحت بدون أي تأثير على الدين أو الحسابات) ---
        $stmt = $conn->prepare("INSERT INTO transactions (customer_id, amount, type, note, created_at, user_id) VALUES (:cid, :amt, :typ, :note, NOW(), :uid)");
        $stmt->execute([':cid' => $customer_id, ':amt' => $total_amount, ':typ' => $transaction_type, ':note' => $description, ':uid' => $user_id]);
        $transaction_id = $conn->lastInsertId();

        // تحديث الملاحظة بإضافة رقم العملية للأرشيف
        $updated_note = "عملية رقم: " . $transaction_id . " ( " . $description . " ) ";
        $conn->prepare("UPDATE transactions SET note = :note WHERE id = :id")->execute([':note' => $updated_note, ':id' => $transaction_id]);

        // ملاحظة: تم تخطي أي تحديث لجدول customers لضمان عدم تأثر الرصيد نهائياً.

    } else if ($is_payment_only) {
        // --- حالة: تسديد فقط (سجل واحد) ---
        $stmt = $conn->prepare("INSERT INTO transactions (customer_id, amount, type, note, created_at, user_id) VALUES (:cid, :amt, :typ, :note, NOW(), :uid)");
        $stmt->execute([':cid' => $customer_id, ':amt' => $paid_amount, ':typ' => $transaction_type, ':note' => $description, ':uid' => $user_id]);
        $transaction_id = $conn->lastInsertId();

        // تحديث الملاحظة بإضافة رقم العملية
        $updated_note = "عملية تسديد رقم: " . $transaction_id . " ( " . $description . " ) ";
        $conn->prepare("UPDATE transactions SET note = :note WHERE id = :id")->execute([':note' => $updated_note, ':id' => $transaction_id]);

        // تحديث المديونية (خصم المبلغ المسدد)
        $conn->prepare("UPDATE customers SET total_debt = total_debt - :amt WHERE id = :cid")->execute([':amt' => $paid_amount, ':cid' => $customer_id]);

    } else {
        // --- حالة: فاتورة بضاعة (سجل الفاتورة + سجل الدفع إن وجد) ---
        
        // 1. تسجيل الفاتورة الأساسية
        $stmt = $conn->prepare("INSERT INTO transactions (customer_id, amount, type, note, created_at, user_id) VALUES (:cid, :amt, :typ, :note, NOW(), :uid)");
        $stmt->execute([':cid' => $customer_id, ':amt' => $total_amount, ':typ' => $transaction_type, ':note' => $description, ':uid' => $user_id]);
        $transaction_id = $conn->lastInsertId();

        // 2. تحديث ملاحظات الفاتورة برقمها
        $updated_note = "فاتورة رقم: " . $transaction_id . " ( " . $description . " ) ";
        $conn->prepare("UPDATE transactions SET note = :note WHERE id = :id")->execute([':note' => $updated_note, ':id' => $transaction_id]);

        // 3. حفظ الأصناف
        if (!empty($items)) {
            $stmtItem = $conn->prepare("INSERT INTO transaction_items (transaction_id, item_name, qty, price, total) VALUES (:tx_id, :name, :qty, :prc, :tot)");
            foreach ($items as $item) {
                $stmtItem->execute([':tx_id' => $transaction_id, ':name' => $item['item_name'], ':qty' => $item['qty'], ':prc' => $item['price'], ':tot' => $item['total']]);
            }
        }

        // 4. إذا كان هناك مبلغ مدفوع، سجل عملية دفع منفصلة
        if ($paid_amount > 0) {
            $stmtPay = $conn->prepare("INSERT INTO transactions (customer_id, amount, type, note, created_at, user_id) VALUES (:cid, :amt, 'تسديد حساب (سدد من عليه)', '', NOW(), :uid)");
            $stmtPay->execute([':cid' => $customer_id, ':amt' => $paid_amount, ':uid' => $user_id]);
            
            $payment_id = $conn->lastInsertId();
            
            $formatted_note = "عملية تسديد رقم: " . $payment_id . " ( سداد للفاتورة رقم: " . $transaction_id . " ) ";
            
            $conn->prepare("UPDATE transactions SET note = :note WHERE id = :id")
                 ->execute([':note' => $formatted_note, ':id' => $payment_id]);
        }

        // 5. تحديث المديونية (الصافي)
        $net_amount = $total_amount - $paid_amount;
        $conn->prepare("UPDATE customers SET total_debt = total_debt + :net WHERE id = :cid")->execute([':net' => $net_amount, ':cid' => $customer_id]);
    }

    $conn->commit();
    echo json_encode(["status" => true, "message" => "تم حفظ العملية بنجاح!"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "خطأ في السيرفر: " . $e->getMessage()]);
}
?>