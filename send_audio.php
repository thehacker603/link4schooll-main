<?php
session_start();
include 'db.php'; // Connessione al DB

if(!isset($_FILES['audio']) || !isset($_POST['to'])){
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File o destinatario mancante']);
    exit;
}

$audio = $_FILES['audio'];
$to = intval($_POST['to']);
$user_id = $_SESSION['user_id'];

// Salva audio
$filename = 'uploads/audio_' . time() . '.webm';
move_uploaded_file($audio['tmp_name'], $filename);

// Inserisci messaggio nel DB
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, type) VALUES (?, ?, ?, ?)");
$type = 'audio';
$stmt->bind_param("iiss", $user_id, $to, $filename, $type);
$stmt->execute();

echo json_encode(['success' => true, 'url' => $filename]);
?>
