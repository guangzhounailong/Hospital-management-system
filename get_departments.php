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

// Get all departments
$sql = "SELECT 
            department_id,
            department_name,
            contact,
            location
        FROM department
        ORDER BY department_name ASC";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $departments = [];
    
    while($row = mysqli_fetch_assoc($result)){
        $departments[] = [
            'department_id' => $row['department_id'],
            'name' => $row['department_name'],
            'contact' => $row['contact'],
            'location' => $row['location']
        ];
    }
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch departments',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

