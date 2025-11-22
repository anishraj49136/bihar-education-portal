<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

// विद्यालय की जानकारी प्राप्त करें
 $school_id = $_SESSION['school_id'];
 $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
 $stmt->execute([$school_id]);
 $school = $stmt->fetch(PDO::FETCH_ASSOC);

// वर्तमान महीना और वर्ष प्राप्त करें
 $current_month = date('F');
 $current_year = date('Y');

// जांचें कि school_enrollment टेबल मौजूद है या नहीं
try {
    $stmt = $conn->prepare("SELECT 1 FROM school_enrollment LIMIT 1");
    $stmt->execute();
} catch (PDOException $e) {
    // यदि टेबल मौजूद नहीं है, तो इसे बनाएं
    $createTableQuery = "CREATE TABLE IF NOT EXISTS `school_enrollment` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `school_id` int(11) NOT NULL,
      `month` varchar(20) NOT NULL,
      `year` int(4) NOT NULL,
      `class_1` int(11) NOT NULL DEFAULT 0,
      `class_2` int(11) NOT NULL DEFAULT 0,
      `class_3` int(11) NOT NULL DEFAULT 0,
      `class_4` int(11) NOT NULL DEFAULT 0,
      `class_5` int(11) NOT NULL DEFAULT 0,
      `class_6` int(11) NOT NULL DEFAULT 0,
      `class_7` int(11) NOT NULL DEFAULT 0,
      `class_8` int(11) NOT NULL DEFAULT 0,
      `class_9` int(11) NOT NULL DEFAULT 0,
      `class_10` int(11) NOT NULL DEFAULT 0,
      `class_11` int(11) NOT NULL DEFAULT 0,
      `class_12` int(11) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_school_month_year` (`school_id`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->exec($createTableQuery);
}

// फॉर्म सबमिशन प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // पहले जांचें कि इस महीने के लिए पहले से डेटा मौजूद है या नहीं
        $stmt = $conn->prepare("SELECT id FROM school_enrollment WHERE school_id = ? AND month = ? AND year = ?");
        $stmt->execute([$school_id, $current_month, $current_year]);
        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // यदि रिकॉर्ड मौजूद है, तो इसे अपडेट करें
            $updateQuery = "UPDATE school_enrollment SET ";
            $updateParams = [];
            for ($i = 1; $i <= 12; $i++) {
                $updateQuery .= "class_{$i} = ?, ";
                $updateParams[] = isset($_POST['enrollment'][$i]) ? $_POST['enrollment'][$i] : 0;
            }
            $updateQuery = rtrim($updateQuery, ", ");
            $updateQuery .= " WHERE school_id = ? AND month = ? AND year = ?";
            $updateParams[] = $school_id;
            $updateParams[] = $current_month;
            $updateParams[] = $current_year;
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute($updateParams);
        } else {
            // यदि रिकॉर्ड मौजूद नहीं है, तो नया रिकॉर्ड डालें
            $insertQuery = "INSERT INTO school_enrollment (school_id, month, year, ";
            $valuesQuery = "VALUES (?, ?, ?, ";
            $insertParams = [$school_id, $current_month, $current_year];
            
            for ($i = 1; $i <= 12; $i++) {
                $insertQuery .= "class_{$i}, ";
                $valuesQuery .= "?, ";
                $insertParams[] = isset($_POST['enrollment'][$i]) ? $_POST['enrollment'][$i] : 0;
            }
            
            $insertQuery = rtrim($insertQuery, ", ");
            $valuesQuery = rtrim($valuesQuery, ", ");
            $insertQuery .= ") " . $valuesQuery . ")";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->execute($insertParams);
        }
        
        $success_message = "नामांकन डेटा सफलतापूर्वक सहेजा गया!";
    } catch (PDOException $e) {
        $error_message = "नामांकन डेटा सहेजते समय त्रुटि: " . $e->getMessage();
    }
}

// वर्तमान महीने के लिए नामांकन डेटा प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM school_enrollment WHERE school_id = ? AND month = ? AND year = ?");
 $stmt->execute([$school_id, $current_month, $current_year]);
 $enrollment_data = $stmt->fetch(PDO::FETCH_ASSOC);

// यदि कोई डेटा नहीं है, तो डिफ़ॉल्ट मान सेट करें
if (!$enrollment_data) {
    $enrollment_data = [];
    for ($i = 1; $i <= 12; $i++) {
        $enrollment_data["class_{$i}"] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>नामांकन - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css">
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
        
        .enrollment-table {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .enrollment-table thead {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .enrollment-table th {
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            border: none;
        }
        
        .enrollment-table td {
            padding: 8px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #e9ecef;
        }
        
        .enrollment-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .class-group-title {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 600;
            text-align: center;
        }
        
        .enrollment-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            background-color: var(--light-color);
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
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
            
            /* टेबल के लिए बेहतर मोबाइल व्यू */
            .enrollment-table {
                font-size: 0.85rem;
            }
            
            .enrollment-table th, .enrollment-table td {
                padding: 6px 4px;
            }
            
            /* फॉर्म एलिमेंट्स के लिए बेहतर स्पेसिंग */
            .form-control {
                padding: 6px 8px;
                font-size: 0.9rem;
            }
            
            /* सारांश अनुभाग के लिए बेहतर स्पेसिंग */
            .enrollment-summary {
                padding: 10px;
            }
            
            .summary-item {
                padding: 6px 0;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .card-header { font-size: 0.9rem; }
            .form-label { font-size: 0.85rem; }
            .form-control, .form-select { font-size: 0.9rem; }
            .btn-primary { padding: 6px 15px; font-size: 0.85rem; }
            .enrollment-table { font-size: 0.8rem; }
            .class-group-title { font-size: 0.9rem; padding: 8px 10px; }
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
                <h4 class="mb-0">नामांकन</h4>
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
        
        <!-- नामांकन फॉर्म -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-graduate me-2"></i>
                माह: <?php echo $current_month . ' ' . $current_year; ?> के लिए नामांकन विवरण
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5>विद्यालय: <?php echo $school['name']; ?></h5>
                    <p class="mb-0">श्रेणी: <?php echo $school['school_category']; ?></p>
                </div>
                
                <form method="post" action="" id="enrollmentForm">
                    <!-- प्राथमिक कक्षाएं (1-5) -->
                    <div class="class-group mb-4">
                        <div class="class-group-title">
                            <i class="fas fa-child me-2"></i>प्राथमिक कक्षाएं (1-5)
                        </div>
                        <div class="table-responsive">
                            <table class="table enrollment-table">
                                <thead>
                                    <tr>
                                        <th>कक्षा</th>
                                        <th>1</th>
                                        <th>2</th>
                                        <th>3</th>
                                        <th>4</th>
                                        <th>5</th>
                                        <th>कुल</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>विद्यार्थी संख्या</strong></td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <td>
                                            <input type="number" name="enrollment[<?php echo $i; ?>]" class="form-control" 
                                                   value="<?php echo isset($enrollment_data["class_{$i}"]) ? $enrollment_data["class_{$i}"] : 0; ?>" 
                                                   min="0" onchange="updateSummary()">
                                        </td>
                                        <?php endfor; ?>
                                        <td id="primaryTotal">0</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- माध्यमिक कक्षाएं (6-8) -->
                    <div class="class-group mb-4">
                        <div class="class-group-title">
                            <i class="fas fa-book me-2"></i>माध्यमिक कक्षाएं (6-8)
                        </div>
                        <div class="table-responsive">
                            <table class="table enrollment-table">
                                <thead>
                                    <tr>
                                        <th>कक्षा</th>
                                        <th>6</th>
                                        <th>7</th>
                                        <th>8</th>
                                        <th>कुल</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>विद्यार्थी संख्या</strong></td>
                                        <?php for ($i = 6; $i <= 8; $i++): ?>
                                        <td>
                                            <input type="number" name="enrollment[<?php echo $i; ?>]" class="form-control" 
                                                   value="<?php echo isset($enrollment_data["class_{$i}"]) ? $enrollment_data["class_{$i}"] : 0; ?>" 
                                                   min="0" onchange="updateSummary()">
                                        </td>
                                        <?php endfor; ?>
                                        <td id="secondaryTotal">0</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- उच्च माध्यमिक कक्षाएं (9-12) -->
                    <div class="class-group mb-4">
                        <div class="class-group-title">
                            <i class="fas fa-graduation-cap me-2"></i>उच्च माध्यमिक कक्षाएं (9-12)
                        </div>
                        <div class="table-responsive">
                            <table class="table enrollment-table">
                                <thead>
                                    <tr>
                                        <th>कक्षा</th>
                                        <th>9</th>
                                        <th>10</th>
                                        <th>11</th>
                                        <th>12</th>
                                        <th>कुल</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>विद्यार्थी संख्या</strong></td>
                                        <?php for ($i = 9; $i <= 12; $i++): ?>
                                        <td>
                                            <input type="number" name="enrollment[<?php echo $i; ?>]" class="form-control" 
                                                   value="<?php echo isset($enrollment_data["class_{$i}"]) ? $enrollment_data["class_{$i}"] : 0; ?>" 
                                                   min="0" onchange="updateSummary()">
                                        </td>
                                        <?php endfor; ?>
                                        <td id="higherSecondaryTotal">0</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- नामांकन सारांश -->
                    <div class="enrollment-summary">
                        <h6 class="mb-3">नामांकन सारांश</h6>
                        <div class="summary-item">
                            <span>प्राथमिक कक्षाएं (1-5):</span>
                            <span id="primarySummary">0</span>
                        </div>
                        <div class="summary-item">
                            <span>माध्यमिक कक्षाएं (6-8):</span>
                            <span id="secondarySummary">0</span>
                        </div>
                        <div class="summary-item">
                            <span>उच्च माध्यमिक कक्षाएं (9-12):</span>
                            <span id="higherSecondarySummary">0</span>
                        </div>
                        <div class="summary-item">
                            <span>कुल नामांकन:</span>
                            <span id="grandSummary">0</span>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">सहेजें</button>
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

        // विंडो आकार बदलने पर साइडबार की जांच करें
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });
        
        // नामांकन सारांश अपडेट करें
        function updateSummary() {
            let primaryTotal = 0;
            let secondaryTotal = 0;
            let higherSecondaryTotal = 0;
            
            // प्राथमिक कक्षाओं का योग
            for (let i = 1; i <= 5; i++) {
                const value = parseInt(document.querySelector(`input[name="enrollment[${i}]"]`).value) || 0;
                primaryTotal += value;
            }
            
            // माध्यमिक कक्षाओं का योग
            for (let i = 6; i <= 8; i++) {
                const value = parseInt(document.querySelector(`input[name="enrollment[${i}]"]`).value) || 0;
                secondaryTotal += value;
            }
            
            // उच्च माध्यमिक कक्षाओं का योग
            for (let i = 9; i <= 12; i++) {
                const value = parseInt(document.querySelector(`input[name="enrollment[${i}]"]`).value) || 0;
                higherSecondaryTotal += value;
            }
            
            // कुल योग
            const grandTotal = primaryTotal + secondaryTotal + higherSecondaryTotal;
            
            // टेबल में कुल अपडेट करें
            document.getElementById('primaryTotal').textContent = primaryTotal;
            document.getElementById('secondaryTotal').textContent = secondaryTotal;
            document.getElementById('higherSecondaryTotal').textContent = higherSecondaryTotal;
            
            // सारांश अपडेट करें
            document.getElementById('primarySummary').textContent = primaryTotal;
            document.getElementById('secondarySummary').textContent = secondaryTotal;
            document.getElementById('higherSecondarySummary').textContent = higherSecondaryTotal;
            document.getElementById('grandSummary').textContent = grandTotal;
        }
        
        // पेज लोड होने पर सारांश अपडेट करें
        document.addEventListener('DOMContentLoaded', function() {
            updateSummary();
        });
    </script>
</body>
</html>