<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';
date_default_timezone_set('Africa/Cairo');

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$customer_type = isset($_POST['customer_type']) ? trim($_POST['customer_type']) : 'customer';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : null;

// استقبال قيمة الأيام الجديدة والمنطقة
$payment_cycle_days = isset($_POST['payment_cycle_days']) ? intval($_POST['payment_cycle_days']) : 0;
$region = isset($_POST['region']) ? trim($_POST['region']) : 'غير محدد';

// التحقق من الاسم
if (empty($name)) {
    echo json_encode(["status" => false, "message" => "الرجاء إدخال الاسم"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($user_id)) {
    echo json_encode(["status" => false, "message" => "خطأ في الجلسة: user_id مفقود"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إضافة العميل/المورد مع حقل region و payment_cycle_days
    $sql = "INSERT INTO customers (name, customer_type, total_debt, phone, notes, payment_cycle_days, region, user_id) 
            VALUES (:name, :customer_type, 0.00, :phone, :notes, :payment_cycle_days, :region, :user_id)";
    
    $stmt = $conn->prepare($sql);
    
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':customer_type', $customer_type);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':payment_cycle_days', $payment_cycle_days);
    $stmt->bindParam(':region', $region);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        $conn->exec("UPDATE app_status SET last_update = NOW(), last_action_type = 'customer_added' WHERE id = 1");
        echo json_encode(["status" => true, "message" => "تم حفظ الحساب بنجاح"], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["status" => false, "message" => "فشل الحفظ"], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "خطأ في قاعدة البيانات: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>