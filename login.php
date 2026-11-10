<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php'; 

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "طريقة اتصال غير مسموح بها"]);
    exit;
}

// التأكد من وجود البيانات
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

try {
    // الاستعلام عن المستخدم
    $sql = "SELECT id, username FROM users WHERE username = :u AND password = :p LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':u' => $username, ':p' => $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // نجاح الدخول
        echo json_encode([
            "status" => true,
            "message" => "تم تسجيل الدخول بنجاح",
            "user" => $user
        ]);
    } else {
        // فشل الدخول
        echo json_encode([
            "status" => false, 
            "message" => "اسم المستخدم أو كلمة المرور غير صحيحة"
        ]);
    }
} catch (PDOException $e) {
    // في حالة حدوث خطأ في الاتصال بقاعدة البيانات
    echo json_encode([
        "status" => false, 
        "message" => "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage()
    ]);
}
?>