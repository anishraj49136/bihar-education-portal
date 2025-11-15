<?php
// डेटाबेस कनेक्शन
 $host = 'localhost';
 $dbname = 'bihar_education';
 $username = 'root';
 $password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

// सत्र शुरू करें
session_start();

// वैश्विक चर
 $GLOBALS['base_url'] = 'http://localhost/bihar_education';
 $GLOBALS['upload_path'] = $_SERVER['DOCUMENT_ROOT'] . '/bihar_education/uploads/';

// फ़ंक्शन: उपयोगकर्ता की जांच करें
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// फ़ंक्शन: उपयोगकर्ता प्रकार की जांच करें
function checkUserType($required_type) {
    // जांचें कि उपयोगकर्ता लॉग इन है या नहीं
    if (!isset($_SESSION['user_type'])) {
        header('Location: login.php');
        exit;
    }
    
    // यदि आवश्यक उपयोगकर्ता प्रकार 'school' है, तो विशेष जांच करें
    if ($required_type === 'school') {
        if ($_SESSION['user_type'] !== 'school') {
            header('Location: login.php');
            exit;
        }
        
        // जांचें कि school_id सेट है या नहीं
        if (!isset($_SESSION['school_id'])) {
            // सत्र को नष्ट करें और लॉगिन पेज पर रीडायरेक्ट करें
            session_unset();
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } 
    // अन्य उपयोगकर्ता प्रकार के लिए
    else if ($_SESSION['user_type'] !== $required_type) {
        header('Location: login.php');
        exit;
    }
}

// फ़ंक्शन: लॉग आउट
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// फ़ंक्शन: सुरक्षित पासवर्ड हैश बनाएं
function securePassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// फ़ंक्शन: पासवर्ड सत्यापित करें
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// फ़ंक्शन: फ़ाइल अपलोड करें
function uploadFile($file, $folder) {
    $target_dir = $GLOBALS['upload_path'] . $folder . '/';
    
    // यदि निर्देशिका मौजूद नहीं है, तो बनाएं
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($file["name"]);
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $folder . '/' . $file_name;
    } else {
        return false;
    }
}

// फ़ंक्शन: संशोधन लॉग करें
function logModification($table, $recordId, $field, $oldValue, $newValue, $userId) {
    global $conn;
    
    $sql = "INSERT INTO modification_log (table_name, record_id, field_name, old_value, new_value, modified_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$table, $recordId, $field, $oldValue, $newValue, $userId]);
}

// फ़ंक्शन: टिकट नंबर जेनरेट करें
function generateTicketNumber() {
    return 'TKT' . date('Y') . strtoupper(substr(md5(uniqid()), 0, 8));
}

// फ़ंक्शन: विद्यालय लॉगिन की जांच करें
function checkSchoolLogin($username, $password) {
    global $conn;
    
    try {
        // विद्यालय लॉगिन - school टेबल से जांच करें
        $stmt = $conn->prepare("SELECT * FROM schools WHERE udise_code = ?");
        $stmt->execute([$username]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($school) {
            // यदि पासवर्ड हैश नहीं है, तो डिफ़ॉल्ट पासवर्ड के लिए जांच करें
            if (empty($school['password']) || $school['password'] === '') {
                if ($password === '123456') {
                    // पासवर्ड को हैश करके अपडेट करें
                    $hashed_password = securePassword('123456');
                    $update_stmt = $conn->prepare("UPDATE schools SET password = ? WHERE id = ?");
                    $update_stmt->execute([$hashed_password, $school['id']]);
                    
                    // सत्र चर सेट करें
                    $_SESSION['user_id'] = $school['id'];
                    $_SESSION['school_id'] = $school['id'];
                    $_SESSION['username'] = $school['udise_code'];
                    $_SESSION['name'] = $school['name'];
                    $_SESSION['user_type'] = 'school';
                    
                    return true;
                }
            } 
            // हैश किए गए पासवर्ड की जांच करें
            else if (password_verify($password, $school['password'])) {
                // सत्र चर सेट करें
                $_SESSION['user_id'] = $school['id'];
                $_SESSION['school_id'] = $school['id'];
                $_SESSION['username'] = $school['udise_code'];
                $_SESSION['name'] = $school['name'];
                $_SESSION['user_type'] = 'school';
                
                return true;
            }
        }
        
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// फ़ंक्शन: उपयोगकर्ता लॉगिन की जांच करें (users टेबल के लिए)
function checkUserLogin($username, $password, $user_type) {
    global $conn;
    
    try {
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
            
            return true;
        }
        
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

?>