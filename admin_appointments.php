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
        getAllAppointments($conn);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        createAppointment($conn, $data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        updateAppointment($conn, $data);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        deleteAppointment($conn, $data['id']);
        break;
}

$conn->close();

function getAllAppointments($conn){
    global $start_time;
    $sql = "SELECT 
                a.appointment_id,
                a.patient_id,
                a.doctor_id,
                a.appointment_date,
                a.time,
                a.symptoms,
                a.status,
                p_patient.name as patient_name,
                p_doctor.name as doctor_name
            FROM appointment a
            JOIN patient AS pat ON a.patient_id = pat.patient_id
            JOIN person AS p_patient ON pat.person_id = p_patient.person_id
            JOIN doctor AS doc ON a.doctor_id = doc.doctor_id
            JOIN person AS p_doctor ON doc.person_id = p_doctor.person_id
            ORDER BY a.appointment_date DESC, a.time DESC";
    
    $result = executeTrackedQuery($conn, $sql);
    
    if($result){
        $appointments = [];
        while($row = mysqli_fetch_assoc($result)){
            $appointments[] = [
                'appointment_id' => $row['appointment_id'],
                'patient_id' => $row['patient_id'],
                'doctor_id' => $row['doctor_id'],
                'appointment_date' => $row['appointment_date'],
                'time' => $row['time'],
                'symptoms' => $row['symptoms'],
                'status' => $row['status'],
                'patient_name' => $row['patient_name'],
                'doctor_name' => $row['doctor_name']
            ];
        }
        
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'data' => $appointments, 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch appointments', 'running_time_ms' => $running_time]);
    }
}

function createAppointment($conn, $data){
    global $start_time;
    if(empty($data['patient_id']) || empty($data['doctor_id']) || empty($data['appointment_date']) || empty($data['time'])){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Missing required fields', 'running_time_ms' => $running_time]);
        return;
    }
    
    // Validate appointment date is not in the past
    $selected_date = strtotime($data['appointment_date']);
    $today = strtotime(date('Y-m-d'));
    
    if($selected_date < $today){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Cannot create appointments for past dates', 'running_time_ms' => $running_time]);
        return;
    }
    
    $max_sql = "SELECT MAX(appointment_id) as max_id FROM appointment";
    $result = executeTrackedQuery($conn, $max_sql);
    $row = mysqli_fetch_assoc($result);
    $new_id = ($row['max_id'] == null) ? 1 : $row['max_id'] + 1;
    
    $symptoms = isset($data['symptoms']) ? $data['symptoms'] : '';
    $status = isset($data['status']) ? $data['status'] : 'Scheduled';
    
    $sql = "INSERT INTO appointment (appointment_id, patient_id, doctor_id, appointment_date, time, symptoms, status) 
            VALUES ('$new_id', '{$data['patient_id']}', '{$data['doctor_id']}', '{$data['appointment_date']}', '{$data['time']}', '$symptoms', '$status')";
    
    if(executeTrackedQuery($conn, $sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'message' => 'Appointment created successfully', 'id' => $new_id, 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to create appointment', 'running_time_ms' => $running_time]);
    }
}

function updateAppointment($conn, $data){
    global $start_time;
    if(empty($data['appointment_id'])){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Appointment ID is required', 'running_time_ms' => $running_time]);
        return;
    }
    
    // Validate appointment date if being updated
    if(isset($data['appointment_date'])){
        $selected_date = strtotime($data['appointment_date']);
        $today = strtotime(date('Y-m-d'));
        
        if($selected_date < $today){
            $end_time = microtime(true);
            $running_time = round(($end_time - $start_time) * 1000, 2);
            echo json_encode(['success' => false, 'message' => 'Cannot update to past dates', 'running_time_ms' => $running_time]);
            return;
        }
    }
    
    $updates = [];
    if(isset($data['patient_id'])) $updates[] = "patient_id = '{$data['patient_id']}'";
    if(isset($data['doctor_id'])) $updates[] = "doctor_id = '{$data['doctor_id']}'";
    if(isset($data['appointment_date'])) $updates[] = "appointment_date = '{$data['appointment_date']}'";
    if(isset($data['time'])) $updates[] = "time = '{$data['time']}'";
    if(isset($data['symptoms'])) $updates[] = "symptoms = '{$data['symptoms']}'";
    if(isset($data['status'])) $updates[] = "status = '{$data['status']}'";
    
    if(!empty($updates)){
        $sql = "UPDATE appointment SET " . implode(', ', $updates) . " WHERE appointment_id = '{$data['appointment_id']}'";
        
        if(executeTrackedQuery($conn, $sql)){
            $end_time = microtime(true);
            $running_time = round(($end_time - $start_time) * 1000, 2);
            echo json_encode(['success' => true, 'message' => 'Appointment updated successfully', 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
        } else {
            $end_time = microtime(true);
            $running_time = round(($end_time - $start_time) * 1000, 2);
            echo json_encode(['success' => false, 'message' => 'Failed to update appointment', 'running_time_ms' => $running_time]);
        }
    }
}

function deleteAppointment($conn, $id){
    global $start_time;
    $sql = "DELETE FROM appointment 
            WHERE appointment_id = '$id'";
    
    if(executeTrackedQuery($conn, $sql)){
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => true, 'message' => 'Appointment deleted successfully', 'running_time_ms' => $running_time, 'sql_queries' => getTrackedQueries()]);
    } else {
        $end_time = microtime(true);
        $running_time = round(($end_time - $start_time) * 1000, 2);
        echo json_encode(['success' => false, 'message' => 'Failed to delete appointment', 'running_time_ms' => $running_time]);
    }
}
?>
