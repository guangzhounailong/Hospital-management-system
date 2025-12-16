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

// Get doctor_id from person_id
$doctor_sql = "SELECT doctor_id FROM doctor WHERE person_id = '$person_id'";
$doctor_result = executeTrackedQuery($conn, $doctor_sql);

if(!$doctor_result || mysqli_num_rows($doctor_result) == 0){
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit();
}

$doctor_id = mysqli_fetch_assoc($doctor_result)['doctor_id'];

// Get date parameter (default to today)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get appointments for this doctor on specified date
$sql = "SELECT 
            a.appointment_id,
            a.patient_id,
            a.doctor_id,
            a.appointment_date,
            a.time,
            a.symptoms,
            a.status,
            p.name AS patient_name
        FROM appointment AS a
        JOIN patient AS pat ON a.patient_id = pat.patient_id
        JOIN person AS p ON pat.person_id = p.person_id
        WHERE a.doctor_id = '$doctor_id' 
        AND a.appointment_date = '$date'
        AND a.status != 'Cancelled'
        ORDER BY a.time ASC";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $appointments = [];
    
    while($row = mysqli_fetch_assoc($result)){
        $appointments[] = [
            'appointment_id' => $row['appointment_id'],
            'patient_id' => $row['patient_id'],
            'doctor_id' => $row['doctor_id'],
            'appointment_date' => $row['appointment_date'],
            'time' => $row['time'],
            'symptoms' => $row['symptoms'],
            'status' => $row['status'],
            'patientName' => $row['patient_name'],
            'patientAvatar' => 'https://i.pravatar.cc/40?img=' . ($row['patient_id'] % 70)
        ];
    }
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $appointments,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch appointments',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

