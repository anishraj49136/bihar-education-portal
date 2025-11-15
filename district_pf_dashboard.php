<?php
session_start();
include('config.php');
include('check_session.php');
include('sidebar_template.php');

// जिला स्टाफ और एडमिन ही देख सकते हैं
if ($_SESSION['role'] != 'district_staff' && $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

 $sql = "SELECT s.school_name, s.udise_code, ps.month, COUNT(ps.id) as total_count,
               SUM(CASE WHEN ps.status = 'Paid' THEN 1 ELSE 0 END) as paid_count
        FROM pf_submissions ps
        JOIN schools s ON ps.school_udise = s.udise_code
        WHERE ps.status = 'Pending at District' OR ps.status = 'Paid'
        GROUP BY s.udise_code, ps.month
        ORDER BY ps.month DESC";

 $result = $conn->query($sql);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">जिला पीएफ डैशबोर्ड</h1>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>विद्यालय का नाम</th>
                    <th>UDISE कोड</th>
                    <th>महीना</th>
                    <th>कुल पीएफ</th>
                    <th>भुगतानित</th>
                    <th>लंबित</th>
                    <th>कार्य</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['udise_code']); ?></td>
                    <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                    <td><?php echo $row['total_count']; ?></td>
                    <td><?php echo $row['paid_count']; ?></td>
                    <td><?php echo $row['total_count'] - $row['paid_count']; ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info update-status" data-udise="<?php echo $row['udise_code']; ?>" data-month="<?php echo $row['month']; ?>">
                            स्थिति अपडेट करें
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- स्थिति अपडेट करने के लिए मोडल -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">भुगतान स्थिति अपडेट करें</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusUpdateForm">
                    <input type="hidden" id="statusUdise" name="udise">
                    <input type="hidden" id="statusMonth" name="month">
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">नई स्थिति चुनें</label>
                        <select class="form-select" id="statusSelect" name="status">
                            <option value="Pending at District">भुगतान की प्रक्रिया में</option>
                            <option value="Paid">भुगतान किया गया</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">अपडेट करें</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.update-status').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('statusUdise').value = this.dataset.udise;
        document.getElementById('statusMonth').value = this.dataset.month;
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    });
});

document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('update_payment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('स्थिति सफलतापूर्वक अपडेट हो गई!');
            location.reload();
        } else {
            alert('त्रुटि: ' + data.message);
        }
    });
});
</script>

<?php include('footer_template.php'); ?>