<?php
require_once 'config.php';
checkUserType('admin');

// स्लाइडर जोड़ने/संपादित करने की प्रक्रिया
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'save_slider') {
            $slider_id = $_POST['slider_id'];
            $image_path = null;
            
            // नई फ़ाइल अपलोड करें यदि मौजूद है
            if (isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] === UPLOAD_ERR_OK) {
                $image_path = uploadFile($_FILES['slider_image'], 'sliders');
            } elseif ($slider_id) {
                // अपडेट करते समय यदि नई फ़ाइल नहीं है, तो पुरानी फ़ाइल पथ रखें
                $stmt = $conn->prepare("SELECT image_path FROM sliders WHERE id = ?");
                $stmt->execute([$slider_id]);
                $old_slider = $stmt->fetch(PDO::FETCH_ASSOC);
                $image_path = $old_slider['image_path'];
            }

            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($slider_id) { // अपडेट
                $stmt = $conn->prepare("UPDATE sliders SET title = ?, image_path = ?, activity_name = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['title'], $image_path, $_POST['activity_name'], $is_active, $slider_id]);
                $_SESSION['success_message'] = "स्लाइडर सफलतापूर्वक अपडेट किया गया!";
            } else { // नया जोड़ना
                $stmt = $conn->prepare("INSERT INTO sliders (title, image_path, activity_name, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['title'], $image_path, $_POST['activity_name'], $is_active]);
                $_SESSION['success_message'] = "नया स्लाइडर सफलतापूर्वक जोड़ा गया!";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_slider') {
            $stmt = $conn->prepare("DELETE FROM sliders WHERE id = ?");
            $stmt->execute([$_POST['slider_id']]);
            $_SESSION['success_message'] = "स्लाइडर सफलतापूर्वर हटा दिया गया!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    }
    header('Location: manage_sliders.php');
    exit;
}

// सभी स्लाइडर प्राप्त करें
 $stmt = $conn->query("SELECT * FROM sliders ORDER BY created_at DESC");
 $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>स्लाइडर प्रबंधन - बिहार शिक्षा विभाग</title>
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
            <li class="nav-item"><a class="nav-link" href="manage_letters.php"><i class="fas fa-envelope"></i> पत्र प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_notices.php"><i class="fas fa-bullhorn"></i> नोटिस प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link active" href="manage_sliders.php"><i class="fas fa-images"></i> स्लाइडर प्रबंधन</a></li>
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
                <h4 class="mb-0">स्लाइडर प्रबंधन</h4>
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

        <!-- स्लाइडर सूची -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">स्लाइडर सूची</h5>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#sliderModal">
                    <i class="fas fa-plus"></i> नया स्लाइडर जोड़ें
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>छवि</th>
                                <th>शीर्षक</th>
                                <th>गतिविधि का नाम</th>
                                <th>स्थिति</th>
                                <th>कार्रवाई</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sliders) > 0): ?>
                                <?php foreach ($sliders as $slider): ?>
                                <tr>
                                    <td>
                                        <?php if ($slider['image_path']): ?>
                                            <img src="<?php echo $GLOBALS['base_url'] . '/uploads/' . $slider['image_path']; ?>" alt="Slider" style="width: 100px; height: 50px; object-fit: cover; border-radius:5px;">
                                        <?php else: ?>
                                            <span>कोई छवि नहीं</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($slider['title']); ?></td>
                                    <td><?php echo htmlspecialchars($slider['activity_name']); ?></td>
                                    <td>
                                        <?php if ($slider['is_active']): ?>
                                            <span class="badge bg-success">सक्रिय</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">निष्क्रिय</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editSlider(<?php echo htmlspecialchars(json_encode($slider)); ?>)"><i class="fas fa-edit"></i></button>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_slider">
                                            <input type="hidden" name="slider_id" value="<?php echo $slider['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('क्या आप वाकई इस स्लाइडर को हटाना चाहते हैं?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center">कोई स्लाइडर नहीं मिला।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- स्लाइडर मोडल -->
    <div class="modal fade" id="sliderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sliderModalTitle">नया स्लाइडर जोड़ें</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manage_sliders.php" enctype="multipart/form-data" id="sliderForm">
                    <input type="hidden" name="action" value="save_slider">
                    <input type="hidden" name="slider_id" id="sliderId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">शीर्षक</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="activity_name" class="form-label">गतिविधि का नाम</label>
                            <input type="text" class="form-control" id="activity_name" name="activity_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="slider_image" class="form-label">छवि</label>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" class="form-control" id="slider_image" name="slider_image" accept="image/*">
                                <p id="filePlaceholder">फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें</p>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
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
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));

        // फ़ाइल अपलोड
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('slider_image');
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = '#e1bee7'; });
        uploadArea.addEventListener('dragleave', () => { uploadArea.style.backgroundColor = 'var(--light-color)'; });
        uploadArea.addEventListener('drop', (e) => { e.preventDefault(); uploadArea.style.backgroundColor = 'var(--light-color)'; if (e.data.files.length) { fileInput.files = e.data.files; document.getElementById('filePlaceholder').textContent = `चयनित फ़ाइल: ${e.data.files[0].name}`; } });
        fileInput.addEventListener('change', () => { if (fileInput.files.length > 0) { document.getElementById('filePlaceholder').textContent = `चयनित फ़ाइल: ${fileInput.files[0].name}`; } });

        function editSlider(slider) {
            document.getElementById('sliderModalTitle').textContent = 'स्लाइडर संपादित करें';
            document.getElementById('sliderId').value = slider.id;
            document.getElementById('title').value = slider.title;
            document.getElementById('activity_name').value = slider.activity_name;
            document.getElementById('is_active').checked = slider.is_active == 1;
            document.getElementById('filePlaceholder').textContent = slider.image_path ? `वर्तमान फ़ाइल: ${slider.image_path.split('/').pop()}` : 'फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें';
            new bootstrap.Modal(document.getElementById('sliderModal')).show();
        }

        // मोडल रीसेट करें
        document.getElementById('sliderModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('sliderForm').reset();
            document.getElementById('sliderModalTitle').textContent = 'नया स्लाइडर जोड़ें';
            document.getElementById('sliderId').value = '';
            document.getElementById('filePlaceholder').textContent = 'फ़ाइल यहाँ खींचें और छोड़ें या फ़ाइल चुनने के लिए क्लिक करें';
        });
    </script>
</body>
</html>