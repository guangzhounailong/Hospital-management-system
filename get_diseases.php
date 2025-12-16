<?php
session_start();
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in (allow both admin and doctor to access)
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Allow admin and doctor to access disease data
if($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$sql = "SELECT disease_id, disease_name as name, category, description, common_treatments 
        FROM disease 
        ORDER BY disease_name ASC";
$result = executeTrackedQuery($conn, $sql);

if($result){
    $diseases = [];
    while($row = mysqli_fetch_assoc($result)){
        $diseases[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $diseases, 'sql_queries' => getTrackedQueries()]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch diseases']);
}

$conn->close();
?>
