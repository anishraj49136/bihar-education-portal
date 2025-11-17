<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

 $school_id = $_SESSION['school_id'];

// टिकट नंबर जेनरेट करने का फंक्शन
function generateTicketNumber() {
    // वर्तमान तिथि और समय के साथ यूनीक टिकट नंबर बनाएं
    $prefix = "SC";
    $date = date('Ymd');
    $time = date('His');
    $random = mt_rand(100, 999);
    
    return $prefix . $date . $time . $random;
}

// शिकायत दर्ज करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'file_complaint') {
    try {
        $ticket_number = generateTicketNumber();
        $file_path = null;
        
        // फाइल अपलोड करें यदि मौजूद है
        if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
            $file_path = uploadFile($_FILES['bill_file'], 'complaints');
        }
        
        // शिक्षक का PRAN/UAN नंबर से शिक्षक की जानकारी प्राप्त करें
        $teacher_pran_uan = $_POST['teacher_pran_uan'];
        $teacher_info = null;
        
        if (!empty($teacher_pran_uan)) {
            $stmt = $conn->prepare("SELECT id, name FROM teachers WHERE (pran_no = ? OR uan_no = ?) AND school_id = ?");
            $stmt->execute([$teacher_pran_uan, $teacher_pran_uan, $school_id]);
            $teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // शिकायत दर्ज करें (teacher_id के बिना)
        $stmt = $conn->prepare("INSERT INTO salary_complaints (ticket_number, school_id, teacher_pran_uan, salary_type, description, file_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $ticket_number,
            $school_id,
            $teacher_pran_uan,
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

// विद्यालय के शिक्षकों की सूची प्राप्त करें (ड्रॉपडाउन के लिए)
 $stmt = $conn->prepare("SELECT id, name, pran_no, uan_no FROM teachers WHERE school_id = ? ORDER BY name");
 $stmt->execute([$school_id]);
 $teachers_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .teacher-select { position: relative; }
        .teacher-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 0 0 10px 10px; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; }
        .teacher-suggestion { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .teacher-suggestion:hover { background-color: #f8f9fa; }
        .teacher-suggestion:last-child { border-bottom: none; }
        .teacher-suggestion strong { color: var(--primary-color); }
        /* मोबाइल रेस्पॉन्सिव स्टाइल */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .main-content { margin-left: 0; } 
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } 
        }
    </style>
</head>
<body>
    <!-- मोबाइल मेन्यू बटन -->
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <!-- साइडबार -->
    <?php include 'sidebar_template.php'; ?>
    
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
                            <div class="teacher-select">
                                <input type="text" class="form-control" id="teacher_pran_uan" name="teacher_pran_uan" required autocomplete="off">
                                <div class="teacher-suggestions" id="teacherSuggestions"></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="salary_type" class="form-label">वेतन प्रकार</label>
                            <select class="form-select" id="salary_type" name="salary_type" required>
                                <option value="regular_salary">नियमित वेतन</option>
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
                        <input type="file" class="form-control" id="bill_file" name="bill_file" accept=".pdf" required>
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
								<th>Reject reason</th>
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
									<td><?php echo $complaint['rejection_reason']; ?></td>
                                    <td>
                                        <?php
                                        $status_class = 'bg-secondary';
                                        $status_text = 'लंबित';
                                        if ($complaint['status'] === 'in_process') { 
                                            $status_class = 'bg-info'; 
                                            $status_text = 'प्रक्रियाधीन'; 
                                        } elseif ($complaint['status'] === 'done') { 
                                            $status_class = 'bg-success'; 
                                            $status_text = 'पूर्ण'; 
                                        } elseif ($complaint['status'] === 'rejected') { 
                                            $status_class = 'bg-danger'; 
                                            $status_text = 'अस्वीकृत'; 
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?> status-badge"><?php echo $status_text; ?></span>
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
        
        // शिक्षक खोजने की कार्यक्षमता
        const teacherInput = document.getElementById('teacher_pran_uan');
        const suggestionsContainer = document.getElementById('teacherSuggestions');
        
        // शिक्षकों की सूची
        const teachers = <?php echo json_encode($teachers_list); ?>;
        
        teacherInput.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            suggestionsContainer.innerHTML = '';
            
            if (value.length > 0) {
                const filteredTeachers = teachers.filter(teacher => 
                    teacher.name.toLowerCase().includes(value) || 
                    (teacher.pran_no && teacher.pran_no.toLowerCase().includes(value)) || 
                    (teacher.uan_no && teacher.uan_no.toLowerCase().includes(value))
                );
                
                if (filteredTeachers.length > 0) {
                    suggestionsContainer.style.display = 'block';
                    filteredTeachers.forEach(teacher => {
                        const suggestion = document.createElement('div');
                        suggestion.className = 'teacher-suggestion';
                        
                        const pranUan = teacher.pran_no || teacher.uan_no || '';
                        suggestion.innerHTML = `<strong>${teacher.name}</strong> (${pranUan})`;
                        
                        suggestion.addEventListener('click', function() {
                            teacherInput.value = pranUan;
                            suggestionsContainer.style.display = 'none';
                        });
                        
                        suggestionsContainer.appendChild(suggestion);
                    });
                } else {
                    suggestionsContainer.style.display = 'none';
                }
            } else {
                suggestionsContainer.style.display = 'none';
            }
        });
        
        // इनपुट बाहर क्लिक करने पर सुझावने छिपाएं
        document.addEventListener('click', function(e) {
            if (!teacherInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>