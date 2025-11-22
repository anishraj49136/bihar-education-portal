<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन, जिला स्टाफ, जिला प्रोग्राम ऑफिसर, जिला शिक्षा अधिकारी, DDO या ब्लॉक ऑफिसर है
if (!isset($_SESSION['user_type']) || 
    !in_array($_SESSION['user_type'], 
    ['admin', 'district_staff', 'district_program_officer', 'district_education_officer', 'ddo', 'block_officer'])) {
    header("Location: login.php");
    exit();
}

 $school_details = null;
 $photos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['udise_code'])) {
    $udise_code = trim($_POST['udise_code']);
    $stmt = $conn->prepare("SELECT id, name, udise_code, village_name FROM schools WHERE udise_code = ?");
    $stmt->execute([$udise_code]);
    $school_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($school_details) {
        $stmt = $conn->prepare("SELECT photo_type, photo_path, sequence_number FROM school_photos WHERE school_id = ? ORDER BY photo_type, sequence_number");
        $stmt->execute([$school_details['id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($results as $photo) {
            $photos[$photo['photo_type']][$photo['sequence_number']] = $photo['photo_path'];
        }
    } else {
        $error_message = "इस UDISE कोड के साथ कोई विद्यालय नहीं मिला।";
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>विद्यालय फोटो देखें - बिहार शिक्षा विभाग</title>
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
            font-size: 0.95rem; 
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
            transition: all 0.3s ease; 
        }
        
        .navbar { 
            background: white; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            border-radius: 10px; 
            margin-bottom: 20px; 
        }
        
        .card { 
            border: none; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
            margin-bottom: 25px; 
        }
        
        .card-header { 
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); 
            color: white; 
            font-weight: 600; 
            border-radius: 15px 15px 0 0 !important; 
            padding: 15px 20px; 
        }
        
        .card-body { 
            padding: 20px; 
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
        
        .viewed-photo { 
            max-width: 100%; 
            height: 250px; 
            object-fit: cover; 
            border-radius: 10px; 
            border: 2px solid var(--primary-color); 
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .viewed-photo:hover {
            transform: scale(1.02);
        }
        
        .no-photo { 
            color: #6c757d; 
            font-style: italic; 
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px dashed #dee2e6;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
        }
        
        .form-control, .form-select { 
            border-radius: 10px; 
            border: 1px solid #ddd; 
            transition: all 0.3s ease; 
        }
        
        .form-control:focus, .form-select:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25); 
        }
        
        .user-type-badge { 
            display: inline-block; 
            padding: 3px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            margin-left: 10px; 
        }
        
        .user-type-admin { 
            background-color: #6a1b9a; 
            color: white; 
        }
        
        .user-type-district { 
            background-color: #2196F3; 
            color: white; 
        }
        
        .user-type-block { 
            background-color: #4CAF50; 
            color: white; 
        }
        
        .photo-section-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* मोबाइल रेस्पॉन्सिव स्टाइल */
        @media (max-width: 992px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.active { transform: translateX(0); } 
            .main-content { margin-left: 0; padding: 15px; } 
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } 
            .navbar { margin-top: 70px; } 
        }

        @media (max-width: 768px) {
            .main-content { padding: 10px; }
            .card-body { padding: 15px; }
            .card-header { padding: 12px 15px; font-size: 1rem; }
            .btn-primary { padding: 8px 20px; font-size: 0.9rem; }
            .navbar h4 { font-size: 1.2rem; }
            .user-avatar { width: 35px; height: 35px; font-size: 0.9rem; }
            .form-label { font-size: 0.9rem; }
            .viewed-photo { height: 200px; }
        }
        
        @media (max-width: 576px) {
            .card-header { font-size: 0.9rem; }
            .form-label { font-size: 0.85rem; }
            .form-control, .form-select { font-size: 0.9rem; }
            .btn-primary { padding: 6px 15px; font-size: 0.85rem; }
            .viewed-photo { height: 180px; }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <?php include 'sidebar_template.php'; ?>

    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <h4 class="mb-0">विद्यालय फोटो देखें</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?>
                            <?php 
                            if ($_SESSION['user_type'] === 'admin') echo '<span class="user-type-badge user-type-admin">एडमिन</span>';
                            elseif (in_array($_SESSION['user_type'], ['district_staff', 'district_program_officer', 'district_education_officer'])) echo '<span class="user-type-badge user-type-district">जिला अधिकारी</span>';
                            elseif (in_array($_SESSION['user_type'], ['ddo', 'block_officer'])) echo '<span class="user-type-badge user-type-block">ब्लॉक अधिकारी</span>';
                            ?>
                        </h6>
                        <small class="text-muted">System User</small>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- सर्च फॉर्म -->
        <div class="card">
            <div class="card-header"><i class="fas fa-search me-2"></i>विद्यालय खोजें</div>
            <div class="card-body">
                <form action="view_school_photos.php" method="post">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="udise_code" placeholder="विद्यालय का UDISE कोड दर्ज करें" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">खोजें</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($school_details): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>विद्यालय की जानकारी</div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($school_details['name']); ?></h5>
                <p class="mb-1"><strong>UDISE कोड:</strong> <?php echo htmlspecialchars($school_details['udise_code']); ?></p>
                <p class="mb-0"><strong>ग्राम:</strong> <?php echo htmlspecialchars($school_details['village_name']); ?></p>
            </div>
        </div>

        <!-- फोटो दिखाने के लिए सेक्शन -->
        <div class="card">
            <div class="card-header"><i class="fas fa-images me-2"></i>विद्यालय की तस्वीरें</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6 class="photo-section-title">विद्यालय का फोटो (गेट)</h6>
                        <?php if (isset($photos['school_gate'][0])): ?>
                            <img src="<?php echo $photos['school_gate'][0]; ?>" class="viewed-photo" alt="School Gate" onclick="window.open('<?php echo $photos['school_gate'][0]; ?>', '_blank')">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <p>यह फोटो उपलब्ध नहीं है।</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="photo-section-title">विद्यालय का ग्राउंड</h6>
                        <?php if (isset($photos['ground'][0])): ?>
                            <img src="<?php echo $photos['ground'][0]; ?>" class="viewed-photo" alt="School Ground" onclick="window.open('<?php echo $photos['ground'][0]; ?>', '_blank')">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <p>यह फोटो उपलब्ध नहीं है।</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="photo-section-title">आईसीटी लैब</h6>
                        <?php if (isset($photos['ict_lab'][0])): ?>
                            <img src="<?php echo $photos['ict_lab'][0]; ?>" class="viewed-photo" alt="ICT Lab" onclick="window.open('<?php echo $photos['ict_lab'][0]; ?>', '_blank')">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <p>यह फोटो उपलब्ध नहीं है।</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="photo-section-title">स्मार्ट क्लास</h6>
                        <?php if (isset($photos['smart_class'][0])): ?>
                            <img src="<?php echo $photos['smart_class'][0]; ?>" class="viewed-photo" alt="Smart Class" onclick="window.open('<?php echo $photos['smart_class'][0]; ?>', '_blank')">
                        <?php else: ?>
                            <div class="no-photo">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <p>यह फोटो उपलब्ध नहीं है।</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- कक्षाओं के फोटो -->
                <?php if (isset($photos['classroom']) && !empty($photos['classroom'])): ?>
                <h5 class="photo-section-title mt-4">कक्षाओं के फोटो</h5>
                <div class="row">
                    <?php foreach($photos['classroom'] as $seq => $path): ?>
                    <div class="col-md-4 mb-3">
                        <h6>कक्षा नंबर-<?php echo $seq; ?></h6>
                        <img src="<?php echo $path; ?>" class="viewed-photo" alt="Classroom <?php echo $seq; ?>" onclick="window.open('<?php echo $path; ?>', '_blank')">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- शौचालयों के फोटो -->
                <?php if (isset($photos['toilet']) && !empty($photos['toilet'])): ?>
                <h5 class="photo-section-title mt-4">शौचालयों के फोटो</h5>
                <div class="row">
                    <?php foreach($photos['toilet'] as $seq => $path): ?>
                    <div class="col-md-4 mb-3">
                        <h6>शौचालय नंबर-<?php echo $seq; ?></h6>
                        <img src="<?php echo $path; ?>" class="viewed-photo" alt="Toilet <?php echo $seq; ?>" onclick="window.open('<?php echo $path; ?>', '_blank')">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // मोबाइल मेन्यू टॉगल
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>