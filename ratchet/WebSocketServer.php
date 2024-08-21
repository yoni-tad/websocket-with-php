<?php
require 'vendor/autoload.php'; // Make sure this path is correct

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->db = new mysqli("localhost", "root", "", "test");

        if ($this->db->connect_error) {
            die("Connection failed: " . $this->db->connect_error);
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        switch ($data['action']) {
            case 'add_data':
                $this->addData($data['table'], $data['data']);
                $this->broadcastData($data['table']);
                break;

            case 'get_data':
                $this->sendData($from, $data['table']);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function addData($table, $data)
    {
        $name = $this->db->real_escape_string($data['name']);
        $message = $this->db->real_escape_string($data['message']);
        $query = "INSERT INTO $table (name, message) VALUES ('$name', '$message')";
        $this->db->query($query);
    }

    private function sendData(ConnectionInterface $conn, $table)
    {
        $query = "SELECT * FROM $table";
        $result = $this->db->query($query);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $conn->send(json_encode([
            'action' => 'data_list',
            'data' => $data
        ]));
    }

    private function broadcastData($table)
    {
        $query = "SELECT * FROM $table";
        $result = $this->db->query($query);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        foreach ($this->clients as $client) {
            $client->send(json_encode([
                'action' => 'data_list',
                'data' => $data
            ]));
        }
    }
}

// Create and run the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    8081
);

$server->run();
