<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $region_name = trim($_POST['region_name'] ?? '');

    if (!empty($region_name)) {
        try {
            $stmt = $conn->prepare("INSERT INTO regions (region_name) VALUES (?)");
            $stmt->execute([$region_name]);
            
            echo json_encode([
                "status" => true,
                "message" => "تمت إضافة المنطقة بنجاح"
            ], JSON_UNESCAPED_UNICODE);

        } catch(PDOException $e) {
            // لو المنطقة موجودة قبل كده (Duplicate entry)
            if ($e->getCode() == 23000) {
                echo json_encode([
                    "status" => false,
                    "message" => "هذه المنطقة موجودة مسبقاً"
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    "status" => false,
                    "message" => "خطأ: " . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "يرجى كتابة اسم المنطقة"
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>