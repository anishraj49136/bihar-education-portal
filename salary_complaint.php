<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

 $school_id = $_SESSION['school_id'];

// शिकायत दर्ज करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'file_complaint') {
    try {
        $ticket_number = generateTicketNumber();
        $file_path = null;
        
        // फ़ाइल अपलोड करें यदि मौजूद है
        if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
            $file_path = uploadFile($_FILES['bill_file'], 'complaints');
        }
        
        $stmt = $conn->prepare("INSERT INTO salary_complaints (ticket_number, school_id, teacher_pran_uan, salary_type, description, file_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ticket_number,
            $school_id,
            $_POST['teacher_pran_uan'],
            $_POST['salary_type'],
            $_POST['description'],
            $file_path
        ]);
        
        $_SESSION['success_message'] = "आपकी शिकायत सफलतापूर्वक दर्ज कर ली गई है! आपका टिकट नंबर है: " . $ticket_number;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "शिकायत दर्ज करते समय त्रुटि: " . $e->getMessage();
    }
    header('Location: salary_complaint.php');
    exit;
}

// इस विद्यालय की शिकायतें प्राप्त करें
 $stmt = $conn->prepare("SELECT sc.*, t.name as teacher_name FROM salary_complaints sc LEFT JOIN teachers t ON sc.teacher_pran_uan = t.pran_no OR sc.teacher_pran_uan = t.uan_no WHERE sc.school_id = ? ORDER BY sc.created_at DESC");
 $stmt->execute([$school_id]);
 $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>वेतन शिकायत - बिहार शिक्षा विभाग</title>
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
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); }
        .status-badge { font-size: 0.8em; padding: 4px 8px; border-radius: 12px; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार -->
    <div class="sidebar" id="sidebar">
        <div class="p-4 text-center">
            <h4>बिहार शिक्षा विभाग</h4>
            <p class="mb-0">विद्यालय डैशबोर्ड</p>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="school_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="school_profile.php"><i class="fas fa-school"></i> विद्यालय प्रोफाइल</a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-user-graduate"></i> नामांकन</a></li>
            <li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण</a></li>
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
            <li class="nav-item"><a class="nav-link active" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">वेतन शिकायत</h4>
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
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- शिकायत फॉर्म -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">नई शिकायत दर्ज करें</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="file_complaint">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="teacher_pran_uan" class="form-label">शिक्षक का PRAN/UAN नंबर</label>
                            <input type="text" class="form-control" id="teacher_pran_uan" name="teacher_pran_uan" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="salary_type" class="form-label">वेतन प्रकार</label>
                            <select class="form-select" id="salary_type" name="salary_type" required>
                                <option value="regular_salary">रेगुलर सैलरी</option>
                                <option value="pending_salary">बकाया वेतन</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">विवरण (1000 अक्षरों तक)</label>
                        <textarea class="form-control" id="description" name="description" rows="5" maxlength="1000" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="bill_file" class="form-label">बिल / आवेदन (PDF में)</label>
                        <input type="file" class="form-control" id="bill_file" name="bill_file" accept=".pdf">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> शिकायत दर्ज करें
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- पिछली शिकायतें -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">आपकी शिकायतों की स्थिति</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>टिकट नंबर</th>
                                <th>शिक्षक</th>
                                <th>वेतन प्रकार</th>
                                <th>विवरण</th>
                                <th>दर्ज करने की तिथि</th>
                                <th>स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($complaints) > 0): ?>
                                <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo $complaint['ticket_number']; ?></td>
                                    <td><?php echo $complaint['teacher_name'] ?: 'N/A'; ?></td>
                                    <td><?php echo ($complaint['salary_type'] === 'regular_salary') ? 'नियमित वेतन' : 'बकाया वेतन'; ?></td>
                                    <td><?php echo substr($complaint['description'], 0, 50) . '...'; ?></td>
                                    <td><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $status_class = 'bg-secondary';
                                        if ($complaint['status'] === 'in_process') $status_class = 'bg-info';
                                        elseif ($complaint['status'] === 'done') $status_class = 'bg-success';
                                        elseif ($complaint['status'] === 'rejected') $status_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?> status-badge"><?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">आपके द्वारा कोई शिकायत दर्ज नहीं की गई है।</td></tr>
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
    </script>
</body>
</html>