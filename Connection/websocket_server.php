<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;

class AttendanceUpdateServer implements MessageComponentInterface
{
    protected $clients;                 // SplObjectStorage of all connections
    protected $students;                // associative array: student_id => [connections]
    protected $loop;
    protected $internalSocket;
    protected $pdo;                     // PDO connection for database and logging

    public function __construct($loop)
    {
        $this->clients = new \SplObjectStorage;
        $this->students = [];
        $this->loop = $loop;
        $this->setupDatabase();
        $this->setupInternalSocket();
    }

    protected function setupDatabase()
    {
        // Replace with your actual database credentials
        $host = 'localhost';
        $dbname = 'beams';
        $user = 'root';
        $pass = '';

        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Database connection established.\n";
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            $this->pdo = null;
        }
    }

    protected function logMessage($message)
    {
        if ($this->pdo) {
            $stmt = $this->pdo->prepare("INSERT INTO websocket_messages (message) VALUES (:msg)");
            $stmt->execute([':msg' => json_encode($message)]);
        }
    }

    protected function setupInternalSocket()
    {
        $internalPort = 8081;
        $this->internalSocket = stream_socket_server('tcp://127.0.0.1:' . $internalPort, $errno, $errstr);
        if (!$this->internalSocket) {
            echo "Failed to create internal socket: $errstr\n";
            return;
        }

        stream_set_blocking($this->internalSocket, false);

        $this->loop->addReadStream($this->internalSocket, function ($socket) {
            $conn = stream_socket_accept($socket);
            if ($conn) {
                $data = fread($conn, 4096);
                fclose($conn);
                $this->handleInternalMessage($data);
            }
        });

        echo "Internal TCP socket listening on 127.0.0.1:$internalPort\n";
    }

    protected function handleInternalMessage($data)
    {
        $message = json_decode($data, true);
        if (!$message) return;

        $this->logMessage($message);

        if (isset($message['student_id'])) {
            $this->sendToStudent($message['student_id'], json_encode($message));
        } else {
            $this->broadcastToAll(json_encode($message));
        }
    }

    protected function sendToStudent($studentId, $message)
    {
        if (isset($this->students[$studentId])) {
            foreach ($this->students[$studentId] as $conn) {
                $conn->send($message);
            }
        }
    }

    protected function broadcastToAll($message)
    {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    protected function sendToClient(ConnectionInterface $client, $data)
    {
        $client->send(json_encode($data));
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $conn->studentId = null;
        echo "New WebSocket connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        $this->logMessage(['incoming' => $data]);

        switch ($data['type'] ?? '') {

            // ---------- ATTENDANCE CRUD ----------
            case 'CREATE_ATTENDANCE':
                // Insert a new attendance record
                if (isset($data['payload']['student_id'], $data['payload']['event_id'])) {
                    $studentId = $data['payload']['student_id'];
                    $eventId = $data['payload']['event_id'];
                    // Optional fields
                    $amLogin = $data['payload']['am_login_time'] ?? null;
                    $amLogout = $data['payload']['am_logout_time'] ?? null;
                    $pmLogin = $data['payload']['pm_login_time'] ?? null;
                    $pmLogout = $data['payload']['pm_logout_time'] ?? null;

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("
                                INSERT INTO attendance (student_id, event_id, am_login_time, am_logout_time, pm_login_time, pm_logout_time)
                                VALUES (:sid, :eid, :amlogin, :amlogout, :pmlogin, :pmlogout)
                            ");
                            $stmt->execute([
                                ':sid' => $studentId,
                                ':eid' => $eventId,
                                ':amlogin' => $amLogin,
                                ':amlogout' => $amLogout,
                                ':pmlogin' => $pmLogin,
                                ':pmlogout' => $pmLogout
                            ]);
                            $attendanceId = $this->pdo->lastInsertId();

                            // Notify the student (and maybe broadcast)
                            $response = [
                                'type' => 'ATTENDANCE_CREATED',
                                'payload' => [
                                    'attendance_id' => $attendanceId,
                                    'student_id' => $studentId,
                                    'event_id' => $eventId,
                                    'am_login_time' => $amLogin,
                                    'am_logout_time' => $amLogout,
                                    'pm_login_time' => $pmLogin,
                                    'pm_logout_time' => $pmLogout
                                ]
                            ];
                            $this->sendToStudent($studentId, json_encode($response));
                            // Optionally broadcast to all (e.g., admin panel)
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Attendance created: ID $attendanceId for student $studentId\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing student_id or event_id']);
                }
                break;

            case 'READ_ATTENDANCE':
                // Return attendance records, optionally filtered by student_id or event_id
                $filters = [];
                $params = [];
                if (isset($data['payload']['student_id'])) {
                    $filters[] = "student_id = :sid";
                    $params[':sid'] = $data['payload']['student_id'];
                }
                if (isset($data['payload']['event_id'])) {
                    $filters[] = "event_id = :eid";
                    $params[':eid'] = $data['payload']['event_id'];
                }
                if (isset($data['payload']['attendance_id'])) {
                    $filters[] = "attendance_id = :aid";
                    $params[':aid'] = $data['payload']['attendance_id'];
                }

                $sql = "SELECT * FROM attendance";
                if (!empty($filters)) {
                    $sql .= " WHERE " . implode(' AND ', $filters);
                }

                if ($this->pdo) {
                    try {
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $this->sendToClient($from, ['type' => 'ATTENDANCE_READ', 'payload' => $rows]);
                    } catch (PDOException $e) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                }
                break;

            case 'UPDATE_ATTENDANCE':
                // Update specific fields (already present, but we keep it)
                if (isset($data['payload']['attendance_id'], $data['payload']['field'], $data['payload']['value'])) {
                    $attendanceId = $data['payload']['attendance_id'];
                    $field = $data['payload']['field'];
                    $value = $data['payload']['value'];

                    $allowedFields = ['am_login_time', 'am_logout_time', 'pm_login_time', 'pm_logout_time'];
                    if (!in_array($field, $allowedFields)) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => "Invalid field: $field"]);
                        break;
                    }

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("UPDATE attendance SET $field = :value WHERE attendance_id = :id");
                            $stmt->execute([':value' => $value, ':id' => $attendanceId]);

                            // Fetch student_id for notification
                            $stmt2 = $this->pdo->prepare("SELECT student_id FROM attendance WHERE attendance_id = ?");
                            $stmt2->execute([$attendanceId]);
                            $studentId = $stmt2->fetchColumn();

                            $response = [
                                'type' => 'ATTENDANCE_UPDATED',
                                'payload' => [
                                    'attendance_id' => $attendanceId,
                                    'field' => $field,
                                    'value' => $value
                                ]
                            ];
                            if ($studentId) {
                                $this->sendToStudent($studentId, json_encode($response));
                            } else {
                                $this->broadcastToAll(json_encode($response));
                            }
                            $this->logMessage($response);
                            echo "Attendance $attendanceId updated: $field = $value\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing attendance_id, field, or value']);
                }
                break;

            case 'DELETE_ATTENDANCE':
                if (isset($data['payload']['attendance_id'])) {
                    $attendanceId = $data['payload']['attendance_id'];

                    $studentId = null;
                    if ($this->pdo) {
                        $stmt = $this->pdo->prepare("SELECT student_id FROM attendance WHERE attendance_id = ?");
                        $stmt->execute([$attendanceId]);
                        $studentId = $stmt->fetchColumn();

                        try {
                            $stmt = $this->pdo->prepare("DELETE FROM attendance WHERE attendance_id = ?");
                            $stmt->execute([$attendanceId]);
                            echo "Attendance $attendanceId deleted.\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                            break;
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                        break;
                    }

                    $response = ['type' => 'ATTENDANCE_DELETED', 'payload' => ['attendance_id' => $attendanceId]];
                    if ($studentId) {
                        $this->sendToStudent($studentId, json_encode($response));
                    } else {
                        $this->broadcastToAll(json_encode($response));
                    }
                    $this->logMessage($response);
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing attendance_id']);
                }
                break;

            // ---------- EVENTS CRUD ----------
            case 'CREATE_EVENT':
                if (isset($data['payload']['event_name'], $data['payload']['event_date'], $data['payload']['event_type'])) {
                    $eventName = $data['payload']['event_name'];
                    $eventDate = $data['payload']['event_date'];
                    $eventType = $data['payload']['event_type'];
                    $halfDayPeriod = $data['payload']['half_day_period'] ?? null;
                    $description = $data['payload']['description'] ?? null;
                    $location = $data['payload']['location'] ?? null;
                    $createdBy = $data['payload']['created_by'] ?? null;

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("
                                INSERT INTO events (event_name, event_date, event_type, half_day_period, description, location, created_by)
                                VALUES (:name, :date, :type, :half, :desc, :loc, :creator)
                            ");
                            $stmt->execute([
                                ':name' => $eventName,
                                ':date' => $eventDate,
                                ':type' => $eventType,
                                ':half' => $halfDayPeriod,
                                ':desc' => $description,
                                ':loc' => $location,
                                ':creator' => $createdBy
                            ]);
                            $eventId = $this->pdo->lastInsertId();

                            $response = [
                                'type' => 'EVENT_CREATED',
                                'payload' => [
                                    'event_id' => $eventId,
                                    'event_name' => $eventName,
                                    'event_date' => $eventDate,
                                    'event_type' => $eventType,
                                    'half_day_period' => $halfDayPeriod,
                                    'description' => $description,
                                    'location' => $location,
                                    'created_by' => $createdBy
                                ]
                            ];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Event created: ID $eventId\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing required fields for event']);
                }
                break;

            case 'READ_EVENT':
                $filters = [];
                $params = [];
                if (isset($data['payload']['event_id'])) {
                    $filters[] = "event_id = :eid";
                    $params[':eid'] = $data['payload']['event_id'];
                }
                if (isset($data['payload']['event_date'])) {
                    $filters[] = "event_date = :edate";
                    $params[':edate'] = $data['payload']['event_date'];
                }

                $sql = "SELECT * FROM events";
                if (!empty($filters)) {
                    $sql .= " WHERE " . implode(' AND ', $filters);
                }

                if ($this->pdo) {
                    try {
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $this->sendToClient($from, ['type' => 'EVENT_READ', 'payload' => $rows]);
                    } catch (PDOException $e) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                }
                break;

            case 'UPDATE_EVENT':
                if (isset($data['payload']['event_id'], $data['payload']['field'], $data['payload']['value'])) {
                    $eventId = $data['payload']['event_id'];
                    $field = $data['payload']['field'];
                    $value = $data['payload']['value'];

                    // Allowed fields (adjust to your table columns)
                    $allowedFields = ['event_name', 'event_date', 'event_type', 'half_day_period', 'description', 'location', 'created_by'];
                    if (!in_array($field, $allowedFields)) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => "Invalid field: $field"]);
                        break;
                    }

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("UPDATE events SET $field = :value WHERE event_id = :id");
                            $stmt->execute([':value' => $value, ':id' => $eventId]);

                            $response = [
                                'type' => 'EVENT_UPDATED',
                                'payload' => [
                                    'event_id' => $eventId,
                                    'field' => $field,
                                    'value' => $value
                                ]
                            ];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Event $eventId updated: $field = $value\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing event_id, field, or value']);
                }
                break;

            case 'DELETE_EVENT':
                if (isset($data['payload']['event_id'])) {
                    $eventId = $data['payload']['event_id'];

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("DELETE FROM events WHERE event_id = ?");
                            $stmt->execute([$eventId]);
                            echo "Event $eventId deleted.\n";

                            $response = ['type' => 'EVENT_DELETED', 'payload' => ['event_id' => $eventId]];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing event_id']);
                }
                break;

            // ---------- OFFICERS CRUD ----------
            case 'CREATE_OFFICER':
                if (isset($data['payload']['officer_id'], $data['payload']['full_name'], $data['payload']['password'], $data['payload']['position'])) {
                    $officerId   = $data['payload']['officer_id'];
                    $fullName    = $data['payload']['full_name'];
                    $plainPwd    = $data['payload']['password'];
                    $position    = $data['payload']['position'];

                    // Validate position
                    if (!in_array($position, ['Admin', 'Officer'])) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'Invalid position']);
                        break;
                    }

                    // Hash password
                    $hashedPwd = password_hash($plainPwd, PASSWORD_DEFAULT);

                    if ($this->pdo) {
                        try {
                            // Check for duplicate officer_id
                            $stmt = $this->pdo->prepare("SELECT officer_id FROM officers WHERE officer_id = :oid");
                            $stmt->execute([':oid' => $officerId]);
                            if ($stmt->fetch()) {
                                $this->sendToClient($from, ['type' => 'error', 'message' => 'Officer ID already exists']);
                                break;
                            }

                            $stmt = $this->pdo->prepare("
                                INSERT INTO officers (officer_id, full_name, password, position, created_at)
                                VALUES (:oid, :name, :pwd, :pos, NOW())
                            ");
                            $stmt->execute([
                                ':oid'  => $officerId,
                                ':name' => $fullName,
                                ':pwd'  => $hashedPwd,
                                ':pos'  => $position
                            ]);

                            $response = [
                                'type'    => 'OFFICER_CREATED',
                                'payload' => [
                                    'officer_id' => $officerId,
                                    'full_name'  => $fullName,
                                    'position'   => $position
                                ]
                            ];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Officer created: $officerId\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing required fields for officer']);
                }
                break;

            case 'READ_OFFICER':
                $filters = [];
                $params  = [];
                if (isset($data['payload']['officer_id'])) {
                    $filters[] = "officer_id = :oid";
                    $params[':oid'] = $data['payload']['officer_id'];
                }
                // Exclude password from results
                $sql = "SELECT officer_id, full_name, position, created_at FROM officers";
                if (!empty($filters)) {
                    $sql .= " WHERE " . implode(' AND ', $filters);
                }

                if ($this->pdo) {
                    try {
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $this->sendToClient($from, ['type' => 'OFFICER_READ', 'payload' => $rows]);
                    } catch (PDOException $e) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                }
                break;

            case 'UPDATE_OFFICER':
                if (isset($data['payload']['officer_id'], $data['payload']['field'], $data['payload']['value'])) {
                    $officerId = $data['payload']['officer_id'];
                    $field     = $data['payload']['field'];
                    $value     = $data['payload']['value'];

                    $allowedFields = ['full_name', 'position']; // Add more if needed, but never allow password update here (handle separately)
                    if (!in_array($field, $allowedFields)) {
                        $this->sendToClient($from, ['type' => 'error', 'message' => "Invalid field: $field"]);
                        break;
                    }

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("UPDATE officers SET $field = :value WHERE officer_id = :oid");
                            $stmt->execute([':value' => $value, ':oid' => $officerId]);

                            $response = [
                                'type'    => 'OFFICER_UPDATED',
                                'payload' => [
                                    'officer_id' => $officerId,
                                    'field'      => $field,
                                    'value'      => $value
                                ]
                            ];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Officer $officerId updated: $field = $value\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing officer_id, field, or value']);
                }
                break;

            case 'DELETE_OFFICER':
                if (isset($data['payload']['officer_id'])) {
                    $officerId = $data['payload']['officer_id'];

                    if ($this->pdo) {
                        try {
                            $stmt = $this->pdo->prepare("DELETE FROM officers WHERE officer_id = ?");
                            $stmt->execute([$officerId]);

                            $response = ['type' => 'OFFICER_DELETED', 'payload' => ['officer_id' => $officerId]];
                            $this->broadcastToAll(json_encode($response));
                            $this->logMessage($response);
                            echo "Officer $officerId deleted.\n";
                        } catch (PDOException $e) {
                            $this->sendToClient($from, ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                    } else {
                        $this->sendToClient($from, ['type' => 'error', 'message' => 'No database connection']);
                    }
                } else {
                    $this->sendToClient($from, ['type' => 'error', 'message' => 'Missing officer_id']);
                }
                break;

            case 'ping':
                $this->sendToClient($from, ['type' => 'pong']);
                break;

            default:
                // ignore unknown
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        if ($conn->studentId !== null && isset($this->students[$conn->studentId])) {
            $this->students[$conn->studentId] = array_filter(
                $this->students[$conn->studentId],
                function($c) use ($conn) { return $c !== $conn; }
            );
            if (empty($this->students[$conn->studentId])) {
                unset($this->students[$conn->studentId]);
            }
        }

        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start the server
$loop = LoopFactory::create();
$webSocketServer = new WsServer(new AttendanceUpdateServer($loop));
$httpServer = new HttpServer($webSocketServer);
$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server = new IoServer($httpServer, $socket, $loop);

echo "WebSocket server started on port 8080\n";
$server->run();