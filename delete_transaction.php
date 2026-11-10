<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($id > 0) {
    try {
        $conn->beginTransaction();

        // 1. جلب بيانات الفاتورة
        $selectSql = "SELECT t.amount, t.type, c.name, t.note 
                      FROM transactions t 
                      JOIN customers c ON t.customer_id = c.id 
                      WHERE t.id = :id";
        
        $stmt = $conn->prepare($selectSql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            
            // 2. فحص هل توجد أصناف لهذه الفاتورة
            $checkItemsSql = "SELECT * FROM transaction_items WHERE transaction_id = :id";
            $itemStmt = $conn->prepare($checkItemsSql);
            $itemStmt->bindParam(':id', $id);
            $itemStmt->execute();
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. إذا كانت فاتورة بضاعة (لها أصناف)، انقل الأصناف للأرشيف أولاً
            if (!empty($items)) {
                $insertItemSql = "INSERT INTO deleted_transaction_items (transaction_id, item_name, qty, price, total) 
                                  VALUES (:tid, :name, :qty, :price, :total)";
                $insertItemStmt = $conn->prepare($insertItemSql);
                
                foreach ($items as $item) {
                    $insertItemStmt->execute([
                        ':tid'   => $id,
                        ':name'  => $item['item_name'],
                        ':qty'   => $item['qty'],
                        ':price' => $item['price'],
                        ':total' => $item['total']
                    ]);
                }
                // حذف الأصناف من الجدول الأساسي
                $conn->prepare("DELETE FROM transaction_items WHERE transaction_id = :id")->execute([':id' => $id]);
            }

            // 4. أرشفة الفاتورة نفسها (كما كنت تفعل)
            $logSql = "INSERT INTO deleted_transactions_log (original_id, customer_name, amount, type, note ,user_id) 
                       VALUES (:id, :name, :amount, :type, :note ,:uid)";
            $conn->prepare($logSql)->execute([
            ':id'     => $id,
            ':name'   => $transaction['name'],
            ':amount' => $transaction['amount'],
            ':type'   => $transaction['type'],
            ':note'   => $transaction['note'],
            ':uid'    => $user_id // أضف هذا السطر ليمرر القيمة التي استقبلتها في بداية الملف
      	     ]);

            // 5. حذف الفاتورة
            $conn->prepare("DELETE FROM transactions WHERE id = :id")->execute([':id' => $id]);

            $conn->commit();
            echo json_encode(["status" => true, "message" => "تم حذف الفاتورة وأرشفتها بنجاح"]);
        } else {
            $conn->rollBack();
            echo json_encode(["status" => false, "message" => "الفاتورة غير موجودة"]);
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        echo json_encode(["status" => false, "message" => "خطأ: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => false, "message" => "ID غير صالح"]);
}
?>