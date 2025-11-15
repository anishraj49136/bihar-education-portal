<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन है
checkUserType('admin');

// वेतन डेटा अपलोड करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['salary_file']) && $_FILES['salary_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['salary_file'];
    $month = $_POST['month'];
    
    // फ़ाइल को अस्थायी रूप से सहेजें
    $file_tmp_path = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $_SESSION['error_message'] = "कृपया केवल CSV फ़ाइल अपलोड करें।";
        header('Location: salary_management.php');
        exit;
    }
    
    try {
        // CSV फ़ाइल खोलें
        if (($handle = fopen($file_tmp_path, 'r')) !== FALSE) {
            // हेडर पंक्ति को छोड़ें
            fgetcsv($handle);
            
            // पहले मौजूदा डेटा हटाएं (अपडेट लॉजिक)
            $stmt = $conn->prepare("DELETE FROM teacher_salary WHERE month = ?");
            $stmt->execute([$month]);
            
            // डेटा डालें
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // फ़ील्ड की संख्या की जांच करें
                if (count($data) >= 12) {
                    $stmt = $conn->prepare("INSERT INTO teacher_salary (month, employee_id, gpf_pran_number, employee_name, designation, service_type, pay_commission, pay_level, status, approve_date, approve_by, process_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $month,
                        $data[0], // Employee ID
                        $data[1], // GPF/PRAN Number
                        $data[2], // Employee Name
                        $data[3], // Designation
                        $data[4], // Service Type
                        $data[5], // Pay Commission
                        $data[6], // Pay Level
                        $data[7], // Status
                        !empty($data[8]) ? date('Y-m-d', strtotime($data[8])) : null, // Approve Date
                        $data[9], // Approve By
                        !empty($data[10]) ? date('Y-m-d', strtotime($data[10])) : null // Process Date
                    ]);
                }
            }
            fclose($handle);
            $_SESSION['success_message'] = "वेतन डेटा सफलतापूर्वक अपलोड और अपडेट किया गया!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "डेटा अपलोड करते समय त्रुटि: " . $e->getMessage();
    }
    header('Location: salary_management.php');
    exit;
}

// वर्तमान महीने का वेतन डेटा प्राप्त करें
 $current_month = date('F');
 $stmt = $conn->prepare("SELECT * FROM teacher_salary WHERE month = ? ORDER BY employee_name");
 $stmt->execute([$current_month]);
 $salary_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>वेतन प्रबंधन - बिहार शिक्षा विभाग</title>
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
            <li class="nav-item"><a class="nav-link" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link active" href="salary_management.php"><i class="fas fa-money-check-alt"></i> वेतन प्रबंधन</a></li>
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
                <h4 class="mb-0">वेतन प्रबंधन</h4>
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
                <h5 class="mb-0">वेतन डेटा अपलोड करें</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="month" class="form-label">महीना चुनें</label>
                        <select class="form-select" id="month" name="month" required>
                            <?php $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']; foreach($months as $month): ?>
                            <option value="<?php echo $month; ?>" <?php echo ($current_month === $month) ? 'selected' : ''; ?>><?php echo $month; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="salary_file" class="form-label">CSV फ़ाइल चुनें</label>
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                            <p>फ़ाइल को यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें</p>
                            <input type="file" class="form-control d-none" id="salary_file" name="salary_file" accept=".csv" required>
                            <button type="button" class="btn btn-outline-primary" id="browseBtn">फ़ाइल ब्राउज़ करें</button>
                        </div>
                        <small class="text-muted">नोट: अपलोड करने से पहले, कृपया सुनिश्चित करें कि CSV फ़ाइल का प्रारूप आवश्यकताओं के अनुसार है। दोबारा अपलोड करने से पिछला डेटा इस महीने के लिए बदल जाएगा।</small>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-upload"></i> अपलोड करें और अपडेट करें
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- वर्तमान डेटा -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">वर्तमान वेतन डेटा (<?php echo $current_month; ?>)</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadData('excel')"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-sm btn-light" onclick="downloadData('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>GPF/PRAN Number</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Service Type</th>
                                <th>Pay Level</th>
                                <th>Status</th>
                                <th>Approve Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($salary_records) > 0): ?>
                                <?php foreach ($salary_records as $record): ?>
                                <tr>
                                    <td><?php echo $record['employee_id']; ?></td>
                                    <td><?php echo $record['gpf_pran_number']; ?></td>
                                    <td><?php echo $record['employee_name']; ?></td>
                                    <td><?php echo $record['designation']; ?></td>
                                    <td><?php echo $record['service_type']; ?></td>
                                    <td><?php echo $record['pay_level']; ?></td>
                                    <td><?php echo $record['status']; ?></td>
                                    <td><?php echo $record['approve_date'] ? date('d M Y', strtotime($record['approve_date'])) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">इस महीने के लिए कोई डेटा नहीं मिला।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
        
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('salary_file');
        const browseBtn = document.getElementById('browseBtn');

        browseBtn.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.backgroundColor = '#e1bee7';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.backgroundColor = 'var(--light-color)';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.backgroundColor = 'var(--light-color)';
            if (e.data.files.length) {
                fileInput.files = e.data.files;
                // Update UI to show selected file name if needed
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                // Update UI to show selected file name if needed
                uploadArea.querySelector('p').textContent = `चयनित फ़ाइल: ${fileInput.files[0].name}`;
            }
        });

        function downloadData(format) {
            alert(`डाउनलोड ${format} का अनुरोध भेजा गया। (यह एक संकल्पनात्मक उदाहरण है)`);
            // window.open(`download_salary.php?format=${format}&month=<?php echo $current_month; ?>`);
        }
    </script>
</body>
</html>