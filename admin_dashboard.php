<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन है
checkUserType('admin');

// सारांश आँकड़े प्राप्त करें
 $stmt = $conn->query("SELECT COUNT(*) as count FROM schools");
 $school_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

 $stmt = $conn->query("SELECT COUNT(*) as count FROM teachers");
 $teacher_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

 $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type != 'admin'");
 $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

 $stmt = $conn->query("SELECT COUNT(*) as count FROM salary_complaints WHERE status = 'pending'");
 $pending_complaints = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// उपयोगकर्ता जोड़ने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, password, user_type, name, email, mobile, district_id, block_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['username'],
            securePassword($_POST['password']),
            $_POST['user_type'],
            $_POST['name'],
            $_POST['email'],
            $_POST['mobile'],
            $_POST['district_id'] ?: null,
            $_POST['block_id'] ?: null
        ]);
        $_SESSION['success_message'] = "उपयोगकर्ता सफलतापूर्वक जोड़ा गया!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: admin_dashboard.php');
    exit;
}

// सभी जिले और प्रखंड प्राप्त करें
 $stmt = $conn->query("SELECT * FROM districts ORDER BY name");
 $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>एडमिन डैशबोर्ड - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6a1b9a; --secondary-color: #9c27b0; --accent-color: #ce93d8; --light-color: #f3e5f5; --dark-color: #4a148c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; overflow-y: auto; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; font-size: 0.9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 20px; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-weight: 600; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .stat-card { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px; padding: 20px; text-align: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(106, 27, 154, 0.3); }
        .stat-card i { font-size: 2.5rem; margin-bottom: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px 15px 0 0; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); width: 280px; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार -->
    <div class="sidebar" id="sidebar">
        <div class="p-4 text-center">
            <h4>बिहार शिक्षा विभाग</h4>
            <p class="mb-0">एडमिन पैनल</p>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="fas fa-users-cog"></i> उपयोगकर्ता प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_management.php"><i class="fas fa-money-check-alt"></i> वेतन प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="eshikshakosh_data.php"><i class="fas fa-database"></i> ई-शिक्षकोष डेटा</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_letters.php"><i class="fas fa-envelope"></i> पत्र प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_notices.php"><i class="fas fa-bullhorn"></i> नोटिस प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_sliders.php"><i class="fas fa-images"></i> स्लाइडर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_categories.php"><i class="fas fa-tags"></i> श्रेणी प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_months.php"><i class="fas fa-calendar-alt"></i> महीना प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">एडमिन डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">System Administrator</small>
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

        <!-- आँकड़े कार्ड -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-school"></i>
                    <h3><?php echo $school_count; ?></h3>
                    <p>कुल विद्यालय</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3><?php echo $teacher_count; ?></h3>
                    <p>कुल शिक्षक</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $user_count; ?></h3>
                    <p>कुल उपयोगकर्ता</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3><?php echo $pending_complaints; ?></h3>
                    <p>लंबित शिकायतें</p>
                </div>
            </div>
        </div>

        <!-- त्वरित कार्य -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">त्वरित कार्य</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                                <i class="fas fa-user-plus"></i> नया उपयोगकर्ता जोड़ें
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="manage_schools.php?action=upload" class="btn btn-primary">
                                <i class="fas fa-upload"></i> विद्यालय डेटा अपलोड करें
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="salary_management.php?action=upload" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i> वेतन डेटा अपलोड करें
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- उपयोगकर्ता मोडल -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">नया उपयोगकर्ता जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="admin_dashboard.php">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">नाम</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">उपयोगकर्ता नाम</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">पासवर्ड</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="user_type" class="form-label">उपयोगकर्ता प्रकार</label>
                                <select class="form-select" id="user_type" name="user_type" required onchange="toggleDistrictBlockFields()">
                                    <option value="" selected disabled>चुनें</option>
                                    <option value="school">विद्यालय</option>
                                    <option value="ddo">DDO</option>
                                    <option value="block_officer">प्रखंड शिक्षा पदाधिकारी</option>
                                    <option value="district_staff">जिला कार्यालय स्टाफ</option>
                                    <option value="district_program_officer">जिला कार्यक्रम पदाधिकारी</option>
                                    <option value="district_education_officer">जिला शिक्षा पदाधिकारी</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">ईमेल</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="mobile" class="form-label">मोबाइल</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" required>
                            </div>
                        </div>
                        <div class="row" id="districtBlockFields" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label for="district_id" class="form-label">जिला</label>
                                <select class="form-select" id="district_id" name="district_id" onchange="loadBlocks(this.value)">
                                    <option value="">चुनें</option>
                                    <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo $district['id']; ?>"><?php echo $district['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="block_id" class="form-label">प्रखंड</label>
                                <select class="form-select" id="block_id" name="block_id">
                                    <option value="">पहले जिला चुनें</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-primary">जोड़ें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        function toggleDistrictBlockFields() {
            const userType = document.getElementById('user_type').value;
            const fields = document.getElementById('districtBlockFields');
            if (userType === 'ddo' || userType === 'block_officer' || userType === 'district_staff' || userType === 'district_program_officer' || userType === 'district_education_officer') {
                fields.style.display = 'flex';
            } else {
                fields.style.display = 'none';
            }
        }

        function loadBlocks(districtId) {
            if (!districtId) {
                document.getElementById('block_id').innerHTML = '<option value="">पहले जिला चुनें</option>';
                return;
            }
            fetch(`get_blocks.php?district_id=${districtId}`)
                .then(response => response.json())
                .then(data => {
                    let blockOptions = '<option value="">प्रखंड चुनें</option>';
                    data.forEach(block => {
                        blockOptions += `<option value="${block.id}">${block.name}</option>`;
                    });
                    document.getElementById('block_id').innerHTML = blockOptions;
                });
        }
    </script>
</body>
</html>