<?php
require_once 'config.php';

try {
    // सभी विद्यालयों के लिए डिफ़ॉल्ट पासवर्ड सेट करें
    $default_password = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE schools SET password = ? WHERE password = '' OR password IS NULL");
    $stmt->execute([$default_password]);
    
    $affected_rows = $stmt->rowCount();
    
    echo "सफलतापूर्वक {$affected_rows} विद्यालयों के लिए डिफ़ॉल्ट पासवर्ड (123456) सेट किया गया है।";
} catch (PDOException $e) {
    echo "त्रुटि: " . $e->getMessage();
}
?>