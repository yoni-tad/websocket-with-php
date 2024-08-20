<?php
$host = 'localhost';
$port = 8081;

// Create WebSocket server
$server = stream_socket_server("tcp://$host:$port", $errno, $errorMessage);
if (!$server) {
    die("Error creating server: $errorMessage\n");
}

echo "WebSocket server running on ws://$host:$port\n";

while (true) {
    $client = @stream_socket_accept($server, -1);
    if ($client) {
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

            // Connect to MySQL database
            $conn = new mysqli("localhost", "root", "", "test");
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            while (true) {
                // Fetch data from the comments table
                $result = $conn->query("SELECT name, message, created_at FROM comments");
                while ($row = $result->fetch_assoc()) {
                    $msg = "Name: " . $row['name'] . " | Message: " . $row['message'] . " | Created at: " . $row['created_at'];
                    sendWebSocketMessage($client, $msg);
                }
                sleep(10); // Wait for 10 seconds before fetching data again
            }
        }

        fclose($client);
    }
}

fclose($server);

// Function to send a WebSocket message
function sendWebSocketMessage($client, $message) {
    $length = strlen($message);
    $header = chr(0x81); // Text frame, FIN bit set
    if ($length <= 125) {
        $header .= chr($length);
    } elseif ($length >= 126 && $length <= 65535) {
        $header .= chr(126) . pack('n', $length);
    } else {
        $header .= chr(127) . pack('J', $length);
    }
    fwrite($client, $header . $message);
}
