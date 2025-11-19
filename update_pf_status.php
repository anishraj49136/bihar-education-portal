<?php
require_once 'config.php';

// सत्र शुरू करें (अगर config.php में नहीं है तो)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// JSON हेडर सेट करें
header('Content-Type: application/json');

// जवाब array
 $response = [
    'success' => false,
    'message' => ''
];

// सत्र की जांच करें
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'अनधिकृत पहुंच। कृपया लॉग इन करें।';
    echo json_encode($response);
    exit;
}

// POST डेटा प्राप्त करें
 $action = $_POST['action'] ?? '';
 $id = $_POST['id'] ?? '';
 $ids = $_POST['ids'] ?? '';

try {
    // --- ब्लॉक अधिकारी द्वारा भेजे गए एक्शन ---

    // एकल PF फॉर्म को जिला अधिकारी को भेजना
    if ($action === 'forward_district' && !empty($id)) {
        // केवल status को अपडेट कर रहे हैं
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'forwarded_to_district' WHERE id = ?");
        $stmt->execute([$id]);
        $response['success'] = true;
        $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक जिला अधिकारी को भेजा गया!';
    }
    
    // एकल PF फॉर्म को स्कूल को वापस भेजना
    elseif ($action === 'send_back_school' && !empty($id)) {
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'sent_back_to_school' WHERE id = ?");
        $stmt->execute([$id]);
        $response['success'] = true;
        $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक स्कूल को वापस भेजा गया!';
    }

    // एकाधिक PF फॉर्म को जिला अधिकारी को भेजना
    elseif ($action === 'forward_multiple_district' && !empty($ids)) {
        $id_array = explode(',', $ids);
        $id_array = array_map('intval', $id_array);
        $id_array = array_filter($id_array);

        if (!empty($id_array)) {
            $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
            
            $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'forwarded_to_district' WHERE id IN ($placeholders)");
            $stmt->execute($id_array);
            $response['success'] = true;
            $response['message'] = count($id_array) . ' चयनित पीएफ फॉर्म सफलतापूर्वक जिला अधिकारी को भेजे गए!';
        } else {
            $response['message'] = 'भेजने के लिए कोई वैध ID नहीं मिली।';
        }
    }
    
    // --- जिला अधिकारी द्वारा भेजे गए एक्शन ---

    // एकल PF फॉर्म स्वीकृत करना
    elseif ($action === 'approve_district' && !empty($id)) {
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'approved_by_district' WHERE id = ?");
        $stmt->execute([$id]);
        $response['success'] = true;
        $response['message'] = 'PF फॉर्म सफलतापूर्वक स्वीकृत किया गया!';
    }
    
    // एकल PF फॉर्म ब्लॉक को वापस भेजना
    elseif ($action === 'send_back_to_block' && !empty($id)) {
        $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'sent_back_to_block' WHERE id = ?");
        $stmt->execute([$id]);
        $response['success'] = true;
        $response['message'] = 'PF फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजा गया!';
    }
    
    else {
        $response['message'] = 'अमान्य कार्रवाई: ' . htmlspecialchars($action);
    }
    
} catch (PDOException $e) {
    // डेटाबेस त्रुटि को लॉग करें
    error_log("PF status update error: " . $e->getMessage());
    // विस्तृत त्रुटि संदेश दिखाएं (केवल डेवलपमेंट के लिए)
    $response['message'] = 'डेटाबेस त्रुटि: ' . $e->getMessage();
} catch (Exception $e) {
    // किसी भी अन्य त्रुटि को लॉग करें
    error_log("General error in update_pf_status: " . $e->getMessage());
    $response['message'] = 'एक त्रुटि हुई। कृपया व्यवस्थापक से संपर्क करें।';
}

// JSON प्रतिक्रिया भेजें
echo json_encode($response);
?>