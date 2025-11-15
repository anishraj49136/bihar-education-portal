<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह विद्यालय उपयोगकर्ता है
checkUserType('school');

// विद्यालय और जिला/प्रखंड की जानकारी प्राप्त करें
 $school_id = $_SESSION['school_id'];
 $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
 $stmt->execute([$school_id]);
 $school_info = $stmt->fetch(PDO::FETCH_ASSOC);

// POST डेटा प्राप्त करें
 $school_id = $_POST['school_id'];
 $month = $_POST['month'];
 $year = $_POST['year'];

// स्कूल का UDISE कोड प्राप्त करें
 $stmt = $conn->prepare("SELECT udise_code FROM schools WHERE id = ?");
 $stmt->execute([$school_id]);
 $school = $stmt->fetch(PDO::FETCH_ASSOC);
 $school_udise = $school['udise_code'];

// महीने को डेटाबेस के लिए फॉर्मेट करें
 $month_for_db = $year . '-' . date('m', strtotime($month));

// पीएफ सबमिशन डेटा प्राप्त करें
 $stmt = $conn->prepare("SELECT * FROM pf_submissions WHERE school_udise = ? AND month = ? ORDER BY category, class_group");
 $stmt->execute([$school_udise, $month_for_db]);
 $pf_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// JSON प्रतिक्रिया भेजें
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'pf_list' => $pf_list
]);
?>