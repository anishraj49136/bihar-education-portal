<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह जिला स्तरीय उपयोगकर्ता है
 $allowed_types = ['district_staff', 'district_program_officer', 'district_education_officer'];
if (!isLoggedIn() || !in_array($_SESSION['user_type'], $allowed_types)) {
    header('Location: login.php');
    exit;
}

// जिले की जानकारी प्राप्त करें
if (!isset($_SESSION['district_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT district_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data && isset($user_data['district_id'])) {
        $district_id = $user_data['district_id'];
        $_SESSION['district_id'] = $district_id;
    } else {
        header('Location: logout.php');
        exit;
    }
} else {
    $district_id = $_SESSION['district_id'];
}

// जिले की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM districts WHERE id = ?");
 $stmt->execute([$district_id]);
 $district_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$district_info) {
    $district_info = ['name' => 'अज्ञात जिला'];
}

// विद्यालयों की कुल संख्या प्राप्त करें
 $stmt = $conn->prepare("SELECT COUNT(*) as total FROM schools WHERE district_id = ?");
 $stmt->execute([$district_id]);
 $total_schools = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// शिक्षकों की कुल संख्या प्राप्त करें
 $stmt = $conn->prepare("SELECT COUNT(*) as total FROM teachers t 
                        JOIN schools s ON t.school_id = s.id 
                        WHERE s.district_id = ?");
 $stmt->execute([$district_id]);
 $total_teachers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// वर्तमान महीने की शिकायतों की संख्या प्राप्त करें
 $current_month = date('F');
 $stmt = $conn->prepare("SELECT COUNT(*) as total FROM salary_complaints sc 
                        LEFT JOIN schools s ON sc.school_id = s.id 
                        WHERE s.district_id = ? AND MONTH(sc.created_at) = MONTH(CURRENT_DATE)");
 $stmt->execute([$district_id]);
 $total_complaints = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// शिक्षक श्रेणियों के अनुसार शिक्षकों की संख्या प्राप्त करें
 $stmt = $conn->prepare("SELECT t.category, COUNT(*) as count 
                        FROM teachers t 
                        JOIN schools s ON t.school_id = s.id 
                        WHERE s.district_id = ? 
                        GROUP BY t.category 
                        ORDER BY count DESC");
 $stmt->execute([$district_id]);
 $category_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// वेतन भुगतान डेटा प्राप्त करें
 $stmt = $conn->prepare("SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed_count,
                        COUNT(*) as total_count
                        FROM teacher_salary 
                        WHERE month = ?");
 $stmt->execute([$current_month]);
 $salary_status = $stmt->fetch(PDO::FETCH_ASSOC);

// ब्लॉकवार विद्यालयों की संख्या प्राप्त करें
 $stmt = $conn->prepare("SELECT b.name as block_name, COUNT(s.id) as school_count 
                        FROM blocks b 
                        LEFT JOIN schools s ON b.id = s.block_id 
                        WHERE b.district_id = ? 
                        GROUP BY b.id, b.name 
                        ORDER BY school_count DESC 
                        LIMIT 5");
 $stmt->execute([$district_id]);
 $block_school_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// हाल ही में दर्ज की गई शिकायतें प्राप्त करें
 $stmt = $conn->prepare("SELECT sc.*, s.name as school_name, t.name as teacher_name 
                        FROM salary_complaints sc 
                        LEFT JOIN schools s ON sc.school_id = s.id 
                        LEFT JOIN teachers t ON sc.teacher_pran_uan = t.pran_no OR sc.teacher_pran_uan = t.uan_no 
                        WHERE s.district_id = ? 
                        ORDER BY sc.created_at DESC 
                        LIMIT 5");
 $stmt->execute([$district_id]);
 $recent_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// पेजिनेशन वेरिएबल्स
 $complaint_page = isset($_GET['complaint_page']) ? (int)$_GET['complaint_page'] : 1;
 $salary_page = isset($_GET['salary_page']) ? (int)$_GET['salary_page'] : 1;
 $complaint_per_page = isset($_GET['complaint_per_page']) ? $_GET['complaint_per_page'] : 20;
 $salary_per_page = isset($_GET['salary_per_page']) ? $_GET['salary_per_page'] : 20;
 $complaint_per_page_value = ($complaint_per_page === 'all') ? 0 : (int)$complaint_per_page;
 $salary_per_page_value = ($salary_per_page === 'all') ? 0 : (int)$salary_per_page;
 $complaint_offset = ($complaint_page - 1) * $complaint_per_page_value;
 $salary_offset = ($salary_page - 1) * $salary_per_page_value;

// AJAX अनुरोधों को संभालें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_complaint_status') {
        try {
            $complaint_id = $_POST['complaint_id'];
            $new_status = $_POST['new_status'];
            $rejection_reason = $_POST['rejection_reason'] ?? null;

            $stmt = $conn->prepare("UPDATE salary_complaints SET status = ?, rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_status, $rejection_reason, $complaint_id]);
            
            echo json_encode(['status' => 'success', 'message' => 'स्थिति सफलतापूर्वक अपडेट की गई!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'त्रुटि: ' . $e->getMessage()]);
        }
        exit;
    } elseif ($_POST['action'] === 'get_complaint_details') {
        try {
            $complaint_id = $_POST['complaint_id'];
            
            // पहले बेसिक शिकायत जानकारी प्राप्त करें
            $stmt = $conn->prepare("SELECT sc.*, s.name as school_name, b.name as block_name 
                                   FROM salary_complaints sc 
                                   LEFT JOIN schools s ON sc.school_id = s.id 
                                   LEFT JOIN blocks b ON s.block_id = b.id
                                   WHERE sc.id = ?");
            $stmt->execute([$complaint_id]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($complaint) {
                // अब शिक्षक की जानकारी प्राप्त करें
                if (!empty($complaint['teacher_pran_uan'])) {
                    $teacher_stmt = $conn->prepare("SELECT name, category, class, mobile, pran_no, uan_no 
                                                   FROM teachers 
                                                   WHERE pran_no = ? OR uan_no = ?");
                    $teacher_stmt->execute([$complaint['teacher_pran_uan'], $complaint['teacher_pran_uan']]);
                    $teacher_info = $teacher_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($teacher_info) {
                        // शिक्षक की जानकारी को शिकायत डेटा में मर्ज करें
                        $complaint = array_merge($complaint, $teacher_info);
                    }
                }
                
                echo json_encode(['status' => 'success', 'complaint' => $complaint]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'शिकायत नहीं मिली।']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'डेटाबेस त्रुटि: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'त्रुटि: ' . $e->getMessage()]);
        }
        exit;
    }
}

// CSV डाउनलोड के लिए
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $type = $_GET['type'] ?? '';
    
    if ($type === 'complaints') {
        // शिकायतों की पूरी जानकारी प्राप्त करें
        $stmt = $conn->prepare("SELECT sc.*, s.name as school_name, b.name as block_name, t.name as teacher_name, 
                               t.category, t.class, t.mobile, t.pran_no, t.uan_no
                               FROM salary_complaints sc 
                               LEFT JOIN schools s ON sc.school_id = s.id 
                               LEFT JOIN blocks b ON s.block_id = b.id
                               LEFT JOIN teachers t ON (sc.teacher_pran_uan = t.pran_no OR sc.teacher_pran_uan = t.uan_no)
                               WHERE s.district_id = ? 
                               ORDER BY sc.created_at DESC");
        $stmt->execute([$district_id]);
        $complaints_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // CSV हेडर तैयार करें
        $csv_data = "टिकट नंबर,प्रखंड,विद्यालय,शिक्षक,श्रेणी,वर्ग,PRAN/UAN,मोबाइल,शिकायत प्रकार,विवरण,स्थिति,दर्ज की तिथि\n";
        
        // CSV डेटा तैयार करें
        foreach ($complaints_data as $complaint) {
            $csv_data .= '"' . $complaint['ticket_number'] . '",';
            $csv_data .= '"' . $complaint['block_name'] . '",';
            $csv_data .= '"' . $complaint['school_name'] . '",';
            $csv_data .= '"' . $complaint['teacher_name'] . '",';
            $csv_data .= '"' . $complaint['category'] . '",';
            $csv_data .= '"' . $complaint['class'] . '",';
            $csv_data .= '"' . ($complaint['pran_no'] ?: $complaint['uan_no']) . '",';
            $csv_data .= '"' . $complaint['mobile'] . '",';
            $csv_data .= '"' . ($complaint['salary_type'] === 'regular_salary' ? 'नियमित वेतन' : 'बकाया वेतन') . '",';
            $csv_data .= '"' . str_replace(["\r", "\n"], ["", " "], $complaint['description']) . '",';
            $csv_data .= '"' . ucfirst(str_replace('_', ' ', $complaint['status'])) . '",';
            $csv_data .= '"' . date('d M Y', strtotime($complaint['created_at'])) . '"' . "\n";
        }
        
        // CSV फाइल डाउनलोड करें
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="salary_complaints_' . date('Y-m-d') . '.csv"');
        echo $csv_data;
        exit;
    } elseif ($type === 'salary') {
        // वेतन डेटा की पूरी जानकारी प्राप्त करें
        $stmt = $conn->prepare("SELECT * FROM teacher_salary WHERE month = ? ORDER BY employee_name");
        $stmt->execute([$current_month]);
        $salary_data_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // CSV हेडर तैयार करें
        $csv_data = "Employee ID,GPF/PRAN Number,Employee Name,Designation,Service Type,Pay Level,Status,Approve Date\n";
        
        // CSV डेटा तैयार करें
        foreach ($salary_data_all as $record) {
            $csv_data .= '"' . $record['employee_id'] . '",';
            $csv_data .= '"' . $record['gpf_pran_number'] . '",';
            $csv_data .= '"' . $record['employee_name'] . '",';
            $csv_data .= '"' . $record['designation'] . '",';
            $csv_data .= '"' . $record['service_type'] . '",';
            $csv_data .= '"' . $record['pay_level'] . '",';
            $csv_data .= '"' . $record['status'] . '",';
            $csv_data .= '"' . ($record['approve_date'] ? date('d M Y', strtotime($record['approve_date'])) : 'N/A') . '"' . "\n";
        }
        
        // CSV फाइल डाउनलोड करें
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="salary_data_' . date('Y-m-d') . '.csv"');
        echo $csv_data;
        exit;
    }
}

// वर्तमान महीने के लिए वेतन शिकायतों की कुल संख्या प्राप्त करें
 $count_stmt = $conn->prepare("SELECT COUNT(*) as total 
                            FROM salary_complaints sc 
                            LEFT JOIN schools s ON sc.school_id = s.id 
                            WHERE s.district_id = ?");
 $count_stmt->execute([$district_id]);
 $total_complaints = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
 $total_complaint_pages = ($complaint_per_page_value > 0) ? ceil($total_complaints / $complaint_per_page_value) : 1;

// वेतन शिकायतें प्राप्त करें (पेजिनेशन के साथ)
 $complaint_sql = "SELECT sc.*, s.name as school_name, t.name as teacher_name 
                  FROM salary_complaints sc 
                  LEFT JOIN schools s ON sc.school_id = s.id 
                  LEFT JOIN teachers t ON (sc.teacher_pran_uan = t.pran_no OR sc.teacher_pran_uan = t.uan_no)
                  WHERE s.district_id = ? 
                  ORDER BY sc.created_at DESC";

if ($complaint_per_page_value > 0) {
    $complaint_sql .= " LIMIT $complaint_per_page_value OFFSET $complaint_offset";
}

 $stmt = $conn->prepare($complaint_sql);
 $stmt->execute([$district_id]);
 $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// वेतन डेटा की कुल संख्या प्राप्त करें
 $salary_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM teacher_salary WHERE month = ?");
 $salary_count_stmt->execute([$current_month]);
 $total_salary_records = $salary_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
 $total_salary_pages = ($salary_per_page_value > 0) ? ceil($total_salary_records / $salary_per_page_value) : 1;

// एडमिन द्वारा अपडेट किए गए वेतन डेटा प्राप्त करें (पेजिनेशन के साथ)
 $salary_sql = "SELECT * FROM teacher_salary WHERE month = ? ORDER BY employee_name";

if ($salary_per_page_value > 0) {
    $salary_sql .= " LIMIT $salary_per_page_value OFFSET $salary_offset";
}

 $stmt = $conn->prepare($salary_sql);
 $stmt->execute([$current_month]);
 $salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जिला डैशबोर्ड - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6a1b9a;
            --secondary-color: #9c27b0;
            --accent-color: #ce93d8;
            --light-color: #f3e5f5;
            --dark-color: #4a148c;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; overflow-y: auto; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; font-size: 0.9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 20px; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .card-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-weight: 600; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .table { border-radius: 10px; overflow: hidden; }
        .table thead { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .status-badge { font-size: 0.8em; padding: 4px 8px; border-radius: 12px; }
        .complaint-actions button { margin-right: 5px; }
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px 15px 0 0; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .pagination-info { color: #6c757d; }
        .per-page-selector { display: flex; align-items: center; gap: 10px; }
        .page-link { color: var(--primary-color); }
        .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
        .section-tabs { display: flex; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .section-tab { padding: 10px 20px; background: none; border: none; border-bottom: 3px solid transparent; color: #6c757d; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .section-tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; border-left: 4px solid var(--primary-color); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary-color); }
        .stat-label { color: #6c757d; font-size: 0.9rem; margin-top: 5px; }
        .chart-container { position: relative; height: 300px; }
        .complaint-item { padding: 15px; border-left: 3px solid var(--primary-color); background: #f8f9fa; margin-bottom: 10px; border-radius: 0 10px 10px 0; }
        .complaint-date { font-size: 0.8rem; color: #6c757d; }
        .view-toggle { position: fixed; top: 80px; right: 20px; z-index: 99; background: var(--primary-color); color: white; border-radius: 50px; padding: 10px 20px; display: flex; align-items: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transition: all 0.3s ease; }
        .view-toggle:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .view-toggle i { margin-left: 10px; }
        .dashboard-view { display: block; }
        .data-view { display: none; }
        .attachment-preview { max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 5px; margin-top: 10px; }
        .attachment-link { display: inline-block; margin-top: 10px; color: var(--primary-color); }
        .complaint-detail-row { margin-bottom: 15px; }
        .complaint-detail-label { font-weight: 600; color: var(--dark-color); }
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); width: 280px; } 
            .sidebar.active { transform: translateX(0); } 
            .main-content { margin-left: 0; } 
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } 
            .pagination-container { flex-direction: column; gap: 15px; }
            .view-toggle { top: 70px; right: 10px; font-size: 0.8rem; padding: 8px 15px; }
            .table-responsive { font-size: 0.85rem; }
            .stat-card { margin-bottom: 15px; }
            .stat-number { font-size: 1.5rem; }
        }
        @media (max-width: 576px) {
            .complaint-actions button { 
                padding: 0.25rem 0.4rem; 
                font-size: 0.75rem; 
                margin-right: 2px;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            .card-body { padding: 1rem; }
            .modal-body { padding: 1rem; }
        }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार -->
    <?php include 'sidebar_template.php'; ?>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">जिला डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?>, <?php echo $district_info['name']; ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <!-- व्यू टॉगल बटन -->
        <button class="view-toggle" id="viewToggle" onclick="toggleView()">
            <span id="viewToggleText">डेटा व्यू</span>
            <i class="fas fa-exchange-alt"></i>
        </button>

        <!-- डैशबोर्ड व्यू -->
        <div id="dashboardView" class="dashboard-view">
            <!-- स्वागत संदेश -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <h4 class="mb-0">जिला शिक्षा विभाग, <?php echo $district_info['name']; ?></h4>
                    <p class="text-muted mb-0">शिक्षा की गुणवत्ता और समृद्धि के लिए प्रतिबद्ध</p>
                </div>
            </div>

            <!-- सांख्यिकी कार्ड -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo number_format($total_schools); ?></div>
                                <div class="stat-label">कुल विद्यालय</div>
                            </div>
                            <div class="text-primary" style="font-size: 2rem;">
                                <i class="fas fa-school"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo number_format($total_teachers); ?></div>
                                <div class="stat-label">कुल शिक्षक</div>
                            </div>
                            <div class="text-primary" style="font-size: 2rem;">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo number_format($total_complaints); ?></div>
                                <div class="stat-label">शिकायतें (इस महीने)</div>
                            </div>
                            <div class="text-primary" style="font-size: 2rem;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo number_format($salary_status['total_count'] ?? 0); ?></div>
                                <div class="stat-label">वेतन प्रक्रिया (<?php echo $current_month; ?>)</div>
                            </div>
                            <div class="text-primary" style="font-size: 2rem;">
                                <i class="fas fa-money-check-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- चार्ट और अतिरिक्त जानकारी -->
            <div class="row">
                <div class="col-md-8">
                    <!-- शिक्षक श्रेणी बार चार्ट -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">श्रेणीवार शिक्षक वितरण</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- ब्लॉकवार विद्यालय -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">ब्लॉकवार विद्यालय (शीर्ष 5)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="blockChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- वेतन स्थिति -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">वेतन स्थिति (<?php echo $current_month; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>लंबित</span>
                                    <span class="badge bg-warning"><?php echo $salary_status['pending_count'] ?? 0; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php $pending_percent = $salary_status['total_count'] > 0 ? (($salary_status['pending_count'] ?? 0) / $salary_status['total_count']) * 100 : 0; ?>
                                    <div class="progress-bar bg-warning" style="width: <?php echo $pending_percent; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>स्वीकृत</span>
                                    <span class="badge bg-success"><?php echo $salary_status['approved_count'] ?? 0; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php $approved_percent = $salary_status['total_count'] > 0 ? (($salary_status['approved_count'] ?? 0) / $salary_status['total_count']) * 100 : 0; ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $approved_percent; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>अस्वीकृत</span>
                                    <span class="badge bg-danger"><?php echo $salary_status['rejected_count'] ?? 0; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php $rejected_percent = $salary_status['total_count'] > 0 ? (($salary_status['rejected_count'] ?? 0) / $salary_status['total_count']) * 100 : 0; ?>
                                    <div class="progress-bar bg-danger" style="width: <?php echo $rejected_percent; ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>प्रसंस्कृत</span>
                                    <span class="badge bg-info"><?php echo $salary_status['processed_count'] ?? 0; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php $processed_percent = $salary_status['total_count'] > 0 ? (($salary_status['processed_count'] ?? 0) / $salary_status['total_count']) * 100 : 0; ?>
                                    <div class="progress-bar bg-info" style="width: <?php echo $processed_percent; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- हाल की शिकायतें -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">हाल की शिकायतें</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_complaints) > 0): ?>
                                <?php foreach ($recent_complaints as $complaint): ?>
                                <div class="complaint-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo $complaint['ticket_number']; ?></strong>
                                            <div class="small"><?php echo $complaint['school_name']; ?></div>
                                            <div class="small text-muted"><?php echo $complaint['teacher_name']; ?></div>
                                        </div>
                                        <div class="text-end">
                                            <?php
                                            $status_class = 'bg-secondary';
                                            if ($complaint['status'] === 'in_process') $status_class = 'bg-info';
                                            elseif ($complaint['status'] === 'done') $status_class = 'bg-success';
                                            elseif ($complaint['status'] === 'rejected') $status_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> status-badge"><?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?></span>
                                            <div class="complaint-date"><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">कोई शिकायत नहीं मिली।</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- त्वरित लिंक -->
            <div class="row mt-4">
                <div class="col-md-3 col-sm-6">
                    <a href="schools.php" class="btn btn-outline-primary w-100 mb-3">
                        <i class="fas fa-school me-2"></i>विद्यालय प्रबंधन
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="teachers.php" class="btn btn-outline-primary w-100 mb-3">
                        <i class="fas fa-chalkboard-teacher me-2"></i>शिक्षक प्रबंधन
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="salary_complaints.php" class="btn btn-outline-primary w-100 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>शिकायतें
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="salary_management.php" class="btn btn-outline-primary w-100 mb-3">
                        <i class="fas fa-money-check-alt me-2"></i>वेतन प्रबंधन
                    </a>
                </div>
            </div>
        </div>

        <!-- डेटा व्यू -->
        <div id="dataView" class="data-view">
            <!-- टैब नेविगेशन -->
            <div class="section-tabs">
                <button class="section-tab active" onclick="showTab('complaints')">वेतन शिकायतें</button>
                <button class="section-tab" onclick="showTab('salary')">वेतन डेटा</button>
            </div>

            <!-- वेतन शिकायतें टैब कंटेंट -->
            <div id="complaints-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">वेतन शिकायतें</h5>
                        <div>
                            <button class="btn btn-sm btn-light" onclick="downloadCSV('complaints')">
                                <i class="fas fa-file-csv"></i> CSV डाउनलोड करें
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>क्र. सं.</th>
                                        <th>टिकट नंबर</th>
                                        <th>विद्यालय</th>
                                        <th>शिक्षक</th>
                                        <th>वेतन प्रकार</th>
                                        <th>विवरण</th>
                                        <th>दर्ज की तिथि</th>
                                        <th>स्थिति</th>
                                        <th>कार्रवाई</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($complaints) > 0): ?>
                                        <?php $serial_number = ($complaint_page - 1) * $complaint_per_page_value + 1; ?>
                                        <?php foreach ($complaints as $complaint): ?>
                                        <tr>
                                            <td><?php echo $serial_number++; ?></td>
                                            <td><?php echo $complaint['ticket_number']; ?></td>
                                            <td><?php echo $complaint['school_name']; ?></td>
                                            <td><?php echo $complaint['teacher_name']; ?></td>
                                            <td><?php echo ($complaint['salary_type'] === 'regular_salary') ? 'नियमित वेतन' : 'बकाया वेतन'; ?></td>
                                            <td><?php echo substr($complaint['description'], 0, 100) . '...'; ?></td>
                                            <td><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'bg-secondary';
                                                if ($complaint['status'] === 'in_process') $status_class = 'bg-info';
                                                elseif ($complaint['status'] === 'done') $status_class = 'bg-success';
                                                elseif ($complaint['status'] === 'rejected') $status_class = 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?> status-badge"><?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?></span>
                                            </td>
                                            <td class="complaint-actions">
                                                <button class="btn btn-sm btn-primary" onclick="viewComplaintDetails(<?php echo $complaint['id']; ?>)"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-sm btn-info" onclick="updateComplaintStatus(<?php echo $complaint['id']; ?>, 'in_process')"><i class="fas fa-cog"></i></button>
                                                <button class="btn btn-sm btn-success" onclick="updateComplaintStatus(<?php echo $complaint['id']; ?>, 'done')"><i class="fas fa-check"></i></button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectComplaint(<?php echo $complaint['id']; ?>)"><i class="fas fa-times"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="9" class="text-center">कोई शिकायत नहीं मिली।</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- पेजिनेशन for वेतन शिकायतें -->
                        <div class="pagination-container">
                            <div class="pagination-info">
                                <?php 
                                if ($complaint_per_page_value > 0) {
                                    $start = ($complaint_page - 1) * $complaint_per_page_value + 1;
                                    $end = min($complaint_page * $complaint_per_page_value, $total_complaints);
                                    echo "दिखा रहे हैं $start से $end कुल $total_complaints शिकायतों में से";
                                } else {
                                    echo "सभी $total_complaints शिकायतें दिखा रहे हैं";
                                }
                                ?>
                            </div>
                            
                            <div class="per-page-selector">
                                <label for="complaint_per_page" class="form-label mb-0">प्रति पृष्ठ:</label>
                                <select class="form-select form-select-sm" id="complaint_per_page" name="complaint_per_page" style="width: auto;" onchange="changeComplaintPerPage()">
                                    <option value="20" <?php echo ($complaint_per_page == 20) ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo ($complaint_per_page == 50) ? 'selected' : ''; ?>>50</option>
                                    <option value="all" <?php echo ($complaint_per_page == 'all') ? 'selected' : ''; ?>>सभी</option>
                                </select>
                            </div>
                            
                            <?php if ($complaint_per_page_value > 0): ?>
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php if ($complaint_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?complaint_page=<?php echo $complaint_page - 1; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $salary_page; ?>&salary_per_page=<?php echo $salary_per_page; ?>">पिछला</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $complaint_page - 2); $i <= min($total_complaint_pages, $complaint_page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $complaint_page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?complaint_page=<?php echo $i; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $salary_page; ?>&salary_per_page=<?php echo $salary_per_page; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($complaint_page < $total_complaint_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?complaint_page=<?php echo $complaint_page + 1; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $salary_page; ?>&salary_per_page=<?php echo $salary_per_page; ?>">अगला</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- वेतन डेटा टैब कंटेंट -->
            <div id="salary-tab" class="tab-content">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">वेतन डेटा (<?php echo $current_month; ?>)</h5>
                        <div>
                            <button class="btn btn-sm btn-light" onclick="downloadCSV('salary')">
                                <i class="fas fa-file-csv"></i> CSV डाउनलोड करें
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>क्र. सं.</th>
                                        <th>Employee ID</th>
                                        <th>GPF/PRAN Number</th>
                                        <th>Employee Name</th>
                                        <th>Designation</th>
                                        <th>Service Type</th>
                                        <th>Pay Level</th>
                                        <th>Status</th>
                                        <th>Approve Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($salary_data) > 0): ?>
                                        <?php $salary_serial_number = ($salary_page - 1) * $salary_per_page_value + 1; ?>
                                        <?php foreach ($salary_data as $record): ?>
                                        <tr>
                                            <td><?php echo $salary_serial_number++; ?></td>
                                            <td><?php echo $record['employee_id']; ?></td>
                                            <td><?php echo $record['gpf_pran_number']; ?></td>
                                            <td><?php echo $record['employee_name']; ?></td>
                                            <td><?php echo $record['designation']; ?></td>
                                            <td><?php echo $record['service_type']; ?></td>
                                            <td><?php echo $record['pay_level']; ?></td>
                                            <td><?php echo $record['status']; ?></td>
                                            <td><?php echo $record['approve_date'] ? date('d M Y', strtotime($record['approve_date'])) : 'N/A'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="10" class="text-center">इस महीने के लिए कोई डेटा नहीं मिला।</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- पेजिनेशन for वेतन डेटा -->
                        <div class="pagination-container">
                            <div class="pagination-info">
                                <?php 
                                if ($salary_per_page_value > 0) {
                                    $start = ($salary_page - 1) * $salary_per_page_value + 1;
                                    $end = min($salary_page * $salary_per_page_value, $total_salary_records);
                                    echo "दिखा रहे हैं $start से $end कुल $total_salary_records रिकॉर्ड्स में से";
                                } else {
                                    echo "सभी $total_salary_records रिकॉर्ड्स दिखा रहे हैं";
                                }
                                ?>
                            </div>
                            
                            <div class="per-page-selector">
                                <label for="salary_per_page" class="form-label mb-0">प्रति पृष्ठ:</label>
                                <select class="form-select form-select-sm" id="salary_per_page" name="salary_per_page" style="width: auto;" onchange="changeSalaryPerPage()">
                                    <option value="20" <?php echo ($salary_per_page == 20) ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo ($salary_per_page == 50) ? 'selected' : ''; ?>>50</option>
                                    <option value="all" <?php echo ($salary_per_page == 'all') ? 'selected' : ''; ?>>सभी</option>
                                </select>
                            </div>
                            
                            <?php if ($salary_per_page_value > 0): ?>
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php if ($salary_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?complaint_page=<?php echo $complaint_page; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $salary_page - 1; ?>&salary_per_page=<?php echo $salary_per_page; ?>">पिछला</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $salary_page - 2); $i <= min($total_salary_pages, $salary_page + 2); $i++): ?>
                                    <li class="page-item <?php echo ($i == $salary_page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?complaint_page=<?php echo $complaint_page; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $i; ?>&salary_per_page=<?php echo $salary_per_page; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($salary_page < $total_salary_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?complaint_page=<?php echo $complaint_page; ?>&complaint_per_page=<?php echo $complaint_per_page; ?>&salary_page=<?php echo $salary_page + 1; ?>&salary_per_page=<?php echo $salary_per_page; ?>">अगला</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- अस्वीकरण रिजेक्शन मोडल -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">अस्वीकरण का कारण</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectionForm">
                    <input type="hidden" name="complaint_id" id="rejectionComplaintId">
                    <div class="modal-body">
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="4" placeholder="कृपया अस्वीकरण का कारण बताएं..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-danger">अस्वीकृत करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- शिकायत विवरण देखने का मोडल -->
    <div class="modal fade" id="complaintDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">शिकायत विवरण</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="complaintDetailsContent">
                    <!-- शिकायत विवरण यहां लोड होगा -->
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="detailComplaintId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">बंद करें</button>
                    <button type="button" class="btn btn-info" onclick="updateComplaintStatusFromDetail('in_process')">
                        <i class="fas fa-cog"></i> प्रक्रिया में
                    </button>
                    <button type="button" class="btn btn-success" onclick="updateComplaintStatusFromDetail('done')">
                        <i class="fas fa-check"></i> पूर्ण करें
                    </button>
                    <button type="button" class="btn btn-danger" onclick="rejectComplaintFromDetail()">
                        <i class="fas fa-times"></i> अस्वीकृत करें
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        // व्यू टॉगल फ़ंक्शन
        function toggleView() {
            const dashboardView = document.getElementById('dashboardView');
            const dataView = document.getElementById('dataView');
            const viewToggleText = document.getElementById('viewToggleText');
            
            if (dashboardView.style.display === 'none') {
                dashboardView.style.display = 'block';
                dataView.style.display = 'none';
                viewToggleText.textContent = 'डेटा व्यू';
            } else {
                dashboardView.style.display = 'none';
                dataView.style.display = 'block';
                viewToggleText.textContent = 'डैशबोर्ड व्यू';
            }
        }

        // टैब स्विचिंग फ़ंक्शन
        function showTab(tabName) {
            // सभी टैब और कंटेंट छिपाएं
            document.querySelectorAll('.section-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // चुनी गई टैब और संबंधित कंटेंट दिखाएं
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        // शिक्षक श्रेणी चार्ट
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($category_counts, 'category')); ?>,
                datasets: [{
                    label: 'शिक्षक संख्या',
                    data: <?php echo json_encode(array_column($category_counts, 'count')); ?>,
                    backgroundColor: [
                        'rgba(106, 27, 154, 0.8)',
                        'rgba(156, 39, 176, 0.8)',
                        'rgba(206, 147, 216, 0.8)',
                        'rgba(74, 20, 140, 0.8)',
                        'rgba(243, 229, 245, 0.8)'
                    ],
                    borderColor: [
                        'rgba(106, 27, 154, 1)',
                        'rgba(156, 39, 176, 1)',
                        'rgba(206, 147, 216, 1)',
                        'rgba(74, 20, 140, 1)',
                        'rgba(243, 229, 245, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // ब्लॉकवार विद्यालय चार्ट
        const blockCtx = document.getElementById('blockChart').getContext('2d');
        const blockChart = new Chart(blockCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($block_school_counts, 'block_name')); ?>,
                datasets: [{
                    label: 'विद्यालय संख्या',
                    data: <?php echo json_encode(array_column($block_school_counts, 'school_count')); ?>,
                    backgroundColor: 'rgba(106, 27, 154, 0.8)',
                    borderColor: 'rgba(106, 27, 154, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // शिकायत विवरण देखने का फ़ंक्शन
        function viewComplaintDetails(complaintId) {
            console.log('Fetching complaint details for ID:', complaintId);
            
            fetch('district_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_complaint_details&complaint_id=${complaintId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.status === 'success') {
                    const complaint = data.complaint;
                    
                    // डिफ़ॉल्ट मान सेट करें यदि डेटा उपलब्ध नहीं है
                    const safeGet = (value, defaultValue = 'N/A') => {
                        return value || defaultValue;
                    };
                    
                    let detailsHtml = `
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">टिकट नंबर:</span> ${safeGet(complaint.ticket_number)}
                                </div>
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">दर्ज की तिथि:</span> ${complaint.created_at ? new Date(complaint.created_at).toLocaleDateString('hi-IN') : 'N/A'}
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">प्रखंड का नाम:</span> ${safeGet(complaint.block_name)}
                                </div>
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">विद्यालय का नाम:</span> ${safeGet(complaint.school_name)}
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">शिक्षक का नाम:</span> ${safeGet(complaint.name)}
                                </div>
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">शिक्षक की श्रेणी:</span> ${safeGet(complaint.category)}
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">शिक्षक का वर्ग:</span> ${safeGet(complaint.class)}
                                </div>
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">PRAN/UAN संख्या:</span> ${safeGet(complaint.teacher_pran_uan)}
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">मोबाइल नंबर:</span> ${safeGet(complaint.mobile)}
                                </div>
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">शिकायत का प्रकार:</span> ${complaint.salary_type === 'regular_salary' ? 'नियमित वेतन' : 'बकाया वेतन'}
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-12">
                                    <span class="complaint-detail-label">शिकायत विवरण:</span>
                                    <p>${safeGet(complaint.description)}</p>
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-12">
                                    <span class="complaint-detail-label">अटैचमेंट:</span>
                                    <div id="attachmentContainer">
                                        ${complaint.attachment ? 
                                            (complaint.attachment.match(/\.(jpg|jpeg|png|gif)$/i) ? 
                                                `<img src="${complaint.attachment}" class="attachment-preview" alt="अटैचमेंट" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                 <div style="display:none; color:red;">छवि लोड नहीं हो सकी</div>` :
                                                `<a href="${complaint.attachment}" target="_blank" class="attachment-link"><i class="fas fa-file-download"></i> अटैचमेंट डाउनलोड करें</a>`) :
                                            'कोई अटैचमेंट नहीं'
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="complaint-detail-row">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">वर्तमान स्थिति:</span> 
                                    <span class="badge ${getStatusClass(complaint.status)}">${ucfirstSafe(complaint.status)}</span>
                                </div>
                                ${complaint.rejection_reason ? `
                                <div class="col-md-6">
                                    <span class="complaint-detail-label">अस्वीकरण का कारण:</span> ${complaint.rejection_reason}
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('complaintDetailsContent').innerHTML = detailsHtml;
                    document.getElementById('detailComplaintId').value = complaintId;
                    
                    const modal = new bootstrap.Modal(document.getElementById('complaintDetailsModal'));
                    modal.show();
                } else {
                    alert('त्रुटि: ' + (data.message || 'अज्ञात त्रुटि'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('शिकायत विवरण प्राप्त करने में त्रुटि। कृपया कंसोल जांचें।');
            });
        }

        // सुरक्षित ucfirst फ़ंक्शन
        function ucfirstSafe(str) {
            if (!str) return 'N/A';
            return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
        }

        // स्टेटस के अनुसार बैज क्लास प्राप्त करने का फ़ंक्शन
        function getStatusClass(status) {
            if (!status) return 'bg-secondary';
            if (status === 'in_process') return 'bg-info';
            if (status === 'done') return 'bg-success';
            if (status === 'rejected') return 'bg-danger';
            return 'bg-secondary';
        }

        // विस्तार व्यू से शिकायत स्थिति अपडेट करने का फ़ंक्शन
        function updateComplaintStatusFromDetail(status) {
            const complaintId = document.getElementById('detailComplaintId').value;
            updateComplaintStatus(complaintId, status);
        }

        // विस्तार व्यू से शिकायत अस्वीकृत करने का फ़ंक्शन
        function rejectComplaintFromDetail() {
            const complaintId = document.getElementById('detailComplaintId').value;
            document.getElementById('rejectionComplaintId').value = complaintId;
            document.getElementById('rejectionReason').value = '';
            
            // विस्तार मोडल बंद करें
            bootstrap.Modal.getInstance(document.getElementById('complaintDetailsModal')).hide();
            
            // अस्वीकरण मोडल खोलें
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }

        // शिकायत स्थिति अपडेट करने का फ़ंक्शन
        function updateComplaintStatus(complaintId, status) {
            if (status === 'rejected') {
                document.getElementById('rejectionComplaintId').value = complaintId;
                document.getElementById('rejectionReason').value = '';
                new bootstrap.Modal(document.getElementById('rejectionModal')).show();
            } else {
                sendUpdateRequest(complaintId, status, null);
            }
        }

        function rejectComplaint(complaintId) {
            document.getElementById('rejectionComplaintId').value = complaintId;
            document.getElementById('rejectionReason').value = '';
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }

        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const complaintId = document.getElementById('rejectionComplaintId').value;
            const reason = document.getElementById('rejectionReason').value;
            sendUpdateRequest(complaintId, 'rejected', reason);
        });

        function sendUpdateRequest(complaintId, status, reason) {
            fetch('district_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_complaint_status&complaint_id=${complaintId}&new_status=${status}&rejection_reason=${reason}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('त्रुटि: ' + data.message);
                }
            });
        }

        function downloadCSV(type) {
            // URL बनाएं जिसमें सभी फ़िल्टर शामिल हों
            const url = new URL(window.location);
            url.searchParams.set('download', 'csv');
            url.searchParams.set('type', type);
            
            // CSV डाउनलोड करें
            window.location.href = url.toString();
        }

        function changeComplaintPerPage() {
            const perPage = document.getElementById('complaint_per_page').value;
            const url = new URL(window.location);
            url.searchParams.set('complaint_per_page', perPage);
            url.searchParams.set('complaint_page', 1); // Reset to first page
            window.location.href = url.toString();
        }

        function changeSalaryPerPage() {
            const perPage = document.getElementById('salary_per_page').value;
            const url = new URL(window.location);
            url.searchParams.set('salary_per_page', perPage);
            url.searchParams.set('salary_page', 1); // Reset to first page
            window.location.href = url.toString();
        }
    </script>
</body>
</html>