<?php
session_start();
include('config.php');
include('check_session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_schools'])) {
    $schools_to_forward = $_POST['forward_schools'];
    $forwarded_count = 0;

    foreach ($schools_to_forward as $school_month) {
        list($udise, $month) = explode('|', $school_month);
        
        // उन सभी रिकॉर्ड्स को अपडेट करें जो इस स्तर पर लंबित हैं
        $update_sql = "UPDATE pf_submissions SET status = 'Pending at District', current_level = 'District', forwarded_to_district_at = NOW() 
                       WHERE school_udise = ? AND month = ? AND status = ?";
        
        $pending_status = ($_SESSION['role'] == 'ddo') ? 'Pending at DDO' : 'Pending at Block Officer';
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sss", $udise, $month, $pending_status);
        
        if ($stmt->execute()) {
            $forwarded_count += $stmt->affected_rows;
        }
    }

    // उपयुक्त डैशबोर्ड पर रीडायरेक्ट करें
    $redirect_page = ($_SESSION['role'] == 'ddo') ? 'ddo_dashboard.php' : 'block_officer_dashboard.php';
    header("Location: " . $redirect_page . "?msg=$forwarded_count रिकॉर्ड सफलतापूर्वक जिला कार्यालय को अग्रसारित किए गए।");
} else {
    header("Location: " . ($_SESSION['role'] == 'ddo' ? 'ddo_dashboard.php' : 'block_officer_dashboard.php') . "?msg=अमान्य अनुरोध।");
}
?>