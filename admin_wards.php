<?php
session_start();
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case 'GET':
        getAllWards($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createWard($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateWard($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteWard($conn, $data['id']);
        break;
}

$conn->close();

function getAllWards($conn){
    $sql = "SELECT ward_id, ward_number, ward_type, bed_count, current_occupancy FROM ward ORDER BY ward_id ASC";
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $wards = [];
        while($row = mysqli_fetch_assoc($result)){
            $wards[] = $row;
        }
        $response = [
            'success' => true, 
            'data' => $wards,
            'sql_queries' => getTrackedQueries()
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch wards']);
    }
}

function createWard($conn, $data){
    if(empty($data['ward_number']) || empty($data['bed_count'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $max_sql = "SELECT MAX(ward_id) AS max_id FROM ward";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $ward_type = isset($data['ward_type']) ? $data['ward_type'] : 'General';
    $current_occupancy = isset($data['current_occupancy']) ? $data['current_occupancy'] : 0;
    
    $sql = "INSERT INTO ward (ward_id, ward_number, ward_type, bed_count, current_occupancy) 
            VALUES ('$new_id', '{$data['ward_number']}', '$ward_type', '{$data['bed_count']}', '$current_occupancy')";
    
    if(executeTrackedQuery($conn, $sql)){
        $response = [
            'success' => true, 
            'message' => 'Ward created successfully', 
            'id' => $new_id,
            'sql_queries' => getTrackedQueries()
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create ward']);
    }
}

function updateWard($conn, $data){
    if(empty($data['ward_id'])){
        echo json_encode(['success' => false, 'message' => 'Ward ID is required']);
        return;
    }
    
    $updates = [];
    if(isset($data['ward_number'])) $updates[] = "ward_number = '{$data['ward_number']}'";
    if(isset($data['ward_type'])) $updates[] = "ward_type = '{$data['ward_type']}'";
    if(isset($data['bed_count'])) $updates[] = "bed_count = '{$data['bed_count']}'";
    if(isset($data['current_occupancy'])) $updates[] = "current_occupancy = '{$data['current_occupancy']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE ward SET " . implode(', ', $updates) . " WHERE ward_id = '{$data['ward_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            $response = [
                'success' => true, 
                'message' => 'Ward updated successfully',
                'sql_queries' => getTrackedQueries()
            ];
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update ward']);
        }
    }
}

function deleteWard($conn, $id){
    $sql = "DELETE FROM ward WHERE ward_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        $response = [
            'success' => true, 
            'message' => 'Ward deleted successfully',
            'sql_queries' => getTrackedQueries()
        ];
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete ward']);
    }
}
?>
