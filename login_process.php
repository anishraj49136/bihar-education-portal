<?php
// सबसे पहले सत्र शुरू करें
session_start();

require_once 'config.php';

// फॉर्म सबमिशन प्रोसेस करें
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    try {

        /* -----------------------------------------
           1. SCHOOL LOGIN (schools table)
        ----------------------------------------- */
        if ($user_type === 'school') {
            
            $stmt = $conn->prepare("SELECT * FROM schools WHERE udise_code = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['school_id'] = $user['id'];
                $_SESSION['username'] = $user['udise_code'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = 'school';
                
                // जरूरी — PF management के लिए
                $_SESSION['school_udise'] = $user['udise_code'];

                header('Location: school_dashboard.php');
                exit;
            } else {
                $error_message = "अमान्य उपयोगकर्ता नाम या पासवर्ड!";
            }
        }

        /* -----------------------------------------
           2. DDO / BEO / ADMIN / DISTRICT LOGIN (users table)
        ----------------------------------------- */
        else {

            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND user_type = ?");
            $stmt->execute([$username, $user_type]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];

                // -----------------------------------------
                // 🔥 PF FORWARDING RULES ONLY FOR:
                //     - BEO (block_officer)
                //     - DDO (ddo)
                // -----------------------------------------
                if ($user['user_type'] === 'block_officer' || $user['user_type'] === 'ddo') {
                    
                    $rules = $conn->prepare("SELECT * FROM pf_forwarding_rules");
                    $rules->execute();
                    $forwarding_rules = $rules->fetchAll(PDO::FETCH_ASSOC);

                    // Store into session
                    $_SESSION['pf_forwarding_rules'] = $forwarding_rules;
                }

                // यदि विद्यालय उपयोगकर्ता है
                if ($user['user_type'] === 'school' && isset($user['school_id'])) {
                    $_SESSION['school_id'] = $user['school_id'];
                }

                // Redirect according to type
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
                    case 'district_program_officer':
                    case 'district_education_officer':
                        header('Location: district_dashboard.php');
                        break;

                    default:
                        header('Location: index.php');
                        break;
                }

                exit;
            } 
            else {
                $error_message = "अमान्य उपयोगकर्ता नाम या पासवर्ड!";
            }
        }

    } catch (PDOException $e) {
        $error_message = "लॉगिन प्रक्रिया में त्रुटि: " . $e->getMessage();
    }
}


// यदि त्रुटि है तो वापस redirect करें
if (isset($error_message)) {
    $_SESSION['error_message'] = $error_message;
    header('Location: login.php');
    exit;
}
?>
