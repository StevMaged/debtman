<?php
header("Content-Type: application/json; charset=utf-8");
include "db.php";

// استقبال البيانات باستخدام isset لتجنب الأخطاء
$customer_id = isset($_POST['customer_id']) ? $_POST['customer_id'] : null;
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;

// التحقق من أن القيم ليست فارغة
if (empty($customer_id) || empty($user_id)) {
    echo json_encode(["status" => false, "message" => "بيانات الحذف غير مكتملة (customer_id أو user_id مفقود)"]);
    exit;
}

try {
    // بدء المعاملة لضمان سلامة البيانات
    $conn->beginTransaction();

    // 1. جلب اسم العميل قبل حذفه للتوثيق
    $stmtName = $conn->prepare("SELECT name FROM customers WHERE id = ?");
    $stmtName->execute([$customer_id]);
    $customer = $stmtName->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $conn->rollBack();
        echo json_encode(["status" => false, "message" => "العميل غير موجود"]);
        exit;
    }

    $deleted_name = $customer['name'];

    // 2. حذف كافة المعاملات المرتبطة بهذا العميل
    $stmt1 = $conn->prepare("DELETE FROM transactions WHERE customer_id = ?");
    $stmt1->execute([$customer_id]);

    // 3. حذف العميل نفسه
    $stmt2 = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt2->execute([$customer_id]);

    // 4. تسجيل العملية في سجل المحذوفات
    if ($stmt2->rowCount() > 0) {
        $sqlLog = "INSERT INTO deleted_transactions_log 
                   (original_id, customer_name, amount, type, deleted_at, user_id, note) 
                   VALUES (?, ?, 0, 'حذف عميل', NOW(), ?, 'تم حذف العميل بالكامل مع معاملاته')";
        
        $stmtLog = $conn->prepare($sqlLog);
        $stmtLog->execute([$customer_id, $deleted_name, $user_id]);

        // اعتماد التغييرات
        $conn->commit();
        echo json_encode([
            "status" => true, 
            "message" => "تم حذف العميل " . $deleted_name . " وكل سجلاته بنجاح"
        ]);
    } else {
        $conn->rollBack();
        echo json_encode(["status" => false, "message" => "فشل الحذف"]);
    }

} catch (Exception $e) {
    // التراجع في حال حدوث أي خطأ
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        "status" => false, 
        "message" => "خطأ في السيرفر: " . $e->getMessage()
    ]);
}
?>