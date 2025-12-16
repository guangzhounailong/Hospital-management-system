<?php
session_start();
include "connectDB.php";

$username = $_POST["username"];
$password = $_POST["password"];

// Query user in person table (using phone as username)
$sql = "SELECT person_id, password, name FROM person WHERE phone='$username'";
$result = executeTrackedQuery($conn, $sql);

if(mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_array($result);
    if($password == $row["password"]){
        // Password correct, determine user role
        $person_id = $row["person_id"];
        $name = $row["name"];
        
        // Check if user is admin
        $admin_sql = "SELECT admin_id FROM admin WHERE person_id='$person_id'";
        $admin_result = executeTrackedQuery($conn, $admin_sql);
        
        if(mysqli_num_rows($admin_result) > 0){
            // Admin login
            $_SESSION['user_id'] = $person_id;
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = 'admin';
            header("Location: admin.html");
            exit();
        }
        
        // Check if user is doctor
        $doctor_sql = "SELECT doctor_id FROM doctor WHERE person_id='$person_id'";
        $doctor_result = executeTrackedQuery($conn, $doctor_sql);
        
        if(mysqli_num_rows($doctor_result) > 0){
            // Doctor login
            $_SESSION['user_id'] = $person_id;
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = 'doctor';
            header("Location: doctor.html");
            exit();
        }
        
        // Check if user is patient
        $patient_sql = "SELECT patient_id FROM patient WHERE person_id='$person_id'";
        $patient_result = executeTrackedQuery($conn, $patient_sql);
        
        if(mysqli_num_rows($patient_result) > 0){
            // Patient login
            $_SESSION['user_id'] = $person_id;
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = 'patient';
            header("Location: patient.html");
            exit();
        }
        
        // User exists but no role assigned
        header("Location: login.html?error=no_role");
    }
    else{
        // Password incorrect
        header("Location: login.html?error=invalid");
    }
}
else{
    // Username not found
    header("Location: login.html?error=invalid");
}

$conn->close();
?>

