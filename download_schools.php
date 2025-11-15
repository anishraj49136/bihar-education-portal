<?php
require_once 'config.php';

// जांचें कि उपयोगकर्ता लॉग इन है और वह एडमिन है
checkUserType('admin');

try {
    // विद्यालयों की सूची प्राप्त करें
    $stmt = $conn->query("SELECT s.*, d.name as district_name, b.name as block_name FROM schools s LEFT JOIN districts d ON s.district_id = d.id LEFT JOIN blocks b ON s.block_id = b.id ORDER BY s.name");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV हेडर सेट करें
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="schools_data_' . date('Y-m-d') . '.csv"');
    
    // आउटपुट स्ट्रीम खोलें
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM जोड़ें (विशेष रूप से हिंदी वर्णों के लिए)
    fwrite($output, "\xEF\xBB\xBF");
    
    // हेडर लिखें
    fputcsv($output, [
        'क्र. सं.',
        'विद्यालय का नाम',
        'UDISE कोड',
        'जिला',
        'प्रखंड',
        'क्लस्टर नाम',
        'स्थान प्रकार',
        'पंचायत नाम',
        'गांव नाम',
        'संसदीय क्षेत्र',
        'विधानसभा क्षेत्र',
        'पिनकोड',
        'प्रबंधन प्रकार',
        'विद्यालय श्रेणी',
        'न्यूनतम कक्षा',
        'अधिकतम कक्षा',
        'विद्यालय प्रकार',
        'प्रभारी प्रकार',
        'प्रधानाध्यापक',
        'प्रधानाध्यापक का मोबाइल',
        'उत्तरदाता प्रकार',
        'उत्तरदाता का नाम',
        'उत्तरदाता का मोबाइल',
        'प्रधानाध्यापक का ईमेल',
        'शिक्षा माध्यम',
        'भाषाओं के नाम',
        'परिचालन स्थिति',
        'अक्षांश',
        'देशांतर',
        'अच्छे कमरे',
        'खराब कमरे',
        'क्रियाशील शौचालय',
        'खराब शौचालय',
        'रैंप की सुविधा',
        'क्रियाशील हैंडपंप',
        'समरसेबल',
        'क्रियाशील समरसेबल',
        'खराब समरसेबल',
        'विद्युत कनेक्शन',
        'कंज्यूमर नंबर',
        'क्रियाशील पंखे',
        'अच्छे बेंच डेस्क',
        'खराब बेंच डेस्क',
        'भूमिहीन',
        'अतिरिक्त भूमि',
        'अतिरिक्त भूमि क्षेत्रफल (वर्ग फीट)',
        'आवश्यक कमरे'
    ]);
    
    // डेटा लिखें
    $serial = 1;
    foreach ($schools as $school) {
        fputcsv($output, [
            $serial++,
            $school['name'],
            $school['udise_code'],
            $school['district_name'],
            $school['block_name'],
            $school['cluster_name'],
            $school['location_type'],
            $school['panchayat_name'],
            $school['village_name'],
            $school['parliamentary_name'],
            $school['assembly_name'],
            $school['pincode'],
            $school['management_name'],
            $school['school_category'],
            $school['school_min_class'],
            $school['school_max_class'],
            $school['school_type'],
            $school['incharge_type'],
            $school['head_of_school'],
            $school['head_of_school_number'],
            $school['respondent_type'],
            $school['respondent_name'],
            $school['respondent_number'],
            $school['hos_email'],
            $school['medium_of_instruction'],
            $school['language_names'],
            $school['operational_status'],
            $school['latitude'],
            $school['longitude'],
            $school['good_rooms'],
            $school['bad_rooms'],
            $school['working_toilets'],
            $school['bad_toilets'],
            $school['has_ramp'],
            $school['working_handpumps'],
            $school['has_samrasal'],
            $school['working_samrasal'],
            $school['bad_samrasal'],
            $school['has_electricity'],
            $school['consumer_number'],
            $school['working_fans'],
            $school['good_bench_desks'],
            $school['bad_bench_desks'],
            $school['is_landless'],
            $school['has_extra_land'],
            $school['extra_land_area_sqft'],
            $school['rooms_needed']
        ]);
    }
    
    // आउटपुट स्ट्रीम बंद करें
    fclose($output);
    exit;
} catch (PDOException $e) {
    echo "त्रुटि: " . $e->getMessage();
}
?>