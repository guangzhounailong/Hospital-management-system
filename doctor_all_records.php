<?php
session_start();
$start_time = microtime(true);
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is doctor
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Get all medical records created by this doctor with patient information
$sql = "SELECT 
            mr.record_id,
            mr.patient_id,
            mr.doctor_id,
            mr.record_date,
            mr.diagnosis,
            mr.treatment_plan,
            mr.vital_signs,
            p.person_id,
            per.name AS patient_name,
            per.phone,
            per.gender,
            per.age,
            pat.blood_type
        FROM medical_record AS mr
        JOIN patient AS p ON mr.patient_id = p.patient_id
        JOIN person AS per ON p.person_id = per.person_id
        LEFT JOIN patient AS pat ON p.patient_id = pat.patient_id
        WHERE mr.doctor_id = '$doctor_id'
        ORDER BY mr.record_date DESC, mr.record_id DESC";

$result = executeTrackedQuery($conn, $sql);

$records = [];

if($result){
    while($row = mysqli_fetch_assoc($result)){
        $record_id = $row['record_id'];
        $patient_id = $row['patient_id'];
        $record_date = $row['record_date'];
        
        // Get prescriptions for this medical record
        $prescription_sql = "SELECT 
                                pr.prescription_id,
                                pm.dosage,
                                pm.usage_statement,
                                pr.validity_period,
                                m.medicine_name,
                                m.type,
                                m.form
                            FROM prescription AS pr
                            LEFT JOIN prescription_medicine AS pm ON pr.prescription_id = pm.prescription_id
                            LEFT JOIN medicine AS m ON pm.medicine_id = m.medicine_id
                            WHERE pr.patient_id = '$patient_id'
                            AND pr.doctor_id = '$doctor_id'
                            AND pr.p_date = '$record_date'";
        
        $prescription_result = executeTrackedQuery($conn, $prescription_sql);
        $prescriptions = [];
        
        if($prescription_result){
            while($presc_row = mysqli_fetch_assoc($prescription_result)){
                if($presc_row['medicine_name']){
                    $prescriptions[] = [
                        'prescription_id' => $presc_row['prescription_id'],
                        'dosage' => $presc_row['dosage'],
                        'usage_statement' => $presc_row['usage_statement'],
                        'validity_period' => $presc_row['validity_period'],
                        'medicine_name' => $presc_row['medicine_name'],
                        'medicine_type' => $presc_row['type'] ?? 'N/A',
                        'medicine_form' => $presc_row['form'] ?? 'N/A'
                    ];
                }
            }
        }
        
        // Format gender
        $gender_display = '';
        switch($row['gender']){
            case 'M': $gender_display = 'Male'; break;
            case 'F': $gender_display = 'Female'; break;
            case 'O': $gender_display = 'Other'; break;
            default: $gender_display = 'Unknown';
        }
        
        $records[] = [
            'record_id' => $row['record_id'],
            'patient_id' => $row['patient_id'],
            'person_id' => $row['person_id'],
            'patient_name' => $row['patient_name'],
            'phone' => $row['phone'],
            'gender' => $gender_display,
            'age' => $row['age'],
            'blood_type' => $row['blood_type'] ?? 'N/A',
            'record_date' => $row['record_date'],
            'diagnosis' => $row['diagnosis'],
            'treatment_plan' => $row['treatment_plan'],
            'vital_signs' => $row['vital_signs'],
            'prescriptions' => $prescriptions,
            'prescription_count' => count($prescriptions)
        ];
    }
}

$end_time = microtime(true);
$running_time = round(($end_time - $start_time) * 1000, 2);

echo json_encode([
    'success' => true,
    'data' => $records,
    'total_records' => count($records),
    'running_time_ms' => $running_time,
    'sql_queries' => getTrackedQueries()
]);

$conn->close();
?>
