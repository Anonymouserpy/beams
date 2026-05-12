<?php
session_start();
require "../Connection/connection.php";

$officer_id = $_POST['officer_id'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($officer_id) || empty($password)) {
    $_SESSION['error'] = "Please enter both Officer ID and Password";
    header("Location: ../officerLogin.php");
    exit();
}

/* GET OFFICER BY ID */
$sql = $conn->prepare("SELECT officer_id, full_name, password, position FROM officers WHERE officer_id = ?");
$sql->bind_param("s", $officer_id);
$sql->execute();
$result = $sql->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid Officer ID or Password";
    header("Location: ../officer_login.php");
    exit();
}

$officer = $result->fetch_assoc();

/* VERIFY PASSWORD */
if (password_verify($password, $officer['password'])) {
    $_SESSION['officer_id'] = $officer['officer_id'];
    $_SESSION['full_name'] = $officer['full_name'];
    $_SESSION['position'] = $officer['position'];
    $_SESSION['logged_in'] = true;
    
    // Redirect to dashboard
    header("Location: ../views/officerpage/officer_dashboard.php?login=success");
    exit();
    
} else {
    header("Location: ../officer_login.php?error=1");
    exit();
}
?>