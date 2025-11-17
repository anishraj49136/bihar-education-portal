<?php
// यह फ़ाइल दोबारा उपयोग की जाने वाली साइडबार HTML है।
// इसे अन्य पृष्ठों में include किया जा सकता है।
?>
<!-- मोबाइल मेन्यू बटन -->
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<!-- साइडबार -->
<div class="sidebar" id="sidebar">
    <div class="p-4 text-center">
        <h4>बिहार शिक्षा विभाग</h4>
        <p class="mb-0"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?> डैशबोर्ड</p>
    </div>
    <hr class="text-white">
    <ul class="nav flex-column">
        <?php if ($_SESSION['user_type'] === 'school'): ?>
            <li class="nav-item"><a class="nav-link" href="school_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="school_profile.php"><i class="fas fa-school"></i> विद्यालय प्रोफाइल</a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-user-graduate"></i> नामांकन</a></li>
            <li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक विवरण</a></li>
            <li class="nav-item"><a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check"></i> उपस्थिति विवरणी</a></li>
			<li class="nav-item"><a class="nav-link active" href="pf_management.php"><i class="fas fa-file-pdf"></i> पीडीएफ प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
			<li class="nav-item"><a class="nav-link" href="upload_photos.php"><i class="fas fa-money-check-alt"></i> विद्यालय तस्वीर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_complaint.php"><i class="fas fa-exclamation-triangle"></i> वेतन शिकायत</a></li>
			<li class="nav-item"><a class="nav-link" href="letters.php"><i class="fas fa-envelope"></i> पत्र</a></li>
            <li class="nav-item"><a class="nav-link" href="notices.php"><i class="fas fa-bullhorn"></i> नोटिस</a></li>
        <?php elseif ($_SESSION['user_type'] === 'ddo' || $_SESSION['user_type'] === 'block_officer'): ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo $_SESSION['user_type']; ?>_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
			<li class="nav-item"><a class="nav-link active" href="pf_management.php"><i class="fas fa-file-pdf"></i> पीडीएफ प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="view_school_photos.php"><i class="fas fa-chalkboard-teacher"></i> विद्यालय तस्वीर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="letters.php"><i class="fas fa-envelope"></i> पत्र</a></li>
            <li class="nav-item"><a class="nav-link" href="notices.php"><i class="fas fa-bullhorn"></i> नोटिस</a></li>
			<li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
        <?php elseif (in_array($_SESSION['user_type'], ['district_staff', 'district_program_officer', 'district_education_officer'])): ?>
            <li class="nav-item"><a class="nav-link" href="district_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
			<li class="nav-item"><a class="nav-link" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="view_school_photos.php"><i class="fas fa-chalkboard-teacher"></i> विद्यालय तस्वीर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="letters.php"><i class="fas fa-envelope"></i> पत्र</a></li>
            <li class="nav-item"><a class="nav-link" href="notices.php"><i class="fas fa-bullhorn"></i> नोटिस</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_status.php"><i class="fas fa-money-check-alt"></i> वेतन स्थिति</a></li>
        <?php elseif ($_SESSION['user_type'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> डैशबोर्ड</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="fas fa-users-cog"></i> उपयोगकर्ता प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_schools.php"><i class="fas fa-school"></i> विद्यालय प्रबंधन</a></li>
			<li class="nav-item"><a class="nav-link" href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> शिक्षक प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="salary_management.php"><i class="fas fa-money-check-alt"></i> वेतन प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="eshikshakosh_data.php"><i class="fas fa-database"></i> ई-शिक्षकोष डेटा</a></li>
			<li class="nav-item"><a class="nav-link" href="view_school_photos.php"><i class="fas fa-chalkboard-teacher"></i> विद्यालय तस्वीर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_letters.php"><i class="fas fa-envelope"></i> पत्र प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_notices.php"><i class="fas fa-bullhorn"></i> नोटिस प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_sliders.php"><i class="fas fa-images"></i> स्लाइडर प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_categories.php"><i class="fas fa-tags"></i> श्रेणी प्रबंधन</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_months.php"><i class="fas fa-calendar-alt"></i> महीना प्रबंधन</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉग आउट</a></li>
    </ul>
</div>