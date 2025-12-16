<?php
session_start();
$start_time = microtime(true);
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is patient
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient'){
    $execution_time = (microtime(true) - $start_time) * 1000;
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'execution_time_ms' => round($execution_time, 2)]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$doctor_id = isset($data['doctor_id']) ? $data['doctor_id'] : '';
$appointment_date = isset($data['appointment_date']) ? $data['appointment_date'] : '';
$time = isset($data['time']) ? $data['time'] : '';
$symptoms = isset($data['symptoms']) ? $data['symptoms'] : '';

// Validate required fields
if(empty($doctor_id) || empty($appointment_date) || empty($time)){
    $execution_time = (microtime(true) - $start_time) * 1000;
    echo json_encode(['success' => false, 'message' => 'Missing required fields', 'execution_time_ms' => round($execution_time, 2)]);
    exit();
}

// Validate appointment date is not in the past
$selected_date = strtotime($appointment_date);
$today = strtotime(date('Y-m-d'));

if($selected_date < $today){
    $execution_time = (microtime(true) - $start_time) * 1000;
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments for past dates', 'execution_time_ms' => round($execution_time, 2)]);
    exit();
}

// Get patient_id from session
$person_id = $_SESSION['user_id'];
$patient_sql = "SELECT patient_id FROM patient WHERE person_id = '$person_id'";
$patient_result = executeTrackedQuery($conn, $patient_sql);

if(!$patient_result || mysqli_num_rows($patient_result) == 0){
    $execution_time = (microtime(true) - $start_time) * 1000;
    echo json_encode(['success' => false, 'message' => 'Patient not found', 'execution_time_ms' => round($execution_time, 2)]);
    exit();
}

$patient_row = mysqli_fetch_assoc($patient_result);
$patient_id = $patient_row['patient_id'];

// Check if time slot is already booked
$check_sql = "SELECT * FROM appointment 
              WHERE doctor_id = '$doctor_id' 
              AND appointment_date = '$appointment_date'
              AND time = '$time'
              AND status != 'Cancelled'";

$check_result = executeTrackedQuery($conn, $check_sql);

if(mysqli_num_rows($check_result) > 0){
    $execution_time = (microtime(true) - $start_time) * 1000;
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked', 'execution_time_ms' => round($execution_time, 2)]);
    exit();
}

// Generate new appointment_id
$max_id_sql = "SELECT MAX(appointment_id) AS max_id FROM appointment";
$max_id_result = executeTrackedQuery($conn, $max_id_sql);
$max_id_row = mysqli_fetch_assoc($max_id_result);
$new_appointment_id = ($max_id_row['max_id'] == null) ? 1 : $max_id_row['max_id'] + 1;

// Insert new appointment
$insert_sql = "INSERT INTO appointment (appointment_id, patient_id, doctor_id, appointment_date, time, symptoms, status)
               VALUES ('$new_appointment_id', '$patient_id', '$doctor_id', '$appointment_date', '$time', '$symptoms', 'Scheduled')";

if(executeTrackedQuery($conn, $insert_sql)){
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => $new_appointment_id,
        'running_time_ms' => $running_time,
        'sql_queries' => getTrackedQueries()
    ]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to book appointment: ' . mysqli_error($conn),
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

