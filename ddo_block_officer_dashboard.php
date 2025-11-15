<?php
session_start();
include('config.php');
include('check_session.php');
include('sidebar_template.php');

 $user_role = $_SESSION['role']; // 'ddo' या 'block_officer'
 $pending_status = ($user_role == 'ddo') ? 'Pending at DDO' : 'Pending at Block Officer';

// उन स्कूलों को खोजें जिनके पीएफ इस अधिकारी के पास लंबित हैं
// यह मानता है कि आपके पास स्कूलों को अधिकारियों से जोड़ने का तरीका है, जैसे schools टेबल में ddo_id या block_officer_id
// यहाँ एक सरलीकृत क्वेरी है, आपको इसे अपने डेटाबेस स्ट्रक्चर के अनुसार बदलना होगा
 $sql = "SELECT s.school_name, s.udise_code, ps.month, COUNT(ps.id) as pending_count
        FROM pf_submissions ps
        JOIN schools s ON ps.school_udise = s.udise_code
        WHERE ps.status = ?
        GROUP BY s.udise_code, ps.month
        ORDER BY ps.month DESC";

 $stmt = $conn->prepare($sql);
 $stmt->bind_param("s", $pending_status);
 $stmt->execute();
 $pending_schools = $stmt->get_result();

?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">लंबित पीएफ - <?php echo ucfirst($user_role); ?> डैशबोर्ड</h1>
    </div>

    <form id="forwardForm" action="forward_to_district.php" method="post">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>विद्यालय का नाम</th>
                        <th>UDISE कोड</th>
                        <th>महीना</th>
                        <th>लंबित पीएफ की संख्या</th>
                        <th>कार्य</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($school = $pending_schools->fetch_assoc()): ?>
                    <tr>
                        <td><input type="checkbox" class="schoolCheckbox" name="forward_schools[]" value="<?php echo $school['udise_code'] . '|' . $school['month']; ?>"></td>
                        <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                        <td><?php echo htmlspecialchars($school['udise_code']); ?></td>
                        <td><?php echo date('F Y', strtotime($school['month'] . '-01')); ?></td>
                        <td><?php echo $school['pending_count']; ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info view-pfs" data-udise="<?php echo $school['udise_code']; ?>" data-month="<?php echo $school['month']; ?>">
                                सभी पीएफ देखें
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-success">चयनित को जिला कार्यालय को अग्रसारित करें</button>
    </form>

    <!-- मोडल के लिए HTML -->
    <div class="modal fade" id="pfModal" tabindex="-1" aria-labelledby="pfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pfModalLabel">पीएफ विवरण</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- AJAX द्वारा भरा जाएगा -->
                </div>
            </div>
        </div>
    </div>

</main>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.schoolCheckbox');
    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
});

document.querySelectorAll('.view-pfs').forEach(button => {
    button.addEventListener('click', function() {
        const udise = this.dataset.udise;
        const month = this.dataset.month;
        
        fetch(`get_pf_details.php?udise=${udise}&month=${month}`)
            .then(response => response.text())
            .then(html => {
                document.querySelector('#pfModal .modal-body').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('pfModal'));
                modal.show();
            });
    });
});
</script>

<?php include('footer_template.php'); ?>