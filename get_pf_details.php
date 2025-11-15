<?php
session_start();
include('config.php');

 $udise = $_GET['udise'];
 $month = $_GET['month'];

 $sql = "SELECT category, class_group, reference_number, uploaded_pdf_path FROM pf_submissions WHERE school_udise = ? AND month = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("ss", $udise, $month);
 $stmt->execute();
 $pfs = $stmt->get_result();

echo "<ul class='list-group'>";
while($pf = $pfs->fetch_assoc()){
    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
    echo "<span>" . htmlspecialchars($pf['category']) . " (" . htmlspecialchars($pf['class_group']) . ") - Ref: " . htmlspecialchars($pf['reference_number']) . "</span>";
    echo "<a href='" . $pf['uploaded_pdf_path'] . "' target='_blank' class='btn btn-sm btn-primary'>देखें</a>";
    echo "</li>";
}
echo "</ul>";
?>