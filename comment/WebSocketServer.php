<?php
class WebSocketServer
{
    private $host = 'localhost';
    private $port = 8081;
    private $clients = [];
    private $db;

    public function __construct()
    {
        // Create a new mysqli connection
        $this->db = new mysqli('localhost', 'root', '', 'test');
        if ($this->db->connect_error) {
            die("Database connection failed: " . $this->db->connect_error);
        }
    }

    public function run()
    {
        $server = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errorMessage);
        if (!$server) {
            die("Error creating server: $errorMessage\n");
        }

        echo "WebSocket server running on ws://{$this->host}:{$this->port}\n";

        while (true) {
            $read = $this->clients;
            $read[] = $server;

            if (stream_select($read, $write, $except, null) > 0) {
                if (in_array($server, $read)) {
                    $client = stream_socket_accept($server);
                    if ($client) {
                        $this->clients[] = $client;
                        $this->performHandshake($client);
                    }
                    $read = array_diff($read, [$server]);
                }

                foreach ($read as $socket) {
                    if ($socket !== $server) {
                        $data = fread($socket, 1024);
                        if ($data) {
                            $this->handleClientMessage($data, $socket);
                        }
                    }
                }
            }

            // Remove disconnected clients
            foreach ($this->clients as $key => $client) {
                if (feof($client)) {
                    fclose($client);
                    unset($this->clients[$key]);
                }
            }
        }

        fclose($server);
    }

    private function performHandshake($client)
    {
        $headers = fread($client, 1024);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $headers, $matches);

        if (isset($matches[1])) {
            $secKey = trim($matches[1]);
            $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

            $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: $secAccept\r\n\r\n";
            fwrite($client, $handshakeResponse);
        }
    }

    private function handleClientMessage($data, $socket)
    {
        $message = '';
        if (ord($data[0]) === 129) {
            $length = ord($data[1]) & 127;
            if ($length === 126) {
                $length = unpack('n', substr($data, 2, 2))[1];
            } elseif ($length === 127) {
                $length = unpack('J', substr($data, 2, 8))[1];
            }
            $message = substr($data, 2 + ($length > 125 ? ($length === 126 ? 2 : 8) : 0), $length);

            // Broadcast the message to all clients
            foreach ($this->clients as $client) {
                if ($client !== $socket) {
                    fwrite($client, chr(129) . chr(strlen($message)) . $message);
                }
            }

            // Optionally, save the comment to the database here
            // $this->saveCommentToDatabase($message);
        }
    }

    private function saveCommentToDatabase($comment)
    {
        $stmt = $this->db->prepare("INSERT INTO comments (message) VALUES (?)");
        $stmt->bind_param("s", $comment);
        $stmt->execute();
        $stmt->close();
    }
}
