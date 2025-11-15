<?php
require_once 'config.php';
checkUserType('admin');

// महीना जोड़ने/संपादित/हटाने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'save_month') {
            $month_id = $_POST['month_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($month_id) { // अपडेट
                $stmt = $conn->prepare("UPDATE months SET name = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $is_active, $month_id]);
                $_SESSION['success_message'] = "महीना सफलतापूर्वक अपडेट किया गया!";
            } else { // नया जोड़ना
                $stmt = $conn->prepare("INSERT INTO months (name, is_active) VALUES (?, ?)");
                $stmt->execute([$_POST['name'], $is_active]);
                $_SESSION['success_message'] = "नया महीना सफलतापूर्वक जोड़ा गया!";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_month') {
            $stmt = $conn->prepare("DELETE FROM months WHERE id = ?");
            $stmt->execute([$_POST['month_id']]);
            $_SESSION['success_message'] = "महीना सफलतापूर्वर हटा दिया गया!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_months.php');
    exit;
}

// सभी महीने प्राप्त करें
 $stmt = $conn->query("SELECT * FROM months ORDER BY id");
 $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>महीना प्रबंधन - बिहार शिक्षा विभाग</title>
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
            font-size: 0.9rem;
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
        }
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
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
            width: 100%;
            min-width: 600px; /* Ensures table has a minimum width */
        }
        .table thead {
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
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25);
        }
        .modal-content {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
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
            <li class="nav-item"><a class="nav-link" href="eshikshakosh_data.php"><i class="fas fa-database"></i> ई-शिक्षकोष डेटा</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_letters.php"><i class="fas fa-envelope"></i> पत्र प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_notices.php"><i class="fas fa-bullhorn"></i> नोटिस प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_sliders.php"><i class="fas fa-images"></i> स्लाइडर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_categories.php"><i class="fas fa-tags"></i> श्रेणी प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link active" href="manage_months.php"><i class="fas fa-calendar-alt"></i> महीना प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>
    
    <!-- मुख्य सामग्री -->
    <div class="main-content">
        <!-- नेविगेशन बार -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">महीना प्रबंधन</h4>
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

        <!-- महीना सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">महीने की सूची</h5>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#monthModal">
                    <i class="fas fa-plus"></i> नया महीना जोड़ें
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>क्र. सं.</th>
                                <th>महीने का नाम</th>
                                <th>स्थिति</th>
                                <th>कार्रवाई</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($months) > 0): ?>
                                <?php foreach ($months as $index => $month): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($month['name']); ?></td>
                                    <td>
                                        <?php if ($month['is_active']): ?>
                                            <span class="badge bg-success">सक्रिय</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">निष्क्रिय</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editMonth(<?php echo htmlspecialchars(json_encode($month)); ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_month">
                                            <input type="hidden" name="month_id" value="<?php echo $month['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस महीने को हटाना चाहते हैं?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center">कोई महीना नहीं मिला।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- महीना मोडल -->
    <div class="modal fade" id="monthModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="monthModalTitle">नया महीना जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manage_months.php" id="monthForm">
                    <input type="hidden" name="action" value="save_month">
                    <input type="hidden" name="month_id" id="monthId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">महीने का नाम</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1">
                            <label class="form-check-label" for="is_active">सक्रिय</label>
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
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
        });

        function editMonth(month) {
            // सुनिश्चित करें कि month एक वैध ऑब्जेक्ट है
            if (typeof month === 'object' && month !== null) {
                document.getElementById('monthModalTitle').textContent = 'महीना संपादित करें';
                document.getElementById('monthId').value = month.id;
                document.getElementById('name').value = month.name;
                document.getElementById('is_active').checked = month.is_active == 1;
                var monthModal = new bootstrap.Modal(document.getElementById('monthModal'));
                monthModal.show();
            } else {
                console.error("Invalid month data provided to editMonth function.");
                alert("डेटा लोड करने में त्रुटि हुई। कृपया फिर से प्रयास करें।");
            }
        }

        // मोडल रीसेट करें
        document.getElementById('monthModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('monthForm').reset();
            document.getElementById('monthModalTitle').textContent = 'नया महीना जोड़ें';
            document.getElementById('monthId').value = '';
        });
    </script>
</body>
</html>