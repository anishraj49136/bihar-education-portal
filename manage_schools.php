<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन, जिला स्टाफ, जिला प्रोग्राम ऑफिसर, जिला शिक्षा अधिकारी, DDO या ब्लॉक ऑफिसर है
if (!isset($_SESSION['user_type']) || 
    !in_array($_SESSION['user_type'], 
    ['admin', 'district_staff', 'district_program_officer', 'district_education_officer', 'ddo', 'block_officer'])) {
    header("Location: login.php");
    exit();
}

// --- NEW: Bulk CSV Download Logic (All schools in current view) ---
if (isset($_GET['download_all_csv'])) {
    $user_type = $_SESSION['user_type'];
    $where_clause = "";
    $params = [];

    if (in_array($user_type, ['district_staff', 'district_program_officer', 'district_education_officer'])) {
        $district_id = $_SESSION['district_id'];
        $where_clause = "WHERE s.district_id = ?";
        $params = [$district_id];
    } elseif (in_array($user_type, ['ddo', 'block_officer'])) {
        $block_id = $_SESSION['block_id'];
        $where_clause = "WHERE s.block_id = ?";
        $params = [$block_id];
    }

    $stmt = $conn->prepare("SELECT s.* FROM schools s $where_clause ORDER BY s.name");
    $stmt->execute($params);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($schools)) {
        // Define all 90+ column headers
        $headers = [
            'id', 'udise_code', 'name', 'district_id', 'block_id', 'cluster_name', 'location_type',
            'panchayat_name', 'village_name', 'parliamentary_name', 'assembly_name', 'pincode',
            'management_name', 'school_category', 'school_min_class', 'school_max_class', 'school_type',
            'incharge_type', 'head_of_school', 'head_of_school_number', 'respondent_type', 'respondent_name',
            'respondent_number', 'hos_email', 'medium_of_instruction', 'language_names', 'operational_status',
            'latitude', 'longitude', 'good_rooms', 'bad_rooms', 'working_toilets', 'bad_toilets', 'has_ramp',
            'working_handpumps', 'has_samrasal', 'working_samrasal', 'bad_samrasal', 'has_electricity',
            'consumer_number', 'working_fans', 'good_bench_desks', 'bad_bench_desks', 'is_landless',
            'has_extra_land', 'extra_land_area_sqft', 'rooms_needed', 'password', 'harmonium_count',
            'tabla_count', 'other_instruments_count', 'wheelchair_count', 'has_boundary', 'boundary_broken',
            'boundary_complete', 'boundary_incomplete', 'boundary_needs_height_increase', 'football_count',
            'small_ball_count', 'bat_count', 'has_ict_lab', 'total_computers', 'working_computers',
            'total_projectors', 'working_projectors', 'working_printers', 'has_smart_class',
            'smart_total_projectors', 'smart_working_projectors', 'total_smart_boards', 'working_smart_boards',
            'television_count', 'working_television_count', 'library_rooms', 'cupboards_count', 'tables_count',
            'chairs_count', 'books_count', 'fln_kits_received', 'fln_kits_distributed', 'fln_kits_remaining',
            'pbl_kits_received', 'has_mid_day_meal', 'plates_count', 'glasses_count', 'jugs_count', 'mats_count',
            'working_cooks_count', 'created_at', 'updated_at'
        ];

        $filename = "all_schools_data_" . date('Y-m-d_H-i-s') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // Add BOM for Excel
        fputcsv($output, $headers);

        foreach ($schools as $school) {
            fputcsv($output, $school);
        }

        fclose($output);
        exit();
    } else {
        $_SESSION['error_message'] = "डाउनलोड करने के लिए कोई डेटा नहीं मिला।";
        header('Location: manage_schools.php');
        exit();
    }
}

// --- NEW: Individual School CSV Download Logic ---
if (isset($_GET['download_school_csv']) && is_numeric($_GET['download_school_csv'])) {
    $school_id = (int)$_GET['download_school_csv'];

    $where_clause = "WHERE s.id = ?";
    $params = [$school_id];

    $user_type = $_SESSION['user_type'];
    if (in_array($user_type, ['district_staff', 'district_program_officer', 'district_education_officer'])) {
        $district_id = $_SESSION['district_id'];
        $where_clause = "WHERE s.id = ? AND s.district_id = ?";
        $params = [$school_id, $district_id];
    } elseif (in_array($user_type, ['ddo', 'block_officer'])) {
        $block_id = $_SESSION['block_id'];
        $where_clause = "WHERE s.id = ? AND s.block_id = ?";
        $params = [$school_id, $block_id];
    }

    $stmt = $conn->prepare("SELECT s.* FROM schools s $where_clause");
    $stmt->execute($params);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($school) {
        $headers = [
            'id', 'udise_code', 'name', 'district_id', 'block_id', 'cluster_name', 'location_type',
            'panchayat_name', 'village_name', 'parliamentary_name', 'assembly_name', 'pincode',
            'management_name', 'school_category', 'school_min_class', 'school_max_class', 'school_type',
            'incharge_type', 'head_of_school', 'head_of_school_number', 'respondent_type', 'respondent_name',
            'respondent_number', 'hos_email', 'medium_of_instruction', 'language_names', 'operational_status',
            'latitude', 'longitude', 'good_rooms', 'bad_rooms', 'working_toilets', 'bad_toilets', 'has_ramp',
            'working_handpumps', 'has_samrasal', 'working_samrasal', 'bad_samrasal', 'has_electricity',
            'consumer_number', 'working_fans', 'good_bench_desks', 'bad_bench_desks', 'is_landless',
            'has_extra_land', 'extra_land_area_sqft', 'rooms_needed', 'password', 'harmonium_count',
            'tabla_count', 'other_instruments_count', 'wheelchair_count', 'has_boundary', 'boundary_broken',
            'boundary_complete', 'boundary_incomplete', 'boundary_needs_height_increase', 'football_count',
            'small_ball_count', 'bat_count', 'has_ict_lab', 'total_computers', 'working_computers',
            'total_projectors', 'working_projectors', 'working_printers', 'has_smart_class',
            'smart_total_projectors', 'smart_working_projectors', 'total_smart_boards', 'working_smart_boards',
            'television_count', 'working_television_count', 'library_rooms', 'cupboards_count', 'tables_count',
            'chairs_count', 'books_count', 'fln_kits_received', 'fln_kits_distributed', 'fln_kits_remaining',
            'pbl_kits_received', 'has_mid_day_meal', 'plates_count', 'glasses_count', 'jugs_count', 'mats_count',
            'working_cooks_count', 'created_at', 'updated_at'
        ];

        $filename = "school_data_" . preg_replace('/[^a-zA-Z0-9_-]/s', '_', $school['name']) . "_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        fputcsv($output, $school);
        fclose($output);
        exit();
    } else {
        $_SESSION['error_message'] = "अनुरोधित विद्यालय नहीं मिला या आपको इसे डाउनलोड करने की अनुमति नहीं है।";
        header('Location: manage_schools.php');
        exit();
    }
}

// पेजिनेशन वेरिएबल्स
 $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
 $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
 $offset = ($page - 1) * $per_page;

// --- MODIFIED: Handling Form Submissions (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // केवल एडमिन को विद्यालय जोड़ने/संपादित करने की अनुमति है
    if ($_SESSION['user_type'] !== 'admin') {
        $_SESSION['error_message'] = "आपको विद्यालय जानकारी संपादित करने की अनुमति नहीं है।";
        header('Location: manage_schools.php');
        exit;
    }
    
    try {
        // --- NEW LOGIC: Handle ONLY password update ---
        if ($_POST['action'] === 'update_password_only') {
            $school_id = $_POST['school_id'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($new_password) || empty($confirm_password)) {
                 $_SESSION['error_message'] = "कृपया नया पासवर्ड और पुष्टि पासवर्ड दोनों भरें।";
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['error_message'] = "नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते।";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE schools SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $school_id]);
                $_SESSION['success_message'] = "पासवर्ड सफलतापूर्वक अपडेट किया गया!";
            }
            header('Location: manage_schools.php');
            exit;
        }

        // --- MODIFIED LOGIC: Handle general school details update (Add or Edit) ---
        if ($_POST['action'] === 'save_school') {
            $school_id = $_POST['school_id'];
            
            // --- Handle all 90 fields ---
            $fields_to_update = [
                'udise_code', 'name', 'district_id', 'block_id', 'cluster_name', 'location_type',
                'panchayat_name', 'village_name', 'parliamentary_name', 'assembly_name', 'pincode',
                'management_name', 'school_category', 'school_min_class', 'school_max_class', 'school_type',
                'incharge_type', 'head_of_school', 'head_of_school_number', 'respondent_type', 'respondent_name',
                'respondent_number', 'hos_email', 'medium_of_instruction', 'language_names', 'operational_status',
                'latitude', 'longitude', 'good_rooms', 'bad_rooms', 'working_toilets', 'bad_toilets', 'has_ramp',
                'working_handpumps', 'has_samrasal', 'working_samrasal', 'bad_samrasal', 'has_electricity',
                'consumer_number', 'working_fans', 'good_bench_desks', 'bad_bench_desks', 'is_landless',
                'has_extra_land', 'extra_land_area_sqft', 'rooms_needed', 'harmonium_count',
                'tabla_count', 'other_instruments_count', 'wheelchair_count', 'has_boundary', 'boundary_broken',
                'boundary_complete', 'boundary_incomplete', 'boundary_needs_height_increase', 'football_count',
                'small_ball_count', 'bat_count', 'has_ict_lab', 'total_computers', 'working_computers',
                'total_projectors', 'working_projectors', 'working_printers', 'has_smart_class',
                'smart_total_projectors', 'smart_working_projectors', 'total_smart_boards', 'working_smart_boards',
                'television_count', 'working_television_count', 'library_rooms', 'cupboards_count', 'tables_count',
                'chairs_count', 'books_count', 'fln_kits_received', 'fln_kits_distributed', 'fln_kits_remaining',
                'pbl_kits_received', 'has_mid_day_meal', 'plates_count', 'glasses_count', 'jugs_count', 'mats_count',
                'working_cooks_count'
            ];
            
            $params = [];
            $set_clause_parts = [];
            foreach($fields_to_update as $field) {
                $set_clause_parts[] = "$field = ?";
                $params[] = $_POST[$field] ?? null;
            }

            if ($school_id) { // अपडेट
                $params[] = $school_id; // for WHERE clause
                $sql = "UPDATE schools SET " . implode(', ', $set_clause_parts) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $_SESSION['success_message'] = "विद्यालय जानकारी सफलतापूर्वक अपडेट की गई!";
            } else { // नया जोड़ें
                $sql = "INSERT INTO schools (" . implode(', ', $fields_to_update) . ", password, created_at) VALUES (" . str_repeat('?,', count($fields_to_update)) . "?, NOW())";
                $params[] = password_hash('123456', PASSWORD_DEFAULT); // default password
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $_SESSION['success_message'] = "नया विद्यालय सफलतापूर्वक जोड़ा गया!";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_schools.php');
    exit;
}

// CSV से विद्यालय अपलोड करने की प्रक्रिया - केवल एडमिन के लिए
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['school_file']) && $_FILES['school_file']['error'] === UPLOAD_ERR_OK) {
    if ($_SESSION['user_type'] !== 'admin') {
        $_SESSION['error_message'] = "आपको CSV फ़ाइल अपलोड करने की अनुमति नहीं है।";
        header('Location: manage_schools.php');
        exit;
    }
    
    $file = $_FILES['school_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $_SESSION['error_message'] = "कृपया केवल CSV फ़ाइल अपलोड करें।";
        header('Location: manage_schools.php');
        exit;
    }
    
    try {
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            $header = fgetcsv($handle);
            // --- MODIFIED: Check for 90 columns ---
            if (count($header) < 90) {
                $_SESSION['error_message'] = "CSV फ़ाइल में कम से कम 90 कॉलम होने चाहिए। वर्तमान में " . count($header) . " कॉलम मिले।";
                fclose($handle);
                header('Location: manage_schools.php');
                exit;
            }
            
            $success_count = 0;
            $error_count = 0;
            
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                if (count($data) < 90) {
                    $error_count++;
                    continue;
                }
                
                try {
                    // --- MODIFIED: Assign all 90 fields ---
                    $udise_code = trim($data[0]);
                    $name = trim($data[1]);
                    // ... map all 90 fields from $data array ...
                    $fields = [
                        'udise_code', 'name', 'district_id', 'block_id', 'cluster_name', 'location_type',
                        'panchayat_name', 'village_name', 'parliamentary_name', 'assembly_name', 'pincode',
                        'management_name', 'school_category', 'school_min_class', 'school_max_class', 'school_type',
                        'incharge_type', 'head_of_school', 'head_of_school_number', 'respondent_type', 'respondent_name',
                        'respondent_number', 'hos_email', 'medium_of_instruction', 'language_names', 'operational_status',
                        'latitude', 'longitude', 'good_rooms', 'bad_rooms', 'working_toilets', 'bad_toilets', 'has_ramp',
                        'working_handpumps', 'has_samrasal', 'working_samrasal', 'bad_samrasal', 'has_electricity',
                        'consumer_number', 'working_fans', 'good_bench_desks', 'bad_bench_desks', 'is_landless',
                        'has_extra_land', 'extra_land_area_sqft', 'rooms_needed', 'harmonium_count',
                        'tabla_count', 'other_instruments_count', 'wheelchair_count', 'has_boundary', 'boundary_broken',
                        'boundary_complete', 'boundary_incomplete', 'boundary_needs_height_increase', 'football_count',
                        'small_ball_count', 'bat_count', 'has_ict_lab', 'total_computers', 'working_computers',
                        'total_projectors', 'working_projectors', 'working_printers', 'has_smart_class',
                        'smart_total_projectors', 'smart_working_projectors', 'total_smart_boards', 'working_smart_boards',
                        'television_count', 'working_television_count', 'library_rooms', 'cupboards_count', 'tables_count',
                        'chairs_count', 'books_count', 'fln_kits_received', 'fln_kits_distributed', 'fln_kits_remaining',
                        'pbl_kits_received', 'has_mid_day_meal', 'plates_count', 'glasses_count', 'jugs_count', 'mats_count',
                        'working_cooks_count'
                    ];
                    $values = [];
                    foreach($fields as $index => $field) {
                        $values[$field] = trim($data[$index + 2]); // +2 because id, udise_code are first two
                    }
                    $password = trim($data[88]); // Assuming password is at index 88
                    $password = password_hash(empty($password) ? '123456' : $password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("SELECT id FROM schools WHERE udise_code = ?");
                    $stmt->execute([$udise_code]);
                    $existing_school = $stmt->fetch();
                    
                    if ($existing_school) {
                        $set_clause = [];
                        $update_params = [];
                        foreach($fields as $field) {
                           $set_clause[] = "$field = ?";
                           $update_params[] = $values[$field];
                        }
                        $update_params[] = $password;
                        $update_params[] = $udise_code; // for WHERE clause
                        $sql = "UPDATE schools SET " . implode(', ', $set_clause) . ", password = ?, updated_at = NOW() WHERE udise_code = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($update_params);
                    } else {
                        $insert_fields = array_merge($fields, ['password', 'created_at']);
                        $insert_values = array_merge(array_values($values), [$password, date('Y-m-d H:i:s')]);
                        $placeholders = str_repeat('?,', count($insert_values) - 1) . '?';
                        $sql = "INSERT INTO schools (" . implode(', ', $insert_fields) . ") VALUES ($placeholders)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($insert_values);
                    }
                    $success_count++;
                } catch (PDOException $e) {
                    $error_count++;
                    // Log detailed error for admin if needed
                }
            }
            fclose($handle);
            $_SESSION['success_message'] = "विद्यालय डेटा सफलतापूर्वक अपलोड और अपडेट किया गया! सफल: {$success_count}, त्रुटि: {$error_count}";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "डेटा अपलोड करते समय त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_schools.php');
    exit;
}

// विद्यालय हटाने की प्रक्रिया - केवल एडमिन के लिए
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_school') {
    if ($_SESSION['user_type'] !== 'admin') {
        $_SESSION['error_message'] = "आपको विद्यालय हटाने की अनुमति नहीं है।";
        header('Location: manage_schools.php');
        exit;
    }
    
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

// उपयोगकर्ता प्रकार के आधार पर विद्यालयों की सूची प्राप्त करें
 $user_type = $_SESSION['user_type'];
 $where_clause = "";
 $params = [];
 $count_params = [];

if (in_array($user_type, ['district_staff', 'district_program_officer', 'district_education_officer'])) {
    $district_id = $_SESSION['district_id'];
    $where_clause = "WHERE s.district_id = ?";
    $params = [$district_id];
    $count_params = [$district_id];
} elseif (in_array($user_type, ['ddo', 'block_officer'])) {
    $block_id = $_SESSION['block_id'];
    $where_clause = "WHERE s.block_id = ?";
    $params = [$block_id];
    $count_params = [$block_id];
}

 $count_query = "SELECT COUNT(*) FROM schools s $where_clause";
 $stmt = $conn->prepare($count_query);
 $stmt->execute($count_params);
 $total_schools = $stmt->fetchColumn();

if ($per_page == 'all') {
    $limit_clause = "";
} else {
    $limit_clause = "LIMIT $per_page OFFSET $offset";
}

 $stmt = $conn->prepare("SELECT s.*, d.name as district_name, b.name as block_name FROM schools s 
                        LEFT JOIN districts d ON s.district_id = d.id 
                        LEFT JOIN blocks b ON s.block_id = b.id 
                        $where_clause 
                        ORDER BY s.name
                        $limit_clause");
 $stmt->execute($params);
 $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $total_pages = ($per_page == 'all') ? 1 : ceil($total_schools / $per_page);
 $current_page = ($per_page == 'all') ? 1 : $page;

 $stmt = $conn->query("SELECT * FROM districts ORDER BY name");
 $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $can_edit = ($_SESSION['user_type'] === 'admin');
 $can_upload = ($_SESSION['user_type'] === 'admin');
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
        .detail-row { display: flex; margin-bottom: 10px; }
        .detail-label { font-weight: 600; width: 150px; color: var(--primary-color); }
        .detail-value { flex: 1; }
        .user-type-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: 10px; }
        .user-type-admin { background-color: #6a1b9a; color: white; }
        .user-type-district { background-color: #2196F3; color: white; }
        .user-type-block { background-color: #4CAF50; color: white; }
        .pagination-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-top: 20px; }
        .pagination-info { color: #666; font-size: 0.9rem; }
        .pagination-controls .page-link { color: var(--primary-color); border-color: #ddd; }
        .pagination-controls .page-link:hover { color: white; background-color: var(--primary-color); border-color: var(--primary-color); }
        .pagination-controls .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
        .per-page-selector { display: flex; align-items: center; gap: 10px; }
        .per-page-selector select { width: auto; }
        .form-container-scroll { max-height: 60vh; overflow-y: auto; padding-right: 10px; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); width: 280px; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
    </style>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar_template.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">विद्यालय प्रबंधन</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?>
                            <?php 
                            if ($_SESSION['user_type'] === 'admin') echo '<span class="user-type-badge user-type-admin">एडमिन</span>';
                            elseif (in_array($_SESSION['user_type'], ['district_staff', 'district_program_officer', 'district_education_officer'])) echo '<span class="user-type-badge user-type-district">जिला अधिकारी</span>';
                            elseif (in_array($_SESSION['user_type'], ['ddo', 'block_officer'])) echo '<span class="user-type-badge user-type-block">ब्लॉक अधिकारी</span>';
                            ?>
                        </h6>
                        <small class="text-muted">System User</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if ($can_upload): ?>
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> अपलोड करें और अपडेट करें</button>
                    </div>
                </form>
                <div class="csv-format-info">
                    <h6><i class="fas fa-info-circle me-2"></i>CSV फॉर्मेट जानकारी</h6>
                    <p class="mb-2">CSV फ़ाइल में निम्नलिखित 90 कॉलम होने चाहिए (इसी क्रम में):</p>
                    <div class="csv-format-columns">
                        <code>id, udise_code, name, district_id, block_id, cluster_name, location_type, panchayat_name, village_name, parliamentary_name, assembly_name, pincode, management_name, school_category, school_min_class, school_max_class, school_type, incharge_type, head_of_school, head_of_school_number, respondent_type, respondent_name, respondent_number, hos_email, medium_of_instruction, language_names, operational_status, latitude, longitude, good_rooms, bad_rooms, working_toilets, bad_toilets, has_ramp, working_handpumps, has_samrasal, working_samrasal, bad_samrasal, has_electricity, consumer_number, working_fans, good_bench_desks, bad_bench_desks, is_landless, has_extra_land, extra_land_area_sqft, rooms_needed, password, harmonium_count, tabla_count, other_instruments_count, wheelchair_count, has_boundary, boundary_broken, boundary_complete, boundary_incomplete, boundary_needs_height_increase, football_count, small_ball_count, bat_count, has_ict_lab, total_computers, working_computers, total_projectors, working_projectors, working_printers, has_smart_class, smart_total_projectors, smart_working_projectors, total_smart_boards, working_smart_boards, television_count, working_television_count, library_rooms, cupboards_count, tables_count, chairs_count, books_count, fln_kits_received, fln_kits_distributed, fln_kits_remaining, pbl_kits_received, has_mid_day_meal, plates_count, glasses_count, jugs_count, mats_count, working_cooks_count, created_at, updated_at</code>
                    </div>
                    <p class="mt-2 mb-0"><strong>नोट:</strong> पासवर्ड कॉलम खाली छोड़ने पर डिफ़ॉल्ट पासवर्ड "123456" सेट हो जाएगा।</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">विद्यालय सूची</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadAllData()"><i class="fas fa-file-csv"></i> CSV डाउनलोड करें</button>
                    <?php if ($can_edit): ?>
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#schoolModal">
                        <i class="fas fa-plus"></i> नया विद्यालय जोड़ें
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchInput" placeholder="विद्यालय का नाम या UDISE कोड से खोजें...">
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
                            <?php 
                            $serial = ($per_page == 'all') ? 1 : (($page - 1) * $per_page) + 1;
                            foreach ($schools as $school): 
                            ?>
                            <tr data-udise="<?php echo strtolower($school['udise_code']); ?>" data-name="<?php echo strtolower($school['name']); ?>">
                                <td class="serial-number"><?php echo $serial++; ?></td>
                                <td><?php echo $school['name']; ?></td>
                                <td><?php echo $school['udise_code']; ?></td>
                                <td><?php echo $school['district_name']; ?></td>
                                <td><?php echo $school['block_name']; ?></td>
                                <td><?php echo $school['head_of_school']; ?></td>
                                <td><?php echo $school['head_of_school_number']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($school)); ?>)"><i class="fas fa-eye"></i></button>
                                    <?php if ($can_edit): ?>
                                    <button class="btn btn-sm btn-primary" onclick="editSchool(<?php echo htmlspecialchars(json_encode($school)); ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete_school">
                                        <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस विद्यालय को हटाना चाहते हैं?');"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="?download_school_csv=<?php echo $school['id']; ?>" class="btn btn-sm btn-success" title="CSV डाउनलोड करें">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination-container">
                    <div class="pagination-info">
                        <?php 
                        if ($per_page == 'all') {
                            echo "कुल " . $total_schools . " विद्यालय";
                        } else {
                            $start = ($page - 1) * $per_page + 1;
                            $end = min($page * $per_page, $total_schools);
                            echo "$start से $end कुल $total_schools विद्यालय";
                        }
                        ?>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="per-page-selector">
                            <label for="perPageSelect" class="form-label mb-0">प्रति पृष्ठ:</label>
                            <select class="form-select form-select-sm" id="perPageSelect" onchange="changePerPage(this.value)">
                                <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="all" <?php echo $per_page == 'all' ? 'selected' : ''; ?>>सभी</option>
                            </select>
                        </div>
                        
                        <?php if ($per_page != 'all' && $total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-controls mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>" tabindex="-1"><i class="fas fa-chevron-left"></i> पिछला</a>
                                </li>
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="?page=1&per_page=' . $per_page . '">1</a></li>'; if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&per_page=' . $per_page . '">' . $i . '</a></li>';
                                }
                                if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&per_page=' . $per_page . '">' . $total_pages . '</a></li>'; }
                                ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>">अगला <i class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- विद्यालय मोडल -->
    <div class="modal fade" id="schoolModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolModalTitle">नया विद्यालय जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manage_schools.php" id="schoolForm">
                    <input type="hidden" name="action" id="formAction" value="save_school">
                    <input type="hidden" name="school_id" id="schoolId">
                    <div class="modal-body">
                        <div class="form-container-scroll">
                            <div class="row">
                                <!-- All 90 fields remain here, unchanged from previous version -->
                                <div class="col-md-4 mb-3"><label for="udise_code" class="form-label">UDISE कोड</label><input type="text" class="form-control" id="udise_code" name="udise_code" required></div>
                                <div class="col-md-4 mb-3"><label for="name" class="form-label">विद्यालय का नाम</label><input type="text" class="form-control" id="name" name="name" required></div>
                                <div class="col-md-4 mb-3"><label for="district_id" class="form-label">जिला</label><select class="form-select" id="district_id" name="district_id" required onchange="loadBlocksForModal(this.value)"><option value="">चुनें</option><?php foreach ($districts as $district): ?><option value="<?php echo $district['id']; ?>"><?php echo $district['name']; ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-4 mb-3"><label for="block_id" class="form-label">प्रखंड</label><select class="form-select" id="block_id" name="block_id" required><option value="">पहले जिला चुनें</option></select></div>
                                <div class="col-md-4 mb-3"><label for="cluster_name" class="form-label">क्लस्टर नाम</label><input type="text" class="form-control" id="cluster_name" name="cluster_name"></div>
                                <div class="col-md-4 mb-3"><label for="location_type" class="form-label">स्थान प्रकार</label><input type="text" class="form-control" id="location_type" name="location_type"></div>
                                <div class="col-md-4 mb-3"><label for="panchayat_name" class="form-label">पंचायत</label><input type="text" class="form-control" id="panchayat_name" name="panchayat_name"></div>
                                <div class="col-md-4 mb-3"><label for="village_name" class="form-label">गांव</label><input type="text" class="form-control" id="village_name" name="village_name"></div>
                                <div class="col-md-4 mb-3"><label for="parliamentary_name" class="form-label">संसदीय क्षेत्र</label><input type="text" class="form-control" id="parliamentary_name" name="parliamentary_name"></div>
                                <div class="col-md-4 mb-3"><label for="assembly_name" class="form-label">विधानसभा क्षेत्र</label><input type="text" class="form-control" id="assembly_name" name="assembly_name"></div>
                                <div class="col-md-4 mb-3"><label for="pincode" class="form-label">पिनकोड</label><input type="text" class="form-control" id="pincode" name="pincode"></div>
                                <div class="col-md-4 mb-3"><label for="management_name" class="form-label">प्रबंधन</label><input type="text" class="form-control" id="management_name" name="management_name"></div>
                                <div class="col-md-4 mb-3"><label for="school_category" class="form-label">विद्यालय श्रेणी</label><input type="text" class="form-control" id="school_category" name="school_category"></div>
                                <div class="col-md-4 mb-3"><label for="school_min_class" class="form-label">न्यूनतम कक्षा</label><input type="text" class="form-control" id="school_min_class" name="school_min_class"></div>
                                <div class="col-md-4 mb-3"><label for="school_max_class" class="form-label">अधिकतम कक्षा</label><input type="text" class="form-control" id="school_max_class" name="school_max_class"></div>
                                <div class="col-md-4 mb-3"><label for="school_type" class="form-label">विद्यालय प्रकार</label><input type="text" class="form-control" id="school_type" name="school_type"></div>
                                <div class="col-md-4 mb-3"><label for="incharge_type" class="form-label">प्रभारी प्रकार</label><input type="text" class="form-control" id="incharge_type" name="incharge_type"></div>
                                <div class="col-md-4 mb-3"><label for="head_of_school" class="form-label">प्रधानाध्यापक</label><input type="text" class="form-control" id="head_of_school" name="head_of_school"></div>
                                <div class="col-md-4 mb-3"><label for="head_of_school_number" class="form-label">प्रधानाध्यापक का मोबाइल नंबर</label><input type="text" class="form-control" id="head_of_school_number" name="head_of_school_number"></div>
                                <div class="col-md-4 mb-3"><label for="respondent_type" class="form-label">Respondent प्रकार</label><input type="text" class="form-control" id="respondent_type" name="respondent_type"></div>
                                <div class="col-md-4 mb-3"><label for="respondent_name" class="form-label">Respondent का नाम</label><input type="text" class="form-control" id="respondent_name" name="respondent_name"></div>
                                <div class="col-md-4 mb-3"><label for="respondent_number" class="form-label">Respondent नंबर</label><input type="text" class="form-control" id="respondent_number" name="respondent_number"></div>
                                <div class="col-md-4 mb-3"><label for="hos_email" class="form-label">प्रधानाध्यापक का ईमेल</label><input type="email" class="form-control" id="hos_email" name="hos_email"></div>
                                <div class="col-md-4 mb-3"><label for="medium_of_instruction" class="form-label">शिक्षण का माध्यम</label><input type="text" class="form-control" id="medium_of_instruction" name="medium_of_instruction"></div>
                                <div class="col-md-4 mb-3"><label for="language_names" class="form-label">भाषाओं के नाम</label><input type="text" class="form-control" id="language_names" name="language_names"></div>
                                <div class="col-md-4 mb-3"><label for="operational_status" class="form-label">परिचालन स्थिति</label><input type="text" class="form-control" id="operational_status" name="operational_status"></div>
                                <div class="col-md-4 mb-3"><label for="latitude" class="form-label">अक्षांश</label><input type="text" class="form-control" id="latitude" name="latitude"></div>
                                <div class="col-md-4 mb-3"><label for="longitude" class="form-label">देशांतर</label><input type="text" class="form-control" id="longitude" name="longitude"></div>
                                <div class="col-md-4 mb-3"><label for="good_rooms" class="form-label">अच्छे कमरे</label><input type="number" class="form-control" id="good_rooms" name="good_rooms"></div>
                                <div class="col-md-4 mb-3"><label for="bad_rooms" class="form-label">खराब कमरे</label><input type="number" class="form-control" id="bad_rooms" name="bad_rooms"></div>
                                <div class="col-md-4 mb-3"><label for="working_toilets" class="form-label">कार्यशील शौचालय</label><input type="number" class="form-control" id="working_toilets" name="working_toilets"></div>
                                <div class="col-md-4 mb-3"><label for="bad_toilets" class="form-label">खराब शौचालय</label><input type="number" class="form-control" id="bad_toilets" name="bad_toilets"></div>
                                <div class="col-md-4 mb-3"><label for="has_ramp" class="form-label">रैंप है</label><input type="text" class="form-control" id="has_ramp" name="has_ramp"></div>
                                <div class="col-md-4 mb-3"><label for="working_handpumps" class="form-label">कार्यशील हैंडपंप</label><input type="number" class="form-control" id="working_handpumps" name="working_handpumps"></div>
                                <div class="col-md-4 mb-3"><label for="has_samrasal" class="form-label">समरसाल है</label><input type="text" class="form-control" id="has_samrasal" name="has_samrasal"></div>
                                <div class="col-md-4 mb-3"><label for="working_samrasal" class="form-label">कार्यशील समरसाल</label><input type="number" class="form-control" id="working_samrasal" name="working_samrasal"></div>
                                <div class="col-md-4 mb-3"><label for="bad_samrasal" class="form-label">खराब समरसाल</label><input type="number" class="form-control" id="bad_samrasal" name="bad_samrasal"></div>
                                <div class="col-md-4 mb-3"><label for="has_electricity" class="form-label">बिजली है</label><input type="text" class="form-control" id="has_electricity" name="has_electricity"></div>
                                <div class="col-md-4 mb-3"><label for="consumer_number" class="form-label">उपभोक्ता संख्या</label><input type="text" class="form-control" id="consumer_number" name="consumer_number"></div>
                                <div class="col-md-4 mb-3"><label for="working_fans" class="form-label">कार्यशील पंखे</label><input type="number" class="form-control" id="working_fans" name="working_fans"></div>
                                <div class="col-md-4 mb-3"><label for="good_bench_desks" class="form-label">अच्छी बेंच-डेस्क</label><input type="number" class="form-control" id="good_bench_desks" name="good_bench_desks"></div>
                                <div class="col-md-4 mb-3"><label for="bad_bench_desks" class="form-label">खराब बेंच-डेस्क</label><input type="number" class="form-control" id="bad_bench_desks" name="bad_bench_desks"></div>
                                <div class="col-md-4 mb-3"><label for="is_landless" class="form-label">भूमि रहित है</label><input type="text" class="form-control" id="is_landless" name="is_landless"></div>
                                <div class="col-md-4 mb-3"><label for="has_extra_land" class="form-label">अतिरिक्त भूमि है</label><input type="text" class="form-control" id="has_extra_land" name="has_extra_land"></div>
                                <div class="col-md-4 mb-3"><label for="extra_land_area_sqft" class="form-label">अतिरिक्त भूमि क्षेत्रफल (वर्ग फुट)</label><input type="number" class="form-control" id="extra_land_area_sqft" name="extra_land_area_sqft"></div>
                                <div class="col-md-4 mb-3"><label for="rooms_needed" class="form-label">कमरों की आवश्यकता</label><input type="number" class="form-control" id="rooms_needed" name="rooms_needed"></div>
                                <div class="col-md-4 mb-3"><label for="harmonium_count" class="form-label">हारमोनियम की संख्या</label><input type="number" class="form-control" id="harmonium_count" name="harmonium_count"></div>
                                <div class="col-md-4 mb-3"><label for="tabla_count" class="form-label">तबला की संख्या</label><input type="number" class="form-control" id="tabla_count" name="tabla_count"></div>
                                <div class="col-md-4 mb-3"><label for="other_instruments_count" class="form-label">अन्य वाद्य यंत्रों की संख्या</label><input type="number" class="form-control" id="other_instruments_count" name="other_instruments_count"></div>
                                <div class="col-md-4 mb-3"><label for="wheelchair_count" class="form-label">व्हीलचेयर की संख्या</label><input type="number" class="form-control" id="wheelchair_count" name="wheelchair_count"></div>
                                <div class="col-md-4 mb-3"><label for="has_boundary" class="form-label">सीमा है</label><input type="text" class="form-control" id="has_boundary" name="has_boundary"></div>
                                <div class="col-md-4 mb-3"><label for="boundary_broken" class="form-label">सीमा टूटी हुई है</label><input type="text" class="form-control" id="boundary_broken" name="boundary_broken"></div>
                                <div class="col-md-4 mb-3"><label for="boundary_complete" class="form-label">सीमा पूरी है</label><input type="text" class="form-control" id="boundary_complete" name="boundary_complete"></div>
                                <div class="col-md-4 mb-3"><label for="boundary_incomplete" class="form-label">सीमा अपूरी है</label><input type="text" class="form-control" id="boundary_incomplete" name="boundary_incomplete"></div>
                                <div class="col-md-4 mb-3"><label for="boundary_needs_height_increase" class="form-label">सीमा की ऊंचाई बढ़ाने की आवश्यकता</label><input type="text" class="form-control" id="boundary_needs_height_increase" name="boundary_needs_height_increase"></div>
                                <div class="col-md-4 mb-3"><label for="football_count" class="form-label">फुटबॉल की संख्या</label><input type="number" class="form-control" id="football_count" name="football_count"></div>
                                <div class="col-md-4 mb-3"><label for="small_ball_count" class="form-label">छोटी गेंद की संख्या</label><input type="number" class="form-control" id="small_ball_count" name="small_ball_count"></div>
                                <div class="col-md-4 mb-3"><label for="bat_count" class="form-label">बल्ले की संख्या</label><input type="number" class="form-control" id="bat_count" name="bat_count"></div>
                                <div class="col-md-4 mb-3"><label for="has_ict_lab" class="form-label">ICT लैब है</label><input type="text" class="form-control" id="has_ict_lab" name="has_ict_lab"></div>
                                <div class="col-md-4 mb-3"><label for="total_computers" class="form-label">कुल कंप्यूटर</label><input type="number" class="form-control" id="total_computers" name="total_computers"></div>
                                <div class="col-md-4 mb-3"><label for="working_computers" class="form-label">कार्यशील कंप्यूटर</label><input type="number" class="form-control" id="working_computers" name="working_computers"></div>
                                <div class="col-md-4 mb-3"><label for="total_projectors" class="form-label">कुल प्रोजेक्टर</label><input type="number" class="form-control" id="total_projectors" name="total_projectors"></div>
                                <div class="col-md-4 mb-3"><label for="working_projectors" class="form-label">कार्यशील प्रोजेक्टर</label><input type="number" class="form-control" id="working_projectors" name="working_projectors"></div>
                                <div class="col-md-4 mb-3"><label for="working_printers" class="form-label">कार्यशील प्रिंटर</label><input type="number" class="form-control" id="working_printers" name="working_printers"></div>
                                <div class="col-md-4 mb-3"><label for="has_smart_class" class="form-label">स्मार्ट क्लास है</label><input type="text" class="form-control" id="has_smart_class" name="has_smart_class"></div>
                                <div class="col-md-4 mb-3"><label for="smart_total_projectors" class="form-label">स्मार्ट कुल प्रोजेक्टर</label><input type="number" class="form-control" id="smart_total_projectors" name="smart_total_projectors"></div>
                                <div class="col-md-4 mb-3"><label for="smart_working_projectors" class="form-label">स्मार्ट कार्यशील प्रोजेक्टर</label><input type="number" class="form-control" id="smart_working_projectors" name="smart_working_projectors"></div>
                                <div class="col-md-4 mb-3"><label for="total_smart_boards" class="form-label">कुल स्मार्ट बोर्ड</label><input type="number" class="form-control" id="total_smart_boards" name="total_smart_boards"></div>
                                <div class="col-md-4 mb-3"><label for="working_smart_boards" class="form-label">कार्यशील स्मार्ट बोर्ड</label><input type="number" class="form-control" id="working_smart_boards" name="working_smart_boards"></div>
                                <div class="col-md-4 mb-3"><label for="television_count" class="form-label">टेलीविजन की संख्या</label><input type="number" class="form-control" id="television_count" name="television_count"></div>
                                <div class="col-md-4 mb-3"><label for="working_television_count" class="form-label">कार्यशील टेलीविजन</label><input type="number" class="form-control" id="working_television_count" name="working_television_count"></div>
                                <div class="col-md-4 mb-3"><label for="library_rooms" class="form-label">पुस्तकालय कमरे</label><input type="number" class="form-control" id="library_rooms" name="library_rooms"></div>
                                <div class="col-md-4 mb-3"><label for="cupboards_count" class="form-label">अलमारी की संख्या</label><input type="number" class="form-control" id="cupboards_count" name="cupboards_count"></div>
                                <div class="col-md-4 mb-3"><label for="tables_count" class="form-label">मेज की संख्या</label><input type="number" class="form-control" id="tables_count" name="tables_count"></div>
                                <div class="col-md-4 mb-3"><label for="chairs_count" class="form-label">कुर्सी की संख्या</label><input type="number" class="form-control" id="chairs_count" name="chairs_count"></div>
                                <div class="col-md-4 mb-3"><label for="books_count" class="form-label">पुस्तकों की संख्या</label><input type="number" class="form-control" id="books_count" name="books_count"></div>
                                <div class="col-md-4 mb-3"><label for="fln_kits_received" class="form-label">FLN किट प्राप्त</label><input type="number" class="form-control" id="fln_kits_received" name="fln_kits_received"></div>
                                <div class="col-md-4 mb-3"><label for="fln_kits_distributed" class="form-label">FLN किट वितरित</label><input type="number" class="form-control" id="fln_kits_distributed" name="fln_kits_distributed"></div>
                                <div class="col-md-4 mb-3"><label for="fln_kits_remaining" class="form-label">FLN किट शेष</label><input type="number" class="form-control" id="fln_kits_remaining" name="fln_kits_remaining"></div>
                                <div class="col-md-4 mb-3"><label for="pbl_kits_received" class="form-label">PBL किट प्राप्त</label><input type="number" class="form-control" id="pbl_kits_received" name="pbl_kits_received"></div>
                                <div class="col-md-4 mb-3"><label for="has_mid_day_meal" class="form-label">मध्याहन भोजन है</label><input type="text" class="form-control" id="has_mid_day_meal" name="has_mid_day_meal"></div>
                                <div class="col-md-4 mb-3"><label for="plates_count" class="form-label">प्लेट्स की संख्या</label><input type="number" class="form-control" id="plates_count" name="plates_count"></div>
                                <div class="col-md-4 mb-3"><label for="glasses_count" class="form-label">ग्लास की संख्या</label><input type="number" class="form-control" id="glasses_count" name="glasses_count"></div>
                                <div class="col-md-4 mb-3"><label for="jugs_count" class="form-label">घड़े की संख्या</label><input type="number" class="form-control" id="jugs_count" name="jugs_count"></div>
                                <div class="col-md-4 mb-3"><label for="mats_count" class="form-label">चटाई की संख्या</label><input type="number" class="form-control" id="mats_count" name="mats_count"></div>
                                <div class="col-md-4 mb-3"><label for="working_cooks_count" class="form-label">कार्यशील रसोइयों की संख्या</label><input type="number" class="form-control" id="working_cooks_count" name="working_cooks_count"></div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
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
                                                <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword"><i class="fas fa-eye"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">पासवर्ड की पुष्टि करें</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="fas fa-eye"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- --- NEW: Separate Password Update Button --- -->
                                    <div class="d-grid gap-2 d-md-block mt-2" id="passwordUpdateButtons" style="display:none;">
                                        <button type="button" class="btn btn-warning" onclick="submitPasswordUpdate()">पासवर्ड अपडेट करें</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <!-- --- MODIFIED: Changed button text and removed name/value --- -->
                        <button type="submit" class="btn btn-primary">विवरण सहेजें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- विद्यालय विवरण मोडल -->
    <div class="modal fade" id="schoolDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">विद्यालय विवरण</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="schoolDetailContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">बंद करें</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
        const uploadArea = document.getElementById('uploadArea'); const fileInput = document.getElementById('school_file'); const browseBtn = document.getElementById('browseBtn');
        browseBtn.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = '#e1bee7'; });
        uploadArea.addEventListener('dragleave', () => { uploadArea.style.backgroundColor = 'var(--light-color)'; });
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = 'var(--light-color)'; if (e.data.files.length) { fileInput.files = e.data.files; } });
        document.getElementById('searchInput').addEventListener('keyup', function() { const value = this.value.toLowerCase(); const rows = document.querySelectorAll('#schoolTable tbody tr'); rows.forEach(row => { const udise = row.getAttribute('data-udise'); const name = row.getAttribute('data-name'); row.style.display = (udise.includes(value) || name.includes(value)) ? '' : 'none'; }); });
        function changePerPage(value) { const url = new URL(window.location); url.searchParams.set('per_page', value); url.searchParams.set('page', 1); window.location.href = url.toString(); }
        document.getElementById('schoolModal').addEventListener('hidden.bs.modal', function () { document.getElementById('schoolForm').reset(); document.getElementById('schoolModalTitle').textContent = 'नया विद्यालय जोड़ें'; document.getElementById('schoolId').value = ''; document.getElementById('passwordSection').style.display = 'none'; document.getElementById('passwordUpdateButtons').style.display = 'none'; });
        function loadBlocksForModal(districtId) { if (!districtId) { document.getElementById('block_id').innerHTML = '<option value="">पहले जिला चुनें</option>'; return; } fetch(`get_blocks.php?district_id=${districtId}`).then(response => response.json()).then(blocks => { let blockOptions = '<option value="">प्रखंड चुनें</option>'; blocks.forEach(block => { blockOptions += `<option value="${block.id}">${block.name}</option>`; }); document.getElementById('block_id').innerHTML = blockOptions; }); }
        
        function editSchool(school) {
            document.getElementById('schoolModalTitle').textContent = 'विद्यालय जानकारी संपादित करें';
            const fields = ['id', 'udise_code', 'name', 'district_id', 'block_id', 'cluster_name', 'location_type', 'panchayat_name', 'village_name', 'parliamentary_name', 'assembly_name', 'pincode', 'management_name', 'school_category', 'school_min_class', 'school_max_class', 'school_type', 'incharge_type', 'head_of_school', 'head_of_school_number', 'respondent_type', 'respondent_name', 'respondent_number', 'hos_email', 'medium_of_instruction', 'language_names', 'operational_status', 'latitude', 'longitude', 'good_rooms', 'bad_rooms', 'working_toilets', 'bad_toilets', 'has_ramp', 'working_handpumps', 'has_samrasal', 'working_samrasal', 'bad_samrasal', 'has_electricity', 'consumer_number', 'working_fans', 'good_bench_desks', 'bad_bench_desks', 'is_landless', 'has_extra_land', 'extra_land_area_sqft', 'rooms_needed', 'harmonium_count', 'tabla_count', 'other_instruments_count', 'wheelchair_count', 'has_boundary', 'boundary_broken', 'boundary_complete', 'boundary_incomplete', 'boundary_needs_height_increase', 'football_count', 'small_ball_count', 'bat_count', 'has_ict_lab', 'total_computers', 'working_computers', 'total_projectors', 'working_projectors', 'working_printers', 'has_smart_class', 'smart_total_projectors', 'smart_working_projectors', 'total_smart_boards', 'working_smart_boards', 'television_count', 'working_television_count', 'library_rooms', 'cupboards_count', 'tables_count', 'chairs_count', 'books_count', 'fln_kits_received', 'fln_kits_distributed', 'fln_kits_remaining', 'pbl_kits_received', 'has_mid_day_meal', 'plates_count', 'glasses_count', 'jugs_count', 'mats_count', 'working_cooks_count'];
            fields.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.value = school[field] || '';
                }
            });
            loadBlocksForModal(school.district_id);
            setTimeout(() => { document.getElementById('block_id').value = school.block_id || ''; }, 300);
            document.getElementById('new_password').value = ''; document.getElementById('confirm_password').value = ''; document.getElementById('passwordSection').style.display = 'none'; document.getElementById('passwordUpdateButtons').style.display = 'none';
            new bootstrap.Modal(document.getElementById('schoolModal')).show();
        }

        // --- UPDATED: New, robust viewDetails function ---
        function viewDetails(school) {
            const detailContent = document.getElementById('schoolDetailContent');
            
            // Create a mapping for more user-friendly labels (Hindi)
            const labelMap = {
                'id': 'आईडी', 'udise_code': 'UDISE कोड', 'name': 'विद्यालय का नाम', 'district_name': 'जिला', 'block_name': 'प्रखंड',
                'cluster_name': 'क्लस्टर नाम', 'location_type': 'स्थान प्रकार', 'panchayat_name': 'पंचायत', 'village_name': 'गांव',
                'parliamentary_name': 'संसदीय क्षेत्र', 'assembly_name': 'विधानसभा क्षेत्र', 'pincode': 'पिनकोड',
                'management_name': 'प्रबंधन', 'school_category': 'विद्यालय श्रेणी', 'school_min_class': 'न्यूनतम कक्षा',
                'school_max_class': 'अधिकतम कक्षा', 'school_type': 'विद्यालय प्रकार', 'incharge_type': 'प्रभारी प्रकार',
                'head_of_school': 'प्रधानाध्यापक', 'head_of_school_number': 'प्रधानाध्यापक का मोबाइल नंबर',
                'respondent_type': 'Respondent प्रकार', 'respondent_name': 'Respondent का नाम', 'respondent_number': 'Respondent नंबर',
                'hos_email': 'प्रधानाध्यापक का ईमेल', 'medium_of_instruction': 'शिक्षण का माध्यम', 'language_names': 'भाषाओं के नाम',
                'operational_status': 'परिचालन स्थिति', 'latitude': 'अक्षांश', 'longitude': 'देशांतर',
                'good_rooms': 'अच्छे कमरे', 'bad_rooms': 'खराब कमरे', 'working_toilets': 'कार्यशील शौचालय',
                'bad_toilets': 'खराब शौचालय', 'has_ramp': 'रैंप', 'working_handpumps': 'कार्यशील हैंडपंप',
                'has_samrasal': 'समरसाल', 'working_samrasal': 'कार्यशील समरसाल', 'bad_samrasal': 'खराब समरसाल',
                'has_electricity': 'बिजली', 'consumer_number': 'उपभोक्ता संख्या', 'working_fans': 'कार्यशील पंखे',
                'good_bench_desks': 'अच्छी बेंच-डेस्क', 'bad_bench_desks': 'खराब बेंच-डेस्क', 'is_landless': 'भूमि रहित',
                'has_extra_land': 'अतिरिक्त भूमि', 'extra_land_area_sqft': 'अतिरिक्त भूमि क्षेत्रफल (वर्ग फुट)',
                'rooms_needed': 'कमरों की आवश्यकता', 'harmonium_count': 'हारमोनियम', 'tabla_count': 'तबला',
                'other_instruments_count': 'अन्य वाद्य यंत्र', 'wheelchair_count': 'व्हीलचेयर', 'has_boundary': 'सीमा',
                'boundary_broken': 'सीमा टूटी हुई', 'boundary_complete': 'सीमा पूरी', 'boundary_incomplete': 'सीमा अपूरी',
                'boundary_needs_height_increase': 'सीमा की ऊंचाई बढ़ाने की आवश्यकता', 'football_count': 'फुटबॉल',
                'small_ball_count': 'छोटी गेंद', 'bat_count': 'बल्ले', 'has_ict_lab': 'ICT लैब',
                'total_computers': 'कुल कंप्यूटर', 'working_computers': 'कार्यशील कंप्यूटर',
                'total_projectors': 'कुल प्रोजेक्टर', 'working_projectors': 'कार्यशील प्रोजेक्टर', 'working_printers': 'कार्यशील प्रिंटर',
                'has_smart_class': 'स्मार्ट क्लास', 'smart_total_projectors': 'स्मार्ट कुल प्रोजेक्टर',
                'smart_working_projectors': 'स्मार्ट कार्यशील प्रोजेक्टर', 'total_smart_boards': 'कुल स्मार्ट बोर्ड',
                'working_smart_boards': 'कार्यशील स्मार्ट बोर्ड', 'television_count': 'टेलीविजन',
                'working_television_count': 'कार्यशील टेलीविजन', 'library_rooms': 'पुस्तकालय कमरे', 'cupboards_count': 'अलमारी',
                'tables_count': 'मेज', 'chairs_count': 'कुर्सी', 'books_count': 'पुस्तकें', 'fln_kits_received': 'FLN किट प्राप्त',
                'fln_kits_distributed': 'FLN किट वितरित', 'fln_kits_remaining': 'FLN किट शेष',
                'pbl_kits_received': 'PBL किट प्राप्त', 'has_mid_day_meal': 'मध्याहन भोजन',
                'plates_count': 'प्लेट्स', 'glasses_count': 'ग्लास', 'jugs_count': 'घड़े', 'mats_count': 'चटाई',
                'working_cooks_count': 'कार्यशील रसोइये', 'created_at': 'बनाया गया', 'updated_at': 'अपडेट किया गया'
            };

            let detailHTML = '<div class="row">';
            let colCount = 0;
            for (const key in school) {
                if (school.hasOwnProperty(key) && labelMap[key]) {
                    const label = labelMap[key];
                    const value = school[key] ? school[key] : '-';

                    if (colCount % 2 === 0) detailHTML += '<div class="col-md-6">';
                    
                    detailHTML += `
                        <div class="detail-row">
                            <div class="detail-label">${label}:</div>
                            <div class="detail-value">${value}</div>
                        </div>
                    `;

                    colCount++;
                    if (colCount % 2 === 0) {
                        detailHTML += '</div>'; // Close col-md-6
                    }
                }
            }
            
            // Ensure the last column is closed if the number of fields is odd
            if (colCount % 2 !== 0) {
                detailHTML += '</div>';
            }

            detailHTML += '</div>'; // Close the main row
            
            detailContent.innerHTML = detailHTML;
            new bootstrap.Modal(document.getElementById('schoolDetailModal')).show();
        }
        
        function downloadAllData() {
            window.location.href = 'manage_schools.php?download_all_csv=true';
        }

        // --- MODIFIED: Password section toggle and new function ---
        document.getElementById('togglePasswordSection').addEventListener('click', function() {
            const section = document.getElementById('passwordSection');
            const buttons = document.getElementById('passwordUpdateButtons');
            const isVisible = section.style.display === 'block';
            
            section.style.display = isVisible ? 'none' : 'block';
            buttons.style.display = isVisible ? 'none' : 'block';
        });

        // --- NEW: Function to handle password update submission ---
        function submitPasswordUpdate() {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;

            if (!newPass || !confirmPass) {
                alert('कृपया नया पासवर्ड और पुष्टि पासवर्ड दोनों भरें।');
                return false;
            }

            if (newPass !== confirmPass) {
                alert('नया पासवर्ड और पुष्टि पासवर्ड मेल नहीं खाते।');
                return false;
            }

            // Set action for form
            document.getElementById('formAction').value = 'update_password_only';
            // Submit form
            document.getElementById('schoolForm').submit();
        }

        document.getElementById('toggleNewPassword').addEventListener('click', function() { const passwordField = document.getElementById('new_password'); const icon = this.querySelector('i'); if (passwordField.type === 'password') { passwordField.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } else { passwordField.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } });
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() { const passwordField = document.getElementById('confirm_password'); const icon = this.querySelector('i'); if (passwordField.type === 'password') { passwordField.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } else { passwordField.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); } });
    </script>
</body>
</html>