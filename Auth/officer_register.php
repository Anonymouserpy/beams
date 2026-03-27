<?php
require "../Connection/connection.php";

// Set header to return JSON
header('Content-Type: application/json');

// Get POST data
$officer_id = $_POST['officer_id'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$password = $_POST['password'] ?? '';
$position = $_POST['position'] ?? '';

// Validation
if (empty($officer_id) || empty($full_name) || empty($password) || empty($position)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required!']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters!']);
    exit;
}

// Check if officer_id already exists
$check = $conn->prepare("SELECT officer_id FROM officers WHERE officer_id = ?");
$check->bind_param("s", $officer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Officer ID already exists!']);
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Insert new officer
$sql = $conn->prepare("INSERT INTO officers (officer_id, full_name, password, position, created_at) VALUES (?, ?, ?, ?, NOW())");
$sql->bind_param("ssss", $officer_id, $full_name, $hashed_password, $position);

// Execute and return JSON response
if ($sql->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Registration Successful! Please login.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $conn->error]);
}
?>