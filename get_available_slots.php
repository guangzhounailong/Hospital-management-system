<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get doctor_id and date from query parameters
$doctor_id = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

if(empty($doctor_id) || empty($date)){
    echo json_encode(['success' => false, 'message' => 'Doctor ID and date are required']);
    exit();
}

// Validate date format
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Define all available time slots for a working day
$all_slots = [
    // Morning slots
    '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    // Afternoon slots
    '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30'
];

// Get already booked time slots for this doctor on this date
$sql = "SELECT time 
        FROM appointment 
        WHERE doctor_id = '$doctor_id' 
        AND appointment_date = '$date'
        AND status != 'Cancelled'";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $booked_slots = [];
    
    while($row = mysqli_fetch_assoc($result)){
        // Normalize time format (remove seconds if present)
        $time = $row['time'];
        // Convert "HH:MM:SS" to "HH:MM"
        if(strlen($time) > 5){
            $time = substr($time, 0, 5);
        }
        $booked_slots[] = $time;
    }
    
    // Calculate available slots (all slots minus booked slots)
    $available_slots = array_values(array_diff($all_slots, $booked_slots));
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds
    
    echo json_encode([
        'success' => true,
        'data' => $available_slots,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch available slots: ' . mysqli_error($conn),
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>
