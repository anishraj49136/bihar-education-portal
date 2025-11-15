<?php
require_once 'config.php';

// यदि उपयोगकर्ता पहले से लॉग इन है, तो उचित डैशबोर्ड पर रीडायरेक्ट करें
if (isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'school':
            header('Location: school_dashboard.php');
            break;
        case 'ddo':
            header('Location: ddo_dashboard.php');
            break;
        case 'block_officer':
            header('Location: block_officer_dashboard.php');
            break;
        case 'district_staff':
            header('Location: district_staff_dashboard.php');
            break;
        case 'district_program_officer':
            header('Location: district_program_officer_dashboard.php');
            break;
        case 'district_education_officer':
            header('Location: district_education_officer_dashboard.php');
            break;
        default:
            header('Location: index.php');
            break;
    }
    exit;
}

// लॉगआउट संदेश को संसाधित करें
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success_message = "आप सफलतापूर्वक लॉग आउट हो गए हैं!";
}

// त्रुटि संदेश को संसाधित करें
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// सफलता संदेश को संसाधित करें
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>लॉगिन - बिहार शिक्षा विभाग</title>
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
        }
        
        .login-image {
            background: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80') center/cover;
            position: relative;
        }
        
        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0.7;
        }
        
        .login-form {
            padding: 40px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 27, 154, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3);
        }
        
        .school-note {
            background-color: var(--light-color);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 767px) {
            .login-image {
                height: 200px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0 h-100">
                <div class="col-md-6 d-none d-md-block">
                    <div class="login-image h-100 d-flex align-items-center justify-content-center">
                        <div class="text-center text-white p-4">
                            <h2 class="mb-4">बिहार शिक्षा विभाग</h2>
                            <p class="lead">बिहार में शिक्षा की गुणवत्ता में सुधार और सभी के लिए शिक्षा तक पहुंच सुनिश्चित करने के लिए समर्पित।</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="login-form h-100 d-flex align-items-center justify-content-center">
                        <div class="w-100">
                            <div class="text-center mb-4">
                                <h3 class="fw-bold">लॉगिन करें</h3>
                                <p class="text-muted">अपनी लॉगिन जानकारी दर्ज करें</p>
                            </div>
                            
                            <!-- सफलता/त्रुटि संदेश -->
                            <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form action="login_process.php" method="post">
                                <div class="mb-3">
                                    <label for="username" class="form-label">उपयोगकर्ता नाम</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">पासवर्ड</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="user_type" class="form-label">उपयोगकर्ता प्रकार</label>
                                    <select class="form-select" id="user_type" name="user_type" required onchange="toggleSchoolNote()">
                                        <option value="" selected disabled>चुनें</option>
                                        <option value="school">विद्यालय</option>
                                        <option value="ddo">DDO</option>
                                        <option value="block_officer">प्रखंड शिक्षा पदाधिकारी</option>
                                        <option value="district_staff">जिला कार्यालय स्टाफ</option>
                                        <option value="district_program_officer">जिला कार्यक्रम पदाधिकारी</option>
                                        <option value="district_education_officer">जिला शिक्षा पदाधिकारी</option>
                                        <option value="admin">एडमिन</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">लॉगिन करें</button>
                                </div>
                            </form>
                            
                            <div id="schoolNote" class="school-note" style="display: none;">
                                <strong>विद्यालय लॉगिन के लिए:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>उपयोगकर्ता नाम: विद्यालय का UDISE कोड</li>
                                    <li>पासवर्ड: 123456 (डिफ़ॉल्ट)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSchoolNote() {
            const userType = document.getElementById('user_type').value;
            const schoolNote = document.getElementById('schoolNote');
            
            if (userType === 'school') {
                schoolNote.style.display = 'block';
            } else {
                schoolNote.style.display = 'none';
            }
        }
    </script>
</body>
</html>