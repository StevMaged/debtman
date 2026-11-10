<?php
// ضبط توقيت الـ PHP على القاهرة
date_default_timezone_set('Africa/Cairo');

// سحب إعدادات الاتصال من بيئة عمل Railway مع وضع القيم الداخلية كبديل آمن
$host     = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$dbname   = getenv('MYSQLDATABASE') ?: 'railway';
$username = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD');
$port     = getenv('MYSQLPORT') ?: '3306';

try {
    // إنشاء الاتصال باستخدام PDO مع تمرير المتغيرات النظيفة
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // ضبط وضع عرض الأخطاء ليكون استثناءً (Exception)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إجبار قاعدة البيانات على توقيت مصر (+03:00)
    $conn->exec("SET time_zone = '+03:00';");
    
} catch(PDOException $e) {
    // في حال فشل الاتصال، نرسل رسالة خطأ بصيغة JSON
    die(json_encode(["status" => false, "message" => "فشل الاتصال بقاعدة البيانات: " . $e->getMessage()]));
}
?>
