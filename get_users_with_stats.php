<?php
header("Content-Type: application/json; charset=UTF-8");
include 'db.php';

$query = "SELECT u.id, u.username,
          (SELECT COUNT(*) FROM customers WHERE user_id = u.id) as customers_count,
          (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as trans_count,
          (SELECT COUNT(*) FROM deleted_transactions_log WHERE user_id = u.id) as deleted_count
          FROM users u";

$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($users as $user) {
    // الحسبة الذكية: مجموع العمليات
    $total_activity = $user['customers_count'] + $user['trans_count'] + $user['deleted_count'];
    $user['total_activity'] = $total_activity;
    $result[] = $user;
}

echo json_encode(["status" => true, "users" => $result]);
?>