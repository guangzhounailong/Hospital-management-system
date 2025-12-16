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
        if(isset($_GET['id'])){
            getSingleDoctor($conn, $_GET['id']);
        } else {
            getAllDoctors($conn);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createDoctor($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateDoctor($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteDoctor($conn, $data['id']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}

$conn->close();

function getAllDoctors($conn){
    $sql = "SELECT 
                d.doctor_id,
                d.person_id,
                p.name,
                p.phone,
                p.gender,
                p.age,
                d.specialty,
                d.year_experience,
                d.salary,
                d.department_id,
                dept.department_name
            FROM doctor AS d
            JOIN person AS p ON d.person_id = p.person_id
            LEFT JOIN department AS dept ON d.department_id = dept.department_id
            ORDER BY d.doctor_id ASC";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $doctors = [];
        while($row = mysqli_fetch_assoc($result)){
            $doctors[] = [
                'doctor_id' => $row['doctor_id'],
                'person_id' => $row['person_id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'age' => $row['age'],
                'specialty' => $row['specialty'],
                'year_experience' => $row['year_experience'] ?? 0,
                'salary' => $row['salary'] ?? 0,
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'] ?? 'Unassigned'
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $doctors, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch doctors']);
    }
}

function getSingleDoctor($conn, $doctor_id){
    $sql = "SELECT 
                d.doctor_id,
                d.person_id,
                p.name,
                p.phone,
                p.gender,
                p.age,
                d.specialty,
                d.year_experience,
                d.salary,
                d.department_id
            FROM doctor AS d
            JOIN person AS p ON d.person_id = p.person_id
            WHERE d.doctor_id = '$doctor_id'";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0){
        $row = mysqli_fetch_assoc($result);
        echo json_encode(['success' => true, 'data' => $row, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    }
}

function createDoctor($conn, $data){
    if(empty($data['name']) || empty($data['phone']) || empty($data['specialty'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get max IDs
    $max_person_sql = "SELECT MAX(person_id) AS max_id FROM person";
    $result = executeTrackedQuery($conn, $max_person_sql);
    $row = mysqli_fetch_assoc($result);
    $new_person_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $max_doctor_sql = "SELECT MAX(doctor_id) AS max_id FROM doctor";
    $result = executeTrackedQuery($conn, $max_doctor_sql);
    $row = mysqli_fetch_assoc($result);
    $new_doctor_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $password = isset($data['password']) ? $data['password'] : 'doctor123';
    $gender = isset($data['gender']) ? $data['gender'] : 'M';
    $age = isset($data['age']) ? $data['age'] : 30;
    
    // Insert into person table
    $person_sql = "INSERT INTO person (person_id, name, gender, age, phone, password) 
                   VALUES ('$new_person_id', '{$data['name']}', '$gender', '$age', '{$data['phone']}', '$password')";
    
    if(executeTrackedQuery($conn, $person_sql)){
        // Insert into doctor table
        $year_exp = isset($data['year_experience']) ? $data['year_experience'] : 0;
        $salary = isset($data['salary']) ? $data['salary'] : 100000;
        $dept_id = isset($data['department_id']) && $data['department_id'] != '' ? "'{$data['department_id']}'" : "NULL";
        
        $doctor_sql = "INSERT INTO doctor (doctor_id, person_id, specialty, year_experience, salary, department_id) 
                       VALUES ('$new_doctor_id', '$new_person_id', '{$data['specialty']}', '$year_exp', '$salary', $dept_id)";
        
        if(executeTrackedQuery($conn, $doctor_sql)){
            echo json_encode([
                'success' => true,
                'message' => 'Doctor created successfully',
                'doctor_id' => $new_doctor_id
            , 'sql_queries' => getTrackedQueries()]);
        } else {
            executeTrackedQuery($conn, "DELETE FROM person WHERE person_id = '$new_person_id'");
            echo json_encode(['success' => false, 'message' => 'Failed to create doctor record']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create person record']);
    }
}

function updateDoctor($conn, $data){
    if(empty($data['doctor_id'])){
        echo json_encode(['success' => false, 'message' => 'Doctor ID is required']);
        return;
    }
    
    $doctor_id = $data['doctor_id'];
    
    // Get person_id from doctor_id
    $person_sql = "SELECT person_id FROM doctor WHERE doctor_id = '$doctor_id'";
    $result = executeTrackedQuery($conn, $person_sql);
    if(!$result || mysqli_num_rows($result) == 0){
        echo json_encode(['success' => false, 'message' => 'Doctor not found']);
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
        $update_person = "UPDATE person SET " . implode(', ', $person_updates) . " WHERE person_id = '$person_id'";
        executeTrackedQuery($conn, $update_person);
    }
    
    // Update doctor table
    $doctor_updates = [];
    if(isset($data['specialty'])) $doctor_updates[] = "specialty = '{$data['specialty']}'";
    if(isset($data['year_experience'])) $doctor_updates[] = "year_experience = '{$data['year_experience']}'";
    if(isset($data['salary'])) $doctor_updates[] = "salary = '{$data['salary']}'";
    if(isset($data['department_id'])){
        $dept_value = $data['department_id'] != '' ? "'{$data['department_id']}'" : "NULL";
        $doctor_updates[] = "department_id = $dept_value";
    }
    
    if(!empty($doctor_updates)){
        $update_doctor = "UPDATE doctor SET " . implode(', ', $doctor_updates) . " WHERE doctor_id = '$doctor_id'";
        
        if(executeTrackedQuery($conn, $update_doctor)){
            echo json_encode(['success' => true, 'message' => 'Doctor updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update doctor']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes to update', 'sql_queries' => getTrackedQueries()]);
    }
}

function deleteDoctor($conn, $doctor_id){
    if(empty($doctor_id)){
        echo json_encode(['success' => false, 'message' => 'Doctor ID is required']);
        return;
    }
    
    // Get person_id first
    $person_sql = "SELECT person_id FROM doctor WHERE doctor_id = '$doctor_id'";
    $result = executeTrackedQuery($conn, $person_sql);
    
    if($result && mysqli_num_rows($result) > 0){
        $person_id = mysqli_fetch_assoc($result)['person_id'];
        
        // Delete from person table (cascade will handle doctor table)
        $delete_sql = "DELETE FROM person WHERE person_id = '$person_id'";
        
        if(executeTrackedQuery($conn, $delete_sql)){
            echo json_encode(['success' => true, 'message' => 'Doctor deleted successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete doctor']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    }
}
?>
