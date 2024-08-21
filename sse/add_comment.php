<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $message = $_POST['message'];

    if (!empty($name) && !empty($message)) {
        $mysqli = new mysqli("localhost", "root", "", "test");

        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        $stmt = $mysqli->prepare("INSERT INTO comments (name, message) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $message);

        if ($stmt->execute()) {
            echo "Comment added";
        } else {
            echo "Error adding comment: " . $stmt->error;
        }

        $stmt->close();
        $mysqli->close();
    } else {
        echo "Error: Name and message are required";
    }
}
