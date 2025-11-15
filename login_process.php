<?php
// सबसे पहले सत्र शुरू करें, यह सुनिश्चित करने के लिए कि कोई भी फाइल इसे शामिल करे या न करे, यह हमेशा चले
session_start(); 

require_once 'config.php';

// फॉर्म सबमिशन प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    try {
        if ($user_type === 'school') {
            // विद्यालय लॉगिन - school टेबल से जांच करें
            $stmt = $conn->prepare("SELECT * FROM schools WHERE udise_code = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // सत्र चर सेट करें
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['school_id'] = $user['id'];
                $_SESSION['username'] = $user['udise_code'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = 'school';
                
                // --- यही वह लाइन है जो आपकी समस्या का समाधान करेगी ---
                $_SESSION['school_udise'] = $user['udise_code']; // pf_management.php के लिए यह ज़रूरी है

                // डैशबोर्ड पर रीडायरेक्ट करें
                header('Location: school_dashboard.php');
                exit;
            } else {
                $error_message = "अमान्य उपयोगकर्ता नाम या पासवर्ड!";
            }
        } else {
            // अन्य उपयोगकर्ता प्रकार - users टेबल से जांच करें
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND user_type = ?");
            $stmt->execute([$username, $user_type]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // सत्र चर सेट करें
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // यदि विद्यालय उपयोगकर्ता है, तो school_id भी सेट करें
                if ($user['user_type'] === 'school' && isset($user['school_id'])) {
                    $_SESSION['school_id'] = $user['school_id'];
                }
                
                // उपयोगकर्ता प्रकार के अनुसार डैशबोर्ड पर रीडायरेक्ट करें
                switch ($user['user_type']) {
                    case 'admin':
                        header('Location: admin_dashboard.php');
                        break;
                    case 'ddo':
                        header('Location: ddo_dashboard.php');
                        break;
                    case 'block_officer':
                        header('Location: block_officer_dashboard.php');
                        break;
                    case 'district_staff':
                        header('Location: district_dashboard.php');
                        break;
                    case 'district_program_officer':
                        header('Location: district_dashboard.php');
                        break;
                    case 'district_education_officer':
                        header('Location: district_dashboard.php');
                        break;
                    default:
                        header('Location: index.php');
                        break;
                }
                exit;
            } else {
                $error_message = "अमान्य उपयोगकर्ता नाम या पासवर्ड!";
            }
        }
    } catch (PDOException $e) {
        $error_message = "लॉगिन प्रक्रिया में त्रुटि: " . $e->getMessage();
    }
}

// यदि कोई त्रुटि है, तो लॉगिन पेज पर वापस जाएं
if (isset($error_message)) {
    $_SESSION['error_message'] = $error_message;
    header('Location: login.php');
    exit;
}
?>