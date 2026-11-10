<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// التأكد من وجود المعرف (id) قبل البدء
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $notes = $_POST['notes'];
    
    // استقبال فترة السداد مع تعيين 0 كقيمة افتراضية إذا لم ترسل
    $payment_cycle = isset($_POST['payment_cycle_days']) ? $_POST['payment_cycle_days'] : 0;
    
    // 🌟 استقبال المنطقة مع تعيين "غير محدد" كقيمة افتراضية
    $region = isset($_POST['region']) ? $_POST['region'] : 'غير محدد';

    // 🌟 تحديث الاستعلام ليشمل عمود الـ region
    $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, notes = ?, payment_cycle_days = ?, region = ? WHERE id = ?");

    // تنفيذ الاستعلام مع إرسال القيم بالترتيب الصحيح
    if($stmt->execute([$name, $phone, $notes, $payment_cycle, $region, $id])) {
        echo json_encode(["status" => true, "message" => "تم التحديث بنجاح"]);
    } else {
        echo json_encode(["status" => false, "message" => "فشل التحديث في قاعدة البيانات"]);
    }
} else {
    // في حال عدم إرسال id من التطبيق
    echo json_encode(["status" => false, "message" => "خطأ: بيانات ناقصة"]);
}
?>