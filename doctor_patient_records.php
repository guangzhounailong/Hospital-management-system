<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is doctor
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get patient_id from query parameter
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : '';

if(empty($patient_id)){
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit();
}

// Get patient basic information
$patient_sql = "SELECT 
                    p.patient_id,
                    p.person_id,
                    per.name,
                    per.phone,
                    per.gender,
                    per.age,
                    p.height_cm,
                    p.weight_kg,
                    p.blood_type,
                    p.address
                FROM patient AS p
                JOIN person AS per ON p.person_id = per.person_id
                WHERE p.patient_id = '$patient_id'";

$patient_result = executeTrackedQuery($conn, $patient_sql);

if(!$patient_result || mysqli_num_rows($patient_result) == 0){
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

$patient_info = mysqli_fetch_assoc($patient_result);

// Format gender display
$gender_display = '';
switch($patient_info['gender']){
    case 'M': $gender_display = 'Male'; break;
    case 'F': $gender_display = 'Female'; break;
    case 'O': $gender_display = 'Other'; break;
    default: $gender_display = 'Unknown';
}

$patient_data = [
    'patient_id' => $patient_info['patient_id'],
    'person_id' => $patient_info['person_id'],
    'name' => $patient_info['name'],
    'phone' => $patient_info['phone'],
    'gender' => $gender_display,
    'age' => $patient_info['age'],
    'height_cm' => $patient_info['height_cm'] ?? 0,
    'weight_kg' => $patient_info['weight_kg'] ?? 0,
    'blood_type' => $patient_info['blood_type'] ?? 'N/A',
    'address' => $patient_info['address'] ?? 'N/A'
];

// Get all medical records for this patient
$records_sql = "SELECT 
                    mr.record_id,
                    mr.patient_id,
                    mr.doctor_id,
                    mr.record_date,
                    mr.diagnosis,
                    mr.treatment_plan,
                    mr.vital_signs,
                    p.name AS doctor_name,
                    d.specialty
                FROM medical_record AS mr
                JOIN doctor AS d ON mr.doctor_id = d.doctor_id
                JOIN person AS p ON d.person_id = p.person_id
                WHERE mr.patient_id = '$patient_id'
                ORDER BY mr.record_date DESC";

$records_result = executeTrackedQuery($conn, $records_sql);

$medical_records = [];

if($records_result){
    while($row = mysqli_fetch_assoc($records_result)){
        $record_id = $row['record_id'];
        
        // Get prescriptions for this medical record
        $prescription_sql = "SELECT 
                                pr.prescription_id,
                                pm.dosage,
                                pm.usage_statement,
                                pr.validity_period,
                                m.medicine_name
                            FROM prescription AS pr
                            LEFT JOIN prescription_medicine AS pm ON pr.prescription_id = pm.prescription_id
                            LEFT JOIN medicine AS m ON pm.medicine_id = m.medicine_id
                            WHERE pr.patient_id = '$patient_id'
                            AND pr.doctor_id = '{$row['doctor_id']}'
                            AND pr.p_date = '{$row['record_date']}'";
        
        $prescription_result = executeTrackedQuery($conn, $prescription_sql);
        $prescriptions = [];
        
        if($prescription_result){
            while($presc_row = mysqli_fetch_assoc($prescription_result)){
                $prescriptions[] = [
                    'prescription_id' => $presc_row['prescription_id'],
                    'dosage' => $presc_row['dosage'],
                    'usage_statement' => $presc_row['usage_statement'],
                    'validity_period' => $presc_row['validity_period'],
                    'medicineName' => $presc_row['medicine_name'] ?? 'N/A'
                ];
            }
        }
        
        $medical_records[] = [
            'record_id' => $row['record_id'],
            'patient_id' => $row['patient_id'],
            'doctor_id' => $row['doctor_id'],
            'record_date' => $row['record_date'],
            'diagnosis' => $row['diagnosis'],
            'treatment_plan' => $row['treatment_plan'],
            'vital_signs' => $row['vital_signs'],
            'doctorName' => 'Dr. ' . $row['doctor_name'],
            'specialty' => $row['specialty'],
            'prescriptions' => $prescriptions
        ];
    }
}

$end_time = microtime(true);
$running_time = round(($end_time - $start_time) * 1000, 2);

echo json_encode([
    'success' => true,
    'data' => [
        'patient' => $patient_data,
        'medical_records' => $medical_records
    ],
    'running_time_ms' => $running_time
]);

$conn->close();
?>

