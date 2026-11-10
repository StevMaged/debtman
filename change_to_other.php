<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

if (isset($_POST['transaction_id'])) {
    $transactionId = intval($_POST['transaction_id']);

    try {
        // تحديث نوع المعاملة إلى "غير ذلك"
        $stmt = $conn->prepare("UPDATE transactions SET type = 'غير ذلك' WHERE id = ?");
        
        if ($stmt->execute([$transactionId])) {
            echo json_encode(["status" => true, "message" => "تم تغيير نوع الفاتورة إلى (غير ذلك) بنجاح"]);
        } else {
            echo json_encode(["status" => false, "message" => "فشل تحديث الفاتورة في قاعدة البيانات"]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => false, "message" => "خطأ في السيرفر: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => false, "message" => "رقم المعاملة غير متوفر"]);
}
?>