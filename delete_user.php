<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

if(isset($_POST['id'])) {
    $target_user_id = $_POST['id'];

    // حماية: لا تسمح بحذف المدير الرئيسي (ID 1)
    if ($target_user_id == 1) {
        echo json_encode(["status" => false, "message" => "لا يمكن حذف المدير الرئيسي للنظام"]);
        exit;
    }

    try {
        // نبدأ عملية ربط (Transaction) لضمان أن كل الخطوات تتم معاً أو تفشل معاً
        $conn->beginTransaction();

        // 1. نقل ملكية (أو تحديث مرجع) العمليات للمدير (ID = 1) لضمان بقاء التاريخ المالي
        // تحديث العملاء الذين أضافهم هذا المستخدم
        $conn->prepare("UPDATE customers SET user_id = 0 WHERE user_id = ?")->execute([$target_user_id]);
        
        // تحديث المعاملات (الفواتير) التي قام بها
        $conn->prepare("UPDATE transactions SET user_id = 0 WHERE user_id = ?")->execute([$target_user_id]);
        
        // تحديث سجلات الحذف
        $conn->prepare("UPDATE deleted_transactions_log SET user_id = 0 WHERE user_id = ?")->execute([$target_user_id]);

        // 2. الآن يمكننا حذف المستخدم بأمان
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);

        // تنفيذ كل ما سبق
        $conn->commit();
        
        echo json_encode(["status" => true, "message" => "تم حذف المستخدم ونقل تبعية عملياته للمدير بنجاح"]);

    } catch (Exception $e) {
        // في حال حدوث أي خطأ، تراجع عن أي تعديل
        $conn->rollBack();
        echo json_encode(["status" => false, "message" => "فشل الحذف: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => false, "message" => "بيانات ناقصة"]);
}
?>