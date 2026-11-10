<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// وقت آخر فحص (القيمة الافتراضية قديمة جداً في حال عدم توفر التاريخ)
$last_check = isset($_GET['since']) ? $_GET['since'] : '2000-01-01 00:00:00';

try {
    // 1. جلب الفواتير المضافة أو المعدلة
   $sql_updates = "SELECT 
                    t.id, 
                    t.amount, 
                    t.type, 
                    c.name, 
                    -- التمييز بين الإضافة والتعديل بناءً على فارق التوقيت
                    CASE 
                        WHEN t.updated_at > (t.created_at + INTERVAL 1 SECOND) THEN 'updated' 
                        ELSE 'created' 
                    END as status, 
                    -- عرض وقت التعديل إذا حدث، وإلا عرض وقت الإنشاء
                    CASE 
                        WHEN t.updated_at > (t.created_at + INTERVAL 1 SECOND) THEN t.updated_at 
                        ELSE t.created_at 
                    END as date, 
                    IFNULL(t.note, '') as note,
                    COALESCE(u_creator.username, 'غير معروف') as added_by,
                    COALESCE(u_modifier.username, 'لا يوجد') as updated_by
                FROM transactions t 
                JOIN customers c ON t.customer_id = c.id 
                LEFT JOIN users u_creator ON t.user_id = u_creator.id
                LEFT JOIN users u_modifier ON t.last_modified_by = u_modifier.id
                WHERE t.updated_at > :last_check OR t.created_at > :last_check";
    
    // 2. جلب الفواتير المحذوفة من الأرشيف
    // في استعلام الفواتير المحذوفة:
$sql_deleted_tx = "SELECT d.original_id as id, d.amount, d.type, d.customer_name as name, 'deleted' as status, d.deleted_at as date, IFNULL(d.note, '') as note, 
                   d.user_id as user_id_debug, 
                   COALESCE(u.username, 'غير معروف') as deleted_by
                   FROM deleted_transactions_log d
                   LEFT JOIN users u ON d.user_id = u.id
                   WHERE d.deleted_at > :last_check AND d.type != 'حذف عميل'";
    
    // 3. جلب كل العملاء المحذوفين (تمت إضافة 'note' كقيمة فارغة لتوحيد الهيكل)
    // 3. جلب كل العملاء المحذوفين مع اسم من قام بالحذف
$sql_deleted_customers = "SELECT d.original_id as id, 
                                 d.amount, 
                                 d.type, 
                                 d.customer_name as name, 
                                 'deleted' as status, 
                                 d.deleted_at as date, 
                                 '' as note, 
                                 COALESCE(u.username, 'غير معروف') as deleted_by
                          FROM deleted_transactions_log d
                          LEFT JOIN users u ON d.user_id = u.id
                          WHERE d.type = 'حذف عميل' AND d.deleted_at > :last_check";
        
    // 4. جلب العملاء الجدد (تمت إضافة 'note' كقيمة فارغة لتوحيد الهيكل)
    $sql_new_customers = "SELECT 
                        c.id, 
                        '0' AS amount, 
                        'عملية إدارية: إضافة عميل' AS type, 
                        c.name, 
                        'updated' AS status, 
                        c.created_at as date, 
                        '' as note,
                        COALESCE(u.username, 'غير معروف') as added_by
                      FROM customers c
                      LEFT JOIN users u ON c.user_id = u.id
                      WHERE DATE_SUB(c.created_at, INTERVAL 3 HOUR) > :last_check";

    // تنفيذ الاستعلامات
    $stmt1 = $conn->prepare($sql_updates);
    $stmt1->execute([':last_check' => $last_check]);
    $updates = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $conn->prepare($sql_deleted_tx);
    $stmt2->execute([':last_check' => $last_check]);
    $deleted_items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $conn->prepare($sql_deleted_customers);
    $stmt3->execute([':last_check' => $last_check]);
    $deleted_customers = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $stmt4 = $conn->prepare($sql_new_customers);
    $stmt4->execute([':last_check' => $last_check]);
    $new_customers = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    // 5. دمج كل النتائج في مصفوفة واحدة
    $all_changes = array_merge($updates, $deleted_items, $deleted_customers, $new_customers);

    echo json_encode([
        "status" => true,
        "count" => count($all_changes),
        "updates" => $all_changes
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>