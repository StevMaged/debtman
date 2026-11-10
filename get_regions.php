<?php
header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php';

try {
    $stmt = $conn->query("SELECT region_name FROM regions ORDER BY id ASC");
    $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "status" => true,
        "regions" => $regions
    ], JSON_UNESCAPED_UNICODE);

} catch(PDOException $e) {
    echo json_encode([
        "status" => false,
        "regions" => [],
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>