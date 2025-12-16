<?php
session_start();
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in (allow both admin and doctor to access)
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Allow admin and doctor to access medicine data
if($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$sql = "SELECT medicine_id, medicine_name AS name, type, form, price, manufacturer, stock 
        FROM medicine 
        ORDER BY medicine_name ASC";
$result = executeTrackedQuery($conn, $sql);

if($result){
    $medicines = [];
    while($row = mysqli_fetch_assoc($result)){
        $medicines[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $medicines, 'sql_queries' => getTrackedQueries()]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch medicines']);
}

$conn->close();
?>
