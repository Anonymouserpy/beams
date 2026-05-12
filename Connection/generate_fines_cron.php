<?php
// generate_fines_cron.php
if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/connection.php';
}

$query = "CALL generate_event_fines()";
if (mysqli_query($conn, $query)) {
    error_log("Fines generated successfully at " . date('Y-m-d H:i:s'));
} else {
    error_log("Error generating fines: " . mysqli_error($conn));
}
if (isset($conn)) {
    mysqli_close($conn);
}
?>