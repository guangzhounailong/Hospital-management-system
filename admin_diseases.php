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
        getAllDiseases($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createDisease($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateDisease($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteDisease($conn, $data['id']);
        break;
}

$conn->close();

function getAllDiseases($conn){
    // Pagination parameters (default: page 1, 100 records per page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, intval($_GET['pageSize']))) : 100;
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    
    // Build WHERE clause for search
    $whereClause = "";
    if($search !== ''){
        $whereClause = "WHERE disease_name LIKE '%$search%' 
                        OR category LIKE '%$search%' 
                        OR description LIKE '%$search%'";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM disease $whereClause";
    $countResult = executeTrackedQuery($conn, $countSql);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalRecords / $pageSize);
    
    // Calculate offset
    $offset = ($page - 1) * $pageSize;
    
    $sql = "SELECT disease_id, disease_name as name, category, description, common_treatments 
            FROM disease 
            $whereClause
            ORDER BY disease_id ASC
            LIMIT $pageSize OFFSET $offset";
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $diseases = [];
        while($row = mysqli_fetch_assoc($result)){
            $diseases[] = $row;
        }
        echo json_encode([
            'success' => true, 
            'data' => $diseases,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalRecords' => intval($totalRecords),
                'totalPages' => intval($totalPages)
            ],
            'sql_queries' => getTrackedQueries()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch diseases']);
    }
}

function createDisease($conn, $data){
    if(empty($data['disease_name'])){
        echo json_encode(['success' => false, 'message' => 'Disease name is required']);
        return;
    }
    
    $max_sql = "SELECT MAX(disease_id) as max_id 
                FROM disease";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $category = isset($data['category']) ? $data['category'] : 'Other';
    $description = isset($data['description']) ? $data['description'] : '';
    $treatments = isset($data['common_treatments']) ? $data['common_treatments'] : '';
    
    $sql = "INSERT INTO disease (disease_id, disease_name, category, description, common_treatments) 
            VALUES ('$new_id', '{$data['disease_name']}', '$category', '$description', '$treatments')";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Disease created successfully', 'id' => $new_id, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create disease']);
    }
}

function updateDisease($conn, $data){
    if(empty($data['disease_id'])){
        echo json_encode(['success' => false, 'message' => 'Disease ID is required']);
        return;
    }
    
    $updates = [];
    if(isset($data['disease_name'])) $updates[] = "disease_name = '{$data['disease_name']}'";
    if(isset($data['category'])) $updates[] = "category = '{$data['category']}'";
    if(isset($data['description'])) $updates[] = "description = '{$data['description']}'";
    if(isset($data['common_treatments'])) $updates[] = "common_treatments = '{$data['common_treatments']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE disease SET " . implode(', ', $updates) . " WHERE disease_id = '{$data['disease_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            echo json_encode(['success' => true, 'message' => 'Disease updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update disease']);
        }
    }
}

function deleteDisease($conn, $id){
    $sql = "DELETE FROM disease 
            WHERE disease_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Disease deleted successfully', 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete disease']);
    }
}
?>


