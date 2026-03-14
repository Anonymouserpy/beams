<?php
require __DIR__ . '/../vendor/autoload.php';   // Adjust path if needed

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;

class StudentUpdateServer implements MessageComponentInterface
{
    protected $clients;
    protected $loop;
    protected $internalSocket;

    public function __construct($loop)
    {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;
        $this->setupInternalSocket();
    }

    protected function setupInternalSocket()
    {
        // Use a TCP port for internal communication (Windows compatible)
        $internalPort = 8081;  // Different from WebSocket port 8080
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
        if ($message) {
            $this->broadcastToAll(json_encode($message));
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New WebSocket connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        switch ($data['type'] ?? '') {
            case 'subscribe':
                $from->send(json_encode(['type' => 'subscribed', 'channel' => $data['channel'] ?? 'all']));
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
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastToAll($message)
    {
        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

$loop = LoopFactory::create();
$webSocketServer = new WsServer(new StudentUpdateServer($loop));
$httpServer = new HttpServer($webSocketServer);
$socket = new React\Socket\Server('0.0.0.0:8080', $loop);
$server = new IoServer($httpServer, $socket, $loop);

echo "WebSocket server started on port 8080\n";
$server->run();