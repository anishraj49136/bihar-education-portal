<?php
require_once 'config.php';

// Disable emulated prepares to fix LIMIT/OFFSET parameter binding
 $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// जांचें कि उपयोगकर्ता लॉग इन है
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// उपयोगकर्ता प्रकार और आईडी प्राप्त करें
 $user_type = $_SESSION['user_type'];
 $user_id = $_SESSION['user_id'];
 $school_id = isset($_SESSION['school_id']) ? $_SESSION['school_id'] : null;
 $block_id = isset($_SESSION['block_id']) ? $_SESSION['block_id'] : null;
 $district_id = isset($_SESSION['district_id']) ? $_SESSION['district_id'] : null;

// Pagination parameters
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
 $offset = ($page - 1) * $per_page;

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teachers_list.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, ['क्र. सं.', 'जिला', 'ब्लॉक', 'विद्यालय', 'ई-शिक्षकोष ID', 'नाम', 'प्रकार', 'कक्षा', 'मोबाइल नंबर', 'PRAN/UAN', 'श्रेणी']);
    
    // Get teachers data without pagination for export
    $export_query = "SELECT t.*, s.name as school_name, b.name as block_name, b.id as block_id, d.name as district_name, d.id as district_id
                    FROM teachers t 
                    JOIN schools s ON t.school_id = s.id 
                    JOIN blocks b ON s.block_id = b.id 
                    JOIN districts d ON b.district_id = d.id 
                    WHERE 1=1 " . $where_clause . " 
                    ORDER BY d.name, b.name, s.name, t.name";
    
    $export_stmt = $conn->prepare($export_query);
    $export_stmt->execute($params);
    $export_teachers = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add data rows
    foreach ($export_teachers as $index => $teacher) {
        $row = [
            $index + 1,
            $teacher['district_name'],
            $teacher['block_name'],
            $teacher['school_name'],
            $teacher['eshikshakosh_id'],
            $teacher['name'],
            $teacher['type'],
            $teacher['class'],
            $teacher['mobile'],
            $teacher['pran_no'] ?: $teacher['uan_no'],
            $teacher['category']
        ];
        fputcsv($output, $row);
    }
    
    // Close output stream
    fclose($output);
    exit;
}

// शिक्षक श्रेणियां प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM teacher_categories ORDER BY name");
 $stmt->execute();
 $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ब्लॉक और जिला जानकारी प्राप्त करें (जरूरत के अनुसार)
 $blocks = [];
 $districts = [];
 $schools = [];

if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo') {
    // सभी जिले प्राप्त करें
    $stmt = $conn->prepare("SELECT * FROM districts ORDER BY name");
    $stmt->execute();
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // यदि जिला चुना गया है, तो उस जिले के ब्लॉक प्राप्त करें
    if (isset($_GET['district_id']) && !empty($_GET['district_id'])) {
        $stmt = $conn->prepare("SELECT * FROM blocks WHERE district_id = ? ORDER BY name");
        $stmt->execute([$_GET['district_id']]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // यदि ब्लॉक चुना गया है, तो उस ब्लॉक के स्कूल प्राप्त करें
        if (isset($_GET['block_id']) && !empty($_GET['block_id'])) {
            $stmt = $conn->prepare("SELECT * FROM schools WHERE block_id = ? ORDER BY name");
            $stmt->execute([$_GET['block_id']]);
            $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} elseif ($user_type === 'beo') {
    // BEO के लिए उसके ब्लॉक के स्कूल प्राप्त करें
    $stmt = $conn->prepare("SELECT * FROM schools WHERE block_id = ? ORDER BY name");
    $stmt->execute([$block_id]);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// शिक्षक जोड़ने/अपडेट करने/हटाने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // जांचें कि उपयोगकर्ता के पास आवश्यक अधिकार हैं या नहीं
        $can_add_edit = ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo' || $user_type === 'school');
        $can_delete = ($user_type === 'admin');
        
        if (isset($_POST['action']) && $_POST['action'] === 'add' && $can_add_edit) {
            // नया शिक्षक जोड़ें
            $target_school_id = $_POST['school_id'];
            
            // सत्यापित करें कि उपयोगकर्ता इस स्कूल के लिए शिक्षक जोड़ सकता है या नहीं
            $can_add = false;
            if ($user_type === 'admin') {
                $can_add = true;
            } elseif ($user_type === 'dpo' || $user_type === 'deo') {
                // जांचें कि स्कूल उपयोगकर्ता के जिले में है
                $stmt = $conn->prepare("SELECT s.id FROM schools s 
                                      JOIN blocks b ON s.block_id = b.id 
                                      WHERE s.id = ? AND b.district_id = ?");
                $stmt->execute([$target_school_id, $district_id]);
                if ($stmt->rowCount() > 0) $can_add = true;
            } elseif ($user_type === 'beo') {
                // जांचें कि स्कूल उपयोगकर्ता के ब्लॉक में है
                $stmt = $conn->prepare("SELECT id FROM schools WHERE id = ? AND block_id = ?");
                $stmt->execute([$target_school_id, $block_id]);
                if ($stmt->rowCount() > 0) $can_add = true;
            } elseif ($user_type === 'school') {
                // जांचें कि स्कूल उपयोगकर्ता का अपना स्कूल है
                if ($target_school_id == $school_id) $can_add = true;
            }
            
            if ($can_add) {
                $stmt = $conn->prepare("INSERT INTO teachers (eshikshakosh_id, name, type, class, aadhar, mobile, pran_no, uan_no, category, school_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['eshikshakosh_id'],
                    $_POST['name'],
                    $_POST['type'],
                    $_POST['class'],
                    $_POST['aadhar'],
                    $_POST['mobile'],
                    $_POST['pran_no'],
                    $_POST['uan_no'],
                    $_POST['category'],
                    $target_school_id
                ]);
                $success_message = "शिक्षक सफलतापूर्वक जोड़ा गया!";
            } else {
                $error_message = "आपके पास इस स्कूल में शिक्षक जोड़ने की अनुमति नहीं है!";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'edit' && $can_add_edit) {
            // शिक्षक अपडेट करें
            $teacher_id = $_POST['teacher_id'];
            
            // पहले शिक्षक की जानकारी प्राप्त करें
            $stmt = $conn->prepare("SELECT t.*, s.block_id, b.district_id FROM teachers t 
                                  JOIN schools s ON t.school_id = s.id 
                                  JOIN blocks b ON s.block_id = b.id 
                                  WHERE t.id = ?");
            $stmt->execute([$teacher_id]);
            $teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // सत्यापित करें कि उपयोगकर्ता इस शिक्षक को अपडेट कर सकता है या नहीं
            $can_edit = false;
            if ($user_type === 'admin') {
                $can_edit = true;
            } elseif ($user_type === 'dpo' || $user_type === 'deo') {
                // जांचें कि शिक्षक उपयोगकर्ता के जिले में है
                if ($teacher_info['district_id'] == $district_id) $can_edit = true;
            } elseif ($user_type === 'beo') {
                // जांचें कि शिक्षक उपयोगकर्ता के ब्लॉक में है
                if ($teacher_info['block_id'] == $block_id) $can_edit = true;
            } elseif ($user_type === 'school') {
                // जांचें कि शिक्षक उपयोगकर्ता के स्कूल में है
                if ($teacher_info['school_id'] == $school_id) $can_edit = true;
            }
            
            if ($can_edit) {
                // पुराना डेटा प्राप्त करें लॉगिंग के लिए
                $old_data = $teacher_info;
                
                // अपडेट करें
                $stmt = $conn->prepare("UPDATE teachers SET 
                                       eshikshakosh_id = ?, name = ?, type = ?, class = ?, 
                                       aadhar = ?, mobile = ?, pran_no = ?, uan_no = ?, category = ? 
                                       WHERE id = ?");
                $stmt->execute([
                    $_POST['eshikshakosh_id'],
                    $_POST['name'],
                    $_POST['type'],
                    $_POST['class'],
                    $_POST['aadhar'],
                    $_POST['mobile'],
                    $_POST['pran_no'],
                    $_POST['uan_no'],
                    $_POST['category'],
                    $teacher_id
                ]);
                
                // लॉग परिवर्तन
                if (function_exists('logModification')) {
                    try {
                        logModification('teachers', $teacher_id, 'name', $old_data['name'], $_POST['name'], $_SESSION['user_id']);
                        logModification('teachers', $teacher_id, 'mobile', $old_data['mobile'], $_POST['mobile'], $_SESSION['user_id']);
                        // ... अन्य फ़ील्ड्स के लिए भी लॉग करें
                    } catch (PDOException $e) {
                        // लॉगिंग त्रुटि को अनदेखा करें, लेकिन रिकॉर्ड करें
                        error_log("Logging error: " . $e->getMessage());
                    }
                }
                
                $success_message = "शिक्षक जानकारी सफलतापूर्वक अपडेट की गई!";
            } else {
                $error_message = "आपके पास इस शिक्षक की जानकारी अपडेट करने की अनुमति नहीं है!";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && $can_delete) {
            // शिक्षक हटाएं (केवल एडमिन के लिए)
            $teacher_id = $_POST['teacher_id'];
            $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $success_message = "शिक्षक सफलतापूर्वक हटा दिया गया!";
        }
    } catch (PDOException $e) {
        $error_message = "त्रुटि: " . $e->getMessage();
    }
}

// शिक्षकों की सूची प्राप्त करें (उपयोगकर्ता प्रकार के आधार पर)
 $where_clause = "";
 $params = [];

if ($user_type === 'admin') {
    // एडमिन सभी शिक्षक देख सकता है, लेकिन फ़िल्टर लागू हो सकते हैं
    if (isset($_GET['district_id']) && !empty($_GET['district_id'])) {
        $where_clause .= " AND b.district_id = ?";
        $params[] = $_GET['district_id'];
        
        if (isset($_GET['block_id']) && !empty($_GET['block_id'])) {
            $where_clause .= " AND s.block_id = ?";
            $params[] = $_GET['block_id'];
            
            if (isset($_GET['school_id']) && !empty($_GET['school_id'])) {
                $where_clause .= " AND t.school_id = ?";
                $params[] = $_GET['school_id'];
            }
        }
    }
} elseif ($user_type === 'dpo' || $user_type === 'deo') {
    // DPO और DEO अपने जिले के शिक्षक देख सकते हैं
    $where_clause = " AND b.district_id = ?";
    $params[] = $district_id;
    
    if (isset($_GET['block_id']) && !empty($_GET['block_id'])) {
        $where_clause .= " AND s.block_id = ?";
        $params[] = $_GET['block_id'];
        
        if (isset($_GET['school_id']) && !empty($_GET['school_id'])) {
            $where_clause .= " AND t.school_id = ?";
            $params[] = $_GET['school_id'];
        }
    }
} elseif ($user_type === 'beo') {
    // BEO अपने ब्लॉक के शिक्षक देख सकता है
    $where_clause = " AND s.block_id = ?";
    $params[] = $block_id;
    
    if (isset($_GET['school_id']) && !empty($_GET['school_id'])) {
        $where_clause .= " AND t.school_id = ?";
        $params[] = $_GET['school_id'];
    }
} elseif ($user_type === 'school') {
    // स्कूल उपयोगकर्ता केवल अपने स्कूल के शिक्षक देख सकता है
    $where_clause = " AND t.school_id = ?";
    $params[] = $school_id;
}

// कुल रिकॉर्ड्स की गिनती करें
 $count_query = "SELECT COUNT(*) as total 
                FROM teachers t 
                JOIN schools s ON t.school_id = s.id 
                JOIN blocks b ON s.block_id = b.id 
                JOIN districts d ON b.district_id = d.id 
                WHERE 1=1 " . $where_clause;

 $count_stmt = $conn->prepare($count_query);
 $count_stmt->execute($params);
 $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// क्वेरी निष्पादित करें
 $query = "SELECT t.*, s.name as school_name, b.name as block_name, b.id as block_id, d.name as district_name, d.id as district_id
          FROM teachers t 
          JOIN schools s ON t.school_id = s.id 
          JOIN blocks b ON s.block_id = b.id 
          JOIN districts d ON b.district_id = d.id 
          WHERE 1=1 " . $where_clause . " 
          ORDER BY d.name, b.name, s.name, t.name";

// Add pagination if not "View All"
if ($per_page > 0) {
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
}

 $stmt = $conn->prepare($query);
 $stmt->execute($params);
 $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total pages
 $total_pages = ($per_page > 0) ? ceil($total_records / $per_page) : 1;
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>शिक्षक विवरण - बिहार शिक्षा विभाग</title>
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
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
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
        
        .table thead {
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
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25);
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .pagination-info {
            color: #6c757d;
        }
        
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            display: none;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- साइडबार -->
    <div class="sidebar" id="sidebar">
        <div class="p-4 text-center">
            <h4>बिहार शिक्षा विभाग</h4>
            <p class="mb-0"><?php echo ucfirst($user_type); ?> डैशबोर्ड</p>
        </div>
        
        <hr class="text-white">
        
        <ul class="nav flex-column">
            <?php if ($user_type === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> डैशबोर्ड
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="districts.php">
                    <i class="fas fa-map-marked-alt"></i> जिले
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="blocks.php">
                    <i class="fas fa-map"></i> ब्लॉक
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schools.php">
                    <i class="fas fa-school"></i> विद्यालय
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण
                </a>
            </li>
            <?php elseif ($user_type === 'dpo' || $user_type === 'deo'): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $user_type; ?>_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> डैशबोर्ड
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="blocks.php">
                    <i class="fas fa-map"></i> ब्लॉक
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schools.php">
                    <i class="fas fa-school"></i> विद्यालय
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण
                </a>
            </li>
            <?php elseif ($user_type === 'beo'): ?>
            <li class="nav-item">
                <a class="nav-link" href="beo_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> डैशबोर्ड
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="schools.php">
                    <i class="fas fa-school"></i> विद्यालय
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण
                </a>
            </li>
            <?php elseif ($user_type === 'school'): ?>
            <li class="nav-item">
                <a class="nav-link" href="school_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> डैशबोर्ड
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="school_profile.php">
                    <i class="fas fa-school"></i> विद्यालय प्रोफाइल
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="enrollment.php">
                    <i class="fas fa-user-graduate"></i> नामांकन
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="attendance.php">
                    <i class="fas fa-calendar-check"></i> उपस्थिति विवरणी
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="salary_status.php">
                    <i class="fas fa-money-check-alt"></i> वेतन स्थिति
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="salary_complaint.php">
                    <i class="fas fa-exclamation-triangle"></i> वेतन शिकायत
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> लॉग आउट
                </a>
            </li>
        </ul>
    </div>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">शिक्षक विवरण</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2">
                        <?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst($user_type); ?></small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- सफलता/त्रुटि संदेश -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- फ़िल्टर सेक्शन -->
        <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo'): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">फ़िल्टर</h5>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row">
                        <?php if ($user_type === 'admin'): ?>
                        <div class="col-md-3 mb-3">
                            <label for="district_id" class="form-label">जिला</label>
                            <select class="form-select" id="district_id" name="district_id" onchange="this.form.submit()">
                                <option value="">सभी जिले</option>
                                <?php foreach ($districts as $district): ?>
                                <option value="<?php echo $district['id']; ?>" <?php echo (isset($_GET['district_id']) && $_GET['district_id'] == $district['id']) ? 'selected' : ''; ?>>
                                    <?php echo $district['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (($user_type === 'admin' && isset($_GET['district_id']) && !empty($_GET['district_id'])) || 
                                 ($user_type === 'dpo' || $user_type === 'deo')): ?>
                        <div class="col-md-3 mb-3">
                            <label for="block_id" class="form-label">ब्लॉक</label>
                            <select class="form-select" id="block_id" name="block_id" onchange="this.form.submit()">
                                <option value="">सभी ब्लॉक</option>
                                <?php foreach ($blocks as $block): ?>
                                <option value="<?php echo $block['id']; ?>" <?php echo (isset($_GET['block_id']) && $_GET['block_id'] == $block['id']) ? 'selected' : ''; ?>>
                                    <?php echo $block['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ((($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo') && isset($_GET['block_id']) && !empty($_GET['block_id'])) || 
                                 ($user_type === 'beo')): ?>
                        <div class="col-md-3 mb-3">
                            <label for="school_id" class="form-label">विद्यालय</label>
                            <select class="form-select" id="school_id" name="school_id" onchange="this.form.submit()">
                                <option value="">सभी विद्यालय</option>
                                <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo (isset($_GET['school_id']) && $_GET['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                    <?php echo $school['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='teachers.php'">रीसेट करें</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- शिक्षक सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">शिक्षक सूची</h5>
                <div>
                    <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo' || $user_type === 'school'): ?>
                    <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#teacherModal">
                        <i class="fas fa-plus"></i> नया शिक्षक जोड़ें
                    </button>
                    <?php endif; ?>
                    <a href="teachers.php?export=csv<?php echo isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-success">
                        <i class="fas fa-file-csv"></i> CSV में निर्यात करें
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>क्र. सं.</th>
                                <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo'): ?>
                                <th>जिला</th>
                                <th>ब्लॉक</th>
                                <th>विद्यालय</th>
                                <?php endif; ?>
                                <th>ई-शिक्षकोष ID</th>
                                <th>नाम</th>
                                <th>प्रकार</th>
                                <th>कक्षा</th>
                                <th>मोबाइल नंबर</th>
                                <th>PRAN/UAN</th>
                                <th>श्रेणी</th>
                                <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo' || $user_type === 'school'): ?>
                                <th>कार्रवाई</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($teachers) > 0): ?>
                                <?php foreach ($teachers as $index => $teacher): ?>
                                <tr>
                                    <td><?php echo ($page - 1) * $per_page + $index + 1; ?></td>
                                    <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo'): ?>
                                    <td><?php echo $teacher['district_name']; ?></td>
                                    <td><?php echo $teacher['block_name']; ?></td>
                                    <td><?php echo $teacher['school_name']; ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $teacher['eshikshakosh_id']; ?></td>
                                    <td><?php echo $teacher['name']; ?></td>
                                    <td><?php echo $teacher['type']; ?></td>
                                    <td><?php echo $teacher['class']; ?></td>
                                    <td><?php echo $teacher['mobile']; ?></td>
                                    <td><?php echo $teacher['pran_no'] ?: $teacher['uan_no']; ?></td>
                                    <td><?php echo $teacher['category']; ?></td>
                                    <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo' || $user_type === 'school'): ?>
                                    <td>
                                        <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo' || $user_type === 'school'): ?>
                                        <button type="button" class="btn btn-sm btn-primary edit-teacher" 
                                                data-id="<?php echo $teacher['id']; ?>"
                                                data-eshikshakosh-id="<?php echo $teacher['eshikshakosh_id']; ?>"
                                                data-name="<?php echo $teacher['name']; ?>"
                                                data-type="<?php echo $teacher['type']; ?>"
                                                data-class="<?php echo $teacher['class']; ?>"
                                                data-aadhar="<?php echo $teacher['aadhar']; ?>"
                                                data-mobile="<?php echo $teacher['mobile']; ?>"
                                                data-pran-no="<?php echo $teacher['pran_no']; ?>"
                                                data-uan-no="<?php echo $teacher['uan_no']; ?>"
                                                data-category="<?php echo $teacher['category']; ?>"
                                                data-school-id="<?php echo $teacher['school_id']; ?>"
                                                data-district-id="<?php echo $teacher['district_id']; ?>"
                                                data-block-id="<?php echo $teacher['block_id']; ?>"
                                                data-school-name="<?php echo $teacher['school_name']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_type === 'admin'): ?>
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस शिक्षक को हटाना चाहते हैं?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo') ? '12' : '9'; ?>" class="text-center">कोई शिक्षक नहीं मिला</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php 
                        $start = ($page - 1) * $per_page + 1;
                        $end = min($page * $per_page, $total_records);
                        echo "दिखा रहे हैं " . $start . " से " . $end . " कुल " . $total_records . " रिकॉर्ड्स में से";
                        ?>
                    </div>
                    
                    <div class="per-page-selector">
                        <label for="per_page" class="form-label mb-0">प्रति पृष्ठ:</label>
                        <select class="form-select form-select-sm" id="per_page" name="per_page" style="width: auto;">
                            <option value="20" <?php echo ($per_page == 20) ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100</option>
                            <option value="0" <?php echo ($per_page == 0) ? 'selected' : ''; ?>>सभी देखें</option>
                        </select>
                    </div>
                    
                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?><?php echo isset($_GET['district_id']) ? '&district_id=' . $_GET['district_id'] : ''; ?><?php echo isset($_GET['block_id']) ? '&block_id=' . $_GET['block_id'] : ''; ?><?php echo isset($_GET['school_id']) ? '&school_id=' . $_GET['school_id'] : ''; ?>">पिछला</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?><?php echo isset($_GET['district_id']) ? '&district_id=' . $_GET['district_id'] : ''; ?><?php echo isset($_GET['block_id']) ? '&block_id=' . $_GET['block_id'] : ''; ?><?php echo isset($_GET['school_id']) ? '&school_id=' . $_GET['school_id'] : ''; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?><?php echo isset($_GET['district_id']) ? '&district_id=' . $_GET['district_id'] : ''; ?><?php echo isset($_GET['block_id']) ? '&block_id=' . $_GET['block_id'] : ''; ?><?php echo isset($_GET['school_id']) ? '&school_id=' . $_GET['school_id'] : ''; ?>">अगला</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- शिक्षक मोडल -->
    <div class="modal fade" id="teacherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="teacherModalTitle">नया शिक्षक जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="teacherForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="teacher_id" id="teacherId">
                        
                        <?php if ($user_type === 'admin' || $user_type === 'dpo' || $user_type === 'deo' || $user_type === 'beo'): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="district_id_modal" class="form-label">जिला</label>
                                <select class="form-select" id="district_id_modal" name="district_id_modal" onchange="loadBlocks()">
                                    <option value="" selected disabled>चुनें</option>
                                    <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo $district['id']; ?>" <?php echo (isset($_GET['district_id']) && $_GET['district_id'] == $district['id']) ? 'selected' : ''; ?>>
                                        <?php echo $district['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="block_id_modal" class="form-label">ब्लॉक</label>
                                <select class="form-select" id="block_id_modal" name="block_id_modal" onchange="loadSchools()">
                                    <option value="" selected disabled>चुनें</option>
                                    <?php foreach ($blocks as $block): ?>
                                    <option value="<?php echo $block['id']; ?>" <?php echo (isset($_GET['block_id']) && $_GET['block_id'] == $block['id']) ? 'selected' : ''; ?>>
                                        <?php echo $block['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="school_id" class="form-label">विद्यालय</label>
                                <select class="form-select" id="school_id" name="school_id" required>
                                    <option value="" selected disabled>चुनें</option>
                                    <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo (isset($_GET['school_id']) && $_GET['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                        <?php echo $school['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- स्कूल उपयोगकर्ता के लिए स्कूल ID छुपा हुआ है -->
                        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="eshikshakosh_id" class="form-label">ई-शिक्षकोष ID</label>
                                <input type="text" class="form-control" id="eshikshakosh_id" name="eshikshakosh_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">शिक्षक का नाम</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">शिक्षक का प्रकार</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="" selected disabled>चुनें</option>
                                    <option value="Exclusive Teacher">Exclusive Teacher</option>
                                    <option value="Government Teacher">Government Teacher</option>
                                    <option value="PRI Teacher">PRI Teacher</option>
                                    <option value="Regular">Regular</option>
                                    <option value="School Teacher (BPSC)">School Teacher (BPSC)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="class" class="form-label">शिक्षक की कक्षा</label>
                                <select class="form-select" id="class" name="class" required onchange="checkClassSelection()">
                                    <option value="" selected disabled>चुनें</option>
                                    <option value="(11-12)">11-12</option>
                                    <option value="(1-5)">1-5</option>
                                    <option value="(1-8)">1-8</option>
                                    <option value="(6-8)">6-8</option>
                                    <option value="(9-10)">9-10</option>
                                    <option value="(9-12)">9-12</option>
                                </select>
                                <div class="warning-box" id="classWarning">
                                    <strong>चेतावनी:</strong> यह कक्षा केवल UHS Head Master के लिए है। यदि आप UHS Head Master हैं, तो ही आगे बढ़ें।
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">मोबाइल नंबर</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="aadhar" class="form-label">आधार नंबर</label>
                                <input type="text" class="form-control" id="aadhar" name="aadhar">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pran_no" class="form-label">PRAN नंबर</label>
                                <input type="text" class="form-control" id="pran_no" name="pran_no">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="uan_no" class="form-label">UAN नंबर</label>
                                <input type="text" class="form-control" id="uan_no" name="uan_no">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">शिक्षक श्रेणी</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="" selected disabled>चुनें</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['name']; ?>"><?php echo $category['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">सहेजें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // मोबाइल मेन्यू टॉगल
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // विंडो आकार बदलने पर साइडबार की जांच करें
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // शिक्षक संपादित करें
        document.querySelectorAll('.edit-teacher').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('teacherModalTitle').textContent = 'शिक्षक जानकारी संपादित करें';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('teacherId').value = this.dataset.id;
                document.getElementById('eshikshakosh_id').value = this.dataset.eshikshakoshId;
                document.getElementById('name').value = this.dataset.name;
                document.getElementById('type').value = this.dataset.type;
                document.getElementById('class').value = this.dataset.class;
                document.getElementById('aadhar').value = this.dataset.aadhar;
                document.getElementById('mobile').value = this.dataset.mobile;
                document.getElementById('pran_no').value = this.dataset.pranNo;
                document.getElementById('uan_no').value = this.dataset.uanNo;
                document.getElementById('category').value = this.dataset.category;
                
                // Set school, district and block values for edit modal
                if (document.getElementById('school_id')) {
                    // For admin, BEO, DPO, DEO users
                    document.getElementById('school_id').value = this.dataset.schoolId;
                    
                    // Add school name option if not already in the list
                    const schoolSelect = document.getElementById('school_id');
                    let schoolExists = false;
                    for (let i =0; i < schoolSelect.options.length; i++) {
                        if (schoolSelect.options[i].value == this.dataset.schoolId) {
                            schoolExists = true;
                            break;
                        }
                    }
                    
                    if (!schoolExists && this.dataset.schoolName) {
                        const option = document.createElement('option');
                        option.value = this.dataset.schoolId;
                        option.textContent = this.dataset.schoolName;
                        option.selected = true;
                        schoolSelect.appendChild(option);
                    }
                }
                
                if (document.getElementById('district_id_modal')) {
                    document.getElementById('district_id_modal').value = this.dataset.districtId;
                    // Load blocks for this district
                    loadBlocksForEdit(this.dataset.districtId, this.dataset.blockId);
                }
                
                if (document.getElementById('block_id_modal')) {
                    document.getElementById('block_id_modal').value = this.dataset.blockId;
                    // Load schools for this block
                    loadSchoolsForEdit(this.dataset.blockId, this.dataset.schoolId);
                }
                
                var teacherModal = new bootstrap.Modal(document.getElementById('teacherModal'));
                teacherModal.show();
            });
        });
        
        // मोडल रीसेट करें
        document.getElementById('teacherModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('teacherForm').reset();
            document.getElementById('teacherModalTitle').textContent = 'नया शिक्षक जोड़ें';
            document.getElementById('formAction').value = 'add';
            document.getElementById('teacherId').value = '';
            document.getElementById('classWarning').style.display = 'none';
        });
        
        // ब्लॉक लोड करें
        function loadBlocks() {
            const districtId = document.getElementById('district_id_modal').value;
            const blockSelect = document.getElementById('block_id_modal');
            const schoolSelect = document.getElementById('school_id');
            
            // ब्लॉक और स्कूल सेलेक्ट रीसेट करें
            blockSelect.innerHTML = '<option value="" selected disabled>चुनें</option>';
            schoolSelect.innerHTML = '<option value="" selected disabled>चुनें</option>';
            
            if (districtId) {
                fetch(`get_blocks.php?district_id=${districtId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(block => {
                            const option = document.createElement('option');
                            option.value = block.id;
                            option.textContent = block.name;
                            blockSelect.appendChild(option);
                        });
                    });
            }
        }
        
        // स्कूल लोड करें
        function loadSchools() {
            const blockId = document.getElementById('block_id_modal').value;
            const schoolSelect = document.getElementById('school_id');
            
            // स्कूल सेलेक्ट रीसेट करें
            schoolSelect.innerHTML = '<option value="" selected disabled>चुनें</option>';
            
            if (blockId) {
                fetch(`get_schools.php?block_id=${blockId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(school => {
                            const option = document.createElement('option');
                            option.value = school.id;
                            option.textContent = school.name;
                            schoolSelect.appendChild(option);
                        });
                    });
            }
        }
        
        // Edit modal के लिए ब्लॉक लोड करें
        function loadBlocksForEdit(districtId, selectedBlockId) {
            const blockSelect = document.getElementById('block_id_modal');
            
            // ब्लॉक सेलेक्ट रीसेट करें
            blockSelect.innerHTML = '<option value="" selected disabled>चुनें</option>';
            
            if (districtId) {
                fetch(`get_blocks.php?district_id=${districtId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(block => {
                            const option = document.createElement('option');
                            option.value = block.id;
                            option.textContent = block.name;
                            if (block.id == selectedBlockId) {
                                option.selected = true;
                            }
                            blockSelect.appendChild(option);
                        });
                    });
            }
        }
        
        // Edit modal के लिए स्कूल लोड करें
        function loadSchoolsForEdit(blockId, selectedSchoolId) {
            const schoolSelect = document.getElementById('school_id');
            
            // स्कूल सेलेक्ट रीसेट करें
            schoolSelect.innerHTML = '<option value="" selected disabled>चुनें</option>';
            
            if (blockId) {
                fetch(`get_schools.php?block_id=${blockId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(school => {
                            const option = document.createElement('option');
                            option.value = school.id;
                            option.textContent = school.name;
                            if (school.id == selectedSchoolId) {
                                option.selected = true;
                            }
                            schoolSelect.appendChild(option);
                        });
                    });
            }
        }
        
        // कक्षा चयन की जांच करें
        function checkClassSelection() {
            const classSelect = document.getElementById('class');
            const warningBox = document.getElementById('classWarning');
            const submitBtn = document.getElementById('submitBtn');
            
            if (classSelect.value === '9-12') {
                warningBox.style.display = 'block';
                // यहाँ आप जांच सकते हैं कि उपयोगकर्ता UHS Head Master है या नहीं
                // यह जांच सर्वर-साइड पर की जानी चाहिए
                // यहाँ हम केवल एक चेतावनी दिखा रहे हैं
                submitBtn.disabled = false;
            } else {
                warningBox.style.display = 'none';
                submitBtn.disabled = false;
            }
        }
        
        // प्रति पृष्ठ रिकॉर्ड्स बदलने पर पेज रीलोड करें
        document.getElementById('per_page').addEventListener('change', function() {
            const perPage = this.value;
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        });
    </script>
</body>
</html>