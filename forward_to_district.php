<?php
require_once 'config.php';

// यहाँ उपयोगकर्ता प्रकार की जांच की गई है
checkUserType(['block_officer', 'ddo']);

header('Content-Type: application/json');

 $response = ['success' => false, 'message' => ''];

try {
    $teacher_id = $_POST['teacher_id'] ?? 0;
    $month = $_POST['month'] ?? '';
    $year = $_POST['year'] ?? '';

    if (empty($teacher_id) || empty($month) || empty($year)) {
        throw new Exception("अपूर्ण जानकारी प्रदान की गई है।");
    }

    // उपस्थिति की स्थिति अपडेट करें
    $stmt = $conn->prepare("UPDATE attendance SET status = 'forwarded_to_district', reviewed_by = ?, reviewed_at = NOW() WHERE teacher_id = ? AND month = ? AND year = ?");
    $stmt->execute([$_SESSION['user_id'], $teacher_id, $month, $year]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'डेटा सफलतापूर्वक जिले को भेजा गया।';
    } else {
        $response['message'] = 'कोई रिकॉर्ड अपडेट नहीं हुआ। कृपया डेटा जांचें।';
    }

} catch (PDOException $e) {
    $response['message'] = 'डेटाबेस त्रुटि: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>