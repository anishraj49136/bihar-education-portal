<?php
// बिना किसी आउटपुट के सबसे पहले सत्र शुरू करें
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// त्रुटि रिपोर्टिंग सक्षम करें
error_reporting(E_ALL);
ini_set('display_errors', 1);

// सत्र आईडी को लॉग करें
 $log_message = date('Y-m-d H:i:s') . " - district_pf_dashboard.php - Session ID: " . session_id() . "\n";
 $log_message .= "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
 $log_message .= "User Type: " . ($_SESSION['user_type'] ?? 'Not set') . "\n";
file_put_contents('session_debug.log', $log_message, FILE_APPEND);

// कॉन्फिगरेशन फ़ाइल शामिल करें
require_once 'config.php';

// यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType('district_staff');

// जिला आईडी प्राप्त करें
 $district_id = null;

// पहले सत्र से जांचें
if (isset($_SESSION['district_id'])) {
    $district_id = $_SESSION['district_id'];
} 
// अगर सत्र में नहीं है, तो डेटाबेस से प्राप्त करें
else {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id) {
            $stmt = $conn->prepare("SELECT district_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['district_id'])) {
                $district_id = $result['district_id'];
                // सत्र में सेव करें ताकि बाद में फिर से क्वेरी न करनी पड़े
                $_SESSION['district_id'] = $district_id;
            }
        }
    } catch (PDOException $e) {
        // क्वेरी फेल होने पर
        error_log("Error fetching district_id: " . $e->getMessage());
    }
}

// अगर भी जिला आईडी नहीं मिली, तो त्रुटि दिखाएं
if (!$district_id) {
    $_SESSION['error_message'] = "जिला शिक्षा पदाधिकारी के लिए जिला ID निर्धारित नहीं है। कृपया लॉगिन करें।";
    header("Location: login.php");
    exit;
}

// जिले की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT d.name as district_name FROM districts d WHERE d.id = ?");
 $stmt->execute([$district_id]);
 $district_info = $stmt->fetch(PDO::FETCH_ASSOC);

// फिल्टर मान प्राप्त करें
 $selected_month = $_GET['month'] ?? date('F');
 $selected_year = $_GET['year'] ?? date('Y');
 $udise_code = $_GET['udise_code'] ?? '';
 $active_tab = $_GET['tab'] ?? 'pf';

// पेजिनेशन वेरिएबल्स
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
 $offset = ($page - 1) * $per_page;

// PF रिकॉर्ड्स प्राप्त करें
 $pf_records = [];
 $total_records = 0;
 $total_pages = 0;

try {
    // कुल रिकॉर्ड्स की गिनती प्राप्त करें
    $count_sql = "SELECT COUNT(*) as total
                  FROM pf_submissions pf
                  JOIN schools s ON pf.school_udise = s.udise_code
                  JOIN blocks b ON s.block_id = b.id
                  WHERE b.district_id = ? AND pf.status = 'forwarded_to_district'";
    
    $count_params = [$district_id];
    if (!empty($udise_code)) {
        $count_sql .= " AND s.udise_code LIKE ?";
        $count_params[] = '%' . $udise_code . '%';
    }
    
    // Add Month/Year filter if exists
    if (!empty($selected_month) && !empty($selected_year)) {
        $date_object = DateTime::createFromFormat('F', $selected_month);
        if ($date_object) {
            $month_number = $date_object->format('m');
            $year_month = $selected_year . '-' . $month_number;
            $count_sql .= " AND pf.month LIKE ?";
            $count_params[] = $year_month . '%';
        }
    }
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // PF रिकॉर्ड्स प्राप्त करें
    $pf_sql = "SELECT pf.*, 
               s.name as school_name, s.udise_code,
               b.name as block_name,
               pf.status as pf_status
               FROM pf_submissions pf
               JOIN schools s ON pf.school_udise = s.udise_code
               JOIN blocks b ON s.block_id = b.id
               WHERE b.district_id = ? AND pf.status = 'forwarded_to_district'";
    
    $pf_params = [$district_id];
    
    // Add UDISE filter if exists
    if (!empty($udise_code)) {
        $pf_sql .= " AND s.udise_code LIKE ?";
        $pf_params[] = '%' . $udise_code . '%';
    }
    
    // Add Month/Year filter if exists
    if (!empty($selected_month) && !empty($selected_year)) {
        $date_object = DateTime::createFromFormat('F', $selected_month);
        if ($date_object) {
            $month_number = $date_object->format('m');
            $year_month = $selected_year . '-' . $month_number;
            $pf_sql .= " AND pf.month LIKE ?";
            $pf_params[] = $year_month . '%';
        }
    }
    
    // पेजिनेशन के लिए LIMIT और OFFSET जोड़ें
    if ($per_page !== 'all') {
        $pf_sql .= " LIMIT $per_page OFFSET $offset";
    }
    
    $pf_stmt = $conn->prepare($pf_sql);
    $pf_stmt->execute($pf_params);
    $pf_records = $pf_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // डेटाबेस त्रुटि होने पर लॉग करें, उपयोगकर्ता को न दिखाएं
    error_log("Error fetching PF records: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जिला शिक्षा अधिकारी डैशबोर्ड - बिहार शिक्षा विभाग</title>
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
        .thead { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .status-badge { font-size: 0.8em; padding:4px 8px; border-radius: 12px; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        .pagination-info { margin-right: 20px; }
        .page-link { color: var(--primary-color); }
        .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
        .nav-tabs .nav-link { color: var(--primary-color); font-weight: 500; }
        .nav-tabs .nav-link.active { color: var(--secondary-color); background-color: rgba(106, 27, 154, 0.1); border-color: var(--primary-color); }
        /* मोबाइल रेस्पॉन्सिव स्टाइल */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .main-content { margin-left: 0; } 
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } 
            .table-responsive { font-size: 0.85rem; }
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
                <h4 class="mb-0">जिला शिक्षा अधिकारी डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">
                            <?php 
                            $user_type = $_SESSION['user_type'];
                            $role_text = 'जिला शिक्षा अधिकारी';
                            if ($user_type === 'district_staff') {
                                $role_text = 'जिला कर्मचारी';
                            } elseif ($user_type === 'district_program_officer') {
                                $role_text = 'जिला कार्यक्रम अधिकारी';
                            } elseif ($user_type === 'district_education_officer') {
                                $role_text = 'जिला शिक्षा अधिकारी';
                            } elseif ($user_type === 'admin') {
                                $role_text = 'व्यवस्थापक';
                            }
                            echo $role_text . ', ' . $district_info['district_name']; 
                            ?>
                        </small>
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

        <!-- PF फिल्टर कार्ड -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0">पीएफ फॉर्म खोजें</h5></div>
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
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> खोजें</button>
                            <a href="district_pf_dashboard.php" class="btn btn-secondary ms-2"><i class="fas fa-redo"></i> रीसेट</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- PF रिकॉर्ड्स -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">पीएफ फॉर्म (ब्लॉक अधिकारी द्वारा भेजे गए)</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="approveSelectedPF">
                        <i class="fas fa-check"></i> चयनित को स्वीकृत करें
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" id="sendBackSelectedPF">
                        <i class="fas fa-undo"></i> चयनित को वापस भेजें
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllPF"></th>
                                <th>विद्यालय</th>
                                <th>ब्लॉक</th>
                                <th>श्रेणी</th>
                                <th>कक्षा समूह</th>
                                <th>महीना</th>
                                <th>जेनरेट किया गया पीएफ</th>
                                <th>हस्ताक्षरित पीएफ</th>
                                <th>क्रियाएं</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pf_records) > 0): ?>
                                <?php foreach ($pf_records as $record): ?>
                                <tr>
                                    <td><input type="checkbox" name="pf_ids[]" value="<?php echo $record['id']; ?>" class="pf-checkbox"></td>
                                    <td><?php echo $record['school_name']; ?><br><small><?php echo $record['udise_code']; ?></small></td>
                                    <td><?php echo htmlspecialchars($record['block_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['category']); ?></td>
                                    <td><?php echo htmlspecialchars($record['class_group']); ?></td>
                                    <td><?php echo date('F Y', strtotime($record['month'] . '-01')); ?></td>
                                    <td>
                                        <?php if (!empty($record['generated_pdf_path'])): ?>
                                            <a href="<?php echo $record['generated_pdf_path']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i> डाउनलोड
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">उपलब्ध नहीं</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($record['uploaded_pdf_path'])): ?>
                                            <a href="<?php echo $record['uploaded_pdf_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> देखें
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">उपलब्ध नहीं</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="viewPFForm(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-eye"></i> देखें
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" onclick="approvePFForm(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-check"></i> स्वीकृत करें
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="sendBackPFForm(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-undo"></i> वापस भेजें
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">कोई पीएफ फॉर्म नहीं मिला।</td></tr>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // सत्र जानकारी कंसोल में लॉग करें
        console.log("Current Session ID (from client):", "<?php echo session_id(); ?>");
        
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
        
        // सभी PF चेकबॉक्स का चयन करें
        document.getElementById('selectAllPF').addEventListener('change', function() {
            document.querySelectorAll('.pf-checkbox').forEach(cb => cb.checked = this.checked);
        });
        
        // PF फॉर्म देखने के लिए
        function viewPFForm(id) {
            console.log("Viewing PF Form with ID:", id);
            window.open('view_pf_form.php?id=' + id, '_blank');
        }
        
        // सत्र जांचने के लिए फंक्शन
        function checkSession() {
            return fetch('check_session.php', {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.logged_in) {
                    alert('आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।');
                    window.location.href = 'login.php';
                    return false;
                }
                return true;
            })
            .catch(error => {
                console.error('Session check error:', error);
                return false;
            });
        }
        
        // PF फॉर्म स्वीकृत करने के लिए
        function approvePFForm(id) {
            console.log("Approving PF Form with ID:", id);
            
            // पहले सत्र जांचें
            checkSession().then(sessionValid => {
                if (!sessionValid) return;
                
                if (confirm('क्या आप इस पीएफ फॉर्म को स्वीकृत करना चाहते हैं?')) {
                    fetch('update_pf_status.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=approve_district&id=' + id
                    })
                    .then(response => {
                        console.log('Server response:', response);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.session_expired) {
                            alert('आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।');
                            window.location.href = 'login.php';
                            return;
                        }
                        
                        if (data.success) {
                            alert('पीएफ फॉर्म सफलतापूर्वक स्वीकृत किया गया!');
                            location.reload();
                        } else {
                            alert('त्रुटि: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('त्रुटि: कृपया बाद में पुन: प्रयास करें।');
                    });
                }
            });
        }
        
        // PF वापस भेजने के लिए
        function sendBackPFForm(id) {
            console.log("Sending back PF Form with ID:", id);
            
            // पहले सत्र जांचें
            checkSession().then(sessionValid => {
                if (!sessionValid) return;
                
                if (confirm('क्या आप इस पीएफ फॉर्म को ब्लॉक अधिकारी को वापस भेजना चाहते हैं?')) {
                    fetch('update_pf_status.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=send_back_to_block&id=' + id
                    })
                    .then(response => {
                        console.log('Server response:', response);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.session_expired) {
                            alert('आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।');
                            window.location.href = 'login.php';
                            return;
                        }
                        
                        if (data.success) {
                            alert('पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजा गया!');
                            location.reload();
                        } else {
                            alert('त्रुटि: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('त्रुटि: कृपया बाद में पुन: प्रयास करें।');
                    });
                }
            });
        }
        
        // चयनित PF स्वीकृत करने के लिए
        document.getElementById('approveSelectedPF').addEventListener('click', function() {
            const selectedPFs = [];
            document.querySelectorAll('.pf-checkbox:checked').forEach(cb => {
                selectedPFs.push(cb.value);
            });
            
            if (selectedPFs.length === 0) {
                alert('कृपया कम से कम एक पीएफ फॉर्म का चयन करें।');
                return;
            }
            
            // पहले सत्र जांचें
            checkSession().then(sessionValid => {
                if (!sessionValid) return;
                
                console.log("Approving multiple PFs:", selectedPFs);
                if (confirm('क्या आप चयनित पीएफ फॉर्म को स्वीकृत करना चाहते हैं?')) {
                    fetch('update_pf_status.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=approve_multiple_district&ids=' + selectedPFs.join(',')
                    })
                    .then(response => {
                        console.log('Server response:', response);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.session_expired) {
                            alert('आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।');
                            window.location.href = 'login.php';
                            return;
                        }
                        
                        if (data.success) {
                            alert('चयनित पीएफ फॉर्म सफलतापूर्वक स्वीकृत किए गए!');
                            location.reload();
                        } else {
                            alert('त्रुटि: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('त्रुटि: कृपया बाद में पुन: प्रयास करें।');
                    });
                }
            });
        });
        
        // चयनित PF वापस भेजने के लिए
        document.getElementById('sendBackSelectedPF').addEventListener('click', function() {
            const selectedPFs = [];
            document.querySelectorAll('.pf-checkbox:checked').forEach(cb => {
                selectedPFs.push(cb.value);
            });
            
            if (selectedPFs.length === 0) {
                alert('कृपया कम से कम एक पीएफ फॉर्म का चयन करें।');
                return;
            }
            
            // पहले सत्र जांचें
            checkSession().then(sessionValid => {
                if (!sessionValid) return;
                
                console.log("Sending back multiple PFs:", selectedPFs);
                if (confirm('क्या आप चयनित पीएफ फॉर्म को ब्लॉक अधिकारी को वापस भेजना चाहते हैं?')) {
                    fetch('update_pf_status.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=send_back_multiple_to_block&ids=' + selectedPFs.join(',')
                    })
                    .then(response => {
                        console.log('Server response:', response);
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.session_expired) {
                            alert('आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।');
                            window.location.href = 'login.php';
                            return;
                        }
                        
                        if (data.success) {
                            alert('चयनित पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजे गए!');
                            location.reload();
                        } else {
                            alert('त्रुटि: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('त्रुटि: कृपया बाद में पुन: प्रयास करें।');
                    });
                }
            });
        });
    </script>
</body>
</html>