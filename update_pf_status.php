<?php
// JSON हेडर सेट करें ताकि जावास्क्रिप्ट को सही प्रतिक्रिया मिले
header('Content-Type: application/json');

// कॉन्फिगरेशन फाइल को शामिल करें
require_once 'config.php';

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
                // सुनिश्चित करें कि सभी आईडी इंटीजर हैं
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

        default:
            $response['message'] = 'अमान्य एक्शन।';
            break;
    }

} catch (PDOException $e) {
    // डेटाबेस त्रुटि होने पर
    $response['message'] = 'डेटाबेस त्रुटि: ' . $e->getMessage();
    error_log("PF Status Update Error: " . $e->getMessage());
} catch (Exception $e) {
    // कोई अन्य त्रुटि होने पर
    $response['message'] = $e->getMessage();
    error_log("PF Status Update General Error: " . $e->getMessage());
}

// रिस्पॉन्स को JSON के रूप में आउटपुट करें
echo json_encode($response);
?>