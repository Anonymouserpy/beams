<?php
require '../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require 'connection.php';

class DashboardServer implements MessageComponentInterface {
    protected $clients;
    protected $db;

    public function __construct($conn) {
        $this->clients = new \SplObjectStorage;
        $this->db = $conn;
        
        if ($this->db->connect_error) {
            die("Database connection failed: " . $this->db->connect_error . "\n");
        }
        
        echo "✓ WebSocket Server started on port 8080\n";
        echo "✓ Database connected\n";
        echo "Waiting for connections...\n\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "✓ New connection! (ID: {$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "← Message from {$from->resourceId}: {$msg}\n";
        
        $data = json_decode($msg, true);
        
        if (!is_array($data) || !isset($data['action'])) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Invalid message format']));
            return;
        }
        
        $action = $data['action'];
        
        switch($action) {
            case 'get_stats':
                $this->sendStats($from);
                break;
            case 'get_students':
                $this->sendStudents($from, $data['yearLevel'] ?? '', $data['section'] ?? '');
                break;
            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;
            default:
                $from->send(json_encode(['type' => 'error', 'message' => 'Unknown action: ' . $action]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "✗ Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "⚠ Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function sendStats($client) {
        try {
            $events = $this->db->query("SELECT COUNT(*) AS total FROM tbl_event")->fetch_assoc();
            $users = $this->db->query("SELECT COUNT(*) AS total FROM tbl_users")->fetch_assoc();
            
            $activity = [];
            $result = $this->db->query("
                SELECT u.fullName, al.status_in_out, al.time, al.status_am_pm
                FROM tbl_attendance_log al
                JOIN tbl_attendance a ON al.AttendanceID = a.AttendanceID
                JOIN tbl_users u ON a.userID = u.userID
                ORDER BY al.attendance_log_ID DESC 
                LIMIT 5
            ");
            
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $time = $row['time'] . ' ' . $row['status_am_pm'];
                    $activity[] = [
                        'user' => $row['fullName'],
                        'status' => $row['status_in_out'],
                        'time' => $time
                    ];
                }
            }
            
            $client->send(json_encode([
                'type' => 'stats',
                'data' => [
                    'events' => (int)($events['total'] ?? 0),
                    'users' => (int)($users['total'] ?? 0)
                ],
                'activity' => $activity
            ]));
            
        } catch (Exception $e) {
            $client->send(json_encode([
                'type' => 'error', 
                'message' => 'Database error: ' . $e->getMessage()
            ]));
        }
    }

    private function sendStudents($client, $yearLevel = '', $section = '') {
        try {
            // Build WHERE clause
            $where = "WHERE u.roleID = 2";
            if ($yearLevel) {
                $yearLevel = $this->db->real_escape_string($yearLevel);
                $where .= " AND u.YearLevel = '$yearLevel'";
            }
            if ($section) {
                $section = $this->db->real_escape_string($section);
                $where .= " AND u.Section = '$section'";
            }
            
            // Get students with their latest status
            $sql = "SELECT u.userID, u.studentID, u.fullName, u.YearLevel, u.Section,
                    (SELECT al.status_in_out 
                     FROM tbl_attendance_log al
                     JOIN tbl_attendance a ON al.AttendanceID = a.AttendanceID
                     WHERE a.userID = u.userID
                     ORDER BY al.attendance_log_ID DESC 
                     LIMIT 1) as status
                    FROM tbl_users u 
                    $where
                    ORDER BY u.fullName";
            
            $result = $this->db->query($sql);
            $students = [];
            
            while ($row = $result->fetch_assoc()) {
                $students[] = [
                    'userID' => $row['userID'],
                    'studentID' => $row['studentID'],
                    'fullName' => $row['fullName'],
                    'YearLevel' => $row['YearLevel'],
                    'Section' => $row['Section'],
                    'status' => $row['status'] ?? null
                ];
            }
            
            // Get stats
            $totalResult = $this->db->query("SELECT COUNT(*) as total FROM tbl_users WHERE roleID = 2")->fetch_assoc();
            $presentResult = $this->db->query("
                SELECT COUNT(DISTINCT a.userID) as count 
                FROM tbl_attendance_log al
                JOIN tbl_attendance a ON al.AttendanceID = a.AttendanceID
                WHERE al.status_in_out = 'IN' 
                AND DATE(al.time) = CURDATE()
            ")->fetch_assoc();
            
            $client->send(json_encode([
                'type' => 'students',
                'data' => [
                    'students' => $students,
                    'stats' => [
                        'totalStudents' => (int)$totalResult['total'],
                        'presentToday' => (int)($presentResult['count'] ?? 0),
                        'absentToday' => (int)$totalResult['total'] - (int)($presentResult['count'] ?? 0),
                        'activeNow' => (int)($presentResult['count'] ?? 0)
                    ]
                ]
            ]));
            
        } catch (Exception $e) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'Students query error: ' . $e->getMessage()
            ]));
        }
    }
    
    // Broadcast to all clients (call this when attendance is recorded)
    public function broadcastAttendanceUpdate($userID, $status) {
        $message = json_encode([
            'type' => 'attendance_update',
            'data' => [
                'userID' => $userID,
                'status' => $status,
                'time' => date('h:i A')
            ]
        ]);
        
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new DashboardServer($conn)
        )
    ),
    8080
);

$server->run();