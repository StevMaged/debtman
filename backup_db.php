<?php
include 'db.php'; 

// إعداد اسم الملف وتاريخ اليوم
$filename = 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename=' . $filename);

// جلب كل الجداول في قاعدة البيانات
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// البدء في كتابة محتوى ملف الـ SQL
$output = "-- Database Backup " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    // 1. هيكل الجدول (Structure)
    $output .= "DROP TABLE IF EXISTS `$table`;\n";
    $create_table = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $output .= $create_table['Create Table'] . ";\n\n";

    // 2. البيانات (Data)
    $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $values = array_map(function($value) use ($conn) {
            return $conn->quote($value);
        }, $row);
        
        $output .= "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n";
    }
    $output .= "\n\n";
}

echo $output;
?>