<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह जिला स्तरीय उपयोगकर्ता है
 $allowed_types = ['district_staff', 'district_program_officer', 'district_education_officer'];
if (!isLoggedIn() || !in_array($_SESSION['user_type'], $allowed_types)) {
    header('Location: login.php');
    exit;
}

// जिले की जानकारी प्राप्त करें
 $district_id = $_SESSION['district_id'];
 $stmt = $conn->prepare("SELECT name FROM districts WHERE id = ?");
 $stmt->execute([$district_id]);
 $district_info = $stmt->fetch(PDO::FETCH_ASSOC);

// वेतन शिकायतों की स्थिति अपडेट करने की प्रक्रिया (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_complaint_status') {
    try {
        $complaint_id = $_POST['complaint_id'];
        $new_status = $_POST['new_status'];
        $rejection_reason = $_POST['rejection_reason'] ?? null;

        $stmt = $conn->prepare("UPDATE salary_complaints SET status = ?, rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_status, $rejection_reason, $complaint_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'स्थिति सफलतापूर्वक अपडेट की गई!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'त्रुटि: ' . $e->getMessage()]);
    }
    exit;
}

// वर्तमान महीने के लिए वेतन शिकायतें प्राप्त करें
 $current_month = date('F');
 $stmt = $conn->prepare("SELECT sc.*, s.name as school_name, t.name as teacher_name 
                         FROM salary_complaints sc 
                         LEFT JOIN schools s ON sc.school_id = s.id 
                         LEFT JOIN teachers t ON sc.teacher_pran_uan = t.pran_no OR sc.teacher_pran_uan = t.uan_no 
                         WHERE s.district_id = ? 
                         ORDER BY sc.created_at DESC");
 $stmt->execute([$district_id]);
 $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// एडमिन द्वारा अपडेट किए गए वेतन डेटा प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM teacher_salary WHERE month = ? ORDER BY employee_name");
 $stmt->execute([$current_month]);
 $salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जिला डैशबोर्ड - बिहार शिक्षा विभाग</title>
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
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; overflow-y: auto; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; font-size: 0.9rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 20px; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
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
        .complaint-actions button { margin-right: 5px; }
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px 15px 0 0; }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); width: 280px; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
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
                <h4 class="mb-0">जिला डैशबोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?>, <?php echo $district_info['name']; ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <!-- वेतन शिकायतें -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">वेतन शिकायतें</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadData('complaints', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-sm btn-light" onclick="downloadData('complaints', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>टिकट नंबर</th>
                                <th>विद्यालय</th>
                                <th>शिक्षक</th>
                                <th>वेतन प्रकार</th>
                                <th>विवरण</th>
                                <th>दर्ज की तिथि</th>
                                <th>स्थिति</th>
                                <th>कार्रवाई</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($complaints) > 0): ?>
                                <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo $complaint['ticket_number']; ?></td>
                                    <td><?php echo $complaint['school_name']; ?></td>
                                    <td><?php echo $complaint['teacher_name']; ?></td>
                                    <td><?php echo ($complaint['salary_type'] === 'regular_salary') ? 'नियमित वेतन' : 'बकाया वेतन'; ?></td>
                                    <td><?php echo substr($complaint['description'], 0, 100) . '...'; ?></td>
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
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="updateComplaintStatus(<?php echo $complaint['id']; ?>, 'in_process')"><i class="fas fa-cog"></i></button>
                                        <button class="btn btn-sm btn-success" onclick="updateComplaintStatus(<?php echo $complaint['id']; ?>, 'done')"><i class="fas fa-check"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectComplaint(<?php echo $complaint['id']; ?>)"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">कोई शिकायत नहीं मिली।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- वेतन डेटा -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">वेतन डेटा (<?php echo $current_month; ?>)</h5>
                <div>
                    <button class="btn btn-sm btn-light" onclick="downloadData('salary', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-sm btn-light" onclick="downloadData('salary', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
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
                            <?php if (count($salary_data) > 0): ?>
                                <?php foreach ($salary_data as $record): ?>
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
                                <tr><td colspan="9" class="text-center">इस महीने के लिए कोई डेटा नहीं मिला।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- अस्वीकरण रीजेक्शन मोडल -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">अस्वीकरण का कारण</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectionForm">
                    <input type="hidden" name="complaint_id" id="rejectionComplaintId">
                    <div class="modal-body">
                        <textarea class="form-control" id="rejectionReason" name="rejection_reason" rows="4" placeholder="कृपया अस्वीकरण का कारण बताएं..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-danger">अस्वीकृत करें</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        function updateComplaintStatus(complaintId, status) {
            if (status === 'rejected') {
                document.getElementById('rejectionComplaintId').value = complaintId;
                document.getElementById('rejectionReason').value = '';
                new bootstrap.Modal(document.getElementById('rejectionModal')).show();
            } else {
                sendUpdateRequest(complaintId, status, null);
            }
        }

        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const complaintId = document.getElementById('rejectionComplaintId').value;
            const reason = document.getElementById('rejectionReason').value;
            sendUpdateRequest(complaintId, 'rejected', reason);
        });

        function sendUpdateRequest(complaintId, status, reason) {
            fetch('district_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_complaint_status&complaint_id=${complaintId}&new_status=${status}&rejection_reason=${reason}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('त्रुटि: ' + data.message);
                }
            });
        }

        function downloadData(type, format) {
            alert(`डाउनलोड ${type} as ${format} का अनुरोध भेजा गया। (यह एक संकल्पनात्मक उदाहरण है)`);
            // window.open(`download.php?type=${type}&format=${format}&month=${<?php echo $current_month; ?>}`);
        }
    </script>
</body>
</html>