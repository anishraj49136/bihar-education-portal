<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

 $school_id = $_SESSION['school_id'];
 $upload_dir = 'uploads/school_photos/';

// फोटो कंप्रेशन फंक्शन
function compressImage($source, $destination, $quality) {
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } else {
        return false; // Unsupported format
    }
    imagejpeg($image, $destination, $quality);
    imagedestroy($image);
    return true;
}

// फॉर्म सबमिशन हैंडलिंग
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo_type = $_POST['photo_type'];
    $sequence_number = isset($_POST['sequence_number']) ? (int)$_POST['sequence_number'] : null;

    // पहले पुरानी फोटो को डिलीट करें अगर मौजूद है (सिंगल फोटो वालों के लिए)
    if (in_array($photo_type, ['school_gate', 'ground', 'ict_lab', 'smart_class'])) {
        $stmt = $conn->prepare("SELECT photo_path FROM school_photos WHERE school_id = ? AND photo_type = ?");
        $stmt->execute([$school_id, $photo_type]);
        $old_photo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old_photo && file_exists($old_photo['photo_path'])) {
            unlink($old_photo['photo_path']);
        }
        $stmt = $conn->prepare("DELETE FROM school_photos WHERE school_id = ? AND photo_type = ?");
        $stmt->execute([$school_id, $photo_type]);
    } else { // क्लासरूम और टॉयलेट के लिए सीक्वेंस नंबर चेक करें
        $stmt = $conn->prepare("SELECT photo_path FROM school_photos WHERE school_id = ? AND photo_type = ? AND sequence_number = ?");
        $stmt->execute([$school_id, $photo_type, $sequence_number]);
        $old_photo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old_photo && file_exists($old_photo['photo_path'])) {
            unlink($old_photo['photo_path']);
        }
        $stmt = $conn->prepare("DELETE FROM school_photos WHERE school_id = ? AND photo_type = ? AND sequence_number = ?");
        $stmt->execute([$school_id, $photo_type, $sequence_number]);
    }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['photo']['tmp_name'];
        $file_name = basename($_FILES['photo']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // वैलिडेशन
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed_extensions)) {
            // यूनीक फाइल नेम जेनरेट करें
            $new_file_name = 'school_' . $school_id . '_' . $photo_type . ($sequence_number ? '_' . $sequence_number : '') . '_' . uniqid() . '.jpg';
            $dest_path = $upload_dir . $new_file_name;

            // फोटो कंप्रेस करें और मूव करें
            if (compressImage($file_tmp_path, $dest_path, 75)) {
                // डेटाबेस में एंट्री करें
                $stmt = $conn->prepare("INSERT INTO school_photos (school_id, photo_type, photo_path, sequence_number) VALUES (?, ?, ?, ?)");
                $stmt->execute([$school_id, $photo_type, $dest_path, $sequence_number]);
                $success_message = "फोटो सफलतापूर्वक अपलोड की गई!";
            } else {
                $error_message = "फोटो अपलोड में त्रुटि। कृपया समर्थित प्रारूप (JPG, PNG) का उपयोग करें।";
            }
        } else {
            $error_message = "अमान्य फाइल प्रकार। केवल JPG और PNG फाइलें अपलोड करें।";
        }
    } else {
        $error_message = "कोई फाइल अपलोड नहीं की गई या अपलोड में त्रुटि हुई।";
    }
}

// मौजूदा फोटो फेच करें
 $existing_photos = [];
 $stmt = $conn->prepare("SELECT photo_type, photo_path, sequence_number FROM school_photos WHERE school_id = ?");
 $stmt->execute([$school_id]);
 $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($results as $photo) {
    $existing_photos[$photo['photo_type']][$photo['sequence_number']] = $photo['photo_path'];
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यालय तस्वीर अपलोड करें - बिहार शिक्षा विभाग</title>
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
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; font-size: 0.95rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 20px; transition: all 0.3s ease; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 10px; margin-bottom: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-weight: 600; border-radius: 15px 15px 0 0 !important; padding: 15px 20px; }
        .card-body { padding: 20px; }
        .btn-primary { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #ddd; transition: all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(106, 27, 154, 0.25); }
        .alert-container { position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 350px; }
        .uploaded-photo { max-width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-top: 15px; border: 2px solid var(--primary-color); }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
            .navbar { margin-top: 70px; }
        }
    </style>
</head>
<body>
    <div class="alert-container" id="alertContainer"></div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    
    <div class="sidebar" id="sidebar">
        <div class="p-4 text-center">
            <h4>बिहार शिक्षा विभाग</h4>
            <p class="mb-0">विद्यालय डैशबोर्ड</p>
        </div>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="school_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="school_profile.php"><i class="fas fa-school"></i> विद्यालय प्रोफाइल</a></li>
            <li class="nav-item"><a class="nav-link active" href="upload_photos.php"><i class="fas fa-camera"></i> फोटो अपलोड करें</a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-user-graduate"></i> नामांकन</a></li>
            <li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण</a></li>
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
            <li class="nav-item"><a class="nav-link" href="pf_management.php"><i class="fas fa-file-pdf"></i> पीडीएफ प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
            <li class="nav-item"><a class="nav-link" href="letters.php"><i class="fas fa-envelope"></i> पत्र</a></li>
            <li class="nav-item"><a class="nav-link" href="notices.php"><i class="fas fa-bullhorn"></i> नोटिस</a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
        </ul>
    </div>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <h4 class="mb-0">विद्यालय तस्वीर अपलोड करें</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2" style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted">विद्यालय</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
        <?php endif; ?>

        <!-- विद्यालय गेट फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-school me-2"></i>विद्यालय का फोटो (आगे से जिसमें गेट स्पष्ट नजर आता हो)</div>
            <div class="card-body">
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="school_gate">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="file" class="form-control" name="photo" accept="image/*" required>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button>
                        </div>
                    </div>
                    <?php if (isset($existing_photos['school_gate'][0])): ?>
                        <img src="<?php echo $existing_photos['school_gate'][0]; ?>" class="uploaded-photo" alt="School Gate">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- विद्यालय ग्राउंड फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-tree me-2"></i>विद्यालय के ग्राउंड का फोटो</div>
            <div class="card-body">
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="ground">
                    <div class="row">
                        <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                    </div>
                    <?php if (isset($existing_photos['ground'][0])): ?>
                        <img src="<?php echo $existing_photos['ground'][0]; ?>" class="uploaded-photo" alt="School Ground">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- कक्षा के फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-door-closed me-2"></i>विद्यालय के कमरे का फोटोग्राफ</div>
            <div class="card-body">
                <div id="classroom-container">
                    <?php
                    $classroom_count = isset($existing_photos['classroom']) ? count($existing_photos['classroom']) : 1;
                    for ($i = 1; $i <= $classroom_count; $i++):
                    ?>
                    <div class="classroom-item mb-3" data-index="<?php echo $i; ?>">
                        <h5>कक्षा नंबर-<?php echo $i; ?></h5>
                        <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="photo_type" value="classroom">
                            <input type="hidden" name="sequence_number" value="<?php echo $i; ?>">
                            <div class="row">
                                <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                                <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                            </div>
                            <?php if (isset($existing_photos['classroom'][$i])): ?>
                                <img src="<?php echo $existing_photos['classroom'][$i]; ?>" class="uploaded-photo" alt="Classroom <?php echo $i; ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addMoreClassroom()"><i class="fas fa-plus"></i> और कक्षा जोड़ें</button>
            </div>
        </div>

        <!-- आईसीटी लैब फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-desktop me-2"></i>आईसीटी लैब रूम का फोटो</div>
            <div class="card-body">
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="ict_lab">
                    <div class="row">
                        <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                    </div>
                    <?php if (isset($existing_photos['ict_lab'][0])): ?>
                        <img src="<?php echo $existing_photos['ict_lab'][0]; ?>" class="uploaded-photo" alt="ICT Lab">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- स्मार्ट क्लास फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-chalkboard me-2"></i>स्मार्ट क्लास रूम का फोटो</div>
            <div class="card-body">
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="smart_class">
                    <div class="row">
                        <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                    </div>
                    <?php if (isset($existing_photos['smart_class'][0])): ?>
                        <img src="<?php echo $existing_photos['smart_class'][0]; ?>" class="uploaded-photo" alt="Smart Class">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- शौचालय के फोटो -->
        <div class="card">
            <div class="card-header"><i class="fas fa-restroom me-2"></i>कार्यरत शौचालय का फोटो</div>
            <div class="card-body">
                <div id="toilet-container">
                    <?php
                    $toilet_count = isset($existing_photos['toilet']) ? count($existing_photos['toilet']) : 1;
                    for ($i = 1; $i <= $toilet_count; $i++):
                    ?>
                    <div class="toilet-item mb-3" data-index="<?php echo $i; ?>">
                        <h5>शौचालय नंबर-<?php echo $i; ?></h5>
                        <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="photo_type" value="toilet">
                            <input type="hidden" name="sequence_number" value="<?php echo $i; ?>">
                            <div class="row">
                                <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                                <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                            </div>
                            <?php if (isset($existing_photos['toilet'][$i])): ?>
                                <img src="<?php echo $existing_photos['toilet'][$i]; ?>" class="uploaded-photo" alt="Toilet <?php echo $i; ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addToilet()"><i class="fas fa-plus"></i> और शौचालय जोड़ें</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        let classroomIndex = <?php echo $classroom_count; ?>;
        function addMoreClassroom() {
            classroomIndex++;
            const container = document.getElementById('classroom-container');
            const newItem = document.createElement('div');
            newItem.className = 'classroom-item mb-3';
            newItem.dataset.index = classroomIndex;
            newItem.innerHTML = `
                <h5>कक्षा नंबर-${classroomIndex}</h5>
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="classroom">
                    <input type="hidden" name="sequence_number" value="${classroomIndex}">
                    <div class="row">
                        <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                    </div>
                </form>
            `;
            container.appendChild(newItem);
        }

        let toiletIndex = <?php echo $toilet_count; ?>;
        function addToilet() {
            toiletIndex++;
            const container = document.getElementById('toilet-container');
            const newItem = document.createElement('div');
            newItem.className = 'toilet-item mb-3';
            newItem.dataset.index = toiletIndex;
            newItem.innerHTML = `
                <h5>शौचालय नंबर-${toiletIndex}</h5>
                <form action="upload_photos.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="photo_type" value="toilet">
                    <input type="hidden" name="sequence_number" value="${toiletIndex}">
                    <div class="row">
                        <div class="col-md-6"><input type="file" class="form-control" name="photo" accept="image/*" required></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary w-100">फोटो अपलोड करें</button></div>
                    </div>
                </form>
            `;
            container.appendChild(newItem);
        }
    </script>
</body>
</html>