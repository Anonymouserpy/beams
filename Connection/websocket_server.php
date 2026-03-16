<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;

class StudentUpdateServer implements MessageComponentInterface
{
    protected $clients;                 // SplObjectStorage of all connections
    protected $students;                 // associative array: student_id => [connections]
    protected $loop;
    protected $internalSocket;

    public function __construct($loop)
    {
        $this->clients = new \SplObjectStorage;
        $this->students = [];
        $this->loop = $loop;
        $this->setupInternalSocket();
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

        // If the message has a student_id, send only to that student's connections
        if (isset($message['student_id'])) {
            $this->sendToStudent($message['student_id'], json_encode($message));
        } else {
            // Otherwise broadcast to all (e.g., general announcements)
            $this->broadcastToAll(json_encode($message));
        }
    }

    /**
     * Send a message to all connections of a specific student
     */
    protected function sendToStudent($studentId, $message)
    {
        if (isset($this->students[$studentId])) {
            foreach ($this->students[$studentId] as $conn) {
                $conn->send($message);
            }
        }
    }

    /**
     * Broadcast a message to all connected clients
     */
    protected function broadcastToAll($message)
    {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        // Initially no student_id assigned
        $conn->studentId = null;
        echo "New WebSocket connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        switch ($data['type'] ?? '') {
            case 'subscribe':
                // Expect a student_id in the message
                if (isset($data['student_id'])) {
                    $studentId = $data['student_id'];

                    // Remove from any previous student mapping (if already subscribed)
                    if ($from->studentId !== null && isset($this->students[$from->studentId])) {
                        $this->students[$from->studentId] = array_filter(
                            $this->students[$from->studentId],
                            function($c) use ($from) { return $c !== $from; }
                        );
                    }

                    // Add to new student's connection list
                    if (!isset($this->students[$studentId])) {
                        $this->students[$studentId] = [];
                    }
                    $this->students[$studentId][] = $from;
                    $from->studentId = $studentId;

                    $from->send(json_encode(['type' => 'subscribed', 'student_id' => $studentId]));
                    echo "Connection {$from->resourceId} subscribed to student $studentId\n";
                }
                break;

            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;

            default:
                // ignore
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        // Remove from global clients
        $this->clients->detach($conn);

        // Remove from student mapping if present
        if ($conn->studentId !== null && isset($this->students[$conn->studentId])) {
            $this->students[$conn->studentId] = array_filter(
                $this->students[$conn->studentId],
                function($c) use ($conn) { return $c !== $conn; }
            );
            // Clean up empty arrays
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

$loop = LoopFactory::create();
$webSocketServer = new WsServer(new StudentUpdateServer($loop));
$httpServer = new HttpServer($webSocketServer);
$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server = new IoServer($httpServer, $socket, $loop);

echo "WebSocket server started on port 8080\n";
$server->run();