<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'patient'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$person_id = $_SESSION['user_id'];

// Get patient complete information
$sql = "SELECT 
            p.patient_id,
            p.person_id,
            per.name,
            per.gender,
            per.date_of_birth,
            TIMESTAMPDIFF(YEAR, per.date_of_birth, CURDATE()) AS age,
            per.phone,
            p.height_cm,
            p.weight_kg,
            p.blood_type,
            p.address
        FROM patient AS p
        JOIN person AS per ON p.person_id = per.person_id
        WHERE p.person_id = '$person_id'";

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
    $patient_data = [
        'patient_id' => $row['patient_id'],
        'person_id' => $row['person_id'],
        'name' => $row['name'],
        'gender' => $gender_display,
        'date_of_birth' => $row['date_of_birth'],
        'age' => $row['age'] ?? 0,
        'phone' => $row['phone'],
        'height_cm' => $row['height_cm'] ?? 0,
        'weight_kg' => $row['weight_kg'] ?? 0,
        'blood_type' => $row['blood_type'] ?? 'N/A',
        'address' => $row['address'] ?? 'N/A',
        'displayName' => $row['name'],
        'roleDisplay' => 'Patient',
        'avatar' => 'https://i.pravatar.cc/48?img=' . ($row['patient_id'] % 70)
    ];
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $patient_data,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Patient data not found',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>
