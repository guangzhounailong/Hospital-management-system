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
        getAllNurses($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createNurse($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateNurse($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteNurse($conn, $data['id']);
        break;
}

$conn->close();

function getAllNurses($conn){
    $sql = "SELECT 
                n.nurse_id,
                n.person_id,
                p.name,
                p.phone,
                p.gender,
                p.age,
                n.title,
                n.schedule,
                n.department_id,
                d.department_name
            FROM nurse AS n
            JOIN person AS p ON n.person_id = p.person_id
            LEFT JOIN department AS d ON n.department_id = d.department_id
            ORDER BY n.nurse_id ASC";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $nurses = [];
        while($row = mysqli_fetch_assoc($result)){
            $nurses[] = [
                'nurse_id' => $row['nurse_id'],
                'person_id' => $row['person_id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'age' => $row['age'],
                'title' => $row['title'],
                'schedule' => $row['schedule'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'] ?? 'Unassigned'
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $nurses, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch nurses']);
    }
}

function createNurse($conn, $data){
    if(empty($data['name']) || empty($data['phone']) || empty($data['title'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get max IDs
    $max_person_sql = "SELECT MAX(person_id) AS max_id FROM person";
    $result = executeTrackedQuery($conn, $max_person_sql);
    $row = mysqli_fetch_assoc($result);
    $new_person_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $max_nurse_sql = "SELECT MAX(nurse_id) AS max_id FROM nurse";
    $result = executeTrackedQuery($conn, $max_nurse_sql);
    $row = mysqli_fetch_assoc($result);
    $new_nurse_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $password = isset($data['password']) ? $data['password'] : 'nurse123';
    $gender = isset($data['gender']) ? $data['gender'] : 'F';
    $age = isset($data['age']) ? $data['age'] : 25;
    
    // Insert into person table
    $person_sql = "INSERT INTO person (person_id, name, gender, age, phone, password) 
                   VALUES ('$new_person_id', '{$data['name']}', '$gender', '$age', '{$data['phone']}', '$password')";
    
    if(executeTrackedQuery($conn, $person_sql)){
        $schedule = isset($data['schedule']) ? $data['schedule'] : 'Day Shift';
        $dept_id = isset($data['department_id']) && $data['department_id'] != '' ? "'{$data['department_id']}'" : "NULL";
        
        $nurse_sql = "INSERT INTO nurse (nurse_id, person_id, title, schedule, department_id) 
                      VALUES ('$new_nurse_id', '$new_person_id', '{$data['title']}', '$schedule', $dept_id)";
        
        if(executeTrackedQuery($conn, $nurse_sql)){
            echo json_encode(['success' => true, 'message' => 'Nurse created successfully', 'nurse_id' => $new_nurse_id, 'sql_queries' => getTrackedQueries()]);
        } else {
            executeTrackedQuery($conn, "DELETE FROM person WHERE person_id = '$new_person_id'");
            echo json_encode(['success' => false, 'message' => 'Failed to create nurse record']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create person record']);
    }
}

function updateNurse($conn, $data){
    if(empty($data['nurse_id'])){
        echo json_encode(['success' => false, 'message' => 'Nurse ID is required']);
        return;
    }
    
    // Get person_id
    $person_sql = "SELECT person_id FROM nurse WHERE nurse_id = '{$data['nurse_id']}'";
    $result = executeTrackedQuery($conn, $person_sql);
    if(!$result || mysqli_num_rows($result) == 0){
        echo json_encode(['success' => false, 'message' => 'Nurse not found']);
        return;
    }
    $person_id = mysqli_fetch_assoc($result)['person_id'];
    
    // Update person table
    $person_updates = [];
    if(isset($data['name'])) $person_updates[] = "name = '{$data['name']}'";
    if(isset($data['phone'])) $person_updates[] = "phone = '{$data['phone']}'";
    if(isset($data['gender'])) $person_updates[] = "gender = '{$data['gender']}'";
    if(isset($data['age'])) $person_updates[] = "age = '{$data['age']}'";
    
    if(!empty($person_updates)){
        executeTrackedQuery($conn, "UPDATE person SET " . implode(', ', $person_updates) . " WHERE person_id = '$person_id'");
    }
    
    // Update nurse table
    $nurse_updates = [];
    if(isset($data['title'])) $nurse_updates[] = "title = '{$data['title']}'";
    if(isset($data['schedule'])) $nurse_updates[] = "schedule = '{$data['schedule']}'";
    if(isset($data['department_id'])){
        $dept_value = $data['department_id'] != '' ? "'{$data['department_id']}'" : "NULL";
        $nurse_updates[] = "department_id = $dept_value";
    }
    
    if(!empty($nurse_updates)){
        $sql = "UPDATE nurse SET " . implode(', ', $nurse_updates) . " WHERE nurse_id = '{$data['nurse_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            echo json_encode(['success' => true, 'message' => 'Nurse updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update nurse']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes to update', 'sql_queries' => getTrackedQueries()]);
    }
}

function deleteNurse($conn, $nurse_id){
    // Get person_id
    $person_sql = "SELECT person_id FROM nurse WHERE nurse_id = '$nurse_id'";
    $result = executeTrackedQuery($conn, $person_sql);
    
    if($result && mysqli_num_rows($result) > 0){
        $person_id = mysqli_fetch_assoc($result)['person_id'];
        
        // Delete from person table (cascade will handle nurse table)
        if(executeTrackedQuery($conn, "DELETE FROM person WHERE person_id = '$person_id'")){
            echo json_encode(['success' => true, 'message' => 'Nurse deleted successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete nurse']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Nurse not found']);
    }
}
?>
