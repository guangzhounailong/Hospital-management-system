<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$person_id = $_SESSION['user_id'];

// Get admin complete information
$sql = "SELECT 
            a.admin_id,
            a.person_id,
            p.name,
            p.gender,
            p.age,
            p.phone
        FROM admin AS a
        JOIN person AS p ON a.person_id = p.person_id
        WHERE a.person_id = '$person_id'";

$result = executeTrackedQuery($conn, $sql);

if($result && mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);
    
    // Prepare response data
    $admin_data = [
        'admin_id' => $row['admin_id'],
        'person_id' => $row['person_id'],
        'name' => $row['name'],
        'displayName' => $row['name'],
        'roleDisplay' => 'Hospital Admin',
        'avatar' => 'https://i.pravatar.cc/48?img=11'
    ];
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $admin_data,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Admin data not found',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>
