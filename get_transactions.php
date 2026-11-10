<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

if (isset($_GET['customer_id'])) {
    $customer_id = intval($_GET['customer_id']);

    try {
        /* الاستعلام المطور:
           - u1: المستخدم الذي أنشأ العملية (user_id)
           - u2: المستخدم الذي عدل العملية (last_modified_by)
        */
        $query = "SELECT t.id, t.customer_id, t.amount, t.type, t.note, t.created_at, t.updated_at,
                         COALESCE(u1.username, 'غير معروف') as created_by_name,
                         COALESCE(u2.username, 'لا يوجد') as modified_by_name
                  FROM transactions t 
                  LEFT JOIN users u1 ON t.user_id = u1.id 
                  LEFT JOIN users u2 ON t.last_modified_by = u2.id
                  WHERE t.customer_id = :customer_id 
                  ORDER BY t.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // تنظيف وتحويل الأنواع لضمان التوافق مع الأندرويد
        foreach ($transactions as &$tx) {
            $tx['id'] = intval($tx['id']);
            $tx['customer_id'] = intval($tx['customer_id']);
            $tx['amount'] = floatval($tx['amount']);
        }

        echo json_encode([
            "status" => true,
            "transactions" => $transactions
        ], JSON_UNESCAPED_UNICODE);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => false, 
            "message" => "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => false, 
        "message" => "معرف العميل مفقود"
    ]);
}
?>