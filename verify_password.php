<?php
// 1. أسطر التصحيح (Debug) لإظهار أي خطأ يسبب الصفحة البيضاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=UTF-8");

// التأكد من وجود ملف قاعدة البيانات
if (!file_exists('db.php')) {
    echo json_encode(["status" => false, "message" => "ملف db.php غير موجود"]);
    exit;
}
include 'db.php'; 

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "طريقة اتصال غير مسموح بها"]);
    exit;
}

// التأكد من وجود البيانات
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($user_id) || empty($password)) {
    echo json_encode(["status" => false, "message" => "البيانات ناقصة"]);
    exit;
}

try {
    // الاستعلام - مطابق لهيكلية الـ login الناجحة
    $sql = "SELECT id FROM users WHERE id = :id AND password = :pwd LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $user_id, ':pwd' => $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode(["status" => true, "message" => "كلمة المرور صحيحة"]);
    } else {
        echo json_encode(["status" => false, "message" => "كلمة المرور غير صحيحة"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => false, "message" => "خطأ في السيرفر: " . $e->getMessage()]);
}
?>