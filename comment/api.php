<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'test';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'] ?? '';
    if ($message) {
        $stmt = $conn->prepare("INSERT INTO comments (message) VALUES (?)");
        $stmt->bind_param("s", $message);
        $stmt->execute();
        $stmt->close();
        echo 'Comment added';

        // Optionally, trigger WebSocket server to broadcast the new comment
        // $this->broadcastNewComment($message);
    } else {
        echo 'No message provided';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['endpoint']) && $_GET['endpoint'] === 'get_comments') {
    $result = $conn->query("SELECT message FROM comments");
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row['message'];
    }
    header('Content-Type: application/json');
    echo json_encode($comments);
    exit;
}

echo 'Invalid request';
