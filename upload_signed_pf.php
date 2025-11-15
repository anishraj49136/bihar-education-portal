<?php
// session_start(); हटा दिया क्योंकि यह पहले से ही config.php में है
include('config.php');
include('check_session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signed_pdf']) && isset($_POST['pf_id'])) {
    $pf_id = $_POST['pf_id'];
    
    // फाइल वैलिडेशन
    $file_name = $_FILES['signed_pdf']['name'];
    $file_tmp = $_FILES['signed_pdf']['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if ($file_ext != 'pdf') {
        header("Location: pf_management.php?msg=कृपया केवल PDF फाइल अपलोड करें।");
        exit();
    }

    // फाइल को अपलोड डायरेक्टरी में मूव करें
    $upload_dir = 'uploads/pf_signed/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $new_file_name = 'signed_' . $pf_id . '_' . time() . '.pdf';
    $upload_path = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // डेटाबेस अपडेट करें
        $update_sql = "UPDATE pf_submissions SET uploaded_pdf_path = ?, status = 'Uploaded', submitted_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bindParam(1, $upload_path);
        $stmt->bindParam(2, $pf_id);
        $stmt->execute();

        // अग्रेसरण का स्तर निर्धारित करें और स्थिति अपडेट करें
        $forward_sql = "SELECT ps.category, ps.class_group, pfr.forward_to 
                       FROM pf_submissions ps 
                       LEFT JOIN pf_forwarding_rules pfr ON ps.category = pfr.category AND ps.class_group = pfr.class_group 
                       WHERE ps.id = ?";
        $stmt = $conn->prepare($forward_sql);
        $stmt->bindParam(1, $pf_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result && $result['forward_to']){
            $forward_to = $result['forward_to'];
            $new_status = ($forward_to == 'DDO') ? 'Pending at DDO' : 'Pending at Block Officer';
            $new_level = $forward_to;

            $final_update_sql = "UPDATE pf_submissions SET status = ?, current_level = ? WHERE id = ?";
            $stmt = $conn->prepare($final_update_sql);
            $stmt->bindParam(1, $new_status);
            $stmt->bindParam(2, $new_level);
            $stmt->bindParam(3, $pf_id);
            $stmt->execute();
        }

        header("Location: pf_management.php?msg=पीएफ सफलतापूर्वक अपलोड और अग्रसरित किया गया।");
    } else {
        header("Location: pf_management.php?msg=फाइल अपलोड में त्रुटि।");
    }
} else {
    header("Location: pf_management.php?msg=अमान्य अनुरोध।");
}
?>