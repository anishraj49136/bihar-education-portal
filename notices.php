<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// सक्रिय नोटिस प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM notices WHERE is_active = 1 ORDER BY created_at DESC");
 $stmt->execute();
 $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>नोटिस - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root { --primary-color: #6a1b9a; --secondary-color: #9c27b0; --accent-color: #ce93d8; --light-color: #f3e5f5; --dark-color: #4a148c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color)); min-height: 100vh; color: white; position: fixed; width: 250px; z-index: 100; transition: all 0.3s ease; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-radius: 0; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid white; }
        .sidebar .nav-link i { margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.15); }
        .card-header { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; font-weight: 600; border-radius: 15px 15px 0 0 !important; }
        .btn-primary { background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(106, 27, 154, 0.3); }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .mobile-menu-btn { display: none; position: fixed; top: 20px; left: 20px; z-index: 101; background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 1.5rem; }
        .notice-item { background-color: white; border-left: 4px solid var(--primary-color); padding: 20px; margin-bottom: 15px; border-radius: 0 5px 5px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .notice-item:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } }
    </style>
</head>
<body>
    <?php include 'sidebar_template.php'; ?>
    
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <h4 class="mb-0">नोटिस बोर्ड</h4>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-2"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                    <div>
                        <h6 class="mb-0"><?php echo $_SESSION['name']; ?></h6>
                        <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?></small>
                    </div>
                </div>
            </div>
        </nav>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">नवीनतम नोटिस</h5>
            </div>
            <div class="card-body">
                <?php if (count($notices) > 0): ?>
                    <?php foreach ($notices as $notice): ?>
                    <div class="notice-item">
                        <h6><?php echo htmlspecialchars($notice['title']); ?></h6>
                        <p><?php echo nl2br(htmlspecialchars($notice['description'])); ?></p>
                        <small class="text-muted">प्रकाशित दिनांक: <?php echo date('d M Y, g:i A', strtotime($notice['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">वर्तमान में कोई नोटिस उपलब्ध नहीं है।</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    </script>
</body>
</html>