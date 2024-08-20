<?php
$mysqli = new mysqli("localhost", "root", "", "test");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Example of querying data and sending it
$query = "SELECT * FROM comments";
$result = $mysqli->query($query);
$data = $result->fetch_all(MYSQLI_ASSOC);
foreach ($data as $row) {
    $json = json_encode($row);
    $response = chr(129) . chr(strlen($json)) . $json;
    fwrite($client, $response);
}

// webserver in php
set_time_limit(0);
$host = '127.0.0.1';
$port = 8080;

// create websocket server
$server = stream_socket_server("tcp://$host:$port", $errno, $errorMessage);
if (!$server) {
    die("Failed to create socket: $errorMessage");
}

echo "Websocket server started at ws://$host:$port\n";

while (true) {
    // Accept incoming message
    $client = stream_socket_accept($server);
    if ($client) {
        // perform websocket handshake
        $headers = fread($client, 1024);
        $headers = explode("\r\n", $headers);
        foreach ($headers as $header) {
            if (strpos($header, 'Sec-Websocket-key:') !== false) {
                $key = trim(substr($header, 17));
            }
        }
        $accept = base64_encode(sha1($key, '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $accept\r\n\r\n";
        fwrite($client, $response);

        // Read data from client and send a response
        while (!feof($client)) {
            $data = fread($client, 1024);
            if ($data) {
                // Decode WebSocket frame
                $frame = ord($data[1]) & 127;
                $text = substr($data, 6, $frame);
                // Send a response (for example, echoing the received data)
                $response = chr(129) . chr(strlen($text)) . $text;
                fwrite($client, $response);
            }
        }
        fclose($client);
    }
}
fclose($server);
