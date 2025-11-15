<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// PRAN/UAN नंबर द्वारा वेतन इतिहास खोजने की प्रक्रिया
 $salary_history = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pran_uan_number'])) {
    $pran_uan_number = $_POST['pran_uan_number'];
    $stmt = $conn->prepare("SELECT * FROM teacher_salary WHERE gpf_pran_number = ? OR employee_id = ? ORDER BY month DESC, year DESC");
    $stmt->execute([$pran_uan_number, $pran_uan_number]);
    $salary_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>वेतन स्थिति - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6a1b9a; --secondary-color: #9c27b0; --accent-color: #ce93d8; --light-color: #f3e5f5; --dark-color: #4a148c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; }
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
        .form-control { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .search-section { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार (उपयोगकर्ता प्रकार के आधार पर बदलें) -->
    <?php include 'sidebar_template.php'; ?>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">वेतन स्थिति</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?></small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- खोज सेक्शन -->
        <div class="search-section">
            <h5 class="mb-4">शिक्षक का वेतन इतिहास खोजें</h5>
            <form method="post" action="">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label for="pran_uan_number" class="form-label">PRAN/UAN नंबर या Employee ID</label>
                        <input type="text" class="form-control" id="pran_uan_number" name="pran_uan_number" placeholder="PRAN/UAN नंबर या Employee ID दर्ज करें" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-light w-100">
                            <i class="fas fa-search"></i> खोजें
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- परिणाम सेक्शन -->
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pran_uan_number'])): ?>
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">वेतन इतिहास</h5>
                <button class="btn btn-sm btn-light" onclick="downloadData('excel')"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
            <div class="card-body">
                <?php if (count($salary_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>महीना</th>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>Designation</th>
                                    <th>Service Type</th>
                                    <th>Pay Level</th>
                                    <th>Status</th>
                                    <th>Approve Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salary_history as $record): ?>
                                <tr>
                                    <td><?php echo $record['month']; ?></td>
                                    <td><?php echo $record['employee_id']; ?></td>
                                    <td><?php echo $record['employee_name']; ?></td>
                                    <td><?php echo $record['designation']; ?></td>
                                    <td><?php echo $record['service_type']; ?></td>
                                    <td><?php echo $record['pay_level']; ?></td>
                                    <td><?php echo $record['status']; ?></td>
                                    <td><?php echo $record['approve_date'] ? date('d M Y', strtotime($record['approve_date'])) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> दिए गए PRAN/UAN नंबर के लिए कोई वेतन रिकॉर्ड नहीं मिला।
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        function downloadData(format) {
            alert(`डाउनलोड ${format} का अनुरोध भेजा गया। (यह एक संकल्पनात्मक उदाहरण है)`);
            // window.open(`download_salary_history.php?pran_uan=${<?php echo $_POST['pran_uan_number'] ?? ''; ?>}&format=${format}`);
        }
    </script>
</body>
</html>