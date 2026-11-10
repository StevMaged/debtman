اعد كتابته هذا كاملا
<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : (isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0);
$amount = isset($data['amount']) ? floatval($data['amount']) : (isset($_POST['amount']) ? floatval($_POST['amount']) : 0);
$type = isset($data['type']) ? trim($data['type']) : (isset($_POST['type']) ? trim($_POST['type']) : '');
$note = isset($data['note']) ? trim($data['note']) : (isset($_POST['note']) ? trim($_POST['note']) : '');

if ($customer_id <= 0 || $amount <= 0 || empty($type)) {
    echo json_encode(["status" => false, "message" => "بيانات العملية غير مكتملة"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // إضافة العملية فقط، والـ Trigger في قاعدة البيانات سيتكفل بتحديث app_status
    $sql = "INSERT INTO transactions (customer_id, amount, type, note, created_by, created_at) 
            VALUES (:customer_id, :amount, :type, :note, 1, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':note', $note);
    
    if ($stmt->execute()) {
        // تم الإدخال بنجاح، والـ Trigger سيعمل في الخلفية
        echo json_encode(["status" => true, "message" => "تم تسجيل العملية بنجاح"], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["status" => false, "message" => "فشل تسجيل العملية"], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "خطأ في قاعدة البيانات: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>