<?php
session_start();
include('config.php');
include('check_session.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'lock') {
    if ($_SESSION['role'] != 'school') {
        echo json_encode(['success' => false, 'message' => 'अनधिकृत एक्सेस।']);
        exit();
    }

    $school_udise = $_SESSION['udise_code'];
    $month = date('Y-m');
    $locked_by = $_SESSION['user_id']; // मान लीजिए user_id सत्र में है

    // जांचें कि क्या पहले से लॉक है
    $check_sql = "SELECT id FROM monthly_attendance_lock WHERE school_udise = ? AND month = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $school_udise, $month);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'यह महीना पहले ही लॉक हो चुका है।']);
        exit();
    }

    // महीना लॉक करें
    $lock_sql = "INSERT INTO monthly_attendance_lock (school_udise, month, locked_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($lock_sql);
    $stmt->bind_param("ssi", $school_udise, $month, $locked_by);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'महीना लॉक करने में त्रुटि।']);
        exit();
    }

    // स्कूल की जानकारी प्राप्त करें
    $school_sql = "SELECT school_name, block_name FROM schools WHERE udise_code = ?";
    $stmt = $conn->prepare($school_sql);
    $stmt->bind_param("s", $school_udise);
    $stmt->execute();
    $school_data = $stmt->get_result()->fetch_assoc();

    // शिक्षकों को श्रेणी के अनुसार ग्रुप करें
    $teachers_sql = "SELECT category, class, teacher_name, designation FROM teachers WHERE school_udise = ?";
    $stmt = $conn->prepare($teachers_sql);
    $stmt->bind_param("s", $school_udise);
    $stmt->execute();
    $teachers_result = $stmt->get_result();
    $teachers_by_category = [];
    while ($row = $teachers_result->fetch_assoc()) {
        $teachers_by_category[$row['category']][] = $row;
    }

    // पीएफ जेनरेशन के नियम
    $pf_rules = [
        ['BPSC Teacher', ['1-5', '6-8'], '1-5 & 6-8'],
        ['BPSC Teacher', ['9-10', '11-12'], '9-10 & 11-12'],
        ['Head Teacher', ['1-5'], '1-5'],
        ['BPSC Head Master UHS', ['9-12'], '9-12'],
        ['Niyojit Teacher', ['1-5', '6-8', '9-10', '11-12'], 'All Classes'],
        ['Exclusive Teacher', ['1-5', '6-8'], '1-5 & 6-8'],
        ['Exclusive Teacher', ['9-10', '11-12'], '9-10 & 11-12'],
        ['Regular Assistant Teacher', ['1-5', '6-8'], '1-8'],
        ['Regular Assistant Teacher', ['9-10', '11-12'], '9-10 & 11-12']
    ];

    $generated_count = 0;
    foreach ($pf_rules as $rule) {
        $category = $rule[0];
        $classes_to_match = $rule[1];
        $class_group_name = $rule[2];

        if (!isset($teachers_by_category[$category])) {
            continue; // अगर इस श्रेणी का कोई शिक्षक स्कूल में नहीं है
        }

        $teachers_for_pdf = [];
        foreach ($teachers_by_category[$category] as $teacher) {
            // कक्षा को मैच करने का लॉजिक
            $is_match = false;
            if ($class_group_name == 'All Classes') {
                $is_match = true;
            } else {
                foreach ($classes_to_match as $class_range) {
                    if (strpos($teacher['class'], $class_range) !== false) {
                        $is_match = true;
                        break;
                    }
                }
            }
            if ($is_match) {
                $teachers_for_pdf[] = $teacher;
            }
        }

        if (empty($teachers_for_pdf)) {
            continue; // अगर इस नियम के लिए कोई शिक्षक नहीं मिला
        }
        
        // रेफरेंस नंबर जेनरेट करें
        $ref_no = 'PF/' . $school_udise . '/' . $month . '/' . strtoupper(uniqid());

        // PDF जेनरेट करने के लिए फंक्शन कॉल करें
        $pdf_path = generatePdfForCategory($school_data, $teachers_for_pdf, $category, $class_group_name, $month, $ref_no);

        if ($pdf_path) {
            // डेटाबेस में एंट्री करें
            $insert_sql = "INSERT INTO pf_submissions (school_udise, month, category, class_group, reference_number, generated_pdf_path) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssss", $school_udise, $month, $category, $class_group_name, $ref_no, $pdf_path);
            $stmt->execute();
            $generated_count++;
        }
    }

    if ($generated_count > 0) {
        echo json_encode(['success' => true, 'message' => "$generated_count पीएफ सफलतापूर्वक जेनरेट हुए।"]);
    } else {
        // अगर कोई पीएफ नहीं बना, तो लॉक को रोलबैक करें
        $rollback_sql = "DELETE FROM monthly_attendance_lock WHERE school_udise = ? AND month = ?";
        $stmt = $conn->prepare($rollback_sql);
        $stmt->bind_param("ss", $school_udise, $month);
        $stmt->execute();
        echo json_encode(['success' => false, 'message' => 'जेनरेट करने के लिए कोई योग्य शिक्षक नहीं मिला। महीना अनलॉक कर दिया गया।']);
    }
}

// PDF जेनरेट करने वाला फंक्शन
function generatePdfForCategory($school, $teachers, $category, $class_group, $month, $ref_no) {
    require_once('fpdf/fpdf.php'); // FPDF लाइब्रेरी शामिल करें

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // हेडर
    $pdf->Cell(0, 10, 'शिक्षक उपस्थिति विवरणी (पीएफ)', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'विद्यालय का नाम: ' . $school['school_name'], 0, 1);
    $pdf->Cell(0, 8, 'UDISE कोड: ' . $school['school_udise_code'], 0, 1);
    $pdf->Cell(0, 8, 'प्रखंड: ' . $school['block_name'], 0, 1);
    $pdf->Cell(0, 8, 'महीना: ' . date('F Y', strtotime($month . '-01')), 0, 1);
    $pdf->Cell(0, 8, 'पीएफ जनरेट करने की तिथि: ' . date('d-m-Y'), 0, 1);
    $pdf->Cell(0, 8, 'रेफरेंस नंबर: ' . $ref_no, 0, 1);
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'श्रेणी: ' . $category . ' | कक्षा समूह: ' . $class_group, 0, 1, 'C');
    $pdf->Ln(5);

    // टेबल हेडर
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(10, 8, 'S.No.', 1, 0, 'C');
    $pdf->Cell(80, 8, 'शिक्षक का नाम', 1, 0, 'C');
    $pdf->Cell(60, 8, 'पद', 1, 0, 'C');
    $pdf->Cell(40, 8, 'कक्षा', 1, 1, 'C');

    // टेबल डेटा
    $pdf->SetFont('Arial', '', 10);
    $serial_no = 1;
    foreach ($teachers as $teacher) {
        $pdf->Cell(10, 8, $serial_no, 1, 0, 'C');
        $pdf->Cell(80, 8, $teacher['teacher_name'], 1, 0);
        $pdf->Cell(60, 8, $teacher['designation'], 1, 0);
        $pdf->Cell(40, 8, $teacher['class'], 1, 1, 'C');
        $serial_no++;
    }

    // फुटर (घोषणा)
    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 10);
    $declaration_text = "उपरोक्त सभी शिक्षक मेरे विद्यालय में कार्यरत हैं|सभी के द्वारा विद्यालय में समय उपस्थित होकर अपने दायित्वों का निर्वहन इनके द्वारा किया गया है| कोई भी अवैध निकासी नहीं की जा रही| इसमें किसी प्रकार की लापरवाही होने पर मेरे विरुद्ध विभागीय एवं अनुशासनिक कार्रवाई करते हुए अवैध भुगतान की राशि मेरे से वसूल किया जा सकता है|";
    $pdf->MultiCell(0, 8, $declaration_text, 0, 'J');
    
    $pdf->Ln(10);
    $pdf->Cell(100, 8, '', 0, 0);
    $pdf->Cell(0, 8, 'प्रधानाध्यापक का हस्ताक्षर', 0, 1, 'R');
    $pdf->Cell(100, 8, 'ज्ञापांक: ____________ दिनांक: ____________', 0, 0);
    $pdf->Cell(0, 8, '', 0, 1);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, 'प्रतिलिपि:-जिला कार्यक्रम पदाधिकारी स्थापना,वैशाली/संबंधित प्रखंड शिक्षा पदाधिकारी/संबंधित चिन्हित विद्यालय के प्रधानाध्यापक को सूचनार्थ प्रेषित|अनुरोध है कि उपरोक्त शिक्षकों का अनुपस्थिति के अनुसार भुगतान करने की कृपा करें|', 0, 'J');

    // फाइल सेव करें
    $output_dir = 'uploads/pf_generated/';
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0777, true);
    }
    $filename = $ref_no . '.pdf';
    $filepath = $output_dir . $filename;
    $pdf->Output($filepath, 'F');
    
    return $filepath;
}
?>