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
        $stmt = $conn->prepare("UPDATE schools SET 
                               good_rooms = ?, bad_rooms = ?, working_toilets = ?, bad_toilets = ?, 
                               has_ramp = ?, working_handpumps = ?, has_samrasal = ?, working_samrasal = ?, 
                               bad_samrasal = ?, has_electricity = ?, consumer_number = ?, working_fans = ?, 
                               good_bench_desks = ?, bad_bench_desks = ?, is_landless = ?, has_extra_land = ?, 
                               extra_land_area_sqft = ?, rooms_needed = ?, updated_at = CURRENT_TIMESTAMP 
                               WHERE id = ?");
        $stmt->execute([
            $_POST['has_ramp'], $_POST['good_rooms'], $_POST['bad_rooms'], $_POST['working_toilets'], $_POST['bad_toilets'],
            $_POST['working_handpumps'], $_POST['has_samrasal'],
            $_POST['working_samrasal'], $_POST['bad_samrasal'], $_POST['has_electricity'],
            $_POST['consumer_number'], $_POST['working_fans'], $_POST['good_bench_desks'],
            $_POST['bad_bench_desks'], $_POST['is_landless'], $_POST['has_extra_land'],
            $_POST['extra_land_area_sqft'], $_POST['rooms_needed'], $school_id
        ]);
        $success_message = "विद्यालय आधारभूत संरचना सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "विद्यालय आधारभूत संरचना अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// वाद्य यंत्र और व्हीलचेयर अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_musical_instruments') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               harmonium_count = ?, tabla_count = ?, other_instruments_count = ?, wheelchair_count = ?, 
                               updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['harmonium_count'], $_POST['tabla_count'], $_POST['other_instruments_count'], $_POST['wheelchair_count'], $school_id]);
        $success_message = "वाद्य यंत्र और व्हीलचेयर जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "वाद्य यंत्र और व्हीलचेयर जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// चार दिवारी अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_boundary') {
    try {
        // सभी स्थितियों को पहले 0 पर सेट करें
        $boundary_complete = 0;
        $boundary_incomplete = 0;
        $boundary_broken = 0;
        $boundary_needs_height_increase = 0;
        
        // चुनी गई स्थिति के अनुसार मान सेट करें
        if (isset($_POST['boundary_status'])) {
            switch ($_POST['boundary_status']) {
                case 'complete':
                    $boundary_complete = 1;
                    break;
                case 'incomplete':
                    $boundary_incomplete = 1;
                    break;
                case 'broken':
                    $boundary_broken = 1;
                    break;
                case 'needs_height':
                    $boundary_needs_height_increase = 1;
                    break;
            }
        }
        
        $stmt = $conn->prepare("UPDATE schools SET 
                               has_boundary = ?, boundary_broken = ?, boundary_complete = ?, 
                               boundary_incomplete = ?, boundary_needs_height_increase = ?, 
                               updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $_POST['has_boundary'],
            $boundary_broken,
            $boundary_complete,
            $boundary_incomplete,
            $boundary_needs_height_increase,
            $school_id
        ]);
        $success_message = "चार दिवारी जानकारी सफलतापूर्वक अपडेट की गई!";
        
        // Refresh school data after update
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "चार दिवारी जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// खेल सामग्री अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_sports_equipment') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               football_count = ?, small_ball_count = ?, bat_count = ?, 
                               updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['football_count'], $_POST['small_ball_count'], $_POST['bat_count'], $school_id]);
        $success_message = "खेल सामग्री जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "खेल सामग्री जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// ICT लैब अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ict_lab') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               has_ict_lab = ?, total_computers = ?, working_computers = ?, 
                               total_projectors = ?, working_projectors = ?, working_printers = ?, 
                               updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $_POST['has_ict_lab'], $_POST['total_computers'], $_POST['working_computers'],
            $_POST['total_projectors'], $_POST['working_projectors'], $_POST['working_printers'], $school_id
        ]);
        $success_message = "ICT लैब जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "ICT लैब जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// स्मार्ट क्लास अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_smart_class') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               has_smart_class = ?, smart_total_projectors = ?, smart_working_projectors = ?, 
                               total_smart_boards = ?, working_smart_boards = ?, television_count = ?, 
                               working_television_count = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $_POST['has_smart_class'], $_POST['smart_total_projectors'], $_POST['smart_working_projectors'],
            $_POST['total_smart_boards'], $_POST['working_smart_boards'], $_POST['television_count'],
            $_POST['working_television_count'], $school_id
        ]);
        $success_message = "स्मार्ट क्लास जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "स्मार्ट क्लास जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// पुस्तकालय अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_library') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               library_rooms = ?, cupboards_count = ?, tables_count = ?, 
                               chairs_count = ?, books_count = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['library_rooms'], $_POST['cupboards_count'], $_POST['tables_count'], $_POST['chairs_count'], $_POST['books_count'], $school_id]);
        $success_message = "पुस्तकालय जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "पुस्तकालय जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// FLN/PBL किट अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_kits') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               fln_kits_received = ?, fln_kits_distributed = ?, fln_kits_remaining = ?, 
                               pbl_kits_received = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['fln_kits_received'], $_POST['fln_kits_distributed'], $_POST['fln_kits_remaining'], $_POST['pbl_kits_received'], $school_id]);
        $success_message = "FLN/PBL किट जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "FLN/PBL किट जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// मध्यान भोजन अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mid_day_meal') {
    try {
        $stmt = $conn->prepare("UPDATE schools SET 
                               has_mid_day_meal = ?, plates_count = ?, glasses_count = ?, 
                               jugs_count = ?, mats_count = ?, working_cooks_count = ?, 
                               updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_POST['has_mid_day_meal'], $_POST['plates_count'], $_POST['glasses_count'], $_POST['jugs_count'], $_POST['mats_count'], $_POST['working_cooks_count'], $school_id]);
        $success_message = "मध्यान भोजन जानकारी सफलतापूर्वक अपडेट की गई!";
        $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "मध्यान भोजन जानकारी अपडेट करते समय त्रुटि: " . $e->getMessage();
    }
}

// पासवर्ड अपडेट प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    try {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE schools SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$hashed_password, $school_id]);
            $success_message = "पासवर्ड सफलतापूर्वक अपडेट किया गया!";
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
        
        .conditional-field {
            display: none;
        }
        
        .form-check {
            margin-bottom: 10px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <td><strong>विद्यालय का नाम:</strong></td>
                                <td><?php echo $school['name']; ?></td>
                                <td><strong>प्रधानाध्यापक का नाम:</strong></td>
                                <td><?php echo $school['head_of_school']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>यूडाइस कोड:</strong></td>
                                <td><?php echo $school['udise_code']; ?></td>
                                <td><strong>मोबाइल नंबर:</strong></td>
                                <td><?php echo $school['head_of_school_number']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>जिला:</strong></td>
                                <td><?php foreach ($districts as $district) if ($district['id'] == $school['district_id']) { echo $district['name']; break; } ?></td>
                                <td><strong>ईमेल:</strong></td>
                                <td><?php echo $school['hos_email']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>प्रखंड:</strong></td>
                                <td><?php if (isset($blocks)) foreach ($blocks as $block) if ($block['id'] == $school['block_id']) { echo $block['name']; break; } ?></td>
                                <td><strong>न्यूनतम कक्षा:</strong></td>
                                <td><?php echo $school['school_min_class']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>ग्राम:</strong></td>
                                <td><?php echo $school['village_name']; ?></td>
                                <td><strong>अधिकतम कक्षा:</strong></td>
                                <td><?php echo $school['school_max_class']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>पिनकोड:</strong></td>
                                <td><?php echo $school['pincode']; ?></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
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
                <form action="school_profile.php" method="post" id="principalForm">
                    <input type="hidden" name="action" value="update_principal">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="head_of_school" class="form-label">प्रधानाध्यापक का नाम <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="head_of_school" name="head_of_school" value="<?php echo $school['head_of_school']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="head_of_school_number" class="form-label">मोबाइल नंबर <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="head_of_school_number" name="head_of_school_number" value="<?php echo $school['head_of_school_number']; ?>" pattern="[0-9]{10}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hos_email" class="form-label">ईमेल <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="hos_email" name="hos_email" value="<?php echo $school['hos_email']; ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="school_min_class" class="form-label">न्यूनतम कक्षा <span class="text-danger">*</span></label>
                            <select class="form-select" id="school_min_class" name="school_min_class" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($school['school_min_class'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="school_max_class" class="form-label">अधिकतम कक्षा <span class="text-danger">*</span></label>
                            <select class="form-select" id="school_max_class" name="school_max_class" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($school['school_max_class'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
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
                <form action="school_profile.php" method="post" id="infrastructureForm">
                    <input type="hidden" name="action" value="update_infrastructure">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="has_ramp" class="form-label">विद्यालय में रैंप की सुविधा है या नहीं <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="has_ramp" id="has_ramp_yes" value="1" 
                                       <?php echo ($school['has_ramp'] == 1) ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="has_ramp_yes">
                                    हाँ
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="has_ramp" id="has_ramp_no" value="0" 
                                       <?php echo ($school['has_ramp'] == 0) ? 'checked' : ''; ?> required>
                                <label class="form-check-label" for="has_ramp_no">
                                    नहीं
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="good_rooms" class="form-label">विद्यालय में अच्छे कमरे की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="good_rooms" name="good_rooms" 
                                   value="<?php echo $school['good_rooms']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_rooms" class="form-label">विद्यालय में खराब कमरे की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="bad_rooms" name="bad_rooms" 
                                   value="<?php echo $school['bad_rooms']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_toilets" class="form-label">विद्यालय में क्रियाशील शौचालय की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="working_toilets" name="working_toilets" 
                                   value="<?php echo $school['working_toilets']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_toilets" class="form-label">विद्यालय में खराब शौचालय की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="bad_toilets" name="bad_toilets" 
                                   value="<?php echo $school['bad_toilets']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_handpumps" class="form-label">विद्यालय में चापाकल क्रियाशील की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="working_handpumps" name="working_handpumps" 
                                   value="<?php echo $school['working_handpumps']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_samrasal" class="form-label">विद्यालय में समरसेबल है या नहीं <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_samrasal" name="has_samrasal" onchange="toggleSamrasalFields()" required>
                                <option value="0" <?php echo ($school['has_samrasal'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_samrasal'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="samrasal_fields" class="conditional-field <?php echo ($school['has_samrasal'] == 1) ? 'd-block' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="working_samrasal" class="form-label">क्रियाशील समरसेबल की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_samrasal" name="working_samrasal" 
                                           value="<?php echo $school['working_samrasal']; ?>" min="0" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="bad_samrasal" class="form-label">खराब समरसेबल की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="bad_samrasal" name="bad_samrasal" 
                                           value="<?php echo $school['bad_samrasal']; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_electricity" class="form-label">विद्यालय में विद्युत कनेक्शन उपलब्ध है या नहीं <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_electricity" name="has_electricity" onchange="toggleElectricityFields()" required>
                                <option value="0" <?php echo ($school['has_electricity'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_electricity'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="electricity_fields" class="conditional-field <?php echo ($school['has_electricity'] == 1) ? 'd-block' : ''; ?>">
                            <div class="col-md-12 mb-3">
                                <label for="consumer_number" class="form-label">कंज्यूमर संख्या <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="consumer_number" name="consumer_number" 
                                       value="<?php echo $school['consumer_number']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="working_fans" class="form-label">विद्यालय में कुल क्रियाशील पंखे की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="working_fans" name="working_fans" 
                                   value="<?php echo $school['working_fans']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="good_bench_desks" class="form-label">विद्यालय में कुल अच्छे बेंच डेस्क की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="good_bench_desks" name="good_bench_desks" 
                                   value="<?php echo $school['good_bench_desks']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bad_bench_desks" class="form-label">विद्यालय में कुल टूटे हुए बेंच डेस्क की संख्या <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="bad_bench_desks" name="bad_bench_desks" 
                                   value="<?php echo $school['bad_bench_desks']; ?>" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="is_landless" class="form-label">क्या विद्यालय भूमिहीन है <span class="text-danger">*</span></label>
                            <select class="form-select" id="is_landless" name="is_landless" required>
                                <option value="0" <?php echo ($school['is_landless'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['is_landless'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="has_extra_land" class="form-label">विद्यालय के पास अतिरिक्त भूमि उपलब्ध है जिसमें कमरे का निर्माण किया जा सकता है <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_extra_land" name="has_extra_land" onchange="toggleExtraLandFields()" required>
                                <option value="0" <?php echo ($school['has_extra_land'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_extra_land'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="extra_land_fields" class="conditional-field <?php echo ($school['has_extra_land'] == 1) ? 'd-block' : ''; ?>">
                            <div class="col-md-12 mb-3">
                                <label for="extra_land_area_sqft" class="form-label">अतिरिक्त भूमि का क्षेत्रफल (वर्ग फीट में) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="extra_land_area_sqft" name="extra_land_area_sqft" 
                                       value="<?php echo $school['extra_land_area_sqft']; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="rooms_needed" class="form-label">विद्यालय में अगर कक्षा की जरूरत है तो निर्माण हेतु भूमि उपलब्ध रहने पर कितने कमरों का निर्माण की आवश्यकता है <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="rooms_needed" name="rooms_needed" 
                                   value="<?php echo $school['rooms_needed']; ?>" min="0" required>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary">अपडेट करें</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- चार दिवारी अपडेट फॉर्म -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-border-style me-2"></i>
        चार दिवारी से संबंधित विवरणी
    </div>
    <div class="card-body">
        <form action="school_profile.php" method="post" id="boundaryForm">
            <input type="hidden" name="action" value="update_boundary">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="has_boundary" class="form-label">विद्यालय में चार दिवारी है या नहीं <span class="text-danger">*</span></label>
                    <select class="form-select" id="has_boundary" name="has_boundary" onchange="toggleBoundaryFields()" required>
                        <option value="0" <?php echo ($school['has_boundary'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                        <option value="1" <?php echo ($school['has_boundary'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                    </select>
                </div>
                
                <div id="boundary_fields" class="conditional-field <?php echo ($school['has_boundary'] == 1) ? 'd-block' : ''; ?>">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">चार दिवारी की स्थिति <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="boundary_status" id="boundary_complete" value="complete" 
                                   <?php 
                                   // Check if boundary_complete is 1 or if boundary_status was 'complete' in the last submission
                                   if ($school['boundary_complete'] == 1 || (isset($_POST['boundary_status']) && $_POST['boundary_status'] == 'complete')) {
                                       echo 'checked';
                                   }
                                   ?>>
                            <label class="form-check-label" for="boundary_complete">पूर्ण है</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="boundary_status" id="boundary_incomplete" value="incomplete" 
                                   <?php 
                                   if ($school['boundary_incomplete'] == 1 || (isset($_POST['boundary_status']) && $_POST['boundary_status'] == 'incomplete')) {
                                       echo 'checked';
                                   }
                                   ?>>
                            <label class="form-check-label" for="boundary_incomplete">अधूरा है</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="boundary_status" id="boundary_broken" value="broken" 
                                   <?php 
                                   if ($school['boundary_broken'] == 1 || (isset($_POST['boundary_status']) && $_POST['boundary_status'] == 'broken')) {
                                       echo 'checked';
                                   }
                                   ?>>
                            <label class="form-check-label" for="boundary_broken">टूटा हुआ है</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="boundary_status" id="boundary_needs_height_increase" value="needs_height" 
                                   <?php 
                                   if ($school['boundary_needs_height_increase'] == 1 || (isset($_POST['boundary_status']) && $_POST['boundary_status'] == 'needs_height')) {
                                       echo 'checked';
                                   }
                                   ?>>
                            <label class="form-check-label" for="boundary_needs_height_increase">ऊंचाई बढ़ाने की आवश्यकता है</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-primary">अपडेट करें</button>
            </div>
        </form>
    </div>
</div>
        
        <!-- ICT लैब अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-desktop me-2"></i>
                ICT लैब से संबंधित विवरणी
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post" id="ictLabForm">
                    <input type="hidden" name="action" value="update_ict_lab">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="has_ict_lab" class="form-label">क्या विद्यालय में आईसीटी लैब उपलब्ध है? <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_ict_lab" name="has_ict_lab" onchange="toggleIctLabFields()" required>
                                <option value="0" <?php echo ($school['has_ict_lab'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_ict_lab'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="ict_lab_fields" class="conditional-field <?php echo ($school['has_ict_lab'] == 1) ? 'd-block' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="total_computers" class="form-label">कुल कंप्यूटर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_computers" name="total_computers" 
                                           value="<?php echo $school['total_computers']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_computers" class="form-label">कार्यरत कंप्यूटर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_computers" name="working_computers" 
                                           value="<?php echo $school['working_computers']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="total_projectors" class="form-label">कुल प्रोजेक्टर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_projectors" name="total_projectors" 
                                           value="<?php echo $school['total_projectors']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_projectors" class="form-label">कार्यरत प्रोजेक्टर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_projectors" name="working_projectors" 
                                           value="<?php echo $school['working_projectors']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_printers" class="form-label">कुल कार्यरत प्रिंटर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_printers" name="working_printers" 
                                           value="<?php echo $school['working_printers']; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">अपडेट करें</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- स्मार्ट क्लास अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chalkboard me-2"></i>
                स्मार्ट क्लास से संबंधित विवरणी
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post" id="smartClassForm">
                    <input type="hidden" name="action" value="update_smart_class">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="has_smart_class" class="form-label">क्या विद्यालय में स्मार्ट क्लास उपलब्ध है? <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_smart_class" name="has_smart_class" onchange="toggleSmartClassFields()" required>
                                <option value="0" <?php echo ($school['has_smart_class'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_smart_class'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="smart_class_fields" class="conditional-field <?php echo ($school['has_smart_class'] == 1) ? 'd-block' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="smart_total_projectors" class="form-label">कुल प्रोजेक्टर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="smart_total_projectors" name="smart_total_projectors" 
                                           value="<?php echo $school['smart_total_projectors']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="smart_working_projectors" class="form-label">कार्यरत प्रोजेक्टर की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="smart_working_projectors" name="smart_working_projectors" 
                                           value="<?php echo $school['smart_working_projectors']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="total_smart_boards" class="form-label">कुल स्मार्ट बोर्ड की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="total_smart_boards" name="total_smart_boards" 
                                           value="<?php echo $school['total_smart_boards']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_smart_boards" class="form-label">कार्यरत स्मार्ट बोर्ड की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_smart_boards" name="working_smart_boards" 
                                           value="<?php echo $school['working_smart_boards']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="television_count" class="form-label">टेलीविजन की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="television_count" name="television_count" 
                                           value="<?php echo $school['television_count']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_television_count" class="form-label">कार्यरत टेलीविजन की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_television_count" name="working_television_count" 
                                           value="<?php echo $school['working_television_count']; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">अपडेट करें</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- मध्यान भोजन अपडेट फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-utensils me-2"></i>
                मध्यान भोजन से संबंधित विवरणी
            </div>
            <div class="card-body">
                <form action="school_profile.php" method="post" id="midDayMealForm">
                    <input type="hidden" name="action" value="update_mid_day_meal">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="has_mid_day_meal" class="form-label">क्या विद्यालय में मध्यान भोजन योजना संचालित है? <span class="text-danger">*</span></label>
                            <select class="form-select" id="has_mid_day_meal" name="has_mid_day_meal" onchange="toggleMidDayMealFields()" required>
                                <option value="0" <?php echo ($school['has_mid_day_meal'] == 0) ? 'selected' : ''; ?>>नहीं</option>
                                <option value="1" <?php echo ($school['has_mid_day_meal'] == 1) ? 'selected' : ''; ?>>हाँ</option>
                            </select>
                        </div>
                        
                        <div id="mid_day_meal_fields" class="conditional-field <?php echo ($school['has_mid_day_meal'] == 1) ? 'd-block' : ''; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="plates_count" class="form-label">प्लेट की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="plates_count" name="plates_count" 
                                           value="<?php echo $school['plates_count']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="glasses_count" class="form-label">ग्लास की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="glasses_count" name="glasses_count" 
                                           value="<?php echo $school['glasses_count']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="jugs_count" class="form-label">जग की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="jugs_count" name="jugs_count" 
                                           value="<?php echo $school['jugs_count']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mats_count" class="form-label">दरी की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="mats_count" name="mats_count" 
                                           value="<?php echo $school['mats_count']; ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_cooks_count" class="form-label">विद्यालय में कार्यरत कूल रसोईया की संख्या <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="working_cooks_count" name="working_cooks_count" 
                                           value="<?php echo $school['working_cooks_count']; ?>" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
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
                <form action="school_profile.php" method="post" id="passwordForm">
                    <input type="hidden" name="action" value="update_password">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">नया पासवर्ड <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">पासवर्ड की पुष्टि करें <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="fas fa-eye"></i></button>
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

        // शर्त रूपी फील्ड टॉगल करने के लिए फंक्शन
        function toggleFields(selectId, fieldId) {
            const selectElement = document.getElementById(selectId);
            const fieldElement = document.getElementById(fieldId);
            if (selectElement.value === '1') {
                fieldElement.style.display = 'block';
                // शर्तीय फ़ील्ड के अंदर के सभी इनपुट को आवश्यक बनाएं
                const inputs = fieldElement.querySelectorAll('input, select');
                inputs.forEach(input => {
                    input.setAttribute('required', '');
                });
                
                // यदि यह चार दिवारी फ़ील्ड है, तो सभी रेडियो बटन को चेक करने की अनुमति दें
                if (fieldId === 'boundary_fields') {
                    const radios = fieldElement.querySelectorAll('input[type="radio"]');
                    radios.forEach(radio => {
                        radio.checked = false;
                    });
                }
            } else {
                fieldElement.style.display = 'none';
                // शर्तीय फ़ील्ड के अंदर के सभी इनपुट से आवश्यकता हटाएं
                const inputs = fieldElement.querySelectorAll('input, select');
                inputs.forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }

        function toggleSamrasalFields() { toggleFields('has_samrasal', 'samrasal_fields'); }
        function toggleElectricityFields() { toggleFields('has_electricity', 'electricity_fields'); }
        function toggleExtraLandFields() { toggleFields('has_extra_land', 'extra_land_fields'); }
        function toggleBoundaryFields() { 
            toggleFields('has_boundary', 'boundary_fields'); 
            
            // यदि चार दिवारी नहीं है, तो सभी रेडियो बटन को अनचेक करें
            const hasBoundary = document.getElementById('has_boundary').value;
            if (hasBoundary === '0') {
                const radios = document.querySelectorAll('#boundary_fields input[type="radio"]');
                radios.forEach(radio => {
                    radio.checked = false;
                });
            }
        }
        function toggleIctLabFields() { toggleFields('has_ict_lab', 'ict_lab_fields'); }
        function toggleSmartClassFields() { toggleFields('has_smart_class', 'smart_class_fields'); }
        function toggleMidDayMealFields() { toggleFields('has_mid_day_meal', 'mid_day_meal_fields'); }

        // पेज लोड होने पर सभी फील्ड की स्थिति सेट करें
        document.addEventListener('DOMContentLoaded', function() {
            toggleSamrasalFields();
            toggleElectricityFields();
            toggleExtraLandFields();
            toggleBoundaryFields();
            toggleIctLabFields();
            toggleSmartClassFields();
            toggleMidDayMealFields();
        });
        
        // पासवर्ड दिखाने/छिपाने के लिए
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('new_password');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
        });

        // फॉर्म वैलिडेशन के लिए
        document.getElementById('boundaryForm').addEventListener('submit', function(event) {
            const hasBoundary = document.getElementById('has_boundary').value;
            const boundaryFields = document.getElementById('boundary_fields');
            
            if (hasBoundary === '1' && boundaryFields.style.display !== 'none') {
                const radios = boundaryFields.querySelectorAll('input[type="radio"]');
                let atLeastOneChecked = false;
                
                radios.forEach(function(radio) {
                    if (radio.checked) {
                        atLeastOneChecked = true;
                    }
                });
                
                if (!atLeastOneChecked) {
                    event.preventDefault();
                    alert('कृपया चार दिवारी की स्थिति का चयन करें');
                }
            }
        });
    </script>
</body>
</html>