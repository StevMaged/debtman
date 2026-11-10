<?php

header("Content-Type: application/json");

include "db.php";

$customer_id = $_GET['customer_id'];

$sql = "

SELECT

    t.id,
    t.amount,
    t.type,
    t.note,
    t.created_at,

    u.full_name AS created_by_name

FROM transactions t

JOIN users u
ON t.created_by = u.id

WHERE t.customer_id = ?

ORDER BY t.created_at DESC

";

$stmt = $conn->prepare($sql);
$stmt->execute([$customer_id]);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => true,
    "transactions" => $transactions
]);
?>