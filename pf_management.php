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
 $month_for_db = date('Y-m'); // Format: YYYY-MM

// जांचें कि वर्तमान महीना लॉक है या नहीं
 $stmt = $conn->prepare("SELECT is_locked FROM attendance 
                         WHERE teacher_id IN (SELECT id FROM teachers WHERE school_id = ?) 
                         AND month = ? AND year = ? AND is_locked = 1 LIMIT 1");
 $stmt->execute([$school_id, $current_month, $current_year]);
 $is_month_locked = $stmt->fetchColumn() ? true : false;

// जांचें कि महीना लॉक है या नहीं
 $school_udise = $school_info['udise_code'];
 $stmt = $conn->prepare("SELECT id FROM monthly_attendance_lock WHERE school_udise = ? AND month = ?");
 $stmt->execute([$school_udise, $month_for_db]);

if ($stmt->rowCount() == 0) {
    $lock_error = "वर्तमान महीना अभी लॉक नहीं है। कृपया पहले महीना लॉक करें।";
} else {
    // सभी जेनरेट किए गए पीएफ प्राप्त करें
    $pf_sql = "SELECT * FROM pf_submissions WHERE school_udise = ? AND month = ?";
    $stmt = $conn->prepare($pf_sql);
    $stmt->execute([$school_udise, $month_for_db]);
    $pf_list = $stmt;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>पीएफ प्रबंधन - बिहार शिक्षा विभाग</title>
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
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
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
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
            <li class="nav-item"><a class="nav-link active" href="pf_management.php"><i class="fas fa-file-pdf"></i> पीएफ प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">पीएफ प्रबंधन - <?php echo date('F Y', strtotime($month_for_db.'-01')); ?></h4>
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

        <?php if (isset($lock_error)): ?>
            <div class="alert alert-warning">
                <?php echo $lock_error; ?>
            </div>
            <div class="text-center mt-3">
                <a href="attendance.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> उपस्थिति विवरणी पर जाएं
                </a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">पीएफ प्रबंधन</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['msg'])) { echo "<div class='alert alert-success'>".$_GET['msg']."</div>"; } ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
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
                                <?php 
                                $count = 1; 
                                if ($pf_list->rowCount() > 0) {
                                    while($pf = $pf_list->fetch(PDO::FETCH_ASSOC)): 
                                ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo htmlspecialchars($pf['category']); ?></td>
                                    <td><?php echo htmlspecialchars($pf['class_group']); ?></td>
                                    <td><?php echo htmlspecialchars($pf['reference_number']); ?></td>
                                    <td>
                                        <a href="<?php echo $pf['generated_pdf_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-download"></i> डाउनलोड करें
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($pf['status'] == 'Generated'): ?>
                                            <form action="upload_signed_pf.php" method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="pf_id" value="<?php echo $pf['id']; ?>">
                                                <input type="file" name="signed_pdf" class="form-control form-control-sm mb-2" accept=".pdf" required>
                                                <button type="submit" class="btn btn-sm btn-success">अपलोड करें</button>
                                            </form>
                                        <?php elseif ($pf['uploaded_pdf_path']): ?>
                                            <a href="<?php echo $pf['uploaded_pdf_path']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-eye"></i> देखें
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">अपलोड नहीं किया गया</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($pf['status'] == 'Paid') ? 'success' : 'secondary'; ?>">
                                            <?php 
                                            $status_text = '';
                                            switch($pf['status']){
                                                case 'Generated': $status_text = 'जेनरेट किया गया'; break;
                                                case 'Uploaded': $status_text = 'अपलोड हो गया'; break;
                                                case 'Pending at DDO': $status_text = 'DDO को भेजा गया'; break;
                                                case 'Pending at Block Officer': $status_text = 'प्रखंड शिक्षा पदाधिकारी को भेजा गया'; break;
                                                case 'Pending at District': $status_text = 'जिला कार्यालय को भेजा गया'; break;
                                                case 'Paid': $status_text = 'भुगतान किया गया'; break;
                                                default: $status_text = $pf['status'];
                                            }
                                            echo $status_text; 
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile; 
                                } else {
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center">कोई पीएफ डेटा नहीं मिला।</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="attendance.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> उपस्थिति विवरणी पर जाएं
                </a>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>