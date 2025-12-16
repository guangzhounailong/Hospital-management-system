<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'running_time_ms' => $running_time]);
    exit();
}

// Get total patients count
$patients_sql = "SELECT COUNT(*) AS count FROM patient";
$patients_result = executeTrackedQuery($conn, $patients_sql);
$total_patients = mysqli_fetch_assoc($patients_result)['count'];

// Get total doctors count
$doctors_sql = "SELECT COUNT(*) AS count FROM doctor";
$doctors_result = executeTrackedQuery($conn, $doctors_sql);
$total_doctors = mysqli_fetch_assoc($doctors_result)['count'];

// Get total departments count
$departments_sql = "SELECT COUNT(*) AS count FROM department";
$departments_result = executeTrackedQuery($conn, $departments_sql);
$total_departments = mysqli_fetch_assoc($departments_result)['count'];

// Get today's appointments count
$today = date('Y-m-d');
$appointments_sql = "SELECT COUNT(*) AS count FROM appointment WHERE appointment_date = '$today' AND status != 'Cancelled'";
$appointments_result = executeTrackedQuery($conn, $appointments_sql);
$today_appointments = mysqli_fetch_assoc($appointments_result)['count'];

// Get recent appointments
$recent_appointments_sql = "SELECT 
                                a.appointment_id,
                                a.patient_id,
                                a.time,
                                a.status,
                                p.name AS patient_name,
                                d.name AS doctor_name
                            FROM appointment AS a
                            JOIN patient AS pat ON a.patient_id = pat.patient_id
                            JOIN person AS p ON pat.person_id = p.person_id
                            JOIN doctor AS doc ON a.doctor_id = doc.doctor_id
                            JOIN person AS d ON doc.person_id = d.person_id
                            WHERE a.appointment_date >= '$today'
                            ORDER BY a.appointment_date ASC, a.time ASC
                            LIMIT 5";

$recent_result = executeTrackedQuery($conn, $recent_appointments_sql);
$recent_appointments = [];

if($recent_result){
    while($row = mysqli_fetch_assoc($recent_result)){
        $recent_appointments[] = [
            'id' => $row['appointment_id'],
            'patientName' => $row['patient_name'],
            'patientAvatar' => 'https://i.pravatar.cc/40?img=' . ($row['patient_id'] % 70),
            'doctor' => 'Dr. ' . $row['doctor_name'],
            'time' => $row['time'],
            'status' => strtolower($row['status'])
        ];
    }
}

$end_time = microtime(true);
$running_time = round(($end_time - $start_time) * 1000, 2);

echo json_encode([
    'success' => true,
    'data' => [
        'stats' => [
            'totalPatients' => (int)$total_patients,
            'totalDoctors' => (int)$total_doctors,
            'totalDepartments' => (int)$total_departments,
            'todayAppointments' => (int)$today_appointments
        ],
        'recentAppointments' => $recent_appointments
    ],
    'running_time_ms' => $running_time
]);

$conn->close();
?>
