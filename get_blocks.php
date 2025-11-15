<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['district_id'])) {
    $district_id = $_GET['district_id'];
    $stmt = $conn->prepare("SELECT id, name FROM blocks WHERE district_id = ? ORDER BY name");
    $stmt->execute([$district_id]);
    $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($blocks);
} else {
    echo json_encode([]);
}
?>