<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

// विद्यालय की जानकारी प्राप्त करें
 $school_id = $_SESSION['school_id'];
 $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
 $stmt->execute([$school_id]);
 $school = $stmt->fetch(PDO::FETCH_ASSOC);

// जिले और प्रखंडों की सूची प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM districts ORDER BY name");
 $stmt->execute();
 $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// प्रधानाध्यापक जानकारी अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_principal') {
    try {
        // केवल प्रधानाध्यापक की जानकारी अपडेट करें
        $stmt = $conn->prepare("UPDATE schools SET 
                               head_of_school = ?, 
                               head_of_school_number = ?, 
                               hos_email = ?, 
                               school_min_class = ?, 
                               school_max_class = ?, 
                               updated_at = CURRENT_TIMESTAMP 
                               WHERE id = ?");
        $stmt->execute([
            $_POST['head_of_school'],
            $_POST['head_of_school_number'],
            $_POST['hos_email'],
            $_POST['school_min_class'],
            $_POST['school_max_class'],
            $school_id
        ]);
        
        $success_message = "प्रधानाध्यापक जानकारी सफलतापूर्वक अपडेट की गई!";
        
        // अपडेट की गई जानकारी प्राप्त करें
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "प्रधानाध्यापक जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// विद्यालय आधारभूत संरचना अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_infrastructure') {
    try {
        // विद्यालय आधारभूत संरचना की जानकारी अपडेट करें
        $stmt = $conn->prepare("UPDATE schools SET 
                               good_rooms = ?, 
                               bad_rooms = ?, 
                               working_toilets = ?, 
                               bad_toilets = ?, 
                               has_ramp = ?, 
                               working_handpumps = ?, 
                               has_samrasal = ?, 
                               working_samrasal = ?, 
                               bad_samrasal = ?, 
                               has_electricity = ?, 
                               consumer_number = ?, 
                               working_fans = ?, 
                               good_bench_desks = ?, 
                               bad_bench_desks = ?, 
                               is_landless = ?, 
                               has_extra_land = ?, 
                               extra_land_area_sqft = ?, 
                               rooms_needed = ?, 
                               updated_at = CURRENT_TIMESTAMP 
                               WHERE id = ?");
        $stmt->execute([
            $_POST['good_rooms'],
            $_POST['bad_rooms'],
            $_POST['working_toilets'],
            $_POST['bad_toilets'],
            isset($_POST['has_ramp']) ? 1 : 0,
            $_POST['working_handpumps'],
            $_POST['has_samrasal'],
            $_POST['working_samrasal'],
            $_POST['bad_samrasal'],
            $_POST['has_electricity'],
            $_POST['consumer_number'],
            $_POST['working_fans'],
            $_POST['good_bench_desks'],
            $_POST['bad_bench_desks'],
            $_POST['is_landless'],
            $_POST['has_extra_land'],
            $_POST['extra_land_area_sqft'],
            $_POST['rooms_needed'],
            $school_id
        ]);
        
        $success_message = "विद्यालय आधारभूत संरचना सफलतापूर्वक अपडेट की गई!";
        
        // अपडेट की गई जानकारी प्राप्त करें
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "विद्यालय आधारभूत संरचना अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// पासवर्ड अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    try {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // नया पासवर्ड और पुष्टि पासवर्ड मेल खाते हैं
        if ($new_password === $confirm_password) {
            // पासवर्ड को हैश करें और अपडेट करें
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE schools SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$hashed_password, $school_id]);
            
            $success_message = "पासवर्ड सफलतापूर्वक अपडेट किया गया!";
            
            // अपडेट की गई जानकारी प्राप्त करें
            $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
            $stmt->execute([$school_id]);
            $school = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error_message = "नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते!";
        }
    } catch (PDOException $e) {
        $error_message = "पासवर्ड अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// चयनित जिले के प्रखंड प्राप्त करें
if (isset($school['district_id'])) {
    $stmt = $conn->prepare("SELECT * FROM blocks WHERE district_id = ? ORDER BY name");
    $stmt->execute([$school['district_id']]);
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यालय प्रोफाइल - बिहार शिक्षा विभाग</title>
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
        
        .conditional-field {
            display: none;
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
            <li class="nav-item"><a class="nav-link active" href="school_profile.php"><i class="fas fa-school"></i> विद्यालय प्रोफाइल</a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-user-graduate"></i> नामांकन</a></li>
            <li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण</a></li>
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
            <li class="nav-item"><a class="nav-link" href="pf_management.php"><i class="fas fa-file-pdf"></i> पीएफ प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">विद्यालय प्रोफाइल</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">विद्यालय</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- सफलता/त्रुटि संदेश -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- विद्यालय प्रोफाइल कार्ड -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-school me-2"></i>
                विद्यालय की जानकारी
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>विद्यालय की जानकारी</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>विद्यालय का नाम:</strong></td>
                                <td><?php echo $school['name']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>यूडाइस कोड:</strong></td>
                                <td><?php echo $school['udise_code']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>जिला:</strong></td>
                                <td><?php 
                                    foreach ($districts as $district) {
                                        if ($district['id'] == $school['district_id']) {
                                            echo $district['name'];
                                            break;
                                        }
                                    }
                                ?></td>
                            </tr>
                            <tr>
                                <td><strong>प्रखंड:</strong></td>
                                <td><?php 
                                    if (isset($blocks)) {
                                        foreach ($blocks as $block) {
                                            if ($block['id'] == $school['block_id']) {
                                                echo $block['name'];
                                                break;
                                            }
                                        }
                                    }
                                ?></td>
                            </tr>
                            <tr>
                                <td><strong>ग्राम:</strong></td>
                                <td><?php echo $school['village_name']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>पिनकोड:</strong></td>
                                <td><?php echo $school['pincode']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>प्रधानाध्यापक की जानकारी</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>प्रधानाध्यापक का नाम:</strong></td>
                                <td><?php echo $school['head_of_school']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>मोबाइल नंबर:</strong></td>
                                <td><?php echo $school['head_of_school_number']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>ईमेल:</strong></td>
                                <td><?php echo $school['hos_email']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>न्यूनतम कक्षा:</strong></td>
                                <td><?php echo $school['school_min_class']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>अधिकतम कक्षा:</strong></td>
                                <td><?php echo $school['school_max_class']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- प्रधानाध्यापक जानकारी अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-edit me-2"></i>
                प्रधानाध्यापक जानकारी अपडेट करें
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post">
                    <input type="hidden" name="action" value="update_principal">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="head_of_school" class="form-label">प्रधानाध्यापक का नाम</label>
                            <input type="text" class="form-control" id="head_of_school" name="head_of_school" 
                                   value="<?php echo $school['head_of_school']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="head_of_school_number" class="form-label">मोबाइल नंबर</label>
                            <input type="tel" class="form-control" id="head_of_school_number" name="head_of_school_number" 
                                   value="<?php echo $school['head_of_school_number']; ?>" pattern="[0-9]{10}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hos_email" class="form-label">ईमेल</label>
                            <input type="email" class="form-control" id="hos_email" name="hos_email" 
                                   value="<?php echo $school['hos_email']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="school_min_class" class="form-label">न्यूनतम कक्षा</label>
                            <select class="form-select" id="school_min_class" name="school_min_class" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($school['school_min_class'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="school_max_class" class="form-label">अधिकतम कक्षा</label>
                            <select class="form-select" id="school_max_class" name="school_max_class" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($school['school_max_class'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">अपडेट करें</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- विद्यालय आधारभूत संरचना अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-building me-2"></i>
                विद्यालय आधारभूत संरचना अपडेट करें
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post">
                    <input type="hidden" name="action" value="update_infrastructure">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="good_rooms" class="form-label">विद्यालय में अच्छे कमरे की संख्या</label>
                            <input type="number" class="form-control" id="good_rooms" name="good_rooms" 
                                   value="<?php echo $school['good_rooms']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_rooms" class="form-label">विद्यालय में खराब कमरे की संख्या</label>
                            <input type="number" class="form-control" id="bad_rooms" name="bad_rooms" 
                                   value="<?php echo $school['bad_rooms']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_toilets" class="form-label">विद्यालय में कार्यशील शौचालय की संख्या</label>
                            <input type="number" class="form-control" id="working_toilets" name="working_toilets" 
                                   value="<?php echo $school['working_toilets']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_toilets" class="form-label">विद्यालय में खराब शौचालय की संख्या</label>
                            <input type="number" class="form-control" id="bad_toilets" name="bad_toilets" 
                                   value="<?php echo $school['bad_toilets']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">विद्यालय में रैंप की सुविधा है या नहीं</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="has_ramp" name="has_ramp" 
                                       <?php echo ($school['has_ramp'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="has_ramp">
                                    हाँ, रैंप की सुविधा उपलब्ध है
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_handpumps" class="form-label">विद्यालय में चालू हैंडपंप की संख्या</label>
                            <input type="number" class="form-control" id="working_handpumps" name="working_handpumps" 
                                   value="<?php echo $school['working_handpumps']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_samrasal" class="form-label">विद्यालय में समरसेबल है या नहीं</label>
                            <select class="form-select" id="has_samrasal" name="has_samrasal" onchange="toggleSamrasalFields()">
                                <option value="0" <?php echo ($school['has_samrasal'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_samrasal'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="samrasal_fields" class="conditional-field <?php echo ($school['has_samrasal'] == 1) ? 'd-block' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="working_samrasal" class="form-label">कार्यशील समरसेबल की संख्या</label>
                                    <input type="number" class="form-control" id="working_samrasal" name="working_samrasal" 
                                           value="<?php echo $school['working_samrasal']; ?>" min="0">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="bad_samrasal" class="form-label">खराब समरसेबल की संख्या</label>
                                    <input type="number" class="form-control" id="bad_samrasal" name="bad_samrasal" 
                                           value="<?php echo $school['bad_samrasal']; ?>" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_electricity" class="form-label">विद्यालय में विद्युत कनेक्शन उपलब्ध है या नहीं</label>
                            <select class="form-select" id="has_electricity" name="has_electricity" onchange="toggleElectricityFields()">
                                <option value="0" <?php echo ($school['has_electricity'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_electricity'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="electricity_fields" class="conditional-field <?php echo ($school['has_electricity'] == 1) ? 'd-block' : ''; ?>">
                            <div class="col-md-12 mb-3">
                                <label for="consumer_number" class="form-label">कंज्यूमर संख्या (0 भी स्वीकार्य है)</label>
                                <input type="text" class="form-control" id="consumer_number" name="consumer_number" 
                                       value="<?php echo $school['consumer_number']; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_fans" class="form-label">विद्यालय में कुल कार्यशील पंखे की संख्या</label>
                            <input type="number" class="form-control" id="working_fans" name="working_fans" 
                                   value="<?php echo $school['working_fans']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="good_bench_desks" class="form-label">विद्यालय में कुल अच्छे बेंच डेस्क की संख्या</label>
                            <input type="number" class="form-control" id="good_bench_desks" name="good_bench_desks" 
                                   value="<?php echo $school['good_bench_desks']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_bench_desks" class="form-label">विद्यालय में कुल टूटे हुए बेंच डेस्क की संख्या</label>
                            <input type="number" class="form-control" id="bad_bench_desks" name="bad_bench_desks" 
                                   value="<?php echo $school['bad_bench_desks']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="is_landless" class="form-label">क्या विद्यालय भूमिहीन है</label>
                            <select class="form-select" id="is_landless" name="is_landless">
                                <option value="0" <?php echo ($school['is_landless'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['is_landless'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_extra_land" class="form-label">विद्यालय के पास अतिरिक्त भूमि उपलब्ध है जिसमें कमरे का निर्माण किया जा सकता है</label>
                            <select class="form-select" id="has_extra_land" name="has_extra_land" onchange="toggleExtraLandFields()">
                                <option value="0" <?php echo ($school['has_extra_land'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_extra_land'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="extra_land_fields" class="conditional-field <?php echo ($school['has_extra_land'] == 1) ? 'd-block' : ''; ?>">
                            <div class="col-md-12 mb-3">
                                <label for="extra_land_area_sqft" class="form-label">अतिरिक्त भूमि का क्षेत्रफल (वर्ग फीट में)</label>
                                <input type="number" class="form-control" id="extra_land_area_sqft" name="extra_land_area_sqft" 
                                       value="<?php echo $school['extra_land_area_sqft']; ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="rooms_needed" class="form-label">विद्यालय में अगर कक्षा की जरूरत है तो निर्माण हेतु भूमि उपलब्ध रहने पर कितने कमरों का निर्माण किया जा सकता है</label>
                            <input type="number" class="form-control" id="rooms_needed" name="rooms_needed" 
                                   value="<?php echo $school['rooms_needed']; ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">अपडेट करें</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- पासवर्ड अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-key me-2"></i>
                पासवर्ड अपडेट करें
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post">
                    <input type="hidden" name="action" value="update_password">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">नया पासवर्ड</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">पासवर्ड की पुष्टि करें</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">पासवर्ड अपडेट करें</button>
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
        
        // समरसेबल फील्ड टॉगल करें
        function toggleSamrasalFields() {
            const hasSamrasal = document.getElementById('has_samrasal').value;
            const samrasalFields = document.getElementById('samrasal_fields');
            
            if (hasSamrasal === '1') {
                samrasalFields.style.display = 'block';
            } else {
                samrasalFields.style.display = 'none';
            }
        }
        
        // विद्युत कनेक्शन फील्ड टॉगल करें
        function toggleElectricityFields() {
            const hasElectricity = document.getElementById('has_electricity').value;
            const electricityFields = document.getElementById('electricity_fields');
            
            if (hasElectricity === '1') {
                electricityFields.style.display = 'block';
            } else {
                electricityFields.style.display = 'none';
            }
        }
        
        // अतिरिक्त भूमि फील्ड टॉगल करें
        function toggleExtraLandFields() {
            const hasExtraLand = document.getElementById('has_extra_land').value;
            const extraLandFields = document.getElementById('extra_land_fields');
            
            if (hasExtraLand === '1') {
                extraLandFields.style.display = 'block';
            } else {
                extraLandFields.style.display = 'none';
            }
        }
        
        // पेज लोड होने पर फील्ड की स्थिति सेट करें
        document.addEventListener('DOMContentLoaded', function() {
            toggleSamrasalFields();
            toggleElectricityFields();
            toggleExtraLandFields();
        });
        
        // पासवर्ड दिखाने/छिपाने के लिए
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('new_password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>