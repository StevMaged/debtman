<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

try {
    // 1. جلب الحالة من جدول app_status (مع إضافة last_deleted_name)
    $stmtStatus = $conn->query("SELECT last_update, last_action_type, last_deleted_name FROM app_status WHERE id = 1");
    $status = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    $last_update = $status['last_update'];
    $last_action = $status['last_action_type'];
    
    // قيمة افتراضية تعني "لا تقم بإشعار المستخدم"
    $message = "no_update"; 

    // 2. فحص نوع الحركة وتوليد الرسالة المناسبة
    if ($last_action == 'customer_added') {
        $stmt = $conn->query("SELECT name FROM customers ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $message = "تم إضافة عميل جديد: " . ($row['name'] ?? 'غير معروف');
    } 
    elseif ($last_action == 'transaction_added') {
        $stmt = $conn->query("SELECT t.amount, c.name, c.customer_type FROM transactions t JOIN customers c ON t.customer_id = c.id ORDER BY t.id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $typeLabel = (strtolower(trim($row['customer_type'])) == 'supplier') ? "للمورد" : "للعميل";
            $message = "تم إضافة حركة مالية بقيمة " . $row['amount'] . " " . $typeLabel . " " . $row['name'];
        }
    }
    elseif ($last_action == 'transaction_updated') {
        $stmt = $conn->query("SELECT t.amount, c.name, c.customer_type FROM transactions t JOIN customers c ON t.customer_id = c.id ORDER BY t.updated_at DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
             $typeLabel = (strtolower(trim($row['customer_type'])) == 'supplier') ? "للمورد" : "للعميل";
            $message = "تم تعديل عملية بقيمة " . $row['amount'] . " " . $typeLabel . " " . $row['name'];
        }
    }
    // الحالات الجديدة (الحذف)
    elseif ($last_action == 'customer_deleted') {
        $message = "تم حذف العميل: " . ($status['last_deleted_name'] ?? 'غير معروف');
    }
    elseif ($last_action == 'transaction_deleted') {
        $message = "تم حذف حركة مالية من النظام";
    }

    echo json_encode([
        "last_update" => $last_update,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(["last_update" => "0", "message" => "no_update"]);
}
?>