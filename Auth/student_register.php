<?php
// Auth/student_register.php

session_start();
require_once "../Connection/connection.php"; // Adjust path if necessary

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

// Get and sanitize inputs
$full_name   = trim($_POST['full_name'] ?? '');
$student_id  = trim($_POST['student_id'] ?? '');
$year_level  = trim($_POST['year_level'] ?? '');
$section     = trim($_POST['section'] ?? '');
$password    = $_POST['password'] ?? '';

// Server-side validation
$errors = [];
if (empty($full_name))   $errors[] = "Full name is required.";
if (empty($student_id))  $errors[] = "Student ID is required.";
if (!in_array($year_level, ['1','2','3','4'])) $errors[] = "Invalid year level.";
if (!in_array($section, ['A','B']))            $errors[] = "Invalid section.";
if (strlen($password) < 6)                     $errors[] = "Password must be at least 6 characters.";

if (!empty($errors)) {
    echo implode("\n", $errors);
    exit;
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 1. Check for duplicate student_id (case‑insensitive, trimmed)
$check_stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
if (!$check_stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo "System error. Please try again later.";
    exit;
}
$check_stmt->bind_param("s", $student_id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo "Student ID already exists. Please use a different ID.";
    $check_stmt->close();
    exit;
}
$check_stmt->close();

// 2. Insert the new student (use try-catch to catch constraint violations)
$insert_stmt = $conn->prepare("INSERT INTO students (full_name, student_id, year_level, section, password) VALUES (?, ?, ?, ?, ?)");
if (!$insert_stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo "System error. Please try again later.";
    exit;
}
$insert_stmt->bind_param("ssiss", $full_name, $student_id, $year_level, $section, $hashed_password);

try {
    if ($insert_stmt->execute()) {
        echo "Successfully registered! Redirecting to login...";
    } else {
        // This should not happen if we caught the exception, but just in case
        error_log("Execute failed: " . $insert_stmt->error);
        echo "Registration failed. Please try again later.";
    }
} catch (mysqli_sql_exception $e) {
    // Log the exact error for debugging
    error_log("Registration exception: " . $e->getMessage());
    // Show a user‑friendly message
    echo "Registration failed. The Student ID may already exist or data is invalid. Please check and try again.";
} finally {
    $insert_stmt->close();
}

$conn->close();
?>