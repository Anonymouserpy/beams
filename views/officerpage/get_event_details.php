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

// Fetch event details
$event = null;
$stmt = $conn->prepare("SELECT event_id, event_name, event_date, event_type, half_day_period, location, description FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit();
}
$event = $result->fetch_assoc();

// Fetch attendance schedule for this event
$schedule = null;
$stmt2 = $conn->prepare("SELECT * FROM attendance_schedule WHERE event_id = ?");
$stmt2->bind_param("i", $event_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
if ($result2->num_rows > 0) {
    $schedule = $result2->fetch_assoc();
}

// Fetch fine settings for this event
$fines = null;
$stmt3 = $conn->prepare("SELECT * FROM event_fines WHERE event_id = ?");
$stmt3->bind_param("i", $event_id);
$stmt3->execute();
$result3 = $stmt3->get_result();
if ($result3->num_rows > 0) {
    $fines = $result3->fetch_assoc();
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'event_name'        => $event['event_name'],
    'event_date'        => $event['event_date'],
    'event_type'        => $event['event_type'],
    'half_day_period'   => $event['half_day_period'],
    'location'          => $event['location'],
    'description'       => $event['description'],
    'schedule'          => $schedule,
    'fines'             => $fines
]);
?>