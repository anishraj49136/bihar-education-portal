<?php
// कॉन्फिगरेशन फ़ाइल शामिल करें
require_once 'config.php';

// सत्र शुरू करें
session_start();

// जांचें कि उपयोगकर्ता लॉग इन है या नहीं
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'आप लॉग इन नहीं हैं।', 'session_expired' => true]);
    exit;
}

// जांचें कि क्या अनुरोध POST विधि का है
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'अमान्य अनुरोध विधि।']);
    exit;
}

// जिला आईडी प्राप्त करें
 $district_id = $_SESSION['district_id'] ?? null;
if (!$district_id) {
    echo json_encode(['success' => false, 'message' => 'जिला आईडी नहीं मिली।']);
    exit;
}

// अनुरोध से डेटा प्राप्त करें
 $action = $_POST['action'] ?? '';
 $ids = $_POST['ids'] ?? '';

try {
    // एकल रिकॉर्ड के लिए
    if ($action === 'approve_district') {
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'approved_by_district', approved_by_district_id = ?, approved_date = NOW() WHERE id = ? AND id IN (SELECT pf.id FROM pf_submissions pf JOIN schools s ON pf.school_udise = s.udise_code JOIN blocks b ON s.block_id = b.id WHERE b.district_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $id, $district_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'पीएफ फॉर्म सफलतापूर्वक स्वीकृत किया गया।']);
        } else {
            echo json_encode(['success' => false, 'message' => 'पीएफ फॉर्म अपडेट करने में विफल।']);
        }
    } 
    // एकल रिकॉर्ड को वापस भेजने के लिए
    elseif ($action === 'send_back_to_block') {
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'returned_to_block', returned_by_district_id = ?, returned_date = NOW() WHERE id = ? AND id IN (SELECT pf.id FROM pf_submissions pf JOIN schools s ON pf.school_udise = s.udise_code JOIN blocks b ON s.block_id = b.id WHERE b.district_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $id, $district_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजा गया।']);
        } else {
            echo json_encode(['success' => false, 'message' => 'पीएफ फॉर्म अपडेट करने में विफल।']);
        }
    }
    // कई रिकॉर्ड्स को स्वीकृत करने के लिए
    elseif ($action === 'approve_multiple_district') {
        $id_array = explode(',', $ids);
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'approved_by_district', approved_by_district_id = ?, approved_date = NOW() WHERE id IN ($placeholders) AND id IN (SELECT pf.id FROM pf_submissions pf JOIN schools s ON pf.school_udise = s.udise_code JOIN blocks b ON s.block_id = b.id WHERE b.district_id = ?)");
        $params = array_merge([$_SESSION['user_id']], $id_array, [$district_id]);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'चयनित पीएफ फॉर्म सफलतापूर्वक स्वीकृत किए गए।']);
        } else {
            echo json_encode(['success' => false, 'message' => 'पीएफ फॉर्म अपडेट करने में विफल।']);
        }
    }
    // कई रिकॉर्ड्स को वापस भेजने के लिए
    elseif ($action === 'send_back_multiple_to_block') {
        $id_array = explode(',', $ids);
        $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'returned_to_block', returned_by_district_id = ?, returned_date = NOW() WHERE id IN ($placeholders) AND id IN (SELECT pf.id FROM pf_submissions pf JOIN schools s ON pf.school_udise = s.udise_code JOIN blocks b ON s.block_id = b.id WHERE b.district_id = ?)");
        $params = array_merge([$_SESSION['user_id']], $id_array, [$district_id]);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'चयनित पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजे गए।']);
        } else {
            echo json_encode(['success' => false, 'message' => 'पीएफ फॉर्म अपडेट करने में विफल।']);
        }
    }
    else {
        echo json_encode(['success' => false, 'message' => 'अमान्य क्रिया।']);
    }
} catch (PDOException $e) {
    error_log("Error updating PF status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'डेटाबेस त्रुटि।']);
}
?>