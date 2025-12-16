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

// Get doctor complete information
$sql = "SELECT 
            d.doctor_id,
            d.person_id,
            p.name,
            p.gender,
            p.age,
            p.phone,
            d.specialty,
            d.year_experience,
            d.salary,
            d.department_id,
            dept.department_name
        FROM doctor AS d
        JOIN person AS p ON d.person_id = p.person_id
        LEFT JOIN department AS dept ON d.department_id = dept.department_id
        WHERE d.person_id = '$person_id'";

$result = executeTrackedQuery($conn, $sql);

if($result && mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);
    
    // Format gender display
    $gender_display = '';
    switch($row['gender']){
        case 'M': $gender_display = 'Male'; break;
        case 'F': $gender_display = 'Female'; break;
        case 'O': $gender_display = 'Other'; break;
        default: $gender_display = 'Unknown';
    }
    
    // Prepare response data
    $doctor_data = [
        'doctor_id' => $row['doctor_id'],
        'person_id' => $row['person_id'],
        'name' => $row['name'],
        'gender' => $gender_display,
        'age' => $row['age'],
        'phone' => $row['phone'],
        'specialty' => $row['specialty'],
        'year_experience' => $row['year_experience'] ?? 0,
        'salary' => $row['salary'] ?? 0,
        'department_id' => $row['department_id'],
        'department_name' => $row['department_name'] ?? 'Unassigned',
        'displayName' => 'Dr. ' . $row['name'],
        'roleDisplay' => 'Senior ' . $row['specialty'],
        'avatar' => 'https://i.pravatar.cc/48?img=' . (($row['doctor_id'] % 70) + 20)
    ];
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $doctor_data,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Doctor data not found',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

