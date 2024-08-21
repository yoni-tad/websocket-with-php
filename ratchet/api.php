<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'test';

$mysqli = new mysqli($host, $user, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';

switch ($action) {
    case 'get_data':
        $query = "SELECT * FROM $table";
        $result = $mysqli->query($query);
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;

    case 'add_data':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $mysqli->real_escape_string($data['name']);
        $message = $mysqli->real_escape_string($data['message']);
        $query = "INSERT INTO $table (name, message) VALUES ('$name', '$message')";
        $mysqli->query($query);
        echo json_encode(['status' => 'success']);
        break;

    // You can add more cases for update_data and delete_data

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$mysqli->close();
