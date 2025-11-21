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
            $stmt->execute([$current_month, $current_year, $teacher_id]);
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

// महीना लॉक करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_month' && !$is_month_locked) {
    
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    error_reporting(0);

    try {
        // पहले महीना लॉक करें
        $stmt = $conn->prepare("UPDATE attendance SET is_locked = 1 
                               WHERE teacher_id IN (SELECT id FROM teachers WHERE school_id = ?) 
                               AND month = ? AND year = ?");
        $stmt->execute([$school_id, $current_month, $current_year]);
        
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach($teachers as $teacher_id) {
            $stmt = $conn->prepare("INSERT IGNORE INTO attendance (teacher_id, month, year, is_locked) VALUES (?, ?, ?, 1)");
            $stmt->execute([$teacher_id, $current_month, $current_year]);
        }
        
        $school_udise = $school_info['udise_code'];
        $month_for_db = date('Y-m');
        
        $stmt = $conn->prepare("INSERT IGNORE INTO monthly_attendance_lock (school_udise, month, locked_by) VALUES (?, ?, ?)");
        $stmt->execute([$school_udise, $month_for_db, $_SESSION['user_id']]);
        
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
        
        $stmt = $conn->prepare("SELECT * FROM pf_forwarding_rules");
        $stmt->execute();
        $pf_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $generated_reports_html = [];
        $generated_count = 0;
        
        foreach ($pf_rules as $rule) {
            $category = $rule['category'];
            $class_group_name = $rule['class_group'];
            
            if (!isset($teachers_by_category[$category])) {
                continue;
            }
            
            $teachers_for_pdf = [];
            foreach ($teachers_by_category[$category] as $teacher) {
                $is_match = false;
                if ($class_group_name == 'All Classes') {
                    $is_match = true;
                } else {
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
                continue;
            }
            
            $ref_no = 'PF/' . $school_udise . '/' . $month_for_db . '/' . strtoupper(uniqid());
            
            // PDF के लिए HTML बनाएं (इसे Client-Side पर कनवर्ट किया जाएगा)
            $html_content = "
            <div id='reportContent' style='font-family: Arial, sans-serif; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 18px; font-weight: bold;'>शिक्षक उपस्थिति विवरणी</h2>
                </div>
                
                <div style='margin-bottom: 15px; font-size: 12px;'>
                    <div style='display: flex; margin-bottom: 5px;'><strong>विद्यालय का नाम:</strong> <span>{$school_info['name']}</span></div>
                    <div style='display: flex; margin-bottom: 5px;'><strong>UDISE कोड:</strong> <span>{$school_info['udise_code']}</span></div>
                    <div style='display: flex; margin-bottom: 5px;'><strong>प्रखंड:</strong> <span>{$school_info['block_name']}</span></div>
                    <div style='display: flex; margin-bottom: 5px;'><strong>महीना:</strong> <span>" . date('F Y', strtotime($month_for_db . '-01')) . "</span></div>
                    <div style='display: flex; margin-bottom: 5px;'><strong>रेफरेंस नंबर:</strong> <span>{$ref_no}</span></div>
                </div>
                
                <div style='text-align: center; font-weight: bold; margin: 15px 0 10px; font-size: 14px;'>
                    श्रेणी: {$category} | कक्षा समूह: {$class_group_name}
                </div>
                
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px;'>
                    <thead>
                        <tr style='background-color: #f2f2f2;'>
                            <th style='border: 1px solid #000; padding: 5px; text-align: center;'>S.No.</th>
                            <th style='border: 1px solid #000; padding: 5px;'>शिक्षक का नाम</th>
                            <th style='border: 1px solid #000; padding: 5px;'>PRAN/UAN</th>
                            <th style='border: 1px solid #000; padding: 5px; text-align: center;'>भुगतान हेतु उपस्थिति दिवस</th>
                            <th style='border: 1px solid #000; padding: 5px; text-align: center;'>अनधिकृत अनुपस्थिति</th>
                            <th style='border: 1px solid #000; padding: 5px; text-align: center;'>अवकाश</th>
                            <th style='border: 1px solid #000; padding: 5px;'>अभियुक्ति</th>
                            <th style='border: 1px solid #000; padding: 5px; text-align: center;'>कक्षा</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            $serial_no = 1;
            foreach ($teachers_for_pdf as $teacher) {
                $stmt = $conn->prepare("SELECT total_attendance_days, in_time_count, out_time_count, unauthorized_absence_days, leave_days, remarks 
                                       FROM attendance 
                                       WHERE teacher_id = ? AND month = ? AND year = ?");
                $stmt->execute([$teacher['id'], $current_month, $current_year]);
                $attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $html_content .= "
                    <tr>
                        <td style='border: 1px solid #000; padding: 5px; text-align: center;'>{$serial_no}</td>
                        <td style='border: 1px solid #000; padding: 5px;'>{$teacher['name']}</td>
                        <td style='border: 1px solid #000; padding: 5px;'>" . ($teacher['pran_no'] ?: $teacher['uan_no']) . "</td>
                        <td style='border: 1px solid #000; padding: 5px; text-align: center;'>" . ($attendance_data['total_attendance_days'] ?? 0) . "</td>
                        <td style='border: 1px solid #000; padding: 5px; text-align: center;'>" . ($attendance_data['unauthorized_absence_days'] ?? 0) . "</td>
                        <td style='border: 1px solid #000; padding: 5px; text-align: center;'>" . ($attendance_data['leave_days'] ?? 0) . "</td>
                        <td style='border: 1px solid #000; padding: 5px;'>" . ($attendance_data['remarks'] ?? '') . "</td>
                        <td style='border: 1px solid #000; padding: 5px; text-align: center;'>{$teacher['class']}</td>
                    </tr>";
                $serial_no++;
            }
            
            $html_content .= "
                    </tbody>
                </table>
                
                <div style='margin: 20px 0; text-align: justify; font-size: 11px;'>
                    <strong>घोषणा:</strong> उपरोक्त सभी शिक्षक मेरे विद्यालय में कार्यरत हैं | सभी के द्वारा विद्यालय में ससमय उपस्थित होकर अपने दायित्वों का निर्वहन किया गया है | कोई भी अवैध निकासी नहीं की जा रही है |
                </div>
				<div class='signature-left'>
                            <strong>ज्ञापांक: ____________ दिनांक: ___________</strong>
                        </div>
                <div style='margin-top: 30px; text-align: center; font-size: 12px;'>
                    <strong>प्रधानाध्यापक का हस्ताक्षर एवं मुहर</strong>
                </div>
				<div style='margin: 20px 0; text-align: justify; font-size: 11px;'>
                    <strong>प्रतिलिपि:</strong> जिला कार्यक्रम पदाधिकारी, स्थापना / सम्बंधित चिन्हित मध्य विद्यालय के प्रधानाध्यापक, जिला-वैशाली को सूचनार्थ प्रेषित | अनुरोध है कि उपर्युक्त वर्णित शिक्षकों का भुगतान करने की कृपा करें |
                </div>
            </div>";
            
            $insert_sql = "INSERT INTO pf_submissions (school_udise, month, category, class_group, reference_number, generated_pdf_path) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->execute([$school_udise, $month_for_db, $category, $class_group_name, $ref_no, $html_content]);
            
            $generated_reports_html[] = [
                'ref_no' => $ref_no,
                'category' => $category,
                'class_group' => $class_group_name,
                'html' => $html_content
            ];
            $generated_count++;
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'महीना सफलतापूर्वक लॉक कर दिया गया है और ' . $generated_count . ' पीएफ रिपोर्ट तैयार हैं!',
            'generated_reports_html' => $generated_reports_html
        ]);

    } catch (Exception $e) {
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
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(106, 27, 154, 0.25);
        }
        
        .attendance-input {
            width: 100px;
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

        /* मोबाइल रेस्पॉन्सिव स्टाइल */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
            .navbar { margin-top: 70px; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 10px; }
            .card-body { padding: 15px; }
            .card-header { padding: 12px 15px; font-size: 1rem; }
            .btn-primary { padding: 8px 20px; font-size: 0.9rem; }
            .navbar h4 { font-size: 1.2rem; }
            .user-avatar { width: 35px; height: 35px; font-size: 0.9rem; }
            .form-label { font-size: 0.9rem; }
            .table-responsive { font-size: 0.85rem; }
            .attendance-input { width: 80px; }
        }
        
        @media (max-width: 576px) {
            .card-header { font-size: 0.9rem; }
            .form-label { font-size: 0.85rem; }
            .form-control, .form-select { font-size: 0.9rem; }
            .btn-primary { padding: 6px 15px; font-size: 0.85rem; }
            .table-responsive { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="alert-container" id="alertContainer"></div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार टेम्पलेट शामिल करें -->
    <?php require_once 'sidebar_template.php'; ?>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light">
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
                    <i class="fas fa-cogs"></i> पीडीएफ प्रबंधन पृष्ठ पर जाएं
                </a>
            </div>
        </div>
    </div>

    <!-- Client-Side PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
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
                                    <button class="btn btn-sm btn-success" onclick="downloadReport('${pf.ref_no}')">
                                        <i class="fas fa-download"></i> डाउनलोड करें (PDF)
                                    </button>
                                </td>
                                <td>
                                    <form action="upload_signed_pf.php" method="post" enctype="multipart/form-data" style="display:inline;">
                                        <input type="hidden" name="pf_id" value="${pf.ref_no}">
                                        <input type="file" name="signed_pdf" class="form-control form-control-sm mb-2" accept=".pdf" required>
                                        <button type="submit" class="btn btn-sm btn-primary">अपलोड करें</button>
                                    </form>
                                </td>
                                <td><span class="badge bg-secondary">जेनरेट किया गया</span></td>
                            `;
                            pfTableBody.appendChild(row);

                            // डाउनलोड करने के लिए छिपा हुआ डिव बनाएं
                            const printDiv = document.createElement('div');
                            printDiv.id = `printable_${pf.ref_no}`;
                            printDiv.className = 'printable-area';
                            printDiv.style.display = 'none';
                            printDiv.style.position = 'absolute';
                            printDiv.style.left = '-9999px'; // स्क्रीन से बाहर रखें
                            printDiv.innerHTML = pf.html;
                            document.body.appendChild(printDiv);
                        });

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

        // नया डाउनलोड रिपोर्ट फंक्शन
        function downloadReport(refId) {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById(`printable_${refId}`);

            // एलिमेंट को अस्थायी रूप से दिखाएं ताकि इसे कैप्चर किया जा सके
            element.style.position = 'absolute';
            element.style.left = '0';
            element.style.top = '0';
            element.style.display = 'block';
            element.style.backgroundColor = 'white';

            showAlert('PDF तैयार हो रहा है, कृपया प्रतीक्षा करें...', 'info');

            html2canvas(element, {
                scale: 2,
                useCORS: true,
                logging: false,
                width: element.scrollWidth,
                height: element.scrollHeight
            }).then(canvas => {
                // एलिमेंट को फिर से छिपा दें
                element.style.display = 'none';
                element.style.position = 'absolute';
                element.style.left = '-9999px';

                const imgData = canvas.toDataURL('image/png');
                
                // A4 size in mm: 210 x 297
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });
                
                const imgWidth = 210; 
                const pageHeight = 295; // A4 height with some margin
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;

                let position = 0;

                // Add first page
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add remaining pages if content is longer than one page
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Save the PDF
                pdf.save(`PF_Report_${refId}.pdf`);
            });
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
                                        <button class="btn btn-sm btn-success" onclick="downloadReport('${pf.reference_number}')">
                                            <i class="fas fa-download"></i> डाउनलोड करें
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
                                                <button type="submit" class="btn btn-sm btn-primary">अपलोड करें</button>
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
                                
                                // डाउनलोड करने के लिए छिपा हुआ डिव बनाएं और उसमें DB से HTML डालें
                                const printDiv = document.createElement('div');
                                printDiv.id = `printable_${pf.reference_number}`;
                                printDiv.className = 'printable-area';
                                printDiv.style.display = 'none';
                                printDiv.style.position = 'absolute';
                                printDiv.style.left = '-9999px';
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