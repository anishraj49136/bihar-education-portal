<?php
require_once 'config.php';
checkUserType('admin');

// पत्र जोड़ने/संपादित करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'save_letter') {
            $letter_id = $_POST['letter_id'];
            $file_path = null;
            
            // नई फ़ाइल अपलोड करें यदि मौजूद है
            if (isset($_FILES['letter_file']) && $_FILES['letter_file']['error'] === UPLOAD_ERR_OK) {
                $file_path = uploadFile($_FILES['letter_file'], 'letters');
            } elseif ($letter_id) {
                // अपडेट करते समय यदि नई फ़ाइल नहीं है, तो पुरानी फ़ाइल पथ रखें
                $stmt = $conn->prepare("SELECT file_path FROM letters WHERE id = ?");
                $stmt->execute([$letter_id]);
                $old_letter = $stmt->fetch(PDO::FETCH_ASSOC);
                $file_path = $old_letter['file_path'];
            }

            $is_public = isset($_POST['is_public']) ? 1 : 0;

            if ($letter_id) { // अपडेट
                $stmt = $conn->prepare("UPDATE letters SET title = ?, description = ?, file_path = ?, is_public = ? WHERE id = ?");
                $stmt->execute([$_POST['title'], $_POST['description'], $file_path, $is_public, $letter_id]);
                $_SESSION['success_message'] = "पत्र सफलतापूर्वक अपडेट किया गया!";
            } else { // नया जोड़ें
                $stmt = $conn->prepare("INSERT INTO letters (title, description, file_path, is_public, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['title'], $_POST['description'], $file_path, $is_public, $_SESSION['user_id']]);
                $_SESSION['success_message'] = "नया पत्र सफलतापूर्वक जोड़ा गया!";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_letter') {
            $stmt = $conn->prepare("DELETE FROM letters WHERE id = ?");
            $stmt->execute([$_POST['letter_id']]);
            $_SESSION['success_message'] = "पत्र सफलतापूर्वक हटा दिया गया!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_letters.php');
    exit;
}

// सभी पत्र प्राप्त करें
 $stmt = $conn->query("SELECT l.*, u.name as uploaded_by_name FROM letters l JOIN users u ON l.uploaded_by = u.id ORDER BY l.created_at DESC");
 $letters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>पत्र प्रबंधन - बिहार शिक्षा विभाग</title>
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
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; border-radius: 15px 15px 0 0; }
        .upload-area { border: 2px dashed var(--primary-color); border-radius: 10px; padding: 20px; text-align: center; background-color: var(--light-color); transition: all 0.3s ease; }
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
            <li class="nav-item"><a class="nav-link" href="salary_management.php"><i class="fas fa-money-check-alt"></i> वेतन प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="eshikshakosh_data.php"><i class="fas fa-database"></i> ई-शिक्षकोष डेटा</a></li>
            <li class="nav-item"><a class="nav-link active" href="manage_letters.php"><i class="fas fa-envelope"></i> पत्र प्रबंधन</a></li>
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
                <h4 class="mb-0">पत्र प्रबंधन</h4>
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

        <!-- पत्र सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">पत्र सूची</h5>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#letterModal">
                    <i class="fas fa-plus"></i> नया पत्र जोड़ें
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>शीर्षक</th>
                                <th>विवरण</th>
                                <th>सार्वजनिक</th>
                                <th>अपलोड द्वारा</th>
                                <th>दिनांक</th>
                                <th>कार्रवाई</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($letters) > 0): ?>
                                <?php foreach ($letters as $letter): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($letter['title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($letter['description'], 0, 100)) . '...'; ?></td>
                                    <td>
                                        <?php if ($letter['is_public']): ?>
                                            <span class="badge bg-success">हाँ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">नहीं</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $letter['uploaded_by_name']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($letter['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editLetter(<?php echo htmlspecialchars(json_encode($letter)); ?>)"><i class="fas fa-edit"></i></button>
                                        <?php if ($letter['file_path']): ?>
                                        <a href="<?php echo $GLOBALS['base_url'] . '/uploads/' . $letter['file_path']; ?>" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-eye"></i></a>
                                        <?php endif; ?>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_letter">
                                            <input type="hidden" name="letter_id" value="<?php echo $letter['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस पत्र को हटाना चाहते हैं?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center">कोई पत्र नहीं मिला।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- पत्र मोडल -->
    <div class="modal fade" id="letterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="letterModalTitle">नया पत्र जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manage_letters.php" enctype="multipart/form-data" id="letterForm">
                    <input type="hidden" name="action" value="save_letter">
                    <input type="hidden" name="letter_id" id="letterId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">शीर्षक</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">विवरण</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="letter_file" class="form-label">PDF फ़ाइल</label>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" class="form-control" id="letter_file" name="letter_file" accept=".pdf">
                                <p id="filePlaceholder">फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें</p>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1">
                            <label class="form-check-label" for="is_public">सार्वजनिक रूप से दिखाएं (होम पेज पर)</label>
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
        const fileInput = document.getElementById('letter_file');
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = '#e1bee7'; });
        uploadArea.addEventListener('dragleave', () => { uploadArea.style.backgroundColor = 'var(--light-color)'; });
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = 'var(--light-color)'; if (e.data.files.length) { fileInput.files = e.data.files; document.getElementById('filePlaceholder').textContent = `चयनित फ़ाइल: ${e.data.files[0].name}`; } });
        fileInput.addEventListener('change', () => { if (fileInput.files.length > 0) { document.getElementById('filePlaceholder').textContent = `चयनित फ़ाइल: ${fileInput.files[0].name}`; } });

        function editLetter(letter) {
            document.getElementById('letterModalTitle').textContent = 'पत्र संपादित करें';
            document.getElementById('letterId').value = letter.id;
            document.getElementById('title').value = letter.title;
            document.getElementById('description').value = letter.description;
            document.getElementById('is_public').checked = letter.is_public == 1;
            document.getElementById('filePlaceholder').textContent = letter.file_path ? `वर्तमान फ़ाइल: ${letter.file_path.split('/').pop()}` : 'फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें';
            new bootstrap.Modal(document.getElementById('letterModal')).show();
        }

        // मोडल रीसेट करें
        document.getElementById('letterModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('letterForm').reset();
            document.getElementById('letterModalTitle').textContent = 'नया पत्र जोड़ें';
            document.getElementById('letterId').value = '';
            document.getElementById('filePlaceholder').textContent = 'फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें';
        });
    </script>
</body>
</html>