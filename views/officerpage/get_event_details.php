<?php
session_start();
require "../../Connection/connection.php";

// Auth check
if (!isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get event ID from query string
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

// Fetch attendance schedule for this event
$schedule = null;
$schedule_result = $conn->query("SELECT * FROM attendance_schedule WHERE event_id = $event_id");
if ($schedule_result && $schedule_result->num_rows > 0) {
    $schedule = $schedule_result->fetch_assoc();
}

// Fetch fine settings for this event
$fines = null;
$fines_result = $conn->query("SELECT * FROM event_fines WHERE event_id = $event_id");
if ($fines_result && $fines_result->num_rows > 0) {
    $fines = $fines_result->fetch_assoc();
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'schedule' => $schedule,
    'fines' => $fines
]);
?>