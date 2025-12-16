<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is patient
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$person_id = $_SESSION['user_id'];

// Get patient_id
$patient_sql = "SELECT patient_id FROM patient WHERE person_id = '$person_id'";
$patient_result = executeTrackedQuery($conn, $patient_sql);

if(!$patient_result || mysqli_num_rows($patient_result) == 0){
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit();
}

$patient_row = mysqli_fetch_assoc($patient_result);
$patient_id = $patient_row['patient_id'];

// Get all medical records for this patient
$sql = "SELECT 
            mr.record_id,
            mr.patient_id,
            mr.doctor_id,
            mr.record_date,
            mr.diagnosis,
            mr.treatment_plan,
            mr.vital_signs,
            p.name AS doctor_name,
            d.specialty,
            dept.department_name
        FROM medical_record AS mr
        JOIN doctor AS d ON mr.doctor_id = d.doctor_id
        JOIN person AS p ON d.person_id = p.person_id
        LEFT JOIN department AS dept ON d.department_id = dept.department_id
        WHERE mr.patient_id = '$patient_id'
        ORDER BY mr.record_date DESC";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $records = [];
    
    while($row = mysqli_fetch_assoc($result)){
        $record_id = $row['record_id'];
        
        // Get prescriptions for this medical record
        $prescription_sql = "SELECT 
                                p.prescription_id,
                                pm.dosage,
                                pm.usage_statement,
                                p.validity_period,
                                m.medicine_name
                            FROM prescription AS p
                            LEFT JOIN prescription_medicine AS pm ON p.prescription_id = pm.prescription_id
                            LEFT JOIN medicine AS m ON pm.medicine_id = m.medicine_id
                            WHERE p.patient_id = '$patient_id'
                            AND p.doctor_id = '{$row['doctor_id']}'
                            AND p.p_date = '{$row['record_date']}'";
        
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
        
        $records[] = [
            'record_id' => $row['record_id'],
            'patient_id' => $row['patient_id'],
            'doctor_id' => $row['doctor_id'],
            'record_date' => $row['record_date'],
            'diagnosis' => $row['diagnosis'],
            'treatment_plan' => $row['treatment_plan'],
            'vital_signs' => $row['vital_signs'],
            'doctorName' => 'Dr. ' . $row['doctor_name'],
            'doctorAvatar' => 'https://i.pravatar.cc/40?img=' . ($row['doctor_id'] % 70),
            'specialty' => $row['specialty'],
            'department' => $row['department_name'] ?? 'N/A',
            'prescriptions' => $prescriptions
        ];
    }
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch medical records',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

