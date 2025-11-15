<?php
require_once 'config.php';

// उपयोगकर्ता की जांच करें
checkUserAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['role'])) {
    $targetRole = $_GET['role'];
    $userId = $_SESSION['user_id'];
    
    // जांचें कि उपयोगकर्ता के पास यह रोल है
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.id 
        WHERE ur.user_id = ? AND r.role_name = ?
    ");
    $stmt->execute([$userId, $targetRole]);
    
    if ($stmt->fetchColumn() > 0) {
        // उपयोगकर्ता को नए डैशबोर्ड पर रीडायरेक्ट करें
        $stmt = $conn->prepare("
            SELECT dashboard_url 
            FROM roles 
            WHERE role_name = ?
        ");
        $stmt->execute([$targetRole]);
        $dashboard = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dashboard) {
            header('Location: ' . $dashboard['dashboard_url']);
            exit();
        }
    }
    
    // यदि रोल नहीं मिला, तो डिफ़ॉल्ट पर जाएं
    header('Location: dashboard.php');
    exit();
}

header('Location: dashboard.php');
?>