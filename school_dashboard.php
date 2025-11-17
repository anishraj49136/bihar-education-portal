<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

// विद्यालय की जानकारी प्राप्त करें
 $school_id = $_SESSION['school_id'];
 $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
 $stmt->execute([$school_id]);
 $school = $stmt->fetch(PDO::FETCH_ASSOC);

// विद्यालय में शिक्षकों की संख्या प्राप्त करें
 $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teachers WHERE school_id = ?");
 $stmt->execute([$school_id]);
 $teacher_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// वर्तमान महीने के लिए लॉक की गई उपस्थिति प्राप्त करें
 $current_month = date('F');
 $current_year = date('Y');
 $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance a 
                       JOIN teachers t ON a.teacher_id = t.id 
                       WHERE t.school_id = ? AND a.month = ? AND a.year = ? AND a.is_locked = 1");
 $stmt->execute([$school_id, $current_month, $current_year]);
 $locked_attendance = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// शिक्षकों की सूची प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM teachers WHERE school_id = ?");
 $stmt->execute([$school_id]);
 $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// जिला और प्रखंड की जानकारी प्राप्त करें
 $stmt = $conn->prepare("SELECT d.name as district_name, b.name as block_name 
                       FROM districts d 
                       JOIN blocks b ON d.id = b.district_id 
                       WHERE d.id = ? AND b.id = ?");
 $stmt->execute([$school['district_id'], $school['block_id']]);
 $location = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यालय डैशबोर्ड - बिहार शिक्षा विभाग</title>
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
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(106, 27, 154, 0.3);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
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
            <p class="mb-0">विद्यालय डैशबोर्ड</p>
        </div>
        
        <hr class="text-white">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="school_dashboard.php">
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
                <a class="nav-link" href="teachers.php">
                    <i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="attendance.php">
                    <i class="fas fa-calendar-check"></i> उपस्थिति विवरणी
					</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pf_management.php">
                    <i class="fas fa-file-pdf"></i> पीडीएफ प्रबंधन
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
            <li class="nav-item">
                <a class="nav-link" href="letters.php">
                    <i class="fas fa-envelope"></i> पत्र
                </a>
			</li>
            <li class="nav-item">
                <a class="nav-link" href="notices.php">
                    <i class="fas fa-bullhorn"></i> नोटिस
                </a>
            </li>
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
                <h4 class="mb-0">विद्यालय डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2">
                        <?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">विद्यालय</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- स्वागत संदेश -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5>स्वागत, <?php echo $_SESSION['name']; ?>!</h5>
                        <p class="mb-0"><?php echo $school['name']; ?>, <?php echo $location['block_name']; ?>, <?php echo $location['district_name']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- आँकड़े कार्ड -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3><?php echo $teacher_count; ?></h3>
                    <p>कुल शिक्षक</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-lock"></i>
                    <h3><?php echo $locked_attendance; ?></h3>
                    <p>लॉक की गई उपस्थिति</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3><?php echo date('F Y'); ?></h3>
                    <p>वर्तमान महीना</p>
                </div>
            </div>
        </div>
        
        <!-- शिक्षक सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">शिक्षक सूची</h4>
                <a href="teachers.php" class="btn btn-sm btn-danger">सभी शिक्षक का PRAN/UAN संख्या अपडेट करने के लिए यहां क्लिक करें  </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>क्र. सं.</th>
                                <th>नाम</th>
                                <th>ई-शिक्षकोष ID</th>
                                <th>मोबाइल नंबर</th>
                                <th>श्रेणी</th>
                                <th>PRAN/UAN No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($teachers) > 0): ?>
                                <?php foreach (array_slice($teachers, 0, 35) as $index => $teacher): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo $teacher['name']; ?></td>
                                    <td><?php echo $teacher['eshikshakosh_id']; ?></td>
                                    <td><?php echo $teacher['mobile']; ?></td>
                                    <td><?php echo $teacher['category']; ?></td>
                                    <td><?php echo $teacher['pran_no'] ?: $teacher['uan_no'];?></td>
                                        
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">कोई शिक्षक नहीं मिला</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
    </script>
</body>
</html>