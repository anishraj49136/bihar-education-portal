<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन है
checkUserType('admin');

// विद्यालय जोड़ने/संपादित करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_school') {
    try {
        $school_id = $_POST['school_id'];
        
        if ($school_id) { // अपडेट
            $stmt = $conn->prepare("UPDATE schools SET udise_code = ?, name = ?, district_id = ?, block_id = ?, cluster_name = ?, 
                                   head_of_school = ?, head_of_school_number = ?, hos_email = ?, school_min_class = ?, school_max_class = ? 
                                   WHERE id = ?");
            $params = [
                $_POST['udise_code'],
                $_POST['name'],
                $_POST['district_id'],
                $_POST['block_id'],
                $_POST['cluster_name'],
                $_POST['head_of_school'],
                $_POST['head_of_school_number'],
                $_POST['hos_email'],
                $_POST['school_min_class'],
                $_POST['school_max_class'],
                $school_id
            ];

            // पासवर्ड अपडेट करने की जांच
            if (!empty($_POST['new_password'])) {
                if ($_POST['new_password'] === $_POST['confirm_password']) {
                    $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    // पासवर्ड को अपडेट क्वेरी में जोड़ें
                    $stmt = $conn->prepare("UPDATE schools SET udise_code = ?, name = ?, district_id = ?, block_id = ?, cluster_name = ?, 
                                           head_of_school = ?, head_of_school_number = ?, hos_email = ?, school_min_class = ?, school_max_class = ?, password = ? 
                                           WHERE id = ?");
                    $params = [
                        $_POST['udise_code'],
                        $_POST['name'],
                        $_POST['district_id'],
                        $_POST['block_id'],
                        $_POST['cluster_name'],
                        $_POST['head_of_school'],
                        $_POST['head_of_school_number'],
                        $_POST['hos_email'],
                        $_POST['school_min_class'],
                        $_POST['school_max_class'],
                        $hashed_password,
                        $school_id
                    ];
                } else {
                    $_SESSION['error_message'] = "नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते।";
                    header("Location: manage_schools.php");
                    exit();
                }
            }

            $stmt->execute($params);
            $_SESSION['success_message'] = "विद्यालय जानकारी सफलतापूर्वक अपडेट की गई!";
        } else { // नया जोड़ें
            $stmt = $conn->prepare("INSERT INTO schools (udise_code, name, district_id, block_id, cluster_name, head_of_school, head_of_school_number, hos_email, school_min_class, school_max_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['udise_code'],
                $_POST['name'],
                $_POST['district_id'],
                $_POST['block_id'],
                $_POST['cluster_name'],
                $_POST['head_of_school'],
                $_POST['head_of_school_number'],
                $_POST['hos_email'],
                $_POST['school_min_class'],
                $_POST['school_max_class']
            ]);
            $_SESSION['success_message'] = "नया विद्यालय सफलतापूर्वक जोड़ा गया!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_schools.php');
    exit;
}

// CSV से विद्यालय अपलोड करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['school_file']) && $_FILES['school_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['school_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $_SESSION['error_message'] = "कृपया केवल CSV फ़ाइल अपलोड करें।";
        header('Location: manage_schools.php');
        exit;
    }
    
    try {
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            // हेडर पढ़ें और कॉलम की संख्या की जांच करें
            $header = fgetcsv($handle);
            $column_count = count($header);
            
            if ($column_count < 49) {
                $_SESSION['error_message'] = "CSV फ़ाइल में कम से कम 49 कॉलम होने चाहिए। वर्तमान में {$column_count} कॉलम मिले।";
                fclose($handle);
                header('Location: manage_schools.php');
                exit;
            }
            
            $success_count = 0;
            $error_count = 0;
            $error_messages = [];
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($data) < 49) {
                    $error_count++;
                    continue;
                }
                
                try {
                    // डेटा को वेरिएबल्स में असाइन करें
                    $udise_code = trim($data[0]);
                    $name = trim($data[1]);
                    $district_id = trim($data[2]);
                    $block_id = trim($data[3]);
                    $cluster_name = trim($data[4]);
                    $location_type = trim($data[5]);
                    $panchayat_name = trim($data[6]);
                    $village_name = trim($data[7]);
                    $parliamentary_name = trim($data[8]);
                    $assembly_name = trim($data[9]);
                    $pincode = trim($data[10]);
                    $management_name = trim($data[11]);
                    $school_category = trim($data[12]);
                    $school_min_class = trim($data[13]);
                    $school_max_class = trim($data[14]);
                    $school_type = trim($data[15]);
                    $incharge_type = trim($data[16]);
                    $head_of_school = trim($data[17]);
                    $head_of_school_number = trim($data[18]);
                    $respondent_type = trim($data[19]);
                    $respondent_name = trim($data[20]);
                    $respondent_number = trim($data[21]);
                    $hos_email = trim($data[22]);
                    $medium_of_instruction = trim($data[23]);
                    $language_names = trim($data[24]);
                    $operational_status = trim($data[25]);
                    $latitude = trim($data[26]);
                    $longitude = trim($data[27]);
                    $created_at = trim($data[28]);
                    $updated_at = trim($data[29]);
                    $good_rooms = trim($data[30]);
                    $bad_rooms = trim($data[31]);
                    $working_toilets = trim($data[32]);
                    $bad_toilets = trim($data[33]);
                    $has_ramp = trim($data[34]);
                    $working_handpumps = trim($data[35]);
                    $has_samrasal = trim($data[36]);
                    $working_samrasal = trim($data[37]);
                    $bad_samrasal = trim($data[38]);
                    $has_electricity = trim($data[39]);
                    $consumer_number = trim($data[40]);
                    $working_fans = trim($data[41]);
                    $good_bench_desks = trim($data[42]);
                    $bad_bench_desks = trim($data[43]);
                    $is_landless = trim($data[44]);
                    $has_extra_land = trim($data[45]);
                    $extra_land_area_sqft = trim($data[46]);
                    $rooms_needed = trim($data[47]);
                    $password = trim($data[48]);
                    
                    // यदि पासवर्ड खाली है, तो डिफ़ॉल्ट पासवर्ड सेट करें
                    if (empty($password)) {
                        $password = password_hash('123456', PASSWORD_DEFAULT);
                    } else {
                        $password = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    // जांचें कि UDISE कोड पहले से मौजूद है या नहीं
                    $stmt = $conn->prepare("SELECT id FROM schools WHERE udise_code = ?");
                    $stmt->execute([$udise_code]);
                    $existing_school = $stmt->fetch();
                    
                    if ($existing_school) {
                        // अपडेट करें
                        $stmt = $conn->prepare("UPDATE schools SET 
                            name = ?, district_id = ?, block_id = ?, cluster_name = ?, 
                            location_type = ?, panchayat_name = ?, village_name = ?, 
                            parliamentary_name = ?, assembly_name = ?, pincode = ?, 
                            management_name = ?, school_category = ?, school_min_class = ?, 
                            school_max_class = ?, school_type = ?, incharge_type = ?, 
                            head_of_school = ?, head_of_school_number = ?, respondent_type = ?, 
                            respondent_name = ?, respondent_number = ?, hos_email = ?, 
                            medium_of_instruction = ?, language_names = ?, operational_status = ?, 
                            latitude = ?, longitude = ?, updated_at = ?, good_rooms = ?, 
                            bad_rooms = ?, working_toilets = ?, bad_toilets = ?, has_ramp = ?, 
                            working_handpumps = ?, has_samrasal = ?, working_samrasal = ?, 
                            bad_samrasal = ?, has_electricity = ?, consumer_number = ?, 
                            working_fans = ?, good_bench_desks = ?, bad_bench_desks = ?, 
                            is_landless = ?, has_extra_land = ?, extra_land_area_sqft = ?, 
                            rooms_needed = ?, password = ? 
                            WHERE udise_code = ?");
                        
                        $stmt->execute([
                            $name, $district_id, $block_id, $cluster_name, 
                            $location_type, $panchayat_name, $village_name, 
                            $parliamentary_name, $assembly_name, $pincode, 
                            $management_name, $school_category, $school_min_class, 
                            $school_max_class, $school_type, $incharge_type, 
                            $head_of_school, $head_of_school_number, $respondent_type, 
                            $respondent_name, $respondent_number, $hos_email, 
                            $medium_of_instruction, $language_names, $operational_status, 
                            $latitude, $longitude, $updated_at, $good_rooms, 
                            $bad_rooms, $working_toilets, $bad_toilets, $has_ramp, 
                            $working_handpumps, $has_samrasal, $working_samrasal, 
                            $bad_samrasal, $has_electricity, $consumer_number, 
                            $working_fans, $good_bench_desks, $bad_bench_desks, 
                            $is_landless, $has_extra_land, $extra_land_area_sqft, 
                            $rooms_needed, $password, $udise_code
                        ]);
                    } else {
                        // डालें
                        $stmt = $conn->prepare("INSERT INTO schools (
                            udise_code, name, district_id, block_id, cluster_name, 
                            location_type, panchayat_name, village_name, parliamentary_name, 
                            assembly_name, pincode, management_name, school_category, 
                            school_min_class, school_max_class, school_type, incharge_type, 
                            head_of_school, head_of_school_number, respondent_type, 
                            respondent_name, respondent_number, hos_email, 
                            medium_of_instruction, language_names, operational_status, 
                            latitude, longitude, created_at, updated_at, good_rooms, 
                            bad_rooms, working_toilets, bad_toilets, has_ramp, 
                            working_handpumps, has_samrasal, working_samrasal, 
                            bad_samrasal, has_electricity, consumer_number, 
                            working_fans, good_bench_desks, bad_bench_desks, 
                            is_landless, has_extra_land, extra_land_area_sqft, 
                            rooms_needed, password
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $udise_code, $name, $district_id, $block_id, $cluster_name, 
                            $location_type, $panchayat_name, $village_name, $parliamentary_name, 
                            $assembly_name, $pincode, $management_name, $school_category, 
                            $school_min_class, $school_max_class, $school_type, $incharge_type, 
                            $head_of_school, $head_of_school_number, $respondent_type, 
                            $respondent_name, $respondent_number, $hos_email, 
                            $medium_of_instruction, $language_names, $operational_status, 
                            $latitude, $longitude, $created_at, $updated_at, $good_rooms, 
                            $bad_rooms, $working_toilets, $bad_toilets, $has_ramp, 
                            $working_handpumps, $has_samrasal, $working_samrasal, 
                            $bad_samrasal, $has_electricity, $consumer_number, 
                            $working_fans, $good_bench_desks, $bad_bench_desks, 
                            $is_landless, $has_extra_land, $extra_land_area_sqft, 
                            $rooms_needed, $password
                        ]);
                    }
                    
                    $success_count++;
                } catch (PDOException $e) {
                    $error_count++;
                    $error_messages[] = "UDISE कोड {$udise_code} के लिए त्रुटि: " . $e->getMessage();
                }
            }
            fclose($handle);
            
            $_SESSION['success_message'] = "विद्यालय डेटा सफलतापूर्वक अपलोड और अपडेट किया गया! सफल: {$success_count}, त्रुटि: {$error_count}";
            
            if (!empty($error_messages)) {
                $_SESSION['error_message'] = implode("<br>", array_slice($error_messages, 0, 5)); // केवल पहले 5 त्रुटियां दिखाएं
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "डेटा अपलोड करते समय त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_schools.php');
    exit;
}

// विद्यालय हटाने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_school') {
    try {
        $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
        $stmt->execute([$_POST['school_id']]);
        $_SESSION['success_message'] = "विद्यालय सफलतापूर्वक हटा दिया गया!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_schools.php');
    exit;
}

// विद्यालयों की सूची प्राप्त करें
 $stmt = $conn->query("SELECT s.*, d.name as district_name, b.name as block_name FROM schools s LEFT JOIN districts d ON s.district_id = d.id LEFT JOIN blocks b ON s.block_id = b.id ORDER BY s.name");
 $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

// सभी जिले और प्रखंड प्राप्त करें
 $stmt = $conn->query("SELECT * FROM districts ORDER BY name");
 $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यालय प्रबंधन - बिहार शिक्षा विभाग</title>
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
        .table { border-radius: 10px; overflow: hidden; }
        .table thead { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .upload-area { border: 2px dashed var(--primary-color); border-radius: 10px; padding: 40px; text-align: center; background-color: var(--light-color); transition: all 0.3s ease; }
        .upload-area:hover { background-color: #e1bee7; }
        .csv-format-info { background-color: #f8f9fa; border-radius: 10px; padding: 15px; margin-top: 15px; }
        .csv-format-info h6 { color: var(--primary-color); margin-bottom: 10px; }
        .csv-format-columns { max-height: 200px; overflow-y: auto; font-size: 0.85rem; }
        .serial-number { font-weight: 600; color: var(--primary-color); text-align: center; }
        .password-section { display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
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
            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="fas fa-users-cog"></i> उपयोगकर्ता प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link active" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
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
                <h4 class="mb-0">विद्यालय प्रबंधन</h4>
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

        <!-- अपलोड सेक्शन -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">CSV से विद्यालय अपलोड करें</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                        <p>फ़ाइल को यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें</p>
                        <input type="file" class="form-control d-none" id="school_file" name="school_file" accept=".csv" required>
                        <button type="button" class="btn btn-outline-primary" id="browseBtn">फ़ाइल ब्राउज़ करें</button>
                    </div>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> अपलोड करें और अपडेट करें
                        </button>
                    </div>
                </form>
                
                <!-- CSV फॉर्मेट जानकारी -->
                <div class="csv-format-info">
                    <h6><i class="fas fa-info-circle me-2"></i>CSV फॉर्मेट जानकारी</h6>
                    <p class="mb-2">CSV फ़ाइल में निम्नलिखित 49 कॉलम होने चाहिए (इसी क्रम में):</p>
                    <div class="csv-format-columns">
                        <code>udise_code, name, district_id, block_id, cluster_name, location_type, panchayat_name, village_name, parliamentary_name, assembly_name, pincode, management_name, school_category, school_min_class, school_max_class, school_type, incharge_type, head_of_school, head_of_school_number, respondent_type, respondent_name, respondent_number, hos_email, medium_of_instruction, language_names, operational_status, latitude, longitude, created_at, updated_at, good_rooms, bad_rooms, working_toilets, bad_toilets, has_ramp, working_handpumps, has_samrasal, working_samrasal, bad_samrasal, has_electricity, consumer_number, working_fans, good_bench_desks, bad_bench_desks, is_landless, has_extra_land, extra_land_area_sqft, rooms_needed, password</code>
                    </div>
                    <p class="mt-2 mb-0"><strong>नोट:</strong> पासवर्ड कॉलम खाली छोड़ने पर डिफ़ॉल्ट पासवर्ड "123456" सेट हो जाएगा।</p>
                </div>
            </div>
        </div>

        <!-- विद्यालय सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">विद्यालय सूची</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadData('excel')"><i class="fas fa-file-excel"></i> Excel</button>
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#schoolModal">
                        <i class="fas fa-plus"></i> नया विद्यालय जोड़ें
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchUdise" placeholder="UDISE कोड से खोजें...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="schoolTable">
                        <thead>
                            <tr>
                                <th>क्र. सं.</th>
                                <th>विद्यालय का नाम</th>
                                <th>UDISE कोड</th>
                                <th>जिला</th>
                                <th>प्रखंड</th>
                                <th>प्रधानाध्यापक</th>
                                <th>संपर्क</th>
                                <th>कार्य</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $serial = 1; foreach ($schools as $school): ?>
                            <tr data-udise="<?php echo strtolower($school['udise_code']); ?>">
                                <td class="serial-number"><?php echo $serial++; ?></td>
                                <td><?php echo $school['name']; ?></td>
                                <td><?php echo $school['udise_code']; ?></td>
                                <td><?php echo $school['district_name']; ?></td>
                                <td><?php echo $school['block_name']; ?></td>
                                <td><?php echo $school['head_of_school']; ?></td>
                                <td><?php echo $school['head_of_school_number']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($school)); ?>)"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-primary" onclick="editSchool(<?php echo htmlspecialchars(json_encode($school)); ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete_school">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस विद्यालय को हटाना चाहते हैं?');"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- विद्यालय मोडल -->
    <div class="modal fade" id="schoolModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolModalTitle">नया विद्यालय जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manage_schools.php" id="schoolForm">
                    <input type="hidden" name="action" value="save_school">
                    <input type="hidden" name="school_id" id="schoolId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">विद्यालय का नाम</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="udise_code" class="form-label">UDISE कोड</label>
                                <input type="text" class="form-control" id="udise_code" name="udise_code" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="district_id" class="form-label">जिला</label>
                                <select class="form-select" id="district_id" name="district_id" required onchange="loadBlocksForModal(this.value)">
                                    <option value="">चुनें</option>
                                    <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo $district['id']; ?>"><?php echo $district['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="block_id" class="form-label">प्रखंड</label>
                                <select class="form-select" id="block_id" name="block_id" required>
                                    <option value="">पहले जिला चुनें</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cluster_name" class="form-label">क्लस्टर नाम</label>
                                <input type="text" class="form-control" id="cluster_name" name="cluster_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="head_of_school" class="form-label">प्रधानाध्यापक का नाम</label>
                                <input type="text" class="form-control" id="head_of_school" name="head_of_school">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="head_of_school_number" class="form-label">प्रधानाध्यापक का मोबाइल नंबर</label>
                                <input type="text" class="form-control" id="head_of_school_number" name="head_of_school_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hos_email" class="form-label">प्रधानाध्यापक का ईमेल</label>
                                <input type="email" class="form-control" id="hos_email" name="hos_email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="school_min_class" class="form-label">न्यूनतम कक्षा</label>
                                <input type="text" class="form-control" id="school_min_class" name="school_min_class">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="school_max_class" class="form-label">अधिकतम कक्षा</label>
                                <input type="text" class="form-control" id="school_max_class" name="school_max_class">
                            </div>
                        </div>
                        
                        <!-- पासवर्ड सेक्शन -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6>पासवर्ड प्रबंधन</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="togglePasswordSection">पासवर्ड बदलें</button>
                                </div>
                                
                                <div id="passwordSection" class="password-section">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">नया पासवर्ड</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="new_password" name="new_password">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">पासवर्ड की पुष्टि करें</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-primary">सहेजें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        // फ़ाइल अपलोड
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('school_file');
        const browseBtn = document.getElementById('browseBtn');
        browseBtn.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = '#e1bee7'; });
        uploadArea.addEventListener('dragleave', () => { uploadArea.style.backgroundColor = 'var(--light-color)'; });
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = 'var(--light-color)'; if (e.data.files.length) { fileInput.files = e.data.files; } });

        // UDISE खोज
        document.getElementById('searchUdise').addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#schoolTable tbody tr');
            rows.forEach(row => {
                const udise = row.getAttribute('data-udise');
                row.style.display = udise.includes(value) ? '' : 'none';
            });
        });

        // मोडल को रीसेट करें
        document.getElementById('schoolModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('schoolForm').reset();
            document.getElementById('schoolModalTitle').textContent = 'नया विद्यालय जोड़ें';
            document.getElementById('schoolId').value = '';
            document.getElementById('passwordSection').style.display = 'none';
        });

        function loadBlocksForModal(districtId) {
            if (!districtId) { 
                document.getElementById('block_id').innerHTML = '<option value="">पहले जिला चुनें</option>'; 
                return; 
            }
            
            fetch(`get_blocks.php?district_id=${districtId}`)
                .then(response => response.json())
                .then(blocks => {
                    let blockOptions = '<option value="">प्रखंड चुनें</option>';
                    blocks.forEach(block => { blockOptions += `<option value="${block.id}">${block.name}</option>`; });
                    document.getElementById('block_id').innerHTML = blockOptions;
                });
        }

        function editSchool(school) {
            document.getElementById('schoolModalTitle').textContent = 'विद्यालय जानकारी संपादित करें';
            document.getElementById('schoolId').value = school.id;
            document.getElementById('name').value = school.name;
            document.getElementById('udise_code').value = school.udise_code;
            document.getElementById('district_id').value = school.district_id;
            loadBlocksForModal(school.district_id);
            setTimeout(() => { document.getElementById('block_id').value = school.block_id; }, 300);
            document.getElementById('cluster_name').value = school.cluster_name;
            document.getElementById('head_of_school').value = school.head_of_school;
            document.getElementById('head_of_school_number').value = school.head_of_school_number;
            document.getElementById('hos_email').value = school.hos_email;
            document.getElementById('school_min_class').value = school.school_min_class;
            document.getElementById('school_max_class').value = school.school_max_class;
            
            // पासवर्ड फ़ील्ड को रीसेट करें
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('passwordSection').style.display = 'none';
            
            new bootstrap.Modal(document.getElementById('schoolModal')).show();
        }

        function viewDetails(school) {
            // विस्तृत विवरण देखने के लिए एक नया मोडल या पेज खोला जा सकता है
            alert('विस्तृत विवरण देखने की कार्यक्षमता यहाँ लागू की जाएगी।');
        }

        function downloadData(format) {
            if (format === 'excel') {
                window.open('download_schools.php', '_blank');
            } else {
                alert(`डाउनलोड ${format} का अनुरोध भेजा गया। (यह एक संकल्पनात्मक उदाहरण है)`);
            }
        }
        
        // पासवर्ड सेक्शन टॉगल करें
        document.getElementById('togglePasswordSection').addEventListener('click', function() {
            const section = document.getElementById('passwordSection');
            section.style.display = section.style.display === 'none' || section.style.display === '' ? 'block' : 'none';
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