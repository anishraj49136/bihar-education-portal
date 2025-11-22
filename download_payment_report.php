<?php
// अनावश्यक आउटपुट को रोकने के लिए
ob_start();

require_once 'config.php';

// सत्र की जांच - 'school' यूज़र टाइप को छोड़कर सभी को अनुमति दें
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] === 'school') {
    die("अनधिकृत पहुंच। केवल स्कूल के अलावा अन्य उपयोगकर्ता इस पेज को एक्सेस कर सकते हैं।");
}

// ब्लॉक आईडी को सत्र से प्राप्त करें
 $block_id = $_SESSION['block_id'] ?? null;
if (!$block_id) {
    die("उपयोगकर्ता का ब्लॉक आईडी सत्र में नहीं मिला।");
}

// AJAX कॉल के लिए श्रेणियाँ प्राप्त करने का लॉजिक
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_categories') {
    $month_name = $_POST['month'] ?? null;
    $year = $_POST['year'] ?? null;
    if (!$month_name || !$year) {
        echo json_encode([]);
        exit;
    }
    try {
        $numeric_month = date('m', strtotime($month_name));
        $year_month = $year . '-' . $numeric_month;
        $sql = "SELECT DISTINCT category FROM pf_submissions pf
                JOIN schools s ON pf.school_udise = s.udise_code
                WHERE s.block_id = ? AND pf.status = 'forwarded_to_district' AND pf.month = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$block_id, $year_month]);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode($categories);
        exit;
    } catch (Exception $e) {
        echo json_encode([]);
        exit;
    }
}

// AJAX कॉल के लिए कक्षाएं प्राप्त करने का लॉजिक (सुधारा हुआ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_classes') {
    $month_name = $_POST['month'] ?? null;
    $year = $_POST['year'] ?? null;
    $selected_category = $_POST['category'] ?? null;

    if (!$month_name || !$year) {
        echo json_encode([]);
        exit;
    }
    try {
        $numeric_month = date('m', strtotime($month_name));
        $year_month = $year . '-' . $numeric_month;

        $sql = "SELECT DISTINCT class_group FROM pf_submissions pf
                JOIN schools s ON pf.school_udise = s.udise_code
                WHERE s.block_id = ? AND pf.status = 'forwarded_to_district' AND pf.month = ?";
        $params = [$block_id, $year_month];

        if (!empty($selected_category)) {
            $sql .= " AND pf.category = ?";
            $params[] = $selected_category;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $class_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $all_classes = [];
        foreach ($class_groups as $class_group) {
            if ($class_group === 'All Classes') {
                $all_classes[] = 'All Classes';
            } else {
                // मुख्य बदलाव: अब ' & ' सेपरेटर का उपयोग करके तोड़ा जाएगा
                $classes = explode(' & ', $class_group);
                foreach ($classes as $class) {
                    $class = trim($class);
                    if (!in_array($class, $all_classes) && !empty($class)) {
                        $all_classes[] = $class;
                    }
                }
            }
        }
        sort($all_classes);
        echo json_encode($all_classes);
        exit;
    } catch (Exception $e) {
        echo json_encode([]);
        exit;
    }
}

// रिपोर्ट जनरेशन का लॉजिक (सुधारा हुआ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $month_name = $_POST['month'] ?? null;
    $year = $_POST['year'] ?? null;
    $selected_category = $_POST['category'] ?? null;
    $selected_class = $_POST['class'] ?? null;

    if (!$month_name || !$year) {
        echo json_encode(['success' => false, 'message' => 'महीना और वर्ष चुनना अनिवार्य है।']);
        exit;
    }

    try {
        $numeric_month = date('m', strtotime($month_name));
        $year_month = $year . '-' . $numeric_month;

        $sql = "SELECT DISTINCT pf.school_udise, pf.category, pf.class_group
                FROM pf_submissions pf
                JOIN schools s ON pf.school_udise = s.udise_code
                WHERE s.block_id = ? AND pf.status = 'forwarded_to_district' AND pf.month = ?";
        $params = [$block_id, $year_month];

        if (!empty($selected_category)) {
            $sql .= " AND pf.category = ?";
            $params[] = $selected_category;
        }

        // मुख्य बदलाव: कक्षा फिल्टर के लिए अब LIKE का उपयोग किया जाएगा
        if (!empty($selected_class) && $selected_class !== 'All Classes') {
            $sql .= " AND (pf.class_group = 'All Classes' OR pf.class_group LIKE ?)";
            $params[] = '%' . $selected_class . '%';
        }
        
        $stmt_forwarded = $conn->prepare($sql);
        $stmt_forwarded->execute($params);
        $forwarded_records = $stmt_forwarded->fetchAll(PDO::FETCH_ASSOC);

        if (empty($forwarded_records)) {
            echo json_encode(['success' => false, 'message' => 'इस महीने के लिए भुगतान हेतु कोई शिक्षक नहीं मिला।']);
            exit;
        }

        // मुख्य बदलाव: शिक्षक को मैच करने का लॉजिक बिल्कुल बदल दिया गया है
        function doesTeacherMatch($teacher, $forwarded_records, $selected_class = null) {
            foreach ($forwarded_records as $record) {
                if ($teacher['udise_code'] === $record['school_udise'] && $teacher['category'] === $record['category']) {
                    $teacher_class = trim($teacher['class']);
                    $class_group_string = trim($record['class_group']);
                    
                    $class_groups_in_record = [];
                    if ($class_group_string === 'All Classes') {
                        $class_groups_in_record = ['All Classes'];
                    } else {
                        // ' & ' सेपरेटर का उपयोग करके क्लास ग्रुप को अलग करें
                        $class_groups_in_record = explode(' & ', $class_group_string);
                    }

                    // जांचें कि क्या शिक्षक की कक्षा, रिकॉर्ड में मौजूद कक्षाओं में से एक है
                    if (in_array($teacher_class, $class_groups_in_record)) {
                        // यदि फिल्टर के लिए एक विशिष्ट कक्षा चुनी गई है, तो सुनिश्चित करें कि यह शिक्षक की कक्षा से मेल खाती हो
                        if ($selected_class && $selected_class !== 'All Classes') {
                            if ($teacher_class === $selected_class) {
                                return true;
                            }
                        } else {
                            // कोई विशिष्ट कक्षा फ़िल्टर नहीं है, तो कोई भी मैच मान्य है
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        $school_udises = array_unique(array_column($forwarded_records, 'school_udise'));
        $placeholders = implode(',', array_fill(0, count($school_udises), '?'));
        $teachers_sql = "SELECT t.name, t.mobile, t.pran_no, t.uan_no, t.class, t.category,
                           s.name as school_name, s.udise_code
                           FROM teachers t
                           JOIN schools s ON t.school_id = s.id
                           WHERE s.udise_code IN ($placeholders)";
        
        $stmt_teachers = $conn->prepare($teachers_sql);
        $stmt_teachers->execute($school_udises);
        $all_potential_teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

        $final_teachers = [];
        foreach ($all_potential_teachers as $teacher) {
            if (doesTeacherMatch($teacher, $forwarded_records, $selected_class)) {
                $final_teachers[] = $teacher;
            }
        }

        if (count($final_teachers) === 0) {
            echo json_encode(['success' => false, 'message' => 'कोई मेल खाने वाला शिक्षक डेटा नहीं मिला।']);
            exit;
        }

        // CSV डेटा तैयार करें
        $filename = "payment_report_" . $block_id . "_" . $month_name . "_" . $year . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM जोड़ें ताकि Excel में हिंदी ठीक से दिखे
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['शिक्षक का नाम', 'मोबाइल नंबर', 'PRAN नंबर', 'UAN नंबर', 'श्रेणी', 'कक्षा', 'विद्यालय का नाम', 'UDISE कोड']);
        foreach ($final_teachers as $teacher) {
            fputcsv($output, [$teacher['name'], $teacher['mobile'], $teacher['pran_no'], $teacher['uan_no'], $teacher['category'], $teacher['class'], $teacher['school_name'], $teacher['udise_code']]);
        }
        fclose($output);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'डेटाबेस त्रुटि: ' . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'एक त्रुटि हुई: ' . $e->getMessage()]);
        exit;
    }
}
ob_end_flush(); // आउटपुट बफ़रिंग को समाप्त करें
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>भुगतान रिपोर्ट डाउनलोड - बिहार शिक्षा विभाग</title>
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
        }
        
        @media (max-width: 576px) {
            .card-header { font-size: 0.9rem; }
            .form-label { font-size: 0.85rem; }
            .form-control, .form-select { font-size: 0.9rem; }
            .btn-primary { padding: 6px 15px; font-size: 0.85rem; }
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
                <h4 class="mb-0">भुगतान रिपोर्ट डाउनलोड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst($_SESSION['user_type']); ?></small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- भुगतान रिपोर्ट डाउनलोड कार्ड -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-download me-2"></i>
                भुगतान रिपोर्ट डाउनलोड करें
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <input type="hidden" name="block_id" value="<?php echo htmlspecialchars($block_id); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-4 mb-3">
                            <label for="month" class="form-label">महीना चुनें <span class="text-danger">*</span></label>
                            <select class="form-select" id="month" name="month" required>
                                <option value="" disabled selected>महीना चुनें</option>
                                <?php 
                                // हिंदी महीनों की सूची
                                $hindi_months = array(
                                    'January' => 'जनवरी',
                                    'February' => 'फरवरी',
                                    'March' => 'मार्च',
                                    'April' => 'अप्रैल',
                                    'May' => 'मई',
                                    'June' => 'जून',
                                    'July' => 'जुलाई',
                                    'August' => 'अगस्त',
                                    'September' => 'सितंबर',
                                    'October' => 'अक्टूबर',
                                    'November' => 'नवंबर',
                                    'December' => 'दिसंबर'
                                );
                                
                                for ($m = 1; $m <= 12; $m++): 
                                    $month_name = date('F', mktime(0, 0, 0, $m, 1, date('Y')));
                                ?>
                                    <option value="<?php echo $month_name; ?>">
                                        <?php echo $hindi_months[$month_name]; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="year" class="form-label">वर्ष चुनें <span class="text-danger">*</span></label>
                            <select class="form-select" id="year" name="year" required>
                                <option value="" disabled selected>वर्ष चुनें</option>
                                <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="category" class="form-label">श्रेणी चुनें (वैकल्पिक)</label>
                            <select class="form-select" id="category" name="category" >
                                <option value="">सभी श्रेणियाँ</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="class" class="form-label">कक्षा (वैकल्पिक)</label>
                            <select class="form-select" id="class" name="class" >
                                <option value="">सभी कक्षा</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary" id="downloadBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="fas fa-download me-2"></i>
                            डाउनलोड रिपोर्ट
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // मोबाइल मेन्यू टॉगल
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // अलर्ट संदेश दिखाने के लिए फंक्शन
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.appendChild(alertDiv);
            
            // 5 सेकंड के बाद अलर्ट को हटा दें
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        $(document).ready(function() {
            const $monthSelect = $('#month');
            const $yearSelect = $('#year');
            const $categorySelect = $('#category');
            const $classSelect = $('#class');
            const $form = $('#reportForm');
            const $downloadBtn = $('#downloadBtn');
            const $spinner = $downloadBtn.find('.spinner-border');

            function updateCategories() {
                const month = $monthSelect.val();
                const year = $yearSelect.val();

                if (month && year) {
                    $categorySelect.prop('disabled', true).html('<option value="">लोड हो रहा है...</option>');
                    
                    $.ajax({
                        url: 'download_payment_report.php',
                        type: 'POST',
                        data: {
                            action: 'get_categories',
                            month: month,
                            year: year
                        },
                        dataType: 'json',
                        success: function(response) {
                            $categorySelect.prop('disabled', false).html('<option value="">सभी श्रेणियाँ</option>');
                            if (response.length > 0) {
                                response.forEach(function(cat) {
                                    $categorySelect.append(new Option(cat, cat));
                                });
                            }
                            updateClasses();
                        },
                        error: function() {
                            $categorySelect.prop('disabled', false).html('<option value="">श्रेणियाँ लोड करने में त्रुटि</option>');
                        }
                    });
                } else {
                    $categorySelect.prop('disabled', true).html('<option value="">सभी श्रेणियाँ</option>');
                    $classSelect.prop('disabled', true).html('<option value="">सभी कक्षा</option>');
                }
            }

            function updateClasses() {
                const month = $monthSelect.val();
                const year = $yearSelect.val();
                const category = $categorySelect.val();

                if (month && year) {
                    $classSelect.prop('disabled', true).html('<option value="">लोड हो रहा है...</option>');
                    
                    $.ajax({
                        url: 'download_payment_report.php',
                        type: 'POST',
                        data: {
                            action: 'get_classes',
                            month: month,
                            year: year,
                            category: category
                        },
                        dataType: 'json',
                        success: function(response) {
                            $classSelect.prop('disabled', false).html('<option value="">सभी कक्षा</option>');
                            if (response.length > 0) {
                                response.forEach(function(cls) {
                                    $classSelect.append(new Option(cls, cls));
                                });
                            }
                        },
                        error: function() {
                            $classSelect.prop('disabled', false).html('<option value="">कक्षाएं लोड करने में त्रुटि</option>');
                        }
                    });
                } else {
                    $classSelect.prop('disabled', true).html('<option value="">सभी कक्षा</option>');
                }
            }

            $monthSelect.on('change', updateCategories);
            $yearSelect.on('change', updateCategories);
            $categorySelect.on('change', updateClasses);

            $form.on('submit', function(e) {
                e.preventDefault();
                
                $spinner.removeClass('d-none');
                $downloadBtn.prop('disabled', true);

                const formData = new FormData(this);
                formData.append('action', 'generate_report');

                $.ajax({
                    url: 'download_payment_report.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        const xhr = new XMLHttpRequest();
                        xhr.onreadystatechange = function() {
                            if (this.readyState === 4 && this.status === 200) {
                                const contentType = this.getResponseHeader('Content-Type');
                                if (contentType && contentType.indexOf('text/csv') !== -1) {
                                    const blob = new Blob([this.response], { type: 'text/csv' });
                                    const url = window.URL.createObjectURL(blob);
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = this.getResponseHeader('Content-Disposition').split('filename=')[1].replace(/"/g, '');
                                    document.body.appendChild(a);
                                    a.click();
                                    document.body.removeChild(a);
                                    window.URL.revokeObjectURL(url);
                                    
                                    showAlert('रिपोर्ट सफलतापूर्वक डाउनलोड की गई!', 'success');
                                } else {
                                    try {
                                        const response = JSON.parse(this.response);
                                        showAlert('त्रुटि: ' + response.message, 'danger');
                                    } catch (e) {
                                        showAlert('रिपोर्ट जनरेट करने में एक अज्ञात त्रुटि हुई।', 'danger');
                                    }
                                }
                            }
                        };
                        return xhr;
                    },
                    error: function() {
                        showAlert('रिपोर्ट जनरेट करने में एक अज्ञात त्रुटि हुई।', 'danger');
                    },
                    complete: function() {
                        $spinner.addClass('d-none');
                        $downloadBtn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>