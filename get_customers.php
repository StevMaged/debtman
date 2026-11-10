<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // الاستعلام الذكي (تم إضافة c.region)
    $query = "SELECT 
                c.id, 
                c.name, 
                c.customer_type, 
                c.phone, 
                c.notes, 
                c.payment_cycle_days,
                c.created_at,
                c.region,
                COALESCE(u.username, 'غير معروف') as added_by,
                
                -- حساب الدين
                COALESCE(SUM(
                    CASE 
                        WHEN t.type LIKE '%سحب%' OR t.type LIKE '%فاتورة%' THEN t.amount
                        WHEN t.type LIKE '%تسديد%' OR t.type LIKE '%سدد%' THEN -t.amount
                        ELSE 0 
                    END
                ), 0) AS total_debt,
                
                -- تحديد اللون
                CASE 
                    WHEN SUM(CASE 
                                WHEN t.type LIKE '%سحب%' OR t.type LIKE '%فاتورة%' THEN t.amount
                                WHEN t.type LIKE '%تسديد%' OR t.type LIKE '%سدد%' THEN -t.amount
                                ELSE 0 
                             END) <= 0 
                         OR c.payment_cycle_days = 0 THEN 'white'
                         
                    WHEN DATEDIFF(NOW(), COALESCE(MAX(CASE WHEN t.type LIKE '%تسديد%' OR t.type LIKE '%سدد%' THEN t.created_at END), c.created_at)) > c.payment_cycle_days THEN 'red'
                    
                    WHEN DATEDIFF(NOW(), COALESCE(MAX(CASE WHEN t.type LIKE '%تسديد%' OR t.type LIKE '%سدد%' THEN t.created_at END), c.created_at)) = c.payment_cycle_days THEN 'yellow'
                    
                    ELSE 'green'
                END as status_color
              FROM customers c 
              LEFT JOIN transactions t ON c.id = t.customer_id 
              LEFT JOIN users u ON c.user_id = u.id 
              GROUP BY c.id, c.name, c.customer_type, c.phone, c.notes, c.payment_cycle_days, c.created_at, c.region, u.username"; 
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحويل القيم
    foreach ($customers as &$customer) {
        $customer['id'] = (int)$customer['id'];
        $customer['total_debt'] = (float)$customer['total_debt'];
        $customer['payment_cycle_days'] = (int)$customer['payment_cycle_days'];
        
        // التأكد من أن المنطقة ليست فارغة في الـ JSON
        if (empty($customer['region'])) {
            $customer['region'] = "غير محدد";
        }
        
        if (empty($customer['added_by'])) {
            $customer['added_by'] = "غير معروف";
        }
    }

    echo json_encode([
        "status" => true,
        "customers" => $customers
    ], JSON_UNESCAPED_UNICODE);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false, 
        "message" => "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage()
    ]);
}
?>