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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$appointment_id = isset($data['appointment_id']) ? $data['appointment_id'] : '';
$action = isset($data['action']) ? $data['action'] : ''; // 'cancel' or 'modify'

if(empty($appointment_id) || empty($action)){
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Get patient_id from session
$person_id = $_SESSION['user_id'];
$patient_sql = "SELECT patient_id FROM patient WHERE person_id = '$person_id'";
$patient_result = executeTrackedQuery($conn, $patient_sql);
$patient_row = mysqli_fetch_assoc($patient_result);
$patient_id = $patient_row['patient_id'];

// Verify this appointment belongs to the logged-in patient
$verify_sql = "SELECT * FROM appointment WHERE appointment_id = '$appointment_id' AND patient_id = '$patient_id'";
$verify_result = executeTrackedQuery($conn, $verify_sql);

if(mysqli_num_rows($verify_result) == 0){
    echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
    exit();
}

if($action === 'cancel'){
    // Cancel appointment (update status to 'Cancelled')
    $update_sql = "UPDATE appointment SET status = 'Cancelled' WHERE appointment_id = '$appointment_id'";
    
    if(executeTrackedQuery($conn, $update_sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        
        echo json_encode([
            'success' => true,
            'message' => 'Appointment cancelled successfully',
            'running_time_ms' => $running_time,
            'sql_queries' => getTrackedQueries()
        ]);
    }
    else{
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel appointment: ' . mysqli_error($conn),
            'running_time_ms' => $running_time
        ]);
    }
}
else if($action === 'modify'){
    // Modify appointment (update date, time, symptoms)
    $new_date = isset($data['appointment_date']) ? $data['appointment_date'] : '';
    $new_time = isset($data['time']) ? $data['time'] : '';
    $new_symptoms = isset($data['symptoms']) ? $data['symptoms'] : '';
    
    if(empty($new_date) || empty($new_time)){
        echo json_encode(['success' => false, 'message' => 'Date and time are required']);
        exit();
    }
    
    // Validate appointment date is not in the past
    $selected_date = strtotime($new_date);
    $today = strtotime(date('Y-m-d'));
    
    if($selected_date < $today){
        echo json_encode(['success' => false, 'message' => 'Cannot modify to past dates']);
        exit();
    }
    
    // Get doctor_id from existing appointment
    $appointment_row = mysqli_fetch_assoc($verify_result);
    $doctor_id = $appointment_row['doctor_id'];
    
    // Check if new time slot is available (if date or time changed)
    if($new_date != $appointment_row['appointment_date'] || $new_time != $appointment_row['time']){
        $check_sql = "SELECT * FROM appointment 
                      WHERE doctor_id = '$doctor_id' 
                      AND appointment_date = '$new_date'
                      AND time = '$new_time'
                      AND status != 'Cancelled'
                      AND appointment_id != '$appointment_id'";
        
        $check_result = executeTrackedQuery($conn, $check_sql);
        
        if(mysqli_num_rows($check_result) > 0){
            echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
            exit();
        }
    }
    
    // Update appointment
    $update_sql = "UPDATE appointment 
                   SET appointment_date = '$new_date', 
                       time = '$new_time', 
                       symptoms = '$new_symptoms' 
                   WHERE appointment_id = '$appointment_id'";
    
    if(executeTrackedQuery($conn, $update_sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        
        echo json_encode([
            'success' => true,
            'message' => 'Appointment updated successfully',
            'running_time_ms' => $running_time,
            'sql_queries' => getTrackedQueries()
        ]);
    }
    else{
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update appointment: ' . mysqli_error($conn),
            'running_time_ms' => $running_time
        ]);
    }
}
else{
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>

