<?php
session_start();
require 'config.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'], $_GET['with'])) {
    echo json_encode(['error' => 'Parametri mancanti']);
    exit;
}

$my_id     = intval($_SESSION['user_id']);
$chat_with = intval($_GET['with']);

$stmt = $conn->prepare("
    SELECT sender_id,
           receiver_id,
           message,
           sent_at
    FROM messages
    WHERE (sender_id = ? AND receiver_id = ?)
       OR (sender_id = ? AND receiver_id = ?)
    ORDER BY sent_at ASC
");
$stmt->bind_param("iiii", $my_id, $chat_with, $chat_with, $my_id);

if (!$stmt->execute()) {
    echo json_encode(['error' => $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
echo json_encode($messages);
