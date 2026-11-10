<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// التأكد من استقبال البيانات عبر POST
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // الدور الافتراضي للمستخدم الجديد هو موظف (employee)
    $role = 'employee'; 

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => false, "message" => "يرجى إدخال اسم المستخدم وكلمة المرور"]);
        exit;
    }

    try {
        // 1. التحقق مما إذا كان اسم المستخدم موجود مسبقاً
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["status" => false, "message" => "اسم المستخدم موجود بالفعل، اختر اسماً آخر"]);
            exit;
        }

        // 2. إدخال المستخدم الجديد في الجدول
        $stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
        
        if ($stmt->execute([$username, $password, $role])) {
            echo json_encode(["status" => true, "message" => "تم إضافة المستخدم بنجاح"]);
        } else {
            echo json_encode(["status" => false, "message" => "فشل حفظ المستخدم في قاعدة البيانات"]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => false, "message" => "خطأ في السيرفر: " . $e->getMessage()]);
    }

} else {
    echo json_encode(["status" => false, "message" => "بيانات ناقصة"]);
}
?>