<?php
require "../Connection/connection.php";

$student_id = $_POST['student_id'];
$password = $_POST['password'];

/* GET STUDENT BY ID */

$sql = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$sql->bind_param("s", $student_id);
$sql->execute();
$result = $sql->get_result();

if($result->num_rows === 0){
    echo "Invalid Student ID or Password";
    exit;
}

$student = $result->fetch_assoc();

/* VERIFY PASSWORD */

if(password_verify($password, $student['password'])){
    session_start();
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['full_name'] = $student['full_name'];
    $_SESSION['year_level'] = $student['year_level'];
    $_SESSION['section'] = $student['section'];
    
	echo "<script>alert('Login successful!'); window.location='../Views/Studentpage/student_dashboard.php';</script>";
}else{
	echo "<script>alert('Incorrect password or student ID'); window.history.back();</script>";
}

?>