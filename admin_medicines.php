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
        getAllMedicines($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createMedicine($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateMedicine($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteMedicine($conn, $data['id']);
        break;
}

$conn->close();

function getAllMedicines($conn){
    // Pagination parameters (default: page 1, 100 records per page)
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['pageSize']) ? max(1, min(100, intval($_GET['pageSize']))) : 100;
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    
    // Build WHERE clause for search
    $whereClause = "";
    if($search !== ''){
        $whereClause = "WHERE medicine_name LIKE '%$search%' 
                        OR manufacturer LIKE '%$search%' 
                        OR type LIKE '%$search%'
                        OR form LIKE '%$search%'";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM medicine $whereClause";
    $countResult = executeTrackedQuery($conn, $countSql);
    $totalRecords = mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalRecords / $pageSize);
    
    // Calculate offset
    $offset = ($page - 1) * $pageSize;
    
    $sql = "SELECT medicine_id, medicine_name AS name, type, form, price, manufacturer, stock 
            FROM medicine 
            $whereClause
            ORDER BY medicine_id ASC
            LIMIT $pageSize OFFSET $offset";
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $medicines = [];
        while($row = mysqli_fetch_assoc($result)){
            $medicines[] = $row;
        }
        echo json_encode([
            'success' => true, 
            'data' => $medicines,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalRecords' => intval($totalRecords),
                'totalPages' => intval($totalPages)
            ],
            'sql_queries' => getTrackedQueries()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch medicines']);
    }
}

function createMedicine($conn, $data){
    if(empty($data['name']) || empty($data['manufacturer'])){
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $max_sql = "SELECT MAX(medicine_id) AS max_id FROM medicine";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $type = isset($data['type']) ? $data['type'] : '';
    $form = isset($data['form']) ? $data['form'] : 'Tablet';
    $price = isset($data['price']) ? $data['price'] : 0;
    $stock = isset($data['stock']) ? $data['stock'] : 0;
    
    $sql = "INSERT INTO medicine (medicine_id, medicine_name, type, form, price, manufacturer, stock) 
            VALUES ('$new_id', '{$data['name']}', '$type', '$form', '$price', '{$data['manufacturer']}', '$stock')";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Medicine created successfully', 'id' => $new_id, 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create medicine']);
    }
}

function updateMedicine($conn, $data){
    if(empty($data['medicine_id'])){
        echo json_encode(['success' => false, 'message' => 'Medicine ID is required']);
        return;
    }
    
    $updates = [];
    if(isset($data['name'])) $updates[] = "medicine_name = '{$data['name']}'";
    if(isset($data['type'])) $updates[] = "type = '{$data['type']}'";
    if(isset($data['form'])) $updates[] = "form = '{$data['form']}'";
    if(isset($data['price'])) $updates[] = "price = '{$data['price']}'";
    if(isset($data['manufacturer'])) $updates[] = "manufacturer = '{$data['manufacturer']}'";
    if(isset($data['stock'])) $updates[] = "stock = '{$data['stock']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE medicine SET " . implode(', ', $updates) . " WHERE medicine_id = '{$data['medicine_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            echo json_encode(['success' => true, 'message' => 'Medicine updated successfully', 'sql_queries' => getTrackedQueries()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update medicine']);
        }
    }
}

function deleteMedicine($conn, $id){
    $sql = "DELETE FROM medicine WHERE medicine_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully', 'sql_queries' => getTrackedQueries()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete medicine']);
    }
}
?>
