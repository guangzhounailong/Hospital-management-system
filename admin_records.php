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
        getAllRecords($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createRecord($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateRecord($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteRecord($conn, $data['id']);
        break;
}

$conn->close();

function getAllRecords($conn){
    // Pagination parameters (default: page 1, 100 records per page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, intval($_GET['pageSize']))) : 100;
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    
    // Build WHERE clause for search
    $whereClause = "";
    if($search !== ''){
        $whereClause = "WHERE p_patient.name LIKE '%$search%' 
                        OR p_doctor.name LIKE '%$search%' 
                        OR mr.diagnosis LIKE '%$search%'
                        OR mr.treatment_plan LIKE '%$search%'";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM medical_record AS mr
                 JOIN patient AS pat ON mr.patient_id = pat.patient_id
                 JOIN person AS p_patient ON pat.person_id = p_patient.person_id
                 JOIN doctor AS doc ON mr.doctor_id = doc.doctor_id
                 JOIN person AS p_doctor ON doc.person_id = p_doctor.person_id
                 $whereClause";
    $countResult = executeTrackedQuery($conn, $countSql);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalRecords / $pageSize);
    
    // Calculate offset
    $offset = ($page - 1) * $pageSize;
    
    $sql = "SELECT 
                mr.record_id,
                mr.patient_id,
                mr.doctor_id,
                mr.record_date,
                mr.diagnosis,
                mr.treatment_plan,
                mr.vital_signs,
                p_patient.name AS patient_name,
                p_doctor.name AS doctor_name,
                (SELECT COUNT(*) FROM prescription pr WHERE pr.patient_id = mr.patient_id AND pr.doctor_id = mr.doctor_id AND pr.p_date = mr.record_date) AS prescription_count
            FROM medical_record AS mr
            JOIN patient AS pat ON mr.patient_id = pat.patient_id
            JOIN person AS p_patient ON pat.person_id = p_patient.person_id
            JOIN doctor AS doc ON mr.doctor_id = doc.doctor_id
            JOIN person AS p_doctor ON doc.person_id = p_doctor.person_id
            $whereClause
            ORDER BY mr.record_date DESC
            LIMIT $pageSize OFFSET $offset";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $records = [];
        while($row = mysqli_fetch_assoc($result)){
            $records[] = [
                'record_id' => $row['record_id'],
                'patient_id' => $row['patient_id'],
                'doctor_id' => $row['doctor_id'],
                'record_date' => $row['record_date'],
                'diagnosis' => $row['diagnosis'],
                'treatment_plan' => $row['treatment_plan'],
                'vital_signs' => $row['vital_signs'],
                'patient_name' => $row['patient_name'],
                'doctor_name' => $row['doctor_name'],
                'prescription_count' => (int)$row['prescription_count']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $records,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalRecords' => intval($totalRecords),
                'totalPages' => intval($totalPages)
            ],
            'sql_queries' => getTrackedQueries()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch medical records']);
    }
}

function createRecord($conn, $data){
    if(empty($data['patient_id']) || empty($data['doctor_id']) || empty($data['record_date']) || empty($data['diagnosis'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $max_sql = "SELECT MAX(record_id) AS max_id FROM medical_record";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $treatment_plan = isset($data['treatment_plan']) ? $data['treatment_plan'] : '';
    $vital_signs = isset($data['vital_signs']) ? $data['vital_signs'] : '';
    
    $sql = "INSERT INTO medical_record (record_id, patient_id, doctor_id, record_date, diagnosis, treatment_plan, vital_signs) 
            VALUES ('$new_id', '{$data['patient_id']}', '{$data['doctor_id']}', '{$data['record_date']}', '{$data['diagnosis']}', '$treatment_plan', '$vital_signs')";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Medical record created successfully', 'id' => $new_id, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create medical record']);
    }
}

function updateRecord($conn, $data){
    if(empty($data['record_id'])){
        echo json_encode(['success' => false, 'message' => 'Record ID is required']);
        return;
    }
    
    $updates = [];
    if(isset($data['patient_id'])) $updates[] = "patient_id = '{$data['patient_id']}'";
    if(isset($data['doctor_id'])) $updates[] = "doctor_id = '{$data['doctor_id']}'";
    if(isset($data['record_date'])) $updates[] = "record_date = '{$data['record_date']}'";
    if(isset($data['diagnosis'])) $updates[] = "diagnosis = '{$data['diagnosis']}'";
    if(isset($data['treatment_plan'])) $updates[] = "treatment_plan = '{$data['treatment_plan']}'";
    if(isset($data['vital_signs'])) $updates[] = "vital_signs = '{$data['vital_signs']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE medical_record SET " . implode(', ', $updates) . " WHERE record_id = '{$data['record_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            echo json_encode(['success' => true, 'message' => 'Medical record updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update medical record']);
        }
    }
}

function deleteRecord($conn, $id){
    $sql = "DELETE FROM medical_record WHERE record_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Medical record deleted successfully', 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete medical record']);
    }
}
?>
