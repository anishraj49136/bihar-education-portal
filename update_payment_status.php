<?php
session_start();
include('config.php');
include('check_session.php');

if ($_SESSION['role'] != 'district_staff' && $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'अनधिकृत']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $udise = $_POST['udise'];
    $month = $_POST['month'];
    $new_status = $_POST['status'];

    $sql = "UPDATE pf_submissions SET status = ? WHERE school_udise = ? AND month = ? AND status = 'Pending at District'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $new_status, $udise, $month);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'अपडेट सफल।']);
    } else {
        echo json_encode(['success' => false, 'message' => 'डेटाबेस त्रुटि।']);
    }
}
?>