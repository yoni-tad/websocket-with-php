<?php
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';

switch ($action) {
    case 'get_data':
        $result = $mysqli->query("SELECT * FROM $table ORDER BY id DESC");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data);
        break;

    case 'add_data':
        $data = json_decode(file_get_contents('php://input'), true);
        $columns = implode(", ", array_keys($data));
        $values = implode("', '", array_map([$mysqli, 'real_escape_string'], array_values($data)));
        $query = "INSERT INTO $table ($columns) VALUES ('$values')";
        $mysqli->query($query);
        echo json_encode(['status' => 'success']);
        break;

    case 'update_data':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $mysqli->real_escape_string($data['id']);
        unset($data['id']);
        $setClause = '';
        foreach ($data as $key => $value) {
            $setClause .= "`$key` = '" . $mysqli->real_escape_string($value) . "', ";
        }
        $setClause = rtrim($setClause, ', ');
        $query = "UPDATE $table SET $setClause WHERE id = $id";
        $mysqli->query($query);
        echo json_encode(['status' => 'success']);
        break;

    case 'delete_data':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $mysqli->real_escape_string($data['id']);
        $query = "DELETE FROM $table WHERE id = $id";
        $mysqli->query($query);
        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$mysqli->close();
