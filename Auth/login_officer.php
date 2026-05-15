<?php
session_start();
require "../Connection/connection.php";

function log_audit($conn, $officer_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    if (is_array($old_data) || is_object($old_data)) {
        $old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($new_data) || is_object($new_data)) {
        $new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);
    }
    
    $query = "INSERT INTO audit_logs (officer_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssss", 
            $officer_id, $action, $table_name, $record_id, $old_data, $new_data, $ip_address, $user_agent
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$officer_id = $_POST['officer_id'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($officer_id) || empty($password)) {
    $_SESSION['error'] = "Please enter both Officer ID and Password";
    log_audit($conn, $officer_id ?: 'unknown', 'LOGIN', 'authentication', $officer_id, null, json_encode(['status' => 'FAILED', 'reason' => 'Missing credentials']));
    header("Location: ../officer_login.php");
    exit();
}

$sql = $conn->prepare("SELECT officer_id, full_name, password, position FROM officers WHERE officer_id = ?");
$sql->bind_param("s", $officer_id);
$sql->execute();
$result = $sql->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid Officer ID or Password";
    log_audit($conn, $officer_id, 'LOGIN', 'authentication', $officer_id, null, json_encode(['status' => 'FAILED', 'reason' => 'Invalid Officer ID']));
    header("Location: ../officer_login.php");
    exit();
}

$officer = $result->fetch_assoc();

if (password_verify($password, $officer['password'])) {
    $_SESSION['officer_id'] = $officer['officer_id'];
    $_SESSION['full_name'] = $officer['full_name'];
    $_SESSION['position'] = $officer['position'];
    $_SESSION['logged_in'] = true;
    
    log_audit($conn, $officer['officer_id'], 'LOGIN', 'authentication', $officer['officer_id'], 
        null, json_encode(['status' => 'SUCCESS', 'full_name' => $officer['full_name'], 'position' => $officer['position']]));
    
    header("Location: ../views/officerpage/officer_dashboard.php?login=success");
    exit();
} else {
    log_audit($conn, $officer_id, 'LOGIN', 'authentication', $officer_id, 
        null, json_encode(['status' => 'FAILED', 'reason' => 'Invalid password', 'officer_name' => $officer['full_name']]));
    header("Location: ../officer_Login.php?error=1");
    exit();
}
?>