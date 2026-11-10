<?php
/**
 * سكربت لحساب حجم قاعدة البيانات الفعلي
 * تم ضبط معامل التصحيح ليتطابق مع القراءة في لوحة تحكم AwardSpace
 */

header("Content-Type: application/json; charset=UTF-8");
include 'db.php'; 

try {
    $dbName = "4760424_data";
    $totalQuotaMiB = 30.0; // الحد الأقصى للمساحة المتاحة في الاستضافة
    
    // معامل التصحيح (3) للوصول للقيمة الفيزيائية التي تظهر في لوحة التحكم
    $correctionFactor = 3; 

    // استعلام لحساب الحجم الكلي للجداول (البيانات + الفهارس + المساحة المحجوزة)
    $sql = "SELECT SUM(data_length + index_length + data_free) AS total_bytes 
            FROM information_schema.tables 
            WHERE table_schema = :dbname";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':dbname' => $dbName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // تحويل الحجم من بايت إلى ميجابايت ثم تطبيق معامل التصحيح
    $totalBytes = isset($result['total_bytes']) ? (float)$result['total_bytes'] : 0;
    
    // الحساب: (البايت ÷ 1024 ÷ 1024) × معامل التصحيح
    $usedMiB = ($totalBytes / (1024 * 1024)) * $correctionFactor;

    // التأكد من عدم تجاوز السعة القصوى
    $finalUsedMiB = min($usedMiB, $totalQuotaMiB);

    // إرجاع النتيجة بتنسيق JSON
    echo json_encode([
        "status" => true,
        "used_mib" => round($finalUsedMiB, 2),
        "total_mib" => $totalQuotaMiB,
        "percentage" => round(($finalUsedMiB / $totalQuotaMiB) * 100, 1)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // تسجيل الخطأ وإرجاع رسالة مفهومة للتطبيق
    http_response_code(500);
    echo json_encode([
        "status" => false, 
        "message" => "حدث خطأ أثناء جلب حجم قاعدة البيانات."
    ], JSON_UNESCAPED_UNICODE);
}
?>