<?php
header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$user = 'root';
$password = ''; // Add your MySQL password here
$dbname = 'test'; // Your database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Check the request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Handle GET request: fetch comments
    $sql = 'SELECT * FROM comments ORDER BY created_at DESC';
    $result = $conn->query($sql);

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'name' => $row['name'],
            'message' => $row['message'],
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode($comments);
} elseif ($method === 'POST') {
    // Handle POST request: add a comment
    $name = $_POST['name'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($name) || empty($message)) {
        echo json_encode(['error' => 'Name and message are required']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO comments (name, message, created_at) VALUES (?, ?, NOW())');
    $stmt->bind_param('ss', $name, $message);

    if ($stmt->execute()) {
        echo json_encode(['success' => 'Comment added']);
    } else {
        echo json_encode(['error' => 'Error adding comment']);
    }
} else {
    // Unsupported method
    echo json_encode(['error' => 'Unsupported request method']);
}

$conn->close();
?>
