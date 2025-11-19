<?php
require_once 'config.php';

// यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType(['block_officer', 'ddo']);

 $id = $_GET['id'] ?? 0;

try {
    $stmt = $conn->prepare("SELECT pf.*, t.name as teacher_name, t.pran_no, t.uan_no, 
                           s.name as school_name, s.udise_code
                           FROM pf_submissions pf
                           JOIN teachers t ON pf.teacher_id = t.id
                           JOIN schools s ON t.school_id = s.id
                           WHERE pf.id = ?");
    $stmt->execute([$id]);
    $pf_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pf_record) {
        $_SESSION['error_message'] = "पीएफ रिकॉर्ड नहीं मिला।";
        header("Location: " . ($_SESSION['user_role'] == 'block_officer' ? 'block_officer_dashboard.php' : 'ddo_dashboard.php'));
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "त्रुटि: " . $e->getMessage();
    header("Location: " . ($_SESSION['user_role'] == 'block_officer' ? 'block_officer_dashboard.php' : 'ddo_dashboard.php'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>पीएफ फॉर्म देखें - बिहार शिक्षा विभाग</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: linear-gradient(to right, #6a1b9a, #9c27b0); color: white; font-weight: 600; }
        .pdf-container { width: 100%; height: 600px; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">पीएफ फॉर्म देखें</h5>
                <button type="button" class="btn btn-light btn-sm" onclick="window.close()">
                    <i class="fas fa-times"></i> बंद करें
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>शिक्षक का नाम:</strong> <?php echo $pf_record['teacher_name']; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>विद्यालय:</strong> <?php echo $pf_record['school_name']; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>PRAN/UAN:</strong> <?php echo $pf_record['pran_no'] ?: $pf_record['uan_no']; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>महीना/वर्ष:</strong> <?php echo $pf_record['month'] . '/' . $pf_record['year']; ?>
                    </div>
                </div>
                
                <ul class="nav nav-tabs" id="pdfTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pf-tab" data-bs-toggle="tab" data-bs-target="#pf-pdf" type="button" role="tab">
                            पीएफ फॉर्म
                        </button>
                    </li>
                    <?php if (!empty($pf_record['signed_pf_file_path'])): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="signed-pf-tab" data-bs-toggle="tab" data-bs-target="#signed-pf-pdf" type="button" role="tab">
                            हस्ताक्षरित पीएफ
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content" id="pdfTabsContent">
                    <div class="tab-pane fade show active" id="pf-pdf" role="tabpanel">
                        <?php if (!empty($pf_record['pf_file_path'])): ?>
                            <div class="pdf-container">
                                <iframe src="<?php echo $pf_record['pf_file_path']; ?>" width="100%" height="100%" frameborder="0"></iframe>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">पीएफ फॉर्म उपलब्ध नहीं है।</div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($pf_record['signed_pf_file_path'])): ?>
                    <div class="tab-pane fade" id="signed-pf-pdf" role="tabpanel">
                        <div class="pdf-container">
                            <iframe src="<?php echo $pf_record['signed_pf_file_path']; ?>" width="100%" height="100%" frameborder="0"></iframe>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>