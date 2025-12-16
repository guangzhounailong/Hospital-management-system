<?php
session_start();
include "connectDB.php";

// Get form data
$name = $_POST["name"];
$gender = $_POST["gender"];
$age = $_POST["age"];
$height = isset($_POST["height"]) && $_POST["height"] != "" ? $_POST["height"] : null;
$weight = isset($_POST["weight"]) && $_POST["weight"] != "" ? $_POST["weight"] : null;
$address = isset($_POST["address"]) && $_POST["address"] != "" ? $_POST["address"] : null;
$username = $_POST["username"];
$password = $_POST["password"];
$passwordConfirm = $_POST["passwordConfirm"];

// Validate required fields are not empty
if($name == null || $gender == null || $age == null || $username == null || $password == null || $passwordConfirm == null){
    header("Location: register.html?error=empty");
    exit();
}

// Check if passwords match
if($password != $passwordConfirm){
    header("Location: register.html?error=password_mismatch");
    exit();
}

// Check if username (phone) already exists
$check_sql = "SELECT * FROM person WHERE phone='$username'";
$check_result = executeTrackedQuery($conn, $check_sql);

if(mysqli_num_rows($check_result) > 0){
    header("Location: register.html?error=user_exists");
    exit();
}

// Get max person_id and increment by 1
$max_id_sql = "SELECT MAX(person_id) AS max_id FROM person";
$max_id_result = executeTrackedQuery($conn, $max_id_sql);
$max_id_row = mysqli_fetch_array($max_id_result);
$new_person_id = ($max_id_row['max_id'] == null) ? 1 : $max_id_row['max_id'] + 1;

// Insert new user into person table
$insert_person_sql = "INSERT INTO person (person_id, name, gender, age, phone, password) 
                      VALUES ('$new_person_id', '$name', '$gender', '$age', '$username', '$password')";

if(executeTrackedQuery($conn, $insert_person_sql)){
    // Get max patient_id and increment by 1
    $max_patient_id_sql = "SELECT MAX(patient_id) AS max_id FROM patient";
    $max_patient_id_result = executeTrackedQuery($conn, $max_patient_id_sql);
    $max_patient_id_row = mysqli_fetch_array($max_patient_id_result);
    $new_patient_id = ($max_patient_id_row['max_id'] == null) ? 1 : $max_patient_id_row['max_id'] + 1;
    
    // Add user to patient table with dynamic SQL construction
    $columns = "patient_id, person_id";
    $values = "'$new_patient_id', '$new_person_id'";
    
    if($height != null){
        $columns .= ", height_cm";
        $values .= ", '$height'";
    }
    if($weight != null){
        $columns .= ", weight_kg";
        $values .= ", '$weight'";
    }
    if($address != null){
        $columns .= ", address";
        $values .= ", '$address'";
    }
    
    $insert_patient_sql = "INSERT INTO patient ($columns) VALUES ($values)";
    
    if(executeTrackedQuery($conn, $insert_patient_sql)){
        // Registration successful, set session and redirect
        $_SESSION['user_id'] = $new_person_id;
        $_SESSION['username'] = $username;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = 'patient';
        header("Location: patient.html");
        exit();
    }
    else{
        // Failed to create patient record, delete created person record
        $delete_sql = "DELETE FROM person WHERE person_id='$new_person_id'";
        executeTrackedQuery($conn, $delete_sql);
        header("Location: register.html?error=failed");
        exit();
    }
}
else{
    header("Location: register.html?error=failed");
    exit();
}

$conn->close();
?>

