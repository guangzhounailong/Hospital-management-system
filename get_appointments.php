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

// Get all appointments for this patient with doctor and department information
$sql = "SELECT 
            a.appointment_id,
            a.patient_id,
            a.doctor_id,
            a.appointment_date,
            a.time,
            a.symptoms,
            a.status,
            p.name AS doctor_name,
            d.specialty,
            d.department_id,
            dept.department_name
        FROM appointment AS a
        JOIN doctor AS d ON a.doctor_id = d.doctor_id
        JOIN person AS p ON d.person_id = p.person_id
        LEFT JOIN department AS dept ON d.department_id = dept.department_id
        WHERE a.patient_id = '$patient_id'
        ORDER BY a.appointment_date DESC, a.time DESC";

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
            'doctorName' => 'Dr. ' . $row['doctor_name'],
            'doctorAvatar' => 'https://i.pravatar.cc/40?img=' . ($row['doctor_id'] % 70),
            'specialty' => $row['specialty'],
            'department' => $row['department_name'] ?? 'N/A'
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

