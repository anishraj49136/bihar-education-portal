<?php
require_once 'config.php';

// यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType(['district_officer', 'admin']);

// जिला आईडी प्राप्त करें
 $district_id = $_SESSION['district_id'];

// फिल्टर मान प्राप्त करें
 $udise_code = $_GET['udise_code'] ?? '';
 $teacher_name = $_GET['teacher_name'] ?? '';
 $pran_uan = $_GET['pran_uan'] ?? '';
 $class_filter = $_GET['class_filter'] ?? '';

// डेटा प्राप्त करें
 $sql = "SELECT t.name, t.mobile, t.pran_no, t.uan_no, t.class, 
               a.total_attendance_days, a.unauthorized_absence_days, a.leave_days, a.remarks,
               s.name as school_name, s.udise_code,
               b.name as block_name
        FROM teachers t
        JOIN attendance a ON t.id = a.teacher_id
        JOIN schools s ON t.school_id = s.id
        JOIN blocks b ON s.block_id = b.id
        WHERE b.district_id = '$district_id' AND a.status = 'forwarded_to_district'";

if (!empty($udise_code)) {
    $sql .= " AND s.udise_code LIKE '%$udise_code%'";
}
if (!empty($teacher_name)) {
    $sql .= " AND t.name LIKE '%$teacher_name%'";
}
if (!empty($pran_uan)) {
    $sql .= " AND (t.pran_no LIKE '%$pran_uan%' OR t.uan_no LIKE '%$pran_uan%')";
}
if (!empty($class_filter)) {
    $sql .= " AND t.class = '$class_filter'";
}

 $sql .= " ORDER BY s.name, t.name";

 $stmt = $conn->prepare($sql);
 $stmt->execute();
 $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV हेडर सेट करें
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=district_attendance_' . date('Y-m-d') . '.csv');

// आउटपुट स्ट्रीम खोलें
 $output = fopen('php://output', 'w');

// CSV हेडर लिखें
fputcsv($output, [
    'विद्यालय का नाम', 'UDISE कोड', 'प्रखंड', 'शिक्षक का नाम', 'मोबाइल नंबर', 'PRAN', 'UAN', 'श्रेणी', 
    'भुगतान हेतु दिवस', 'अनधिकृत अनुपस्थिति', 'अवकाश', 'अभियुक्ति'
]);

// डेटा लिखें
foreach ($records as $record) {
    fputcsv($output, [
        $record['school_name'],
        $record['udise_code'],
        $record['block_name'],
        $record['name'],
        $record['mobile'],
        $record['pran_no'],
        $record['uan_no'],
        $record['class'],
        $record['total_attendance_days'],
        $record['unauthorized_absence_days'],
        $record['leave_days'],
        $record['remarks']
    ]);
}

// आउटपुट स्ट्रीम बंद करें
fclose($output);
exit;
?>