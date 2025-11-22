<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json'); // JSON रिस्पॉन्स के लिए

// जांचें कि क्या यह एक POST रिक्वेस्ट है और उपयोगकर्ता लॉग इन है
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'अवैध अनुरोध।']);
    exit;
}

// जांचें कि क्या उपयोगकर्ता प्रकार ब्लॉक अधिकारी है
checkUserType('block_officer');

 $udise_code = $_POST['udise_code'] ?? null;
 $month = $_POST['month'] ?? null;
 $year = $_POST['year'] ?? null;

if (!$udise_code || !$month || !$year) {
    echo json_encode(['success' => false, 'message' => 'आवश्यक जानकारी गायब है।']);
    exit;
}

// दोबारा जांचें कि PF जिले को तो नहीं भेजा जा चुका
 $check_pf_stmt = $conn->prepare("SELECT id FROM pf_submissions WHERE school_udise = ? AND month = ? AND status = 'Pending at District'");
 $check_pf_stmt->execute([$udise_code, date('Y-m', strtotime($year . '-' . date('m', strtotime($month . ' 1')))]);
if ($check_pf_stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'इस विद्यालय का PF जिले को भेजा जा चुका है, इसे रीसेट नहीं किया जा सकता।']);
    exit;
}

try {
    $conn->beginTransaction();

    // 1. attendance टेबल से डेटा हटाएं
    $stmt_attendance = $conn->prepare("DELETE a FROM attendance a JOIN teachers t ON a.teacher_id = t.id WHERE t.school_id = (SELECT id FROM schools WHERE udise_code = ?) AND a.month = ? AND a.year = ?");
    $stmt_attendance->execute([$udise_code, $month, $year]);

    // 2. pf_submissions टेबल से डेटा हटाएं
    $stmt_pf = $conn->prepare("DELETE FROM pf_submissions WHERE school_udise = ? AND month = ?");
    $stmt_pf->execute([$udise_code, date('Y-m', strtotime($year . '-' . date('m', strtotime($month . ' 1')))]);

    // 3. monthly_attendance_lock टेबल से डेटा हटाएं
    $stmt_lock = $conn->prepare("DELETE FROM monthly_attendance_lock WHERE school_udise = ? AND month = ? AND year = ?");
    $stmt_lock->execute([$udise_code, $month, $year]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'विद्यालय की उपस्थिति विवरणी सफलतापूर्वक रीसेट कर दी गई है।']);

} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Reset Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'डेटाबेस त्रुटि: ' . $e->getMessage()]);
}