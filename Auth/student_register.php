<?php
require "../Connection/connection.php";

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method";
    exit;
}

// Get POST data
$student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$year_level = isset($_POST['year_level']) ? intval($_POST['year_level']) : 0;
$section = isset($_POST['section']) ? trim($_POST['section']) : '';

// Validation
if (empty($student_id) || empty($full_name) || empty($password) || $year_level === 0 || empty($section)) {
    echo "All fields are required!";
    exit;
}

if (strlen($password) < 6) {
    echo "Password must be at least 6 characters!";
    exit;
}

/* CHECK IF STUDENT ID EXISTS */
$check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
$check->bind_param("s", $student_id);
$check->execute();
$result = $check->get_result();

if($result->num_rows > 0){
    echo "Student ID already exists!";
    exit;
}

/* HASH PASSWORD */
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

/* INSERT STUDENT */
$sql = $conn->prepare("INSERT INTO students
(student_id, full_name, password, year_level, section)
VALUES (?,?,?,?,?)");

$sql->bind_param("sssis",
    $student_id,
    $full_name,
    $hashed_password,
    $year_level,
    $section
);

if($sql->execute()){
    echo "Student Registered Successfully";
} else {
    echo "Registration Failed: " . $conn->error;
}

?>