<?php
require_once 'config.php';

// 1. यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType('block_officer');

// ब्लॉक आईडी प्राप्त करें
 $block_id = null;

// पहले सेशन से जांचें
if (isset($_SESSION['block_id'])) {
    $block_id = $_SESSION['block_id'];
} 
// अगर सेशन में नहीं है, तो डेटाबेस से प्राप्त करें
else {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $stmt = $conn->prepare("SELECT block_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['block_id'])) {
                $block_id = $result['block_id'];
                // सेशन में सेव करें ताकि बाद में फिर से क्वेरी न करनी पड़े
                $_SESSION['block_id'] = $block_id;
            }
        }
    } catch (PDOException $e) {
        // क्वेरी फेल होने पर
        error_log("Error fetching block_id: " . $e->getMessage());
    }
}

// अगर भी ब्लॉक आईडी नहीं मिली, तो त्रुटि दिखाएं
if (!$block_id) {
    $_SESSION['error_message'] = "प्रखंड शिक्षा अधिकारी के लिए ब्लॉक ID निर्धारित नहीं है। कृपया लॉगिन करें।";
    header("Location: login.php");
    exit;
}

// प्रखंड की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT b.name as block_name, d.name as district_name FROM blocks b JOIN districts d ON b.district_id = d.id WHERE b.id = ?");
 $stmt->execute([$block_id]);
 $block_info = $stmt->fetch(PDO::FETCH_ASSOC);

// फिल्टर मान प्राप्त करें
 $selected_month = $_GET['month'] ?? date('F');
 $selected_year = $_GET['year'] ?? date('Y');
 $udise_code = $_GET['udise_code'] ?? '';

// पेजिनेशन वेरिएबल्स
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
 $offset = ($page - 1) * $per_page;

// जांचें कि attendance टेबल में status कॉलम मौजूद है या नहीं
 $has_status_column = false;
try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM attendance LIKE 'status'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $has_status_column = true;
    }
} catch (PDOException $e) {
    $has_status_column = false;
}

// उपस्थिति डेटा अपडेट करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
    try {
        foreach ($_POST['attendance_data'] as $teacher_id => $data) {
            $stmt = $conn->prepare("UPDATE attendance SET 
                                   total_attendance_days = ?, 
                                   unauthorized_absence_days = ?, 
                                   leave_days = ?, 
                                   remarks = ? 
                                   WHERE teacher_id = ? AND month = ? AND year = ?");
            $stmt->execute([
                $data['total_attendance_days'],
                $data['unauthorized_absence_days'],
                $data['leave_days'],
                $data['remarks'],
                $teacher_id,
                $selected_month,
                $selected_year
            ]);
        }
        $_SESSION['success_message'] = "उपस्थिति विवरण सफलतापूर्वक अपडेट की गई!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header("Location: block_officer_dashboard.php?month=$selected_month&year=$selected_year&udise_code=$udise_code&page=$page&per_page=$per_page");
    exit;
}

// आगे भेजने या वापस भेजने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action_forward']) || isset($_POST['action_send_back']))) {
    $teacher_ids = $_POST['teacher_ids'] ?? [];
    $status = isset($_POST['action_forward']) ? 'forwarded_to_admin' : 'sent_back_to_school';
    
    try {
        if (!empty($teacher_ids)) {
            $placeholders = implode(',', array_fill(0, count($teacher_ids), '?'));
            
            if ($has_status_column) {
                $stmt = $conn->prepare("UPDATE attendance SET status = ?, reviewed_by = ?, is_locked = ? WHERE teacher_id IN ($placeholders) AND month = ? AND year = ?");
                $stmt->execute(array_merge([$status, $_SESSION['user_id'], ($status === 'sent_back_to_school' ? 0 : 1)], $teacher_ids, [$selected_month, $selected_year]));
            } else {
                $stmt = $conn->prepare("UPDATE attendance SET reviewed_by = ?, is_locked = ? WHERE teacher_id IN ($placeholders) AND month = ? AND year = ?");
                $stmt->execute(array_merge([$_SESSION['user_id'], ($status === 'sent_back_to_school' ? 0 : 1)], $teacher_ids, [$selected_month, $selected_year]));
            }
            
            $action_text = $status === 'forwarded_to_admin' ? 'एडमिन को भेजा गया' : 'विद्यालय को वापस भेजा गया';
            $_SESSION['success_message'] = "चयनित रिकॉर्ड $action_text!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header("Location: block_officer_dashboard.php?month=$selected_month&year=$selected_year&udise_code=$udise_code&page=$page&per_page=$per_page");
    exit;
}

// कुल रिकॉर्ड्स की गिनती प्राप्त करें
 $count_sql = "SELECT COUNT(*) as total
        FROM teachers t
        JOIN attendance a ON t.id = a.teacher_id
        JOIN schools s ON t.school_id = s.id
        WHERE s.block_id = '$block_id' AND a.month = '$selected_month' AND a.year = '$selected_year'
        AND (t.class LIKE '%9-10%' OR t.class LIKE '%11-12%')"; // 3. यहाँ कक्षा फिल्टर बदला गया है

if (!empty($udise_code)) {
    $count_sql .= " AND s.udise_code = '$udise_code'";
}

 $count_stmt = $conn->prepare($count_sql);
 $count_stmt->execute();
 $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
 $total_pages = ceil($total_records / $per_page);

// उपस्थिति रिकॉर्ड प्राप्त करें
 $sql = "SELECT t.id as teacher_id, t.name, t.mobile, t.pran_no, t.uan_no, t.class, 
               a.total_attendance_days, a.in_time_count, a.out_time_count, a.unauthorized_absence_days, a.leave_days, a.remarks";
               
if ($has_status_column) {
    $sql .= ", a.status";
}

 $sql .= ", s.name as school_name, s.udise_code
        FROM teachers t
        JOIN attendance a ON t.id = a.teacher_id
        JOIN schools s ON t.school_id = s.id
        WHERE s.block_id = '$block_id' AND a.month = '$selected_month' AND a.year = '$selected_year'
        AND (t.class LIKE '%9-10%' OR t.class LIKE '%11-12%')"; // 3. यहाँ कक्षा फिल्टर बदला गया है

if (!empty($udise_code)) {
    $sql .= " AND s.udise_code = '$udise_code'";
}

// पेजिनेशन के लिए LIMIT और OFFSET जोड़ें
if ($per_page !== 'all') {
    $sql .= " LIMIT $per_page OFFSET $offset";
}

 $stmt = $conn->prepare($sql);
 $stmt->execute();
 $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 2. यहाँ शीर्षक बदला गया है -->
    <title>प्रखंड शिक्षा अधिकारी डैशबोर्ड - बिहार शिक्षा विभाग</title>
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
                <!-- 2. यहाँ शीर्षक बदला गया है -->
                <h4 class="mb-0">प्रखंड शिक्षा अधिकारी डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <!-- 2. यहाँ भूमिका बदली गई है -->
                        <small class="text-muted">प्रखंड शिक्षा अधिकारी, <?php echo $block_info['block_name']; ?></small>
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
                            <label for="month" class="form-label">महीना</label>
                            <select class="form-select" id="month" name="month">
                                <?php $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']; foreach($months as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo ($selected_month === $month) ? 'selected' : ''; ?>><?php echo $month; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="year" class="form-label">वर्ष</label>
                            <input type="number" class="form-control" id="year" name="year" value="<?php echo $selected_year; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="udise_code" class="form-label">UDISE कोड</label>
                            <input type="text" class="form-control" id="udise_code" name="udise_code" value="<?php echo htmlspecialchars($udise_code); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">प्रति पृष्ठ रिकॉर्ड</label>
                            <select class="form-select" id="per_page" name="per_page">
                                <option value="20" <?php echo ($per_page === 20) ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo ($per_page === 50) ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($per_page === 100) ? 'selected' : ''; ?>>100</option>
                                <option value="500" <?php echo ($per_page === 500) ? 'selected' : ''; ?>>500</option>
                                <option value="all" <?php echo ($per_page === 'all') ? 'selected' : ''; ?>>सभी</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> खोजें</button>
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
                    <!-- 4. यहाँ कार्ड हेडर बदला गया है -->
                    <h5 class="mb-0">उपस्थिति विवरणी (कक्षा 9-12)</h5>
                    <div>
                        <button type="submit" class="btn btn-light btn-sm"><i class="fas fa-save"></i> परिवर्तन सहेजें</button>
                        <?php if ($has_status_column): ?>
                        <button type="submit" name="action_forward" value="1" class="btn btn-success btn-sm"><i class="fas fa-paper-plane"></i> एडमिन को भेजें</button>
                        <button type="submit" name="action_send_back" value="1" class="btn btn-warning btn-sm"><i class="fas fa-undo"></i> विद्यालय को भेजें</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>क्रमांक</th>
                                    <?php if ($has_status_column): ?>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <?php endif; ?>
                                    <th>विद्यालय</th>
                                    <th>शिक्षक का नाम</th>
                                    <th>PRAN/UAN</th>
                                    <th>भुगतान हेतु दिवस</th>
                                    <th>अनधिकृत अनुपस्थिति</th>
                                    <th>अवकाश</th>
                                    <th>अभियुक्ति</th>
                                    <?php if ($has_status_column): ?>
                                    <th>स्थिति</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($attendance_records) > 0): ?>
                                    <?php $serial_number = ($page - 1) * $per_page + 1; ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo $serial_number; ?></td>
                                        <?php if ($has_status_column): ?>
                                        <td><input type="checkbox" name="teacher_ids[]" value="<?php echo $record['teacher_id']; ?>" class="teacher-checkbox"></td>
                                        <?php endif; ?>
                                        <td><?php echo $record['school_name']; ?><br><small><?php echo $record['udise_code']; ?></small></td>
                                        <td><?php echo $record['name']; ?></td>
                                        <td><?php echo $record['pran_no'] ?: $record['uan_no']; ?></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['teacher_id']; ?>][total_attendance_days]" value="<?php echo $record['total_attendance_days'] ?? 0; ?>"></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['teacher_id']; ?>][unauthorized_absence_days]" value="<?php echo $record['unauthorized_absence_days'] ?? 0; ?>"></td>
                                        <td><input type="number" class="form-control attendance-input" name="attendance_data[<?php echo $record['teacher_id']; ?>][leave_days]" value="<?php echo $record['leave_days'] ?? 0; ?>"></td>
                                        <td><textarea class="form-control" name="attendance_data[<?php echo $record['teacher_id']; ?>][remarks]" rows="1"><?php echo $record['remarks'] ?? ''; ?></textarea></td>
                                        <?php if ($has_status_column): ?>
                                        <td>
                                            <?php
                                            $status_text = 'Pending';
                                            $status_class = 'bg-secondary';
                                            if (isset($record['status'])) {
                                                if ($record['status'] === 'forwarded_to_admin') { $status_text = 'Forwarded'; $status_class = 'bg-success'; }
                                                elseif ($record['status'] === 'sent_back_to_school') { $status_text = 'Sent Back'; $status_class = 'bg-warning'; }
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> status-badge"><?php echo $status_text; ?></span>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php $serial_number++; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?php echo $has_status_column ? '10' : '9'; ?>" class="text-center">कोई रिकॉर्ड नहीं मिला।</td></tr>
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
                                    <a class="page-link" href="?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&udise_code=<?php echo $udise_code; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php 
                                $max_visible_pages = 5;
                                $start_page = max(1, $page - floor($max_visible_pages / 2));
                                $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?month='.$selected_month.'&year='.$selected_year.'&udise_code='.$udise_code.'&per_page='.$per_page.'&page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active_class = ($i == $page) ? 'active' : '';
                                    echo '<li class="page-item '.$active_class.'"><a class="page-link" href="?month='.$selected_month.'&year='.$selected_year.'&udise_code='.$udise_code.'&per_page='.$per_page.'&page='.$i.'">'.$i.'</a></li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?month='.$selected_month.'&year='.$selected_year.'&udise_code='.$udise_code.'&per_page='.$per_page.'&page='.$total_pages.'">'.$total_pages.'</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&udise_code=<?php echo $udise_code; ?>&per_page=<?php echo $per_page; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
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
        
        <?php if ($has_status_column): ?>
        // सभी चेकबॉक्स का चयन करें
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.teacher-checkbox').forEach(cb => cb.checked = this.checked);
        });
        <?php endif; ?>
    </script>
</body>
</html>