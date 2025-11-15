<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है या नहीं
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// उपयोगकर्ता प्रकार के आधार पर रीडायरेक्ट करें
switch ($_SESSION['user_type']) {
    case 'school':
        header('Location: school_dashboard.php');
        break;
    case 'ddo':
        header('Location: ddo_dashboard.php');
        break;
    case 'block_officer':
        header('Location: block_officer_dashboard.php');
        break;
    case 'district_staff':
        header('Location: district_staff_dashboard.php');
        break;
    case 'district_program_officer':
        header('Location: district_program_officer_dashboard.php');
        break;
    case 'district_education_officer':
        header('Location: district_education_officer_dashboard.php');
        break;
    case 'admin':
        header('Location: admin_dashboard.php');
        break;
    default:
        // यदि उपयोगकर्ता प्रकार मान्य नहीं है, तो लॉग आउट करें
        logout();
}
?>