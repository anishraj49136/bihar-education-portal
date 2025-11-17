<?php
// =======================================================
// SESSION START (ONLY IF NOT STARTED)
// =======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================================================
// DATABASE CONNECTION
// =======================================================
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "bihar_education";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

// =======================================================
// CHECK IF USER LOGGED IN
// =======================================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// =======================================================
// LOGOUT FUNCTION
// =======================================================
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// =======================================================
// USER TYPE CHECK FUNCTION
// =======================================================
function checkUserType($required_type) {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $required_type) {
        logout();
    }
}

// =======================================================
// STORE SESSION DETAILS
// =======================================================
function setUserSession($data) {
    $_SESSION['user_id']       = $data['id'];
    $_SESSION['username']      = $data['username'];
    $_SESSION['user_type']     = $data['user_type'];
    $_SESSION['name']          = $data['name'];
    $_SESSION['district_id']   = $data['district_id'];
    $_SESSION['block_id']      = $data['block_id'];
    $_SESSION['school_id']     = $data['school_id'];

    // PF Forwarding Rules
    $_SESSION['assigned_category']     = $data['category'] ?? null;
    $_SESSION['assigned_class_group']  = $data['class_group'] ?? null;
}

// =======================================================
// LOGIN FUNCTION (School + Users Table + PF Rules)
// =======================================================
function loginUser($username, $password, $user_type) {
    global $conn;

    // ===================================================
    // 1) SCHOOL LOGIN (ALAG TABLE)
    // ===================================================
    if ($user_type === "school") {
        $q = $conn->prepare("SELECT * FROM schools WHERE school_code = ?");
        $q->execute([$username]);
        $school = $q->fetch(PDO::FETCH_ASSOC);

        if (!$school) return false;
        if ($school['password'] !== $password) return false;

        setUserSession([
            'id'               => $school['id'],
            'username'         => $school['school_code'],
            'user_type'        => 'school',
            'name'             => $school['school_name'],
            'district_id'      => $school['district'],
            'block_id'         => $school['block'],
            'school_id'        => $school['id'],
            'category'         => null,
            'class_group'      => null
        ]);

        return true;
    }

    // ===================================================
    // 2) OTHER USERS LOGIN (DDO, BEO, DTO, ADMIN)
    // ===================================================
    $q = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $q->execute([$username]);
    $user = $q->fetch(PDO::FETCH_ASSOC);

    if (!$user) return false;
    if (!password_verify($password, $user['password'])) return false;
    if ($user['user_type'] !== $user_type) return false;

    // ===================================================
    // 3) FETCH PF FORWARDING RULES (CATEGORY + CLASS GROUP)
    // ===================================================
    $cat = null;
    $class_group = null;

    $rule = $conn->prepare("SELECT category, class_group FROM pf_forwarding_rules WHERE user_id = ?");
    $rule->execute([$user['id']]);
    $r = $rule->fetch(PDO::FETCH_ASSOC);

    if ($r) {
        $cat = $r['category'];
        $class_group = $r['class_group'];
    }

    // SET SESSION
    setUserSession([
        'id'               => $user['id'],
        'username'         => $user['username'],
        'user_type'        => $user['user_type'],
        'name'             => $user['name'],
        'district_id'      => $user['district_id'],
        'block_id'         => $user['block_id'],
        'school_id'        => $user['school_id'],
        'category'         => $cat,
        'class_group'      => $class_group
    ]);

    return true;
}
?>
