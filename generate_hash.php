<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    echo "<h3>आपका पासवर्ड: " . htmlspecialchars($password) . "</h3>";
    echo "<h3>हैश्ड पासवर्ड: " . $hashed_password . "</h3>";
    echo "<p>इस हैश्ड पासवर्ड को कॉपी करके SQL UPDATE क्वेरी में उपयोग करें।</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
</head>
<body>
    <form method="post" action="">
        <label for="password">पासवर्ड दर्ज करें:</label>
        <input type="text" name="password" id="password" required>
        <button type="submit">हैश जेनरेट करें</button>
    </form>
</body>
</html>