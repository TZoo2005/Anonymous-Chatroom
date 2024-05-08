<?php
header("Content-Type: application/json");

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $roomId = $_GET['room'] ?? null;

        $sql = "SELECT * FROM messages";
        if ($roomId !== null && $roomId !== '') {
            $sql .= " WHERE room_secret = :room_secret";
        } else {
            $sql .= " WHERE room_secret IS NULL";
        }
        $sql .= " ORDER BY timestamp ASC";

        $stmt = $pdo->prepare($sql);
        if ($roomId !== null && $roomId !== '') {
            $stmt->bindParam(':room_secret', $roomId);
        }
        $stmt->execute();

        $messages = $stmt->fetchAll();
        echo json_encode($messages);
} else {
    http_response_code(405);
    echo json_encode(array("error" => "Method Not Allowed"));
}
?>
