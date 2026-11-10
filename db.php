<?php
// ضبط توقيت الـ PHP على القاهرة
date_default_timezone_set('Africa/Cairo');

// سحب إعدادات الاتصال تلقائياً من بيئة عمل Railway الداخلية
$host     = getenv('MYSQLHOST');
$dbname   = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$port     = getenv('MYSQLPORT');

try {
    // إنشاء الاتصال باستخدام PDO مع تمرير البورت الداخلي/الخارجي الصحيح
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // ضبط وضع عرض الأخطاء ليكون استثناءً (Exception)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🔥 السطر السحري: إجبار قاعدة البيانات (MySQL) على توقيت مصر (+03:00) في هذه الجلسة
    $conn->exec("SET time_zone = '+03:00';");
    
} catch(PDOException $e) {
    // في حال فشل الاتصال، نرسل رسالة خطأ بصيغة JSON
    die(json_encode(["status" => false, "message" => "فشل الاتصال بقاعدة البيانات: " . $e->getMessage()]));
}
?>