<?php
session_start();
require "../../Connection/connection.php";

if (!isset($_SESSION['officer_id']) || !isset($_GET['event_id'])) {
    http_response_code(400);
    exit();
}

$event_id = intval($_GET['event_id']);

// Fetch schedule
$schedule = null;
$sched_res = $conn->query("SELECT * FROM attendance_schedule WHERE event_id = $event_id");
if ($sched_res && $sched_res->num_rows > 0) {
    $schedule = $sched_res->fetch_assoc();
}

// Fetch fines
$fines = null;
$fines_res = $conn->query("SELECT * FROM event_fines WHERE event_id = $event_id");
if ($fines_res && $fines_res->num_rows > 0) {
    $fines = $fines_res->fetch_assoc();
}

header('Content-Type: application/json');
echo json_encode(['schedule' => $schedule, 'fines' => $fines]);