<?php
require_once 'config.php';

// यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType(['district_officer', 'admin']);

// जिला आईडी प्राप्त करें
 $district_id = null;
if (isset($_SESSION['district_id'])) {
    $district_id = $_SESSION['district_id'];
} else {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $stmt = $conn->prepare("SELECT district_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['district_id'])) {
                $district_id = $result['district_id'];
                $_SESSION['district_id'] = $district_id;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching district_id: " . $e->getMessage());
    }
}

if (!$district_id) {
    $_SESSION['error_message'] = "जिला अधिकारी के लिए जिला ID निर्धारित नहीं है। कृपया लॉगिन करें।";
    header("Location: login.php");
    exit;
}

// जिले की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT name as district_name FROM districts WHERE id = ?");
 $stmt->execute([$district_id]);
 $district_info = $stmt->fetch(PDO::FETCH_ASSOC);

// फिल्टर मान प्राप्त करें
 $udise_code = $_GET['udise_code'] ?? '';
 $teacher_name = $_GET['teacher_name'] ?? '';
 $pran_uan = $_GET['pran_uan'] ?? '';
 $class_filter = $_GET['class_filter'] ?? '';

// पेजिनेशन वेरिएबल्स
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
 $offset = ($page - 1) * $per_page;

// उपस्थिति डेटा अपडेट करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
    try {
        foreach ($_POST['attendance_data'] as $attendance_id => $data) {
            $stmt = $conn->prepare("UPDATE attendance SET 
                                   total_attendance_days = ?, 
                                   unauthorized_absence_days = ?, 
                                   leave_days = ?, 
                                   remarks = ?,
                                   district_reviewed_by = ?,
                                   district_reviewed_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([
                $data['total_attendance_days'],
                $data['unauthorized_absence_days'],
                $data['leave_days'],
                $data['remarks'],
                $_SESSION['user_id'],
                $attendance_id
            ]);
        }
        $_SESSION['success_message'] = "उपस्थिति विवरण सफलतापूर्वक अपडेट की गई!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header("Location: district_attendance_dashboard.php?" . http_build_query($_GET));
    exit;
}

// कुल रिकॉर्ड्स की गिनती प्राप्त करें
 $count_sql = "SELECT COUNT(*) as total
        FROM teachers t
        JOIN attendance a ON t.id = a.teacher_id
        JOIN schools s ON t.school_id = s.id
        JOIN blocks b ON s.block_id = b.id
        WHERE b.district_id = '$district_id' AND a.status = 'forwarded_to_district'";

if (!empty($udise_code)) {
    $count_sql .= " AND s.udise_code LIKE '%$udise_code%'";
}
if (!empty($teacher_name)) {
    $count_sql .= " AND t.name LIKE '%$teacher_name%'";
}
if (!empty($pran_uan)) {
    $count_sql .= " AND (t.pran_no LIKE '%$pran_uan%' OR t.uan_no LIKE '%$pran_uan%')";
}

 $count_stmt = $conn->prepare($count_sql);
 $count_stmt->execute();
 $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
 $total_pages = ceil($total_records / $per_page);

// उपस्थिति रिकॉर्ड प्राप्त करें
 $sql = "SELECT a.id as attendance_id, t.id as teacher_id, t.name, t.mobile, t.pran_no, t.uan_no, t.class, 
               a.total_attendance_days, a.in_time_count, a.out_time_count, a.unauthorized_absence_days, a.leave_days, a.remarks,
               a.district_reviewed_by, a.district_reviewed_at,
               s.name as school_name, s.udise_code,
               u.name as reviewer_name
        FROM teachers t
        JOIN attendance a ON t.id = a.teacher_id
        JOIN schools s ON t.school_id = s.id
        JOIN blocks b ON s.block_id = b.id
        LEFT JOIN users u ON a.district_reviewed_by = u.id
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

 $sql .= " ORDER BY a.district_reviewed_at DESC, s.name, t.name";

if ($per_page !== 'all') {
    $sql .= " LIMIT $per_page OFFSET $offset";
}

 $stmt = $conn->prepare($sql);
 $stmt->execute();
 $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV डाउनलोड के लिए कक्षाएं प्राप्त करें
 $class_options = [];
try {
    $class_stmt = $conn->prepare("SELECT DISTINCT class FROM teachers ORDER BY class");
    $class_stmt->execute();
    $class_options = $class_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जिला उपस्थिति डैशबोर्ड - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6a1b9a; --secondary-color: #9c27b0; --accent-color: #ce93d8; --light-color: #f3e5f5; --dark-color: #4a148c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-weight: 600; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .table { border-radius: 10px; overflow: hidden; }
        .table thead { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .attendance-input { width: 100px; }
        .status-badge { font-size: 0.8em; padding:4px 8px; border-radius: 12px; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .pagination-info { margin-right: 20px; }
        .page-link { color: var(--primary-color); }
        .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
        .field-updated { background-color: #fff3cd; border: 1px solid #ffeaa7; }
        /* मोबाइल रेस्पॉन्सिव स्टाइल */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .main-content { margin-left: 0; } 
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } 
            .table-responsive { font-size: 0.85rem; }
            .attendance-input { width: 80px; }
        }
        @media (max-width: 768px) {
            .card-body { padding: 10px; }
            .form-control, .form-select { font-size: 0.85rem; }
            .btn { padding: 6px 12px; font-size: 0.85rem; }
            .table th, .table td { padding: 5px; }
            .pagination-container { flex-direction: column; align-items: flex-start; }
            .pagination-info { margin-bottom: 10px; }
        }
        @media (max-width: 576px) {
            .table-responsive { font-size: 0.75rem; }
            .attendance-input { width: 70px; }
            .table th, .table td { padding: 3px; }
            .user-avatar { width: 30px; height: 30px; font-size: 0.8rem; }
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
                <h4 class="mb-0">जिला उपस्थिति डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst($_SESSION['user_role']); ?>, <?php echo $district_info['district_name']; ?></small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- सफलता/त्रुटि संदेश -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- फिल्टर कार्ड -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0">शिक्षक उपस्थिति विवरणी खोजें</h5></div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="udise_code" class="form-label">UDISE कोड</label>
                            <input type="text" class="form-control" id="udise_code" name="udise_code" value="<?php echo htmlspecialchars($udise_code); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="teacher_name" class="form-label">शिक्षक का नाम</label>
                            <input type="text" class="form-control" id="teacher_name" name="teacher_name" value="<?php echo htmlspecialchars($teacher_name); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="pran_uan" class="form-label">PRAN/UAN नंबर</label>
                            <input type="text" class="form-control" id="pran_uan" name="pran_uan" value="<?php echo htmlspecialchars($pran_uan); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">प्रति पृष्ठ रिकॉर्ड</label>
                            <select class="form-select" id="per_page" name="per_page">
                                <option value="20" <?php echo ($per_page === 20) ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($per_page === 50) ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($per_page === 100) ? 'selected' : ''; ?>>100</option>
                                <option value="all" <?php echo ($per_page === 'all') ? 'selected' : ''; ?>>सभी</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> खोजें</button>
                            <a href="district_attendance_dashboard.php" class="btn btn-secondary"><i class="fas fa-redo"></i> रीसेट</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- उपस्थिति रिकॉर्ड्स -->
        <form method="post" action="" id="attendanceForm">
            <input type="hidden" name="action" value="update_attendance">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">फॉरवर्ड किए गए शिक्षक उपस्थिति विवरण</h5>
                    <div>
                        <button type="submit" class="btn btn-light btn-sm"><i class="fas fa-save"></i> परिवर्तन सहेजें</button>
                        <button type="button" class="btn btn-success btn-sm" onclick="downloadCSV()"><i class="fas fa-download"></i> CSV डाउनलोड करें</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- CSV डाउनलोड फिल्टर -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="class_filter" class="form-label">श्रेणी के अनुसार CSV डाउनलोड करें</label>
                            <select class="form-select" id="class_filter" name="class_filter">
                                <option value="">सभी श्रेणियां</option>
                                <?php foreach($class_options as $class): ?>
                                <option value="<?php echo $class; ?>"><?php echo $class; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>क्रमांक</th>
                                    <th>विद्यालय</th>
                                    <th>शिक्षक का नाम</th>
                                    <th>PRAN/UAN</th>
                                    <th>भुगतान हेतु दिवस</th>
                                    <th>अनधिकृत अनुपस्थिति</th>
                                    <th>अवकाश</th>
                                    <th>अभियुक्ति</th>
                                    <th>अंतिम बार अपडेट किया</th>
                                    <th>अपडेट करने वाले</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($attendance_records) > 0): ?>
                                    <?php $serial_number = ($page - 1) * $per_page + 1; ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo $serial_number; ?></td>
                                        <td><?php echo $record['school_name']; ?><br><small><?php echo $record['udise_code']; ?></small></td>
                                        <td><?php echo $record['name']; ?></td>
                                        <td><?php echo $record['pran_no'] ?: $record['uan_no']; ?></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['attendance_id']; ?>][total_attendance_days]" value="<?php echo $record['total_attendance_days'] ?? 0; ?>" onchange="this.classList.add('field-updated')"></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['attendance_id']; ?>][unauthorized_absence_days]" value="<?php echo $record['unauthorized_absence_days'] ?? 0; ?>" onchange="this.classList.add('field-updated')"></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['attendance_id']; ?>][leave_days]" value="<?php echo $record['leave_days'] ?? 0; ?>" onchange="this.classList.add('field-updated')"></td>
                                        <td><textarea class="form-control" name="attendance_data[<?php echo $record['attendance_id']; ?>][remarks]" rows="1" onchange="this.classList.add('field-updated')"><?php echo $record['remarks'] ?? ''; ?></textarea></td>
                                        <td><?php echo $record['district_reviewed_at'] ? date('d-m-Y H:i', strtotime($record['district_reviewed_at'])) : '-'; ?></td>
                                        <td><?php echo $record['reviewer_name'] ?? '-'; ?></td>
                                    </tr>
                                    <?php $serial_number++; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" class="text-center">कोई रिकॉर्ड नहीं मिला।</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- पेजिनेशन -->
                    <?php if ($total_records > 0): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <?php 
                            $start = ($page - 1) * $per_page + 1;
                            $end = min($page * $per_page, $total_records);
                            echo "दिखा रहे हैं $start से $end कुल $total_records रिकॉर्ड्स में से";
                            ?>
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php 
                                $max_visible_pages = 5;
                                $start_page = max(1, $page - floor($max_visible_pages / 2));
                                $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active_class = ($i == $page) ? 'active' : '';
                                    echo '<li class="page-item '.$active_class.'"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => $i])).'">'.$i.'</a></li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page' => $total_pages])).'">'.$total_pages.'</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
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
        
        // CSV डाउनलोड फंक्शन
        function downloadCSV() {
            const classFilter = document.getElementById('class_filter').value;
            const url = 'export_district_attendance.php?' + new URLSearchParams({
                udise_code: '<?php echo $udise_code; ?>',
                teacher_name: '<?php echo $teacher_name; ?>',
                pran_uan: '<?php echo $pran_uan; ?>',
                class_filter: classFilter
            });
            window.open(url, '_blank');
        }
    </script>
</body>
</html>