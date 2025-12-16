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

// Get department_id from query parameter
$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';

if(empty($department_id)){
    echo json_encode(['success' => false, 'message' => 'Department ID is required']);
    exit();
}

// Get doctors by department
$sql = "SELECT 
            d.doctor_id,
            d.person_id,
            p.name,
            d.specialty,
            d.year_experience,
            d.department_id
        FROM doctor AS d
        JOIN person AS p ON d.person_id = p.person_id
        WHERE d.department_id = '$department_id'
        ORDER BY d.year_experience DESC, p.name ASC";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $doctors = [];
    
    while($row = mysqli_fetch_assoc($result)){
        $doctors[] = [
            'doctor_id' => $row['doctor_id'],
            'person_id' => $row['person_id'],
            'name' => $row['name'],
            'specialty' => $row['specialty'],
            'year_experience' => $row['year_experience'],
            'department_id' => $row['department_id']
        ];
    }
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $doctors,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch doctors',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

