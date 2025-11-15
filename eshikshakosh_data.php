<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन है
checkUserType('admin');

// ई-शिक्षकोष डेटा अपलोड करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['eshikshakosh_file']) && $_FILES['eshikshakosh_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['eshikshakosh_file'];
    $month = $_POST['month'];
    $attendance_date = $_POST['attendance_date'];
    
    // फ़ाइल को अस्थायी रूप से सहेजें
    $file_tmp_path = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $_SESSION['error_message'] = "कृपया केवल CSV फ़ाइल अपलोड करें।";
        header('Location: eshikshakosh_data.php');
        exit;
    }
    
    try {
        // CSV फ़ाइल खोलें
        if (($handle = fopen($file_tmp_path, 'r')) !== FALSE) {
            // हेडर पंक्ति को छोड़ें
            fgetcsv($handle);
            
            // पहले मौजूदा डेटा हटाएं (अपडेट लॉजिक)
            $stmt = $conn->prepare("DELETE FROM eshikshakosh_data WHERE attendance_date = ?");
            $stmt->execute([$attendance_date]);
            
            // डेटा डालें
            $imported_count = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // फ़ील्ड की संख्या की जांच करें
                if (count($data) >= 14) {
                    $stmt = $conn->prepare("INSERT INTO eshikshakosh_data (district, block, cluster, school, teacher_code, teacher_name, teacher_mobile, nature_of_appointment, teacher_title, attendance_date, attendance_status, attendance_type, in_time, out_time, month) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $data[0], // District
                        $data[1], // Block
                        $data[2], // Cluster
                        $data[3], // School
                        $data[4], // Teacher Code
                        $data[5], // Teacher Name
                        $data[6], // Teacher Mobile
                        $data[7], // Nature Of Appointment
                        $data[8], // Teacher Title
                        $attendance_date, // Attendance Date
                        $data[10], // Attendance Status
                        $data[11], // Attendance Type
                        $data[12], // In Time
                        $data[13], // Out Time
                        $month // Month
                    ]);
                    $imported_count++;
                }
            }
            fclose($handle);
            $_SESSION['success_message'] = "ई-शिक्षकोष डेटा सफलतापूर्वक अपलोड किया गया! कुल {$imported_count} रिकॉर्ड आयात किए गए।";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "डेटा अपलोड करते समय त्रुटि: " . $e->getMessage();
    }
    header('Location: eshikshakosh_data.php');
    exit;
}

// वर्तमान महीने का ई-शिक्षकोष डेटा प्राप्त करें
 $current_month = date('F');
 $stmt = $conn->prepare("SELECT * FROM eshikshakosh_data WHERE month = ? ORDER BY attendance_date DESC, school, teacher_name");
 $stmt->execute([$current_month]);
 $eshikshakosh_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// सभी महीने प्राप्त करें
 $stmt = $conn->query("SELECT * FROM months WHERE is_active = 1 ORDER BY id");
 $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ई-शिक्षकोष डेटा - बिहार शिक्षा विभाग</title>
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
        .csv-format { background-color: #f8f9fa; border-left: 4px solid var(--primary-color); padding: 15px; border-radius: 0 5px 5px 0; margin-top: 20px; }
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
            <li class="nav-item"><a class="nav-link" href="salary_management.php"><i class="fas fa-money-check-alt"></i> वेतन प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link active" href="eshikshakosh_data.php"><i class="fas fa-database"></i> ई-शिक्षकोष डेटा</a></li>
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
                <h4 class="mb-0">ई-शिक्षकोष डेटा</h4>
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
                <h5 class="mb-0">ई-शिक्षकोष डेटा अपलोड करें</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="month" class="form-label">महीना चुनें</label>
                            <select class="form-select" id="month" name="month" required>
                                <?php foreach($months as $month): ?>
                                <option value="<?php echo $month['name']; ?>" <?php echo ($current_month === $month['name']) ? 'selected' : ''; ?>><?php echo $month['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="attendance_date" class="form-label">उपस्थिति तिथि</label>
                            <input type="date" class="form-control" id="attendance_date" name="attendance_date" required>
                        </div>
                        <div class="col-md-4">
                            <label for="eshikshakosh_file" class="form-label">CSV फ़ाइल चुनें</label>
                            <input type="file" class="form-control" id="eshikshakosh_file" name="eshikshakosh_file" accept=".csv" required>
                        </div>
                    </div>
                    
                    <div class="upload-area mt-3" id="uploadArea">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                        <p>फ़ाइल को यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें</p>
                        <input type="file" class="form-control d-none" id="hiddenFileInput" accept=".csv">
                        <button type="button" class="btn btn-outline-primary" id="browseBtn">फ़ाइल ब्राउज़ करें</button>
                    </div>
                    
                    <div class="csv-format">
                        <h6>CSV फ़ाइल प्रारूप:</h6>
                        <p>फ़ाइल में निम्नलिखित कॉलम होने चाहिए (इसी क्रम में):</p>
                        <ol>
                            <li>District</li>
                            <li>Block</li>
                            <li>Cluster</li>
                            <li>School</li>
                            <li>Teacher Code</li>
                            <li>Teacher Name</li>
                            <li>Teacher Mobile</li>
                            <li>Nature Of Appointment</li>
                            <li>Teacher Title</li>
                            <li>Attendance Date</li>
                            <li>Attendance Status</li>
                            <li>Attendance Type</li>
                            <li>In Time</li>
                            <li>Out Time</li>
                        </ol>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-upload"></i> अपलोड करें
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- वर्तमान डेटा -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">वर्तमान डेटा (<?php echo $current_month; ?>)</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadData('excel')"><i class="fas fa-file-excel"></i> Excel</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>जिला</th>
                                <th>प्रखंड</th>
                                <th>विद्यालय</th>
                                <th>शिक्षक कोड</th>
                                <th>शिक्षक का नाम</th>
                                <th>मोबाइल</th>
                                <th>उपस्थिति तिथि</th>
                                <th>उपस्थिति स्थिति</th>
                                <th>In Time</th>
                                <th>Out Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($eshikshakosh_records) > 0): ?>
                                <?php foreach ($eshikshakosh_records as $record): ?>
                                <tr>
                                    <td><?php echo $record['district']; ?></td>
                                    <td><?php echo $record['block']; ?></td>
                                    <td><?php echo $record['school']; ?></td>
                                    <td><?php echo $record['teacher_code']; ?></td>
                                    <td><?php echo $record['teacher_name']; ?></td>
                                    <td><?php echo $record['teacher_mobile']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['attendance_date'])); ?></td>
                                    <td><?php echo $record['attendance_status']; ?></td>
                                    <td><?php echo $record['in_time']; ?></td>
                                    <td><?php echo $record['out_time']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center">इस महीने के लिए कोई डेटा नहीं मिला।</td></tr>
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
        
        // फ़ाइल अपलोड
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('eshikshakosh_file');
        const hiddenFileInput = document.getElementById('hiddenFileInput');
        const browseBtn = document.getElementById('browseBtn');
        
        browseBtn.addEventListener('click', () => fileInput.click());
        hiddenFileInput.addEventListener('click', () => fileInput.click());
        
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
                uploadArea.querySelector('p').textContent = `चयनित फ़ाइल: ${e.data.files[0].name}`;
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
            // window.open(`download_eshikshakosh.php?format=${format}&month=${<?php echo $current_month; ?>}`);
        }
    </script>
</body>
</html>