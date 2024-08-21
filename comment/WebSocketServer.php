<?php
class WebSocketServer
{
    private $host = 'localhost';
    private $port = 8081;
    private $clients = [];

    public function __construct()
    {
        $serverSocket = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        if (!$serverSocket) {
            die("Error creating server: $errstr ($errno)\n");
        }

        echo "WebSocket server running on ws://{$this->host}:{$this->port}\n";

        $this->acceptClients($serverSocket);
    }

    private function acceptClients($serverSocket)
    {
        while (true) {
            $clientSocket = stream_socket_accept($serverSocket, -1);
            if ($clientSocket) {
                $this->performHandshake($clientSocket);
                $this->clients[] = $clientSocket;

                $this->listenToClients($clientSocket);
            }
        }
    }

    private function performHandshake($clientSocket)
    {
        $request = fread($clientSocket, 1024);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        $key = base64_encode(pack('H*', sha1(trim($matches[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        $headers = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $key\r\n\r\n";
        fwrite($clientSocket, $headers);
    }

    private function listenToClients($clientSocket)
    {
        while (true) {
            $data = fread($clientSocket, 1024);
            if (!$data) {
                fclose($clientSocket);
                $this->clients = array_filter($this->clients, function ($client) use ($clientSocket) {
                    return $client !== $clientSocket;
                });
                break;
            }

            $message = $this->unmask($data);
            $this->handleMessage($clientSocket, $message);
        }
    }

    private function handleMessage($clientSocket, $message)
    {
        $jsonMessage = json_decode($message, true);
        $action = $jsonMessage['action'] ?? '';

        if ($action == 'add_data') {
            $table = $jsonMessage['table'] ?? '';
            $data = $jsonMessage['data'] ?? [];
            $this->addData($table, $data);
            $this->broadcastMessage(json_encode([
                'action' => 'new_data',
                'table' => $table,
                'data' => $data,
            ]));
        } elseif ($action == 'get_data') {
            $table = $jsonMessage['table'] ?? '';
            $data = $this->getData($table);
            fwrite($clientSocket, $this->mask(json_encode([
                'action' => 'data_list',
                'table' => $table,
                'data' => $data,
            ])));
        }
    }

    private function addData($table, $data)
    {
        $conn = new mysqli("localhost", "root", "", "test");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $columns = implode(", ", array_keys($data));
        $values = implode("', '", array_map([$conn, 'real_escape_string'], array_values($data)));
        $query = "INSERT INTO $table ($columns) VALUES ('$values')";

        $conn->query($query);
        $conn->close();
    }

    private function getData($table)
    {
        $conn = new mysqli("localhost", "root", "", "test");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $result = $conn->query("SELECT * FROM $table ORDER BY id DESC");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $conn->close();
        return $data;
    }

    private function broadcastMessage($message)
    {
        foreach ($this->clients as $client) {
            fwrite($client, $this->mask($message));
        }
    }

    private function unmask($payload)
    {
        $length = ord($payload[1]) & 127;
        if ($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } elseif ($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }
        $text = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    private function mask($text)
    {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }

        return $header . $text;
    }

    public function run()
    {
        // Just calling the constructor starts the server
    }
}

// To use the WebSocket server, create an instance of the class and call run()
$server = new WebSocketServer();
$server->run();
