<?php
require_once 'config.php';

// session_start() की चेतावनी से बचने के लिए
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// सत्र की जांच
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'block_officer') {
    die("अनधिकृत पहुंच।");
}

// GET पैरामीटर प्राप्त करें
 $block_id = $_GET['block_id'] ?? null;
 $month_name = $_GET['month'] ?? null;
 $year = $_GET['year'] ?? null;

if (!$block_id || !$month_name || !$year) {
    die("आवश्यक पैरामीटर गुम हैं।");
}

// महीने के नाम को संख्या में बदलें ("November" -> "11")
 $numeric_month = date('m', strtotime($month_name));
 $year_month = $year . '-' . $numeric_month; // परिणाम होगा: '2025-11'

try {
    // चरण 1: pf_submissions टेबल से सभी फॉरवर्डेड रिकॉर्ड्स के लिए school_udise निकालें
    $forwarded_schools_sql = "SELECT DISTINCT pf.school_udise, pf.category, pf.class_group
                              FROM pf_submissions pf
                              JOIN schools s ON pf.school_udise = s.udise_code
                              WHERE s.block_id = ? AND pf.status = 'forwarded_to_district' AND pf.month = ?";
    
    $stmt_forwarded = $conn->prepare($forwarded_schools_sql);
    $stmt_forwarded->execute([$block_id, $year_month]);
    $forwarded_records = $stmt_forwarded->fetchAll(PDO::FETCH_ASSOC);

    if (empty($forwarded_records)) {
        die("इस महीने के लिए भुगतान हेतु कोई शिक्षक नहीं मिला।");
    }

    // चरण 2: इन रिकॉर्ड्स से शिक्षकों को फ़िल्टर करने के लिए एक सहायक फ़ंक्शन
    function doesTeacherMatch($teacher, $forwarded_records) {
        foreach ($forwarded_records as $record) {
            // स्कूल, श्रेणी और कक्षा का मिलान करें
            if ($teacher['udise_code'] === $record['school_udise'] && $teacher['category'] === $record['category']) {
                
                $teacher_class = trim($teacher['class']); // शिक्षक की कक्षा, जैसे '1-5'
                $class_group = trim($record['class_group']); // सबमिशन का कक्षा समूह, जैसे '1-5 & 6-8'

                // --- नया और सुधारा हुआ तर्क ---
                // 'All Classes' के लिए सीधे मिलान
                if ($class_group === 'All Classes') {
                    return true;
                }
                
                // जांचें कि क्या शिक्षक की कक्षा ('1-5') सबमिशन के कक्षा समूह ('1-5 & 6-8') में मौजूद है
                if (strpos($class_group, $teacher_class) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    // चरण 3: उन सभी शिक्षकों को ढूंढें जो उन स्कूलों से संबंधित हैं जिनका PF फॉरवर्ड किया गया है
    $school_udises = array_unique(array_column($forwarded_records, 'school_udise'));
    
    if (empty($school_udises)) {
        die("कोई स्कूल नहीं मिला।");
    }

    $placeholders = implode(',', array_fill(0, count($school_udises), '?'));
    $teachers_sql = "SELECT t.name, t.mobile, t.pran_no, t.uan_no, t.class, t.category,
                       s.name as school_name, s.udise_code
                       FROM teachers t
                       JOIN schools s ON t.school_id = s.id
                       WHERE s.udise_code IN ($placeholders)";
    
    $stmt_teachers = $conn->prepare($teachers_sql);
    $stmt_teachers->execute($school_udises);
    $all_potential_teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

    // चरण 4: इन शिक्षकों को pf_submissions के डेटा के खिलाफ फ़िल्टर करें
    $final_teachers = [];
    foreach ($all_potential_teachers as $teacher) {
        if (doesTeacherMatch($teacher, $forwarded_records)) {
            $final_teachers[] = $teacher;
        }
    }

    if (count($final_teachers) === 0) {
        die("कोई मेल खाने वाला शिक्षक डेटा नहीं मिला।");
    }

    // CSV फाइल बनाना
    $filename = "payment_report_" . $block_id . "_" . $month_name . "_" . $year . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV हेडर लिखें
    fputcsv($output, ['शिक्षक का नाम', 'मोबाइल नंबर', 'PRAN नंबर', 'UAN नंबर', 'श्रेणी', 'कक्षा', 'विद्यालय का नाम', 'UDISE कोड']);

    // डेटा लिखें
    foreach ($final_teachers as $teacher) {
        fputcsv($output, [
            $teacher['name'],
            $teacher['mobile'],
            $teacher['pran_no'],
            $teacher['uan_no'],
            $teacher['category'],
            $teacher['class'],
            $teacher['school_name'],
            $teacher['udise_code']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("डेटाबेस त्रुटि: " . $e->getMessage());
} catch (Exception $e) {
    die("एक त्रुटि हुई: " . $e->getMessage());
}
?>