<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id = $_SESSION['user_id'];
$to = isset($_POST['to']) ? intval($_POST['to']) : 0;

if ($to === 0) {
    header("Location: chat.php");
    exit;
}

// Controlla se esiste già una richiesta in sospeso
$stmt = $conn->prepare("
    SELECT id FROM chat_requests 
    WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $my_id, $to);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Esiste già una richiesta pendente
    $_SESSION['message'] = "Hai già inviato una richiesta di chat.";
    header("Location: chat.php?with=" . $to);
    exit;
}

// Aggiungi la richiesta di chat
$stmt = $conn->prepare("
    INSERT INTO chat_requests (sender_id, receiver_id, status, created_at)
    VALUES (?, ?, 'pending', NOW())
");
$stmt->bind_param("ii", $my_id, $to);
$stmt->execute();

$_SESSION['message'] = "Richiesta di chat inviata.";
header("Location: chat.php?with=" . $to);
exit;
?>
