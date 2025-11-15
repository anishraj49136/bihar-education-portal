<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

// विद्यालय और जिला/प्रखंड की जानकारी प्राप्त करें
 $school_id = $_SESSION['school_id'];
 $stmt = $conn->prepare("SELECT s.*, d.name as district_name, b.name as block_name 
                         FROM schools s 
                         JOIN districts d ON s.district_id = d.id 
                         JOIN blocks b ON s.block_id = b.id 
                         WHERE s.id = ?");
 $stmt->execute([$school_id]);
 $school_info = $stmt->fetch(PDO::FETCH_ASSOC);

// वर्तमान महीना और वर्ष
 $current_month = date('F');
 $current_year = date('Y');

// जांचें कि वर्तमान महीना लॉक है या नहीं
 $stmt = $conn->prepare("SELECT is_locked FROM attendance 
                         WHERE teacher_id IN (SELECT id FROM teachers WHERE school_id = ?) 
                         AND month = ? AND year = ? AND is_locked = 1 LIMIT 1");
 $stmt->execute([$school_id, $current_month, $current_year]);
 $is_month_locked = $stmt->fetchColumn() ? true : false;

// फॉर्म सबमिशन प्रोसेस करें (सेविंग अटेंडेंस)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance' && !$is_month_locked) {
    try {
        // JSON डेटा को डिकोड करें
        $attendance_data = json_decode($_POST['attendance_data'], true);
        
        foreach ($attendance_data as $teacher_id => $data) {
            // ई-शिक्षकोष से In/Out टाइम काउंट प्राप्त करें
            $stmt = $conn->prepare("SELECT t.eshikshakosh_id, 
                                   (SELECT COUNT(*) FROM eshikshakosh_data WHERE teacher_code = t.eshikshakosh_id AND month = ? AND in_time IS NOT NULL) as in_time_count,
                                   (SELECT COUNT(*) FROM eshikshakosh_data WHERE teacher_code = t.eshikshakosh_id AND month = ? AND out_time IS NOT NULL) as out_time_count
                                   FROM teachers t WHERE t.id = ?");
            $stmt->execute([$current_month, $current_month, $teacher_id]);
            $e_shiksha_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // अपडेट या इन्सर्ट क्वेरी
            $stmt = $conn->prepare("INSERT INTO attendance (teacher_id, month, year, total_attendance_days, in_time_count, out_time_count, unauthorized_absence_days, leave_days, remarks) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE 
                                   total_attendance_days = VALUES(total_attendance_days),
                                   in_time_count = VALUES(in_time_count),
                                   out_time_count = VALUES(out_time_count),
                                   unauthorized_absence_days = VALUES(unauthorized_absence_days),
                                   leave_days = VALUES(leave_days),
                                   remarks = VALUES(remarks)");
            $stmt->execute([
                $teacher_id,
                $current_month,
                $current_year,
                $data['total_attendance_days'],
                $e_shiksha_data['in_time_count'],
                $e_shiksha_data['out_time_count'],
                $data['unauthorized_absence_days'],
                $data['leave_days'],
                $data['remarks']
            ]);
        }
        echo json_encode(['status' => 'success', 'message' => 'उपस्थिति विवरण सफलतापूर्वक सहेजा गया!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'त्रुटि: ' . $e->getMessage()]);
    }
    exit;
}

// महीना लॉक करने की प्रक्रिया - अपडेटेड (FPDF के बिना)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_month' && !$is_month_locked) {
    
    // किसी भी अप्रत्याशित आउटपुट को साफ़ करें
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    error_reporting(0); // PHP Warnings को JSON में आने से रोकें

    try {
        // पहले महीना लॉक करें
        $stmt = $conn->prepare("UPDATE attendance SET is_locked = 1 
                               WHERE teacher_id IN (SELECT id FROM teachers WHERE school_id = ?) 
                               AND month = ? AND year = ?");
        $stmt->execute([$school_id, $current_month, $current_year]);
        
        // उन शिक्षकों के लिए भी रिकॉर्ड बनाएं जिनके लिए अभी तक नहीं बनाया गया है
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach($teachers as $teacher_id) {
            $stmt = $conn->prepare("INSERT IGNORE INTO attendance (teacher_id, month, year, is_locked) VALUES (?, ?, ?, 1)");
            $stmt->execute([$teacher_id, $current_month, $current_year]);
        }
        
        // स्कूल की जानकारी प्राप्त करें
        $school_udise = $school_info['udise_code'];
        $month_for_db = date('Y-m'); // Format: YYYY-MM
        
        // मासिक लॉक रिकॉर्ड बनाएं
        $stmt = $conn->prepare("INSERT IGNORE INTO monthly_attendance_lock (school_udise, month, locked_by) VALUES (?, ?, ?)");
        $stmt->execute([$school_udise, $month_for_db, $_SESSION['user_id']]);
        
        // शिक्षकों को श्रेणी के अनुसार ग्रुप करें
        $teachers_sql = "SELECT t.*, pfr.category as category_name, pfr.class_group 
                        FROM teachers t 
                        JOIN pf_forwarding_rules pfr ON t.category_id = pfr.id 
                        WHERE t.school_id = ?";
        $stmt = $conn->prepare($teachers_sql);
        $stmt->execute([$school_id]);
        $teachers_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $teachers_by_category = [];
        
        foreach ($teachers_result as $teacher) {
            $teachers_by_category[$teacher['category_name']][] = $teacher;
        }
        
        // पीएफ जेनरेशन के नियम अब डेटाबेस से प्राप्त करें
        $stmt = $conn->prepare("SELECT * FROM pf_forwarding_rules");
        $stmt->execute();
        $pf_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // हम PDF नहीं बना रहे, बल्कि डेटा को एक ऐरे में इकट्ठा कर रहे हैं
        $generated_reports_html = [];
        $generated_count = 0;
        
        foreach ($pf_rules as $rule) {
            $category = $rule['category'];
            $class_group_name = $rule['class_group'];
            
            if (!isset($teachers_by_category[$category])) {
                continue; // अगर इस श्रेणी का कोई शिक्षक स्कूल में नहीं है
            }
            
            $teachers_for_pdf = [];
            foreach ($teachers_by_category[$category] as $teacher) {
                // कक्षा को मैच करने का लॉजिक
                $is_match = false;
                if ($class_group_name == 'All Classes') {
                    $is_match = true;
                } else {
                    // class_group को "&" के आधार पर विभाजित करें और जांचें
                    $class_groups = explode(' & ', $class_group_name);
                    foreach ($class_groups as $group) {
                        if (trim($teacher['class']) == trim($group)) {
                            $is_match = true;
                            break;
                        }
                    }
                }
                if ($is_match) {
                    $teachers_for_pdf[] = $teacher;
                }
            }
            
            if (empty($teachers_for_pdf)) {
                continue; // अगर इस नियम के लिए कोई शिक्षक नहीं मिला
            }
            
            // रेफरेंस नंबर जेनरेट करें
            $ref_no = 'PF/' . $school_udise . '/' . $month_for_db . '/' . strtoupper(uniqid());
            
            // बेहतर PDF जेनरेट करने के लिए HTML बनाएं
            $html_content = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>शिक्षक उपस्थिति विवरणी</title>
                <style>
                    @page {
                        size: A4;
                        margin: 1cm;
                    }
                    
                    body {
                        font-family: 'Arial', sans-serif;
                        font-size: 12px;
                        line-height: 1.4;
                        margin: 0;
                        padding: 0;
                    }
                    
                    .header {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    
                    .header h2 {
                        margin: 0;
                        font-size: 18px;
                        font-weight: bold;
                    }
                    
                    .info-section {
                        margin-bottom: 15px;
                    }
                    
                    .info-row {
                        display: flex;
                        margin-bottom: 5px;
                    }
                    
                    .info-label {
                        font-weight: bold;
                        width: 180px;
                    }
                    
                    .info-value {
                        flex: 1;
                    }
                    
                    .section-title {
                        text-align: center;
                        font-weight: bold;
                        margin: 15px 0 10px;
                        font-size: 14px;
                    }
                    
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 15px;
                    }
                    
                    th, td {
                        border: 1px solid #000;
                        padding: 5px;
                        text-align: left;
                        vertical-align: top;
                    }
                    
                    th {
                        background-color: #f2f2f2;
                        font-weight: bold;
                        text-align: center;
                        font-size: 11px;
                    }
                    
                    td {
                        font-size: 11px;
                    }
                    
                    .text-center {
                        text-align: center;
                    }
                    
                    .declaration {
                        margin: 20px 0;
                        text-align: justify;
                    }
                    
                    .signature-section {
                        margin-top: 30px;
                    }
                    
                    .signature-row {
                        display: flex;
                        margin-bottom: 10px;
                    }
                    
                    .signature-left {
                        width: 50%;
                        text-align: center;
                    }
                    
                    .signature-right {
                        width: 50%;
                        text-align: center;
                    }
                    
                    /* पेज ब्रेक के लिए */
                    .page-break {
                        page-break-after: always;
                    }
                    
                    /* हर पेज पर टेबल हेडर दोहराने के लिए */
                    thead {
                        display: table-header-group;
                    }
                    
                    tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    
                    tr:hover {
                        background-color: #f1f1f1;
                    }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h2>शिक्षक उपस्थिति विवरणी (वैशाली)</h2>
                </div>
                
                <div class='info-section'>
                    <div class='info-row'>
                        <div class='info-label'>विद्यालय का नाम:</div>
                        <div class='info-value'>{$school_info['name']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>UDISE कोड:</div>
                        <div class='info-value'>{$school_info['udise_code']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>प्रखंड:</div>
                        <div class='info-value'>{$school_info['block_name']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>महीना:</div>
                        <div class='info-value'>" . date('F Y', strtotime($month_for_db . '-01')) . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>पीएफ जनरेट करने की तिथि:</div>
                        <div class='info-value'>" . date('d-m-Y') . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>रेफरेंस नंबर:</div>
                        <div class='info-value'>{$ref_no}</div>
                    </div>
                </div>
                
                <div class='section-title'>
                    श्रेणी: {$category} | कक्षा समूह: {$class_group_name}
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th width='5%'>S.No.</th>
                            <th width='20%'>शिक्षक का नाम</th>
                            <th width='12%'>PRAN/UAN</th>
                            <th width='8%'>कुल भुगतान हेतु उपस्थित दिवस</th>
                            <th width='8%'>कुल Eshikshakosh आगमन दिवस</th>
                            <th width='8%'>कुल Eshikshakosh प्रस्थान दिवस</th>
                            <th width='8%'>अनधिकृत अनुपस्थिति दिवस</th>
                            <th width='8%'>वर्तमान माह में ली गई सभी प्रकार की अवकाश की संख्या</th>
                            <th width='12%'>अभियुक्ति</th>
                            <th width='5%'>कक्षा</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            $serial_no = 1;
            foreach ($teachers_for_pdf as $teacher) {
                // शिक्षक की उपस्थिति डेटा प्राप्त करें
                $stmt = $conn->prepare("SELECT total_attendance_days, in_time_count, out_time_count, unauthorized_absence_days, leave_days, remarks 
                                       FROM attendance 
                                       WHERE teacher_id = ? AND month = ? AND year = ?");
                $stmt->execute([$teacher['id'], $current_month, $current_year]);
                $attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // हर 15 शिक्षकों के बाद नया पेज शुरू करें
                if ($serial_no > 1 && ($serial_no - 1) % 15 == 0) {
                    $html_content .= "
                    </tbody>
                </table>
                <div class='page-break'></div>
                <div class='section-title'>
                    श्रेणी: {$category} | कक्षा समूह: {$class_group_name} (जारी)
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width='5%'>S.No.</th>
                            <th width='20%'>शिक्षक का नाम</th>
                            <th width='12%'>PRAN/UAN</th>
                            <th width='8%'>कुल भुगतान हेतु उपस्थित दिवस</th>
                            <th width='8%'>कुल Eshikshakosh आगमन दिवस</th>
                            <th width='8%'>कुल Eshikshakosh प्रस्थान दिवस</th>
                            <th width='8%'>अनधिकृत अनुपस्थिति दिवस</th>
                            <th width='8%'>वर्तमान माह में ली गई सभी प्रकार की अवकाश की संख्या</th>
                            <th width='12%'>अभियुक्ति</th>
                            <th width='5%'>कक्षा</th>
                        </tr>
                    </thead>
                    <tbody>";
                }
                
                $html_content .= "
                    <tr>
                        <td class='text-center'>{$serial_no}</td>
                        <td>{$teacher['name']}</td>
                        <td>" . ($teacher['pran_no'] ?: $teacher['uan_no']) . "</td>
                        <td class='text-center'>" . ($attendance_data['total_attendance_days'] ?? 0) . "</td>
                        <td class='text-center'>" . ($attendance_data['in_time_count'] ?? 0) . "</td>
                        <td class='text-center'>" . ($attendance_data['out_time_count'] ?? 0) . "</td>
                        <td class='text-center'>" . ($attendance_data['unauthorized_absence_days'] ?? 0) . "</td>
                        <td class='text-center'>" . ($attendance_data['leave_days'] ?? 0) . "</td>
                        <td>" . ($attendance_data['remarks'] ?? '') . "</td>
                        <td>{$teacher['class']}</td>
                    </tr>";
                $serial_no++;
            }
            
            $html_content .= "
                    </tbody>
                </table>
                
                <div class='declaration'>
                    <strong>घोषणा:</strong> उपरोक्त सभी शिक्षक मेरे विद्यालय में कार्यरत हैं | सभी के द्वारा विद्यालय में ससमय उपस्थित होकर अपने दायित्वों का निर्वहन  किया गया है | कोई भी अवैध निकासी नहीं की जा रही है | इसमें किसी प्रकार की लापरवाही होने पर मेरे विरुद्ध विभागीय एवं अनुशासनिक कार्रवाई करते हुए अवैध भुगतान की राशि मेरे से वसूल किया जा सकता है |
                </div>
                        <div class='declaration-left'>
                            <strong>ज्ञापांक: ____________ दिनांक: ___________</strong>
                        </div>
                    </div>
                    <div class='signature-section'>
                    <div class='signature-row'>
                        <div class='signature-left'>
                        </div>
                        <div class='signature-right'>
                            <strong>         प्रधानाध्यापक का हस्ताक्षर एवं मुहर</strong>
                        </div>
                        <div class='signature-right'>
                        </div>
                    </div>
                </div>
                
                <div class='declaration'>
                    <strong>प्रतिलिपि:</strong> जिला कार्यक्रम पदाधिकारी, स्थापना/चिन्हित मध्य विद्यालय के प्रधानाध्यापक, जिला-वैशाली को सूचनार्थ प्रेषित | अनुरोध है कि उपर्युक्त वर्णित शिक्षकों का भुगतान करने की कृपा करें |
                </div>
            </body>
            </html>";
            
            // डेटाबेस में एंट्री करें (generated_pdf_path में अब HTML सामग्री सेव करेंगे)
            $insert_sql = "INSERT INTO pf_submissions (school_udise, month, category, class_group, reference_number, generated_pdf_path) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([$school_udise, $month_for_db, $category, $class_group_name, $ref_no, $html_content]);
            
            // HTML को ऐरे में जोड़ें
            $generated_reports_html[] = [
                'ref_no' => $ref_no,
                'category' => $category,
                'class_group' => $class_group_name,
                'html' => $html_content
            ];
            $generated_count++;
        }
        
        // सफलता का JSON रिस्पॉन्स भेजें
        echo json_encode([
            'status' => 'success', 
            'message' => 'महीना सफलतापूर्वक लॉक कर दिया गया है और ' . $generated_count . ' पीएफ रिपोर्ट तैयार हैं!',
            'generated_reports_html' => $generated_reports_html
        ]);

    } catch (Exception $e) {
        // त्रुटि का JSON रिस्पॉन्स भेजें
        echo json_encode(['status' => 'error', 'message' => 'त्रुटि: ' . $e->getMessage()]);
    }
    exit;
}

// विद्यालय के शिक्षकों और उनकी उपस्थिति की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT t.*, a.total_attendance_days, a.in_time_count, a.out_time_count, a.unauthorized_absence_days, a.leave_days, a.remarks 
                         FROM teachers t 
                         LEFT JOIN attendance a ON t.id = a.teacher_id AND a.month = ? AND a.year = ?
                         WHERE t.school_id = ? ORDER BY t.name");
 $stmt->execute([$current_month, $current_year, $school_id]);
 $teachers_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>उपस्थिति विवरणी - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a1b9a;
            --secondary-color: #9c27b0;
            --accent-color: #ce93d8;
            --light-color: #f3e5f5;
            --dark-color: #4a148c;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .thead {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25);
        }
        
        .attendance-input {
            width: 100px;
        }
        
        .print-section {
            display: none;
            background-color: #e8f5e9;
            border: 1px dashed #4caf50;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .pf-section {
            display: none;
            background-color: #e3f2fd;
            border: 1px dashed #2196f3;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }

        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="alert-container" id="alertContainer"></div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <div class="p-4 text-center">
            <h4>बिहार शिक्षा विभाग</h4>
            <p class="mb-0">विद्यालय डैशबोर्ड</p>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="school_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="school_profile.php"><i class="fas fa-school"></i> विद्यालय प्रोफाइल</a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-user-graduate"></i> नामांकन</a></li>
            <li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण</a></li>
            <li class="nav-item"><a class="nav-link active" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">उपस्थिति विवरणी</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">विद्यालय</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <div class="alert alert-info">
            <strong>माह:</strong> <?php echo $current_month . ' ' . $current_year; ?> | 
            <strong>विद्यालय:</strong> <?php echo $school_info['name']; ?> | 
            <strong>UDISE कोड:</strong> <?php echo $school_info['udise_code']; ?>
            <?php if ($is_month_locked): ?>
            <span class="badge bg-danger ms-2">महीना लॉक कर दिया गया है</span>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">शिक्षक उपस्थिति विवरणी</h5>
                <div>
                    <button type="button" class="btn btn-light" id="saveAttendanceBtn" <?php echo $is_month_locked ? 'disabled' : ''; ?>>
                        <i class="fas fa-save"></i> सहेजें
                    </button>
                    <button type="button" class="btn btn-warning text-white" id="lockMonthBtn" <?php echo $is_month_locked ? 'disabled' : ''; ?>>
                        <i class="fas fa-lock"></i> महीना लॉक करें और पीएफ जेनरेट करें
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="attendanceForm">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead">
                                <tr>
                                    <th>क्र. सं.</th>
                                    <th>शिक्षक का नाम</th>
                                    <th>PRAN/UAN नंबर</th>
                                    <th>मोबाइल नंबर</th>
                                    <th>कुल भुगतान हेतु उपस्थित दिवस</th>
                                    <th>In Time Count</th>
                                    <th>Out Time Count</th>
                                    <th>अनधिकृत अनुपस्थिति</th>
                                    <th>अवकाश की संख्या</th>
                                    <th>अभियुक्ति</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($teachers_attendance) > 0): ?>
                                    <?php foreach ($teachers_attendance as $index => $teacher): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo $teacher['name']; ?></td>
                                        <td><?php echo $teacher['pran_no'] ?: $teacher['uan_no']; ?></td>
                                        <td><?php echo $teacher['mobile']; ?></td>
                                        <td>
                                            <input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $teacher['id']; ?>][total_attendance_days]" 
                                                   value="<?php echo $teacher['total_attendance_days'] ?? 0; ?>" min="0" <?php echo $is_month_locked ? 'disabled' : ''; ?>>
                                        </td>
                                        <td><?php echo $teacher['in_time_count'] ?? 0; ?></td>
                                        <td><?php echo $teacher['out_time_count'] ?? 0; ?></td>
                                        <td>
                                            <input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $teacher['id']; ?>][unauthorized_absence_days]" 
                                                   value="<?php echo $teacher['unauthorized_absence_days'] ?? 0; ?>" min="0" <?php echo $is_month_locked ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $teacher['id']; ?>][leave_days]" 
                                                   value="<?php echo $teacher['leave_days'] ?? 0; ?>" min="0" <?php echo $is_month_locked ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <textarea class="form-control" name="attendance_data[<?php echo $teacher['id']; ?>][remarks]" rows="1" <?php echo $is_month_locked ? 'disabled' : ''; ?>><?php echo $teacher['remarks'] ?? ''; ?></textarea>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center">कोई शिक्षक नहीं मिला। कृपया पहले शिक्षक जोड़ें।</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>

        <div class="pf-section" id="pfSection">
            <h5>पीएफ प्रबंधन</h5>
            <p class="text-muted">महीना लॉक हो गया है। अब आप पीएफ डाउनलोड और अपलोड कर सकते हैं।</p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="pfTable">
                    <thead>
                        <tr>
                            <th>क्र. सं.</th>
                            <th>श्रेणी</th>
                            <th>कक्षा समूह</th>
                            <th>रेफरेंस नंबर</th>
                            <th>जेनरेट किया गया पीएफ</th>
                            <th>हस्ताक्षरित पीएफ अपलोड करें</th>
                            <th>स्थिति</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="pf_management.php" class="btn btn-primary">
                    <i class="fas fa-cogs"></i> पीएफ प्रबंधन पृष्ठ पर जाएं
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        let isSaving = false;
        
        document.getElementById('saveAttendanceBtn').addEventListener('click', function(event) {
            event.preventDefault();
            
            if (isSaving) {
                showAlert('कृपया प्रतीक्षा करें, डेटा सहेजा जा रहा है।', 'info');
                return;
            }
            
            isSaving = true;
            const originalButtonText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> सहेज रहे हैं...';

            const form = document.getElementById('attendanceForm');
            const formData = new FormData(form);
            const attendanceData = {};
            
            for (let [key, value] of formData.entries()) {
                const match = key.match(/attendance_data\[(\d+)\]\[(.+)\]/);
                if (match) {
                    const teacherId = match[1];
                    const field = match[2];
                    if (!attendanceData[teacherId]) attendanceData[teacherId] = {};
                    attendanceData[teacherId][field] = value;
                }
            }

            fetch('attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_attendance',
                    attendance_data: JSON.stringify(attendanceData)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                } else {
                    showAlert('त्रुटि: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('त्रुटि: ' + error.message, 'danger');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalButtonText;
                isSaving = false;
            });
        });
        
        document.getElementById('lockMonthBtn').addEventListener('click', function() {
            if (confirm('क्या आप वाकई इस महीने की उपस्थिति को लॉक करना चाहते हैं?\n\nचेतावनी: इसके बाद कोई भी बदलाव संभव नहीं होगा और सभी प्रकार के पीएफ स्वचालित रूप से जेनरेट हो जाएंगे।')) {
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> प्रक्रिया में...';
                
                fetch('attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=lock_month'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showAlert(data.message, 'success');
                        
                        // पीएफ सेक्शन दिखाएं और टेबल को अपडेट करें
                        let pfContainer = document.getElementById('pfSection');
                        pfContainer.style.display = 'block';
                        const pfTableBody = document.querySelector('#pfTable tbody');
                        pfTableBody.innerHTML = '';

                        data.generated_reports_html.forEach((pf, index) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${index + 1}</td>
                                <td>${pf.category}</td>
                                <td>${pf.class_group}</td>
                                <td>${pf.ref_no}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="printReport('${pf.ref_no}')">
                                        <i class="fas fa-print"></i> प्रिंट करें (PDF)
                                    </button>
                                </td>
                                <td>
                                    <form action="upload_signed_pf.php" method="post" enctype="multipart/form-data" style="display:inline;">
                                        <input type="hidden" name="pf_id" value="${pf.ref_no}">
                                        <input type="file" name="signed_pdf" class="form-control form-control-sm mb-2" accept=".pdf" required>
                                        <button type="submit" class="btn btn-sm btn-success">अपलोड करें</button>
                                    </form>
                                </td>
                                <td><span class="badge bg-secondary">जेनरेट किया गया</span></td>
                            `;
                            pfTableBody.appendChild(row);

                            // प्रिंट करने के लिए छिपा हुआ डिव बनाएं
                            const printDiv = document.createElement('div');
                            printDiv.id = `printable_${pf.ref_no}`;
                            printDiv.className = 'printable-area';
                            printDiv.style.display = 'none';
                            printDiv.innerHTML = pf.html;
                            document.body.appendChild(printDiv);
                        });

                        // बटन को छिपा दें
                        this.style.display = 'none';

                    } else {
                        showAlert('त्रुटि: ' + data.message, 'danger');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-lock"></i> महीना लॉक करें और पीएफ जेनरेट करें';
                    }
                })
                .catch(error => {
                    showAlert('त्रुटि: ' + error.message, 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-lock"></i> महीना लॉक करें और पीएफ जेनरेट करें';
                });
            }
        });

        function printReport(refId) {
            // सभी प्रिंटेबल एरिया को छिपाएं
            document.querySelectorAll('.printable-area').forEach(area => {
                area.style.display = 'none';
            });
            
            // केवल वर्तमान रिपोर्ट को दिखाएं
            const reportToPrint = document.getElementById(`printable_${refId}`);
            if (reportToPrint) {
                reportToPrint.style.display = 'block';
                window.print();
                // प्रिंट के बाद फिर से छिपा दें (वैकल्पिक, लेकिन अच्छा अभ्यास है)
                reportToPrint.style.display = 'none';
            }
        }
        
        function getStatusText(status) {
            const statusMap = {
                'Generated': 'जेनरेट किया गया',
                'Uploaded': 'अपलोड हो गया',
                'Pending at DDO': 'DDO को भेजा गया',
                'Pending at Block Officer': 'प्रखंड शिक्षा पदाधिकारी को भेजा गया',
                'Pending at District': 'जिला कार्यालय को भेजा गया',
                'Paid': 'भुगतान किया गया'
            };
            return statusMap[status] || status;
        }

        // यह ब्लॉक केवल तभी चलेगा जब पेज लोड होने पर महीना पहले से लॉक होगा
        <?php if ($is_month_locked): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const pfSection = document.getElementById('pfSection');
                pfSection.style.display = 'block';
                
                fetch('get_pf_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'school_id=<?php echo $school_id; ?>&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const pfTableBody = document.querySelector('#pfTable tbody');
                        pfTableBody.innerHTML = '';
                        
                        if (data.pf_list.length === 0) {
                            pfTableBody.innerHTML = '<tr><td colspan="7" class="text-center">इस महीने के लिए कोई पीएफ डेटा नहीं मिला।</td></tr>';
                        } else {
                            data.pf_list.forEach((pf, index) => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${index + 1}</td>
                                    <td>${pf.category}</td>
                                    <td>${pf.class_group}</td>
                                    <td>${pf.reference_number}</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="printReport('${pf.reference_number}')">
                                            <i class="fas fa-print"></i> प्रिंट करें
                                        </button>
                                    </td>
                                    <td>
                                        ${pf.uploaded_pdf_path ? 
                                            `<a href="${pf.uploaded_pdf_path}" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye"></i> देखें
                                            </a>` :
                                            `<form action="upload_signed_pf.php" method="post" enctype="multipart/form-data" style="display:inline;">
                                                <input type="hidden" name="pf_id" value="${pf.id}">
                                                <input type="file" name="signed_pdf" class="form-control form-control-sm mb-2" accept=".pdf" required>
                                                <button type="submit" class="btn btn-sm btn-success">अपलोड करें</button>
                                            </form>`
                                        }
                                    </td>
                                    <td>
                                        <span class="badge bg-${pf.status === 'Paid' ? 'success' : 'secondary'}">
                                            ${getStatusText(pf.status)}
                                        </span>
                                    </td>
                                `;
                                pfTableBody.appendChild(row);
                                
                                // प्रिंट करने के लिए छिपा हुआ डिव बनाएं और उसमें DB से HTML डालें
                                const printDiv = document.createElement('div');
                                printDiv.id = `printable_${pf.reference_number}`;
                                printDiv.className = 'printable-area';
                                printDiv.style.display = 'none';
                                printDiv.innerHTML = pf.generated_pdf_path; // DB से HTML सामग्री
                                document.body.appendChild(printDiv);
                            });
                        }
                    } else {
                        showAlert('पीएफ डेटा लोड करने में त्रुटि: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showAlert('त्रुटि: ' + error.message, 'danger');
                });
            });
        <?php endif; ?>
    </script>
</body>
</html>