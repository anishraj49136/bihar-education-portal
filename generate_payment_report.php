<?php
require_once 'config.php';

// सत्र की जांच
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'block_officer') {
    die("अनधिकृत पहुंच।");
}

// GET पैरामीटर प्राप्त करें
 $block_id = $_GET['block_id'] ?? null;
 $month = $_GET['month'] ?? null;
 $year = $_GET['year'] ?? null;

if (!$block_id || !$month || !$year) {
    die("आवश्यक पैरामीटर गुम हैं।");
}

try {
    // उन श्रेणी-वर्ग संयोजनों को ढूंढें जिनका PF जिले को भेजा गया है
    $locked_combinations_sql = "SELECT DISTINCT category, class_group 
                               FROM pf_submissions pf
                               JOIN schools s ON pf.school_udise = s.udise_code
                               WHERE s.block_id = ? AND pf.status = 'forwarded_to_district'";
    
    $stmt_locked = $conn->prepare($locked_combinations_sql);
    $stmt_locked->execute([$block_id]);
    $locked_results = $stmt_locked->fetchAll(PDO::FETCH_ASSOC);

    $locked_combinations = [];
    foreach ($locked_results as $row) {
        $key = $row['category'] . '|' . $row['class_group']; 
        $locked_combinations[$key] = true;
    }

    if (empty($locked_combinations)) {
        die("इस महीने के लिए भुगतान हेतु कोई शिक्षक नहीं मिला।");
    }

    // अब उन शिक्षकों का डेटा निकालें जो लॉक किए गए समूह से मेल खाते हैं
    $placeholders = implode(',', array_fill(0, count($locked_combinations), '?'));
    $categories = [];
    $classes = [];
    foreach ($locked_combinations as $key => $value) {
        list($cat, $cls) = explode('|', $key);
        $categories[] = $cat;
        $classes[] = $cls;
    }

    $teachers_sql = "SELECT t.name, t.mobile, t.pran_no, t.uan_no, t.class, t.category,
                       s.name as school_name, s.udise_code
                       FROM teachers t
                       JOIN schools s ON t.school_id = s.id
                       WHERE s.block_id = ? AND (t.category IN ($placeholders) AND t.class IN ($placeholders))";
    
    // पैरामीटर को तैयार करें (category और class दोनों को दो बार भेजना होगा)
    $params = array_merge([$block_id], $categories, $classes);

    $stmt_teachers = $conn->prepare($teachers_sql);
    $stmt_teachers->execute($params);
    $teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

    if (count($teachers) === 0) {
        die("कोई मेल खाने वाला शिक्षक डेटा नहीं मिला।");
    }

    // CSV फाइल बनाना
    $filename = "payment_report_" . $block_id . "_" . $month . "_" . $year . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // आउटपुट स्ट्रीम खोलें
    $output = fopen('php://output', 'w');

    // CSV हेडर लिखें
    fputcsv($output, ['शिक्षक का नाम', 'मोबाइल नंबर', 'PRAN नंबर', 'UAN नंबर', 'श्रेणी', 'कक्षा', 'विद्यालय का नाम', 'UDISE कोड']);

    // डेटा लिखें
    foreach ($teachers as $teacher) {
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