<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// التأكد من أن الطلب تم بطريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "طريقة طلب غير صحيحة"], JSON_UNESCAPED_UNICODE);
    exit();
}

// استلام البيانات المرسلة من التطبيق (عبر Form-URL-Encoded)
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$old_password = isset($_POST['old_password']) ? trim($_POST['old_password']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

// التحقق من عدم إفراغ البيانات
if ($user_id <= 0 || empty($old_password) || empty($new_password)) {
    echo json_encode(["status" => false, "message" => "جميع الحقول مطلوبة"], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. التحقق من وجود المستخدم وكلمة المرور القديمة
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => false, "message" => "المستخدم غير موجود"], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. مطابقة كلمة المرور القديمة مع قاعدة البيانات
    if ($user['password'] !== $old_password) {
        echo json_encode(["status" => false, "message" => "كلمة المرور القديمة غير صحيحة"], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 3. تحديث كلمة المرور بكلمة المرور الجديدة
    $updateStmt = $conn->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
    $updateStmt->execute([
        ':new_password' => $new_password,
        ':user_id' => $user_id
    ]);

    echo json_encode(["status" => true, "message" => "تم تغيير كلمة المرور بنجاح"], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "خطأ في قاعدة البيانات: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>