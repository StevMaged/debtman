<?php

header("Content-Type: application/json");

include "db.php";

$total_debts_sql = "

SELECT COALESCE(SUM(amount),0) AS total
FROM transactions
WHERE type = 'debt'

";

$total_payments_sql = "

SELECT COALESCE(SUM(amount),0) AS total
FROM transactions
WHERE type = 'payment'

";

$customers_sql = "

SELECT COUNT(*) AS total
FROM customers

";

$total_debts = $conn->query($total_debts_sql)->fetch();
$total_payments = $conn->query($total_payments_sql)->fetch();
$total_customers = $conn->query($customers_sql)->fetch();

$remaining = $total_debts['total'] - $total_payments['total'];

echo json_encode([

    "status" => true,

    "dashboard" => [

        "total_debts" => $total_debts['total'],

        "total_payments" => $total_payments['total'],

        "remaining" => $remaining,

        "customers_count" => $total_customers['total']
    ]
]);
?>