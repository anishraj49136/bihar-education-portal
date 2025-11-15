<?php
require_once 'config.php';

// नवीनतम पत्र प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM letters WHERE is_public = 1 ORDER BY created_at DESC LIMIT 5");
 $stmt->execute();
 $publicLetters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// नवीनतम नोटिस प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
 $stmt->execute();
 $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// सक्रिय स्लाइडर प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM sliders WHERE is_active = 1 ORDER BY created_at DESC");
 $stmt->execute();
 $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>बिहार शिक्षा विभाग</title>
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
        
        .navbar {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80') center/cover;
            opacity: 0.2;
            z-index: -1;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 600;
            border-bottom: none;
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
        
        .carousel-item {
            height: 400px;
        }
        
        .carousel-item img {
            height: 100%;
            object-fit: cover;
        }
        
        .footer {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 0;
            margin-top: 50px;
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .notice-item {
            background-color: white;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 5px 5px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .notice-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .letter-item {
            background-color: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .letter-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .login-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 15px;
            color: white;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .login-section .form-control {
            border-radius: 50px;
            padding: 12px 20px;
            border: none;
        }
        
        .login-section .btn {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 0;
            }
            
            .card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- नेविगेशन बार -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>बिहार शिक्षा विभाग
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">होम</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">हमारे बारे में</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">सेवाएं</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">संपर्क</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">लॉगिन</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- हीरो सेक्शन -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">बिहार शिक्षा विभाग</h1>
                    <p class="lead mb-4">बिहार में शिक्षा की गुणवत्ता में सुधार और सभी के लिए शिक्षा तक पहुंच सुनिश्चित करने के लिए समर्पित।</p>
                    <a href="login.php" class="btn btn-light btn-lg">लॉगिन करें</a>
                </div>
                <div class="col-lg-6">
                    <div class="floating">
                        <img src="https://images.unsplash.com/photo-1581078426770-6d336e5de7bf?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="शिक्षा" class="img-fluid rounded-3">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- स्लाइडर सेक्शन -->
    <?php if (!empty($sliders)): ?>
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">हमारी गतिविधियां</h2>
            <div id="activityCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php for ($i = 0; $i < count($sliders); $i++): ?>
                    <button type="button" data-bs-target="#activityCarousel" data-bs-slide-to="<?php echo $i; ?>" <?php echo $i == 0 ? 'class="active"' : ''; ?>></button>
                    <?php endfor; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($sliders as $index => $slider): ?>
                    <div class="carousel-item <?php echo $index == 0 ? 'active' : ''; ?>">
                        <img src="<?php echo $GLOBALS['base_url'] . '/uploads/' . $slider['image_path']; ?>" class="d-block w-100" alt="<?php echo $slider['activity_name']; ?>">
                        <div class="carousel-caption d-none d-md-block">
                            <h5><?php echo $slider['activity_name']; ?></h5>
                            <p><?php echo $slider['title']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#activityCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#activityCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- लॉगिन सेक्शन -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="login-section">
                        <h3 class="text-center mb-4">लॉगिन करें</h3>
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
                                <select class="form-select" id="user_type" name="user_type" required>
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
                                <button type="submit" class="btn btn-light">लॉगिन करें</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- नोटिस सेक्शन -->
    <?php if (!empty($notices)): ?>
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">नवीनतम नोटिस</h2>
            <div class="row">
                <div class="col-lg-6">
                    <?php foreach ($notices as $notice): ?>
                    <div class="notice-item">
                        <h5><?php echo $notice['title']; ?></h5>
                        <p><?php echo substr($notice['description'], 0, 10000) . '...'; ?></p>
                        <small class="text-muted"><?php echo date('d M Y', strtotime($notice['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- पत्र सेक्शन -->
    <?php if (!empty($publicLetters)): ?>
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">नवीनतम पत्र</h2>
            <div class="row">
                <div class="col-lg-6">
                    <?php foreach ($publicLetters as $letter): ?>
                    <div class="letter-item">
                        <h5><?php echo $letter['title']; ?></h5>
                        <p><?php echo substr($letter['description'], 0, 100) . '...'; ?></p>
                        <a href="<?php echo $GLOBALS['base_url'] . '/uploads/' . $letter['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">पीडीएफ देखें</a>
                        <small class="text-muted ms-2"><?php echo date('d M Y', strtotime($letter['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- फुटर -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>बिहार शिक्षा विभाग</h5>
                    <p>बिहार में शिक्षा की गुणवत्ता में सुधार और सभी के लिए शिक्षा तक पहुंच सुनिश्चित करने के लिए समर्पित।</p>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>त्वरित लिंक</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">हमारे बारे में</a></li>
                        <li><a href="#" class="text-white">सेवाएं</a></li>
                        <li><a href="#" class="text-white">संपर्क</a></li>
                        <li><a href="#" class="text-white">नीतियां</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5>संपर्क जानकारी</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i> शिक्षा विभाग, पटना, बिहार</p>
                    <p><i class="fas fa-phone me-2"></i> +91 0612 223 0000</p>
                    <p><i class="fas fa-envelope me-2"></i> education.bihar@gov.in</p>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> बिहार शिक्षा विभाग. सर्वाधिकार सुरक्षित.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // स्मूथ स्क्रॉलिंग
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>