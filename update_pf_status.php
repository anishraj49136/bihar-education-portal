<?php
// सबसे पहले सत्र शुरू करें
session_start();

// यह जांचना आवश्यक है कि अनुरोध का प्रकार क्या है
// अगर यह एक CORS Preflight OPTIONS अनुरोध है, तो उसे अलग से संभालें
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
    // CORS हेडर सेट करें जो ब्राउज़र को बताएंगे कि POST अनुरोध स्वीकार्य है
    header("Access-Control-Allow-Origin: *"); // या अपने डोमेन के लिए विशिष्ट करें
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Credentials: true"); // कुकीज़ के लिए महत्वपूर्ण

    // Preflight अनुरोध के लिए केवल हेडर भेजने की आवश्यकता है, कोई बॉडी नहीं
    http_response_code(200);
    exit(); // स्क्रिप्ट को यहीं रोक दें
}

// अब वास्तविक POST/GET अनुरोधों के लिए आगे बढ़ें
require_once 'config.php';

// JSON हेडर सेट करें ताकि जावास्क्रिप्ट को सही प्रतिक्रिया मिले
header('Content-Type: application/json');

// एक एसोसिएटिव ऐरे रिस्पॉन्स तैयार करें
 $response = [
    'success' => false,
    'message' => 'अमान्य अनुरोध।'
];

try {
    // जांचें कि अनुरोध POST मेथड से आया है या नहीं
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('केवल POST अनुरोध स्वीकार किए जाते हैं।');
    }

    // यहाँ उपयोगकर्ता की जांच करें (यह POST अनुरोध के लिए है)
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        $response['session_expired'] = true;
        $response['message'] = 'आपका सत्र समाप्त हो गया है। कृपया फिर से लॉग इन करें।';
        echo json_encode($response);
        exit;
    }

    // एक्शन प्राप्त करें
    $action = $_POST['action'] ?? '';

    // एक्शन के आधार पर कार्रवाई करें
    switch ($action) {
        case 'forward_district':
            $id = $_POST['id'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'forwarded_to_district' WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                    $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक जिला अधिकारी को भेजा गया।';
                } else {
                    $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'send_back_school':
            $id = $_POST['id'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'sent_back_to_school' WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                    $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक विद्यालय को वापस भेजा गया।';
                } else {
                    $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'forward_multiple_district':
            $ids_string = $_POST['ids'] ?? '';
            if (!empty($ids_string)) {
                $ids = explode(',', $ids_string);
                $ids = array_map('intval', $ids);
                $ids = array_filter($ids);

                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'forwarded_to_district' WHERE id IN ($placeholders)");
                    if ($stmt->execute($ids)) {
                        $response['success'] = true;
                        $response['message'] = 'चयनित पीएफ फॉर्म सफलतापूर्वक जिला अधिकारी को भेजे गए।';
                    } else {
                        $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                    }
                } else {
                    $response['message'] = 'मान्य आईडी प्रदान नहीं की गई।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'approve_district':
            $id = $_POST['id'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'approved_by_district' WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                    $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक स्वीकृत किया गया।';
                } else {
                    $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'send_back_to_block':
            $id = $_POST['id'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'sent_back_to_block' WHERE id = ?");
                if ($stmt->execute([$id])) {
                    $response['success'] = true;
                    $response['message'] = 'पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजा गया।';
                } else {
                    $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'approve_multiple_district':
            $ids_string = $_POST['ids'] ?? '';
            if (!empty($ids_string)) {
                $ids = explode(',', $ids_string);
                $ids = array_map('intval', $ids);
                $ids = array_filter($ids);

                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'approved_by_district' WHERE id IN ($placeholders)");
                    if ($stmt->execute($ids)) {
                        $response['success'] = true;
                        $response['message'] = 'चयनित पीएफ फॉर्म सफलतापूर्वक स्वीकृत किए गए।';
                    } else {
                        $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                    }
                } else {
                    $response['message'] = 'मान्य आईडी प्रदान नहीं की गई।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        case 'send_back_multiple_to_block':
            $ids_string = $_POST['ids'] ?? '';
            if (!empty($ids_string)) {
                $ids = explode(',', $ids_string);
                $ids = array_map('intval', $ids);
                $ids = array_filter($ids);

                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $conn->prepare("UPDATE pf_submissions SET status = 'sent_back_to_block' WHERE id IN ($placeholders)");
                    if ($stmt->execute($ids)) {
                        $response['success'] = true;
                        $response['message'] = 'चयनित पीएफ फॉर्म सफलतापूर्वक ब्लॉक अधिकारी को वापस भेजे गए।';
                    } else {
                        $response['message'] = 'डेटाबेस अपडेट करने में विफल।';
                    }
                } else {
                    $response['message'] = 'मान्य आईडी प्रदान नहीं की गई।';
                }
            } else {
                $response['message'] = 'आईडी प्रदान नहीं की गई।';
            }
            break;

        default:
            $response['message'] = 'अमान्य एक्शन।';
            break;
    }

} catch (PDOException $e) {
    $response['message'] = 'डेटाबेस त्रुटि: ' . $e->getMessage();
    error_log("PF Status Update Error: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("PF Status Update General Error: " . $e->getMessage());
}

// रिस्पॉन्स को JSON के रूप में आउटपुट करें
echo json_encode($response);
?>