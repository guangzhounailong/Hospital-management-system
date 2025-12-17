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
        // Get all patients or single patient by ID
        if(isset($_GET['id'])){
            getSinglePatient($conn, $_GET['id']);
        } else {
            getAllPatients($conn);
        }
        break;
        
    case 'POST':
        // Create new patient
        $data = json_decode(file_get_contents('php://input'), true);
        createPatient($conn, $data);
        break;
        
    case 'PUT':
        // Update existing patient
        $data = json_decode(file_get_contents('php://input'), true);
        updatePatient($conn, $data);
        break;
        
    case 'DELETE':
        // Delete patient
        $data = json_decode(file_get_contents('php://input'), true);
        deletePatient($conn, $data['id']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}

$conn->close();

// Get all patients with server-side pagination
function getAllPatients($conn){
    // Pagination parameters (default: page 1, 100 records per page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, intval($_GET['pageSize']))) : 100;
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    
    // Build WHERE clause for search
    $whereClause = "";
    if($search !== ''){
        $whereClause = "WHERE per.name LIKE '%$search%' 
                        OR per.phone LIKE '%$search%' 
                        OR p.person_id LIKE '%$search%' 
                        OR p.blood_type LIKE '%$search%'";
    }
    
    // Get total count for pagination info
    $countSql = "SELECT COUNT(*) as total 
                 FROM patient AS p
                 JOIN person AS per ON p.person_id = per.person_id
                 $whereClause";
    $countResult = executeTrackedQuery($conn, $countSql);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalRecords / $pageSize);
    
    // Calculate offset
    $offset = ($page - 1) * $pageSize;
    
    // Main query with LIMIT and OFFSET
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
            JOIN person AS per ON p.person_id = per.person_id
            $whereClause
            ORDER BY p.patient_id ASC
            LIMIT $pageSize OFFSET $offset";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $patients = [];
        while($row = mysqli_fetch_assoc($result)){
            $patients[] = [
                'patient_id' => $row['patient_id'],
                'person_id' => $row['person_id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'gender' => $row['gender'],
                'age' => $row['age'],
                'height_cm' => $row['height_cm'] ?? 0,
                'weight_kg' => $row['weight_kg'] ?? 0,
                'blood_type' => $row['blood_type'] ?? 'N/A',
                'address' => $row['address'] ?? 'N/A'
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $patients,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalRecords' => intval($totalRecords),
                'totalPages' => intval($totalPages)
            ],
            'sql_queries' => getTrackedQueries()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch patients']);
    }
}

// Get single patient by person_id
function getSinglePatient($conn, $person_id){
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
            JOIN person AS per ON p.person_id = per.person_id
            WHERE p.person_id = '$person_id'";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0){
        $row = mysqli_fetch_assoc($result);
        $patient = [
            'patient_id' => $row['patient_id'],
            'person_id' => $row['person_id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'gender' => $row['gender'],
            'age' => $row['age'],
            'height_cm' => $row['height_cm'],
            'weight_kg' => $row['weight_kg'],
            'blood_type' => $row['blood_type'],
            'address' => $row['address']
        ];
        
        echo json_encode(['success' => true, 'data' => $patient, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
    }
}

// Create new patient
function createPatient($conn, $data){
    // Validate required fields
    if(empty($data['name']) || empty($data['phone']) || empty($data['gender']) || empty($data['age'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get max person_id
    $max_person_sql = "SELECT MAX(person_id) AS max_id FROM person";
    $result = executeTrackedQuery($conn, $max_person_sql);
    $row = mysqli_fetch_assoc($result);
    $new_person_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    // Get max patient_id
    $max_patient_sql = "SELECT MAX(patient_id) AS max_id FROM patient";
    $result = executeTrackedQuery($conn, $max_patient_sql);
    $row = mysqli_fetch_assoc($result);
    $new_patient_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    // Default password for new patients
    $password = isset($data['password']) ? $data['password'] : 'patient123';
    
    // Insert into person table
    $person_sql = "INSERT INTO person (person_id, name, gender, age, phone, password) 
                   VALUES ('$new_person_id', '{$data['name']}', '{$data['gender']}', '{$data['age']}', '{$data['phone']}', '$password')";
    
    if(executeTrackedQuery($conn, $person_sql)){
        // Build patient insert SQL dynamically
        $columns = "patient_id, person_id";
        $values = "'$new_patient_id', '$new_person_id'";
        
        if(isset($data['height_cm']) && $data['height_cm'] != ''){
            $columns .= ", height_cm";
            $values .= ", '{$data['height_cm']}'";
        }
        if(isset($data['weight_kg']) && $data['weight_kg'] != ''){
            $columns .= ", weight_kg";
            $values .= ", '{$data['weight_kg']}'";
        }
        if(isset($data['blood_type']) && $data['blood_type'] != ''){
            $columns .= ", blood_type";
            $values .= ", '{$data['blood_type']}'";
        }
        if(isset($data['address']) && $data['address'] != ''){
            $columns .= ", address";
            $values .= ", '{$data['address']}'";
        }
        
        $patient_sql = "INSERT INTO patient ($columns) VALUES ($values)";
        
        if(executeTrackedQuery($conn, $patient_sql)){
            echo json_encode([
                'success' => true,
                'message' => 'Patient created successfully',
                'patient_id' => $new_patient_id,
                'person_id' => $new_person_id
            , 'sql_queries' => getTrackedQueries()]);
        } else {
            // Rollback: delete person record
            executeTrackedQuery($conn, "DELETE FROM person WHERE person_id = '$new_person_id'");
            echo json_encode(['success' => false, 'message' => 'Failed to create patient record']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create person record']);
    }
}

// Update patient
function updatePatient($conn, $data){
    if(empty($data['person_id'])){
        echo json_encode(['success' => false, 'message' => 'Person ID is required']);
        return;
    }
    
    $person_id = $data['person_id'];
    
    // Update person table
    if(isset($data['name']) || isset($data['phone']) || isset($data['gender']) || isset($data['age'])){
        $updates = [];
        if(isset($data['name'])) $updates[] = "name = '{$data['name']}'";
        if(isset($data['phone'])) $updates[] = "phone = '{$data['phone']}'";
        if(isset($data['gender'])) $updates[] = "gender = '{$data['gender']}'";
        if(isset($data['age'])) $updates[] = "age = '{$data['age']}'";
        
        if(!empty($updates)){
            $person_sql = "UPDATE person SET " . implode(', ', $updates) . " WHERE person_id = '$person_id'";
            executeTrackedQuery($conn, $person_sql);
        }
    }
    
    // Update patient table
    $patient_updates = [];
    if(isset($data['height_cm'])) $patient_updates[] = "height_cm = '{$data['height_cm']}'";
    if(isset($data['weight_kg'])) $patient_updates[] = "weight_kg = '{$data['weight_kg']}'";
    if(isset($data['blood_type'])) $patient_updates[] = "blood_type = '{$data['blood_type']}'";
    if(isset($data['address'])) $patient_updates[] = "address = '{$data['address']}'";
    
    if(!empty($patient_updates)){
        $patient_sql = "UPDATE patient SET " . implode(', ', $patient_updates) . " WHERE person_id = '$person_id'";
        
        if(executeTrackedQuery($conn, $patient_sql)){
            echo json_encode(['success' => true, 'message' => 'Patient updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update patient']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes to update', 'sql_queries' => getTrackedQueries()]);
    }
}

// Delete patient
function deletePatient($conn, $person_id){
    if(empty($person_id)){
        echo json_encode(['success' => false, 'message' => 'Person ID is required']);
        return;
    }
    
    // Delete from person table (cascade will handle patient table)
    $sql = "DELETE FROM person WHERE person_id = '$person_id'";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Patient deleted successfully', 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete patient: ' . mysqli_error($conn)]);
    }
}
?>
