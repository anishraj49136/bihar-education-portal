<?php
require_once 'config.php';

// session_start() की जाँच करने के लिए
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// सुरक्षा जाँच: यह सुनिश्चित करें कि उपयोगकर्ता लॉग इन है और या तो 'block_officer' या 'ddo' है
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['block_officer', 'ddo'])) {
    die("अनधिकृत प्रवेश।");
}

// GET पैरामीटर प्राप्त करें
 $block_id = $_GET['block_id'] ?? null;
 $month_name = $_GET['month'] ?? null;
 $year = $_GET['year'] ?? null;

if (!$block_id || !$month_name || !$year) {
    die("आवश्यक पैरामीटर गुम हैं।");
}

// वर्तमान उपयोगकर्ता प्रकार प्राप्त करें ('block_officer' या 'ddo')
 $user_type = $_SESSION['user_type'];

// महीने के नाम को संख्या में बदलें ("November" -> "11")
 $numeric_month = date('m', strtotime($month_name));
 $year_month = $year . '-' . $numeric_month; // प्रारूप होगा: '2024-11'

try {
    // --- मुख्य तर्क: pf_forwarding_rules के साथ JOIN करके रिपोर्ट तैयार करें ---
    // यह क्वेरी सुनिश्चित करती है कि केवल वही शिक्षक आएँगे जिनका PF फॉर्म
    // 1. इस ब्लॉक से है,
    // 2. इस महीने के लिए है,
    // 3. 'forwarded_to_district' स्थिति में भेजा गया है (block_officer द्वारा मंजूरी दी गई),
    // 4. और pf_forwarding_rules टेबल में इस शिक्षक की श्रेणी/वर्ग समूह के लिए forward_to कॉलम वर्तमान उपयोगकर्ता प्रकार (user_type) के बराबर है।
    $teachers_sql = "SELECT DISTINCT
                       t.name,
                       t.mobile,
                       t.pran_no,
                       t.uan_no,
                       t.class,
                       t.category,
                       s.name as school_name,
                       s.udise_code
                       FROM teachers t
                       JOIN schools s ON t.school_id = s.id
                       JOIN pf_submissions pf ON s.udise_code = pf.school_udise
                       JOIN pf_forwarding_rules pfr ON pf.category = pfr.category AND pf.class_group = pfr.class_group
                       WHERE
                           s.block_id = ?
                           AND pf.month LIKE ?
                           AND pf.status = 'forwarded_to_district'
                           AND pfr.forward_to = ?";
    
    // 'LIKE' का उपयोग करके हम '2024-11' जैसे महीने से मेल खाने वाले किसी भी दिनांक को पकड़ सकते हैं।
    $month_param = $year_month . '%';
    
    // पैरामीटर: block_id, month (with %), और वर्तमान उपयोगकर्ता प्रकार
    $params = [$block_id, $month_param, $user_type];
    
    $stmt_teachers = $conn->prepare($teachers_sql);
    $stmt_teachers->execute($params);
    $final_teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

    if (count($final_teachers) === 0) {
        die("इस महीने और आपके अधिकार क्षेत्र के लिए भुगतान हेतु कोई शिक्षक नहीं मिला।");
    }

    // CSV फाइल बनाना
    $filename = "payment_report_" . $user_type . "_" . $block_id . "_" . $month_name . "_" . $year . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // UTF-8 BOM जोड़ें ताकि एक्सेल में हिंदी अक्षर सही तरीके से दिखें
    echo "\xEF\xBB\xBF";

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