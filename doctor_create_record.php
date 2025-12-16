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

$person_id = $_SESSION['user_id'];

// Get doctor_id
$doctor_sql = "SELECT doctor_id FROM doctor WHERE person_id = '$person_id'";
$doctor_result = executeTrackedQuery($conn, $doctor_sql);

if(!$doctor_result || mysqli_num_rows($doctor_result) == 0){
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit();
}

$doctor_id = mysqli_fetch_assoc($doctor_result)['doctor_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if(empty($data['patient_id']) || empty($data['diagnosis']) || empty($data['treatment_plan'])){
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$patient_id = $data['patient_id'];
$record_date = isset($data['record_date']) && $data['record_date'] != '' ? $data['record_date'] : date('Y-m-d');
$diagnosis = $data['diagnosis'];
$treatment_plan = $data['treatment_plan'];
$vital_signs = isset($data['vital_signs']) ? $data['vital_signs'] : '';

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Generate new record_id
    $max_record_sql = "SELECT MAX(record_id) AS max_id FROM medical_record";
    $result = executeTrackedQuery($conn, $max_record_sql);
    $row = mysqli_fetch_assoc($result);
    $new_record_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    // Insert medical record
    $record_sql = "INSERT INTO medical_record (record_id, patient_id, doctor_id, record_date, diagnosis, treatment_plan, vital_signs) 
                   VALUES ('$new_record_id', '$patient_id', '$doctor_id', '$record_date', '$diagnosis', '$treatment_plan', '$vital_signs')";
    
    if(!executeTrackedQuery($conn, $record_sql)){
        throw new Exception('Failed to create medical record');
    }
    
    // Handle prescriptions if provided
    if(isset($data['prescriptions']) && is_array($data['prescriptions']) && count($data['prescriptions']) > 0){
        foreach($data['prescriptions'] as $prescription){
            if(empty($prescription['medicine_id']) || empty($prescription['dosage'])){
                continue; // Skip invalid prescriptions
            }
            
            // Generate new prescription_id
            $max_presc_sql = "SELECT MAX(prescription_id) AS max_id FROM prescription";
            $presc_result = executeTrackedQuery($conn, $max_presc_sql);
            $presc_row = mysqli_fetch_assoc($presc_result);
            $new_prescription_id = ($presc_row['max_id'] == null) ? 1 : $presc_row['max_id'] + 1;
            
            $dosage = $prescription['dosage'];
            $usage_statement = isset($prescription['usage_statement']) ? $prescription['usage_statement'] : '';
            $validity_period = isset($prescription['validity_period']) ? $prescription['validity_period'] : 7;
            
            // Insert prescription
            $presc_sql = "INSERT INTO prescription (prescription_id, patient_id, doctor_id, p_date, dosage, usage_statement, validity_period) 
                         VALUES ('$new_prescription_id', '$patient_id', '$doctor_id', '$record_date', '$dosage', '$usage_statement', '$validity_period')";
            
            if(!executeTrackedQuery($conn, $presc_sql)){
                throw new Exception('Failed to create prescription');
            }
            
            // Link prescription to medicine
            $medicine_id = $prescription['medicine_id'];
            $link_sql = "INSERT INTO prescription_medicine (prescription_id, medicine_id) 
                        VALUES ('$new_prescription_id', '$medicine_id')";
            
            if(!executeTrackedQuery($conn, $link_sql)){
                throw new Exception('Failed to link prescription to medicine');
            }
        }
    }
    
    // Update appointment status to Completed if appointment_id provided
    if(isset($data['appointment_id']) && $data['appointment_id'] != ''){
        $appointment_id = $data['appointment_id'];
        $update_apt_sql = "UPDATE appointment SET status = 'Completed' WHERE appointment_id = '$appointment_id'";
        executeTrackedQuery($conn, $update_apt_sql);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'message' => 'Medical record and prescriptions created successfully',
        'record_id' => $new_record_id,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

