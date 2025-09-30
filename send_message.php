<?php
session_start();
require 'config.php';

// Imposto header JSON
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verifica autenticazione
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Non autorizzato']);
    exit;
}

// Dati input
$sender_id   = intval($_SESSION['user_id']);
$receiver_id = isset($_POST['to']) ? intval($_POST['to']) : 0;
$message     = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validazioni minime
if ($receiver_id <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Parametri mancanti o non validi']);
    exit;
}

// Controllo se il destinatario ti ha bloccato
$stmt = $conn->prepare("
    SELECT 1 FROM user_blocks
    WHERE blocker_id = ? AND blocked_id = ?
");
$stmt->bind_param("ii", $receiver_id, $sender_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    // Sei bloccato dal destinatario
    echo json_encode(['status' => 'blocked', 'error' => 'Non puoi inviare messaggi a questo utente']);
    $stmt->close();
    exit;
}
$stmt->close();

// Inserimento messaggio
$stmt = $conn->prepare("
    INSERT INTO messages (sender_id, receiver_id, message, sent_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Database error: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$stmt->close();
echo json_encode(['status' => 'ok']);
