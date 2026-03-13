<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "../../Connection/connection.php";

$event_id = 1; // Change to your test event ID

echo "<h2>Testing Delete Process</h2>";

// Check if tables exist
$tables = ['event_fines', 'attendance_schedule', 'attendance', 'events'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "$table: " . ($result->num_rows > 0 ? "EXISTS" : "NOT FOUND") . "<br>";
}

echo "<hr>";

// Try deleting step by step
echo "Step 1: Delete from event_fines...<br>";
$r1 = $conn->query("DELETE FROM event_fines WHERE event_id = $event_id");
echo "Result: " . ($r1 ? "OK (rows: " . $conn->affected_rows . ")" : "ERROR: " . $conn->error) . "<br>";

echo "Step 2: Delete from attendance_schedule...<br>";
$r2 = $conn->query("DELETE FROM attendance_schedule WHERE event_id = $event_id");
echo "Result: " . ($r2 ? "OK (rows: " . $conn->affected_rows . ")" : "ERROR: " . $conn->error) . "<br>";

echo "Step 3: Delete from attendance...<br>";
$r3 = $conn->query("DELETE FROM attendance WHERE event_id = $event_id");
echo "Result: " . ($r3 ? "OK (rows: " . $conn->affected_rows . ")" : "ERROR: " . $conn->error) . "<br>";

echo "Step 4: Delete from events...<br>";
$r4 = $conn->query("DELETE FROM events WHERE event_id = $event_id");
echo "Result: " . ($r4 ? "OK (rows: " . $conn->affected_rows . ")" : "ERROR: " . $conn->error) . "<br>";

echo "<hr>Done!";
?>