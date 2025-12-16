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

// Get all patients (doctors can view all patients for creating records)
$sql = "SELECT 
            p.patient_id,
            p.person_id,
            per.name,
            per.phone,
            per.gender,
            per.age,
            p.height_cm,
            p.weight_kg,
            p.blood_type,
            p.address
        FROM patient AS p
        INNER JOIN person AS per ON p.person_id = per.person_id
        ORDER BY per.name ASC";

$result = executeTrackedQuery($conn, $sql);

if($result){
    $patients = [];
    
    while($row = mysqli_fetch_assoc($result)){
        // Format gender display
        $gender_display = '';
        switch($row['gender']){
            case 'M': $gender_display = 'Male'; break;
            case 'F': $gender_display = 'Female'; break;
            case 'O': $gender_display = 'Other'; break;
            default: $gender_display = 'Unknown';
        }
        
        $patients[] = [
            'patient_id' => $row['patient_id'],
            'person_id' => $row['person_id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'gender' => $gender_display,
            'age' => $row['age'],
            'height_cm' => $row['height_cm'] ?? 0,
            'weight_kg' => $row['weight_kg'] ?? 0,
            'blood_type' => $row['blood_type'] ?? 'N/A',
            'address' => $row['address'] ?? 'N/A'
        ];
    }
    
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'data' => $patients,
        'running_time_ms' => $running_time
    , 'sql_queries' => getTrackedQueries()]);
}
else{
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch patients',
        'running_time_ms' => $running_time
    ]);
}

$conn->close();
?>

