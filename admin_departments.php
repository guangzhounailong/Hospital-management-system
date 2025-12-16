<?php
session_start();
$start_time = microtime(true); // Record start time
header('Content-Type: application/json');
include "connectDB.php";

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    $end_time = microtime(true);
    $running_time = round(($end_time - $start_time) * 1000, 2);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'running_time_ms' => $running_time]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case 'GET':
        getAllDepartments($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createDepartment($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateDepartment($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteDepartment($conn, $data['id']);
        break;
}

$conn->close();

function getAllDepartments($conn){
    global $start_time;
    $sql = "SELECT department_id, department_name as name, contact, location 
            FROM department 
            ORDER BY department_id ASC";
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $departments = [];
        while($row = mysqli_fetch_assoc($result)){
            $departments[] = $row;
        }
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'data' => $departments, 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch departments', 'running_time_ms' => $running_time]);
    }
}

function createDepartment($conn, $data){
    global $start_time;
    if(empty($data['name']) || empty($data['contact'])){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Missing required fields', 'running_time_ms' => $running_time]);
        return;
    }
    
    $max_sql = "SELECT MAX(department_id) as max_id 
                FROM department";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $location = isset($data['location']) ? $data['location'] : '';
    
    $sql = "INSERT INTO department (department_id, department_name, contact, location) 
            VALUES ('$new_id', '{$data['name']}', '{$data['contact']}', '$location')";
    
    if(executeTrackedQuery($conn, $sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'message' => 'Department created successfully', 'id' => $new_id, 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to create department', 'running_time_ms' => $running_time]);
    }
}

function updateDepartment($conn, $data){
    global $start_time;
    if(empty($data['department_id'])){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Department ID is required', 'running_time_ms' => $running_time]);
        return;
    }
    
    $updates = [];
    if(isset($data['name'])) $updates[] = "department_name = '{$data['name']}'";
    if(isset($data['contact'])) $updates[] = "contact = '{$data['contact']}'";
    if(isset($data['location'])) $updates[] = "location = '{$data['location']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE department SET " . implode(', ', $updates) . " WHERE department_id = '{$data['department_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            $end_time = microtime(true);
            $running_time = round(($end_time - $start_time) * 1000, 2);
            echo json_encode(['success' => true, 'message' => 'Department updated successfully', 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
        } else {
            $end_time = microtime(true);
            $running_time = round(($end_time - $start_time) * 1000, 2);
            echo json_encode(['success' => false, 'message' => 'Failed to update department', 'running_time_ms' => $running_time]);
        }
    }
}

function deleteDepartment($conn, $id){
    global $start_time;
    $sql = "DELETE FROM department 
            WHERE department_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'message' => 'Department deleted successfully', 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to delete department', 'running_time_ms' => $running_time]);
    }
}
?>
