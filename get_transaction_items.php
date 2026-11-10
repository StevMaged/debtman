<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

// التأكد من استقبال رقم المعاملة
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;

if ($transaction_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Missing or invalid transaction_id"]);
    exit;
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. محاولة جلب بيانات الفاتورة من الجدول الأساسي (النشط)
    $sqlTx = "SELECT created_at, note, type, amount FROM transactions WHERE id = :tx_id LIMIT 1";
    $stmtTx = $conn->prepare($sqlTx);
    $stmtTx->execute([':tx_id' => $transaction_id]);
    $transactionInfo = $stmtTx->fetch(PDO::FETCH_ASSOC);

    // 2. إذا لم توجد في الجدول الأساسي، نبحث في جدول الأرشيف (Log)
    $is_archived = false;
    if (!$transactionInfo) {
        // تم استبدال '---' بـ deleted_at لجلب التاريخ الفعلي من جدول الأرشيف
        $sqlArchived = "SELECT deleted_at as created_at, note, type, amount FROM deleted_transactions_log WHERE original_id = :tx_id LIMIT 1";
        $stmtArchived = $conn->prepare($sqlArchived);
        $stmtArchived->execute([':tx_id' => $transaction_id]);
        $transactionInfo = $stmtArchived->fetch(PDO::FETCH_ASSOC);
        
        if ($transactionInfo) {
            $is_archived = true;
        } else {
            echo json_encode(["status" => false, "message" => "العملية غير موجودة في السجلات"]);
            exit;
        }
    }

    // 3. جلب الأصناف (من الجدول الأساسي إذا كانت نشطة، أو من جدول الأرشيف إذا كانت محذوفة)
    $itemsSql = $is_archived ? 
                "SELECT item_name, qty, price, total FROM deleted_transaction_items WHERE transaction_id = :tx_id" : 
                "SELECT item_name, qty, price, total FROM transaction_items WHERE transaction_id = :tx_id";

    $stmtItems = $conn->prepare($itemsSql);
    $stmtItems->execute([':tx_id' => $transaction_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // تنسيق البيانات للأصناف
    foreach ($items as &$item) {
        $item['qty'] = floatval($item['qty']);
        $item['price'] = floatval($item['price']);
        $item['total'] = floatval($item['total']);
    }

    // 4. الرد النهائي (نفس الهيكلية المتوقعة في الأندرويد)
    echo json_encode([
        "status" => true,
        "created_at" => $transactionInfo['created_at'],
        "note" => $transactionInfo['note'],
        "type" => $transactionInfo['type'],
        "amount" => floatval($transactionInfo['amount']),
        "items" => $items
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>