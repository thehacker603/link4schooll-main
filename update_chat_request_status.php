<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['request_id']) || !isset($_POST['status'])) {
    exit;
}

$my_id = $_SESSION['user_id'];
$request_id = (int)$_POST['request_id'];
$status = $_POST['status'];

// Verifica che la richiesta di chat appartenga all'utente che sta cercando di approvarla o rifiutarla
$stmt = $conn->prepare("SELECT receiver_id FROM chat_requests WHERE id = ? AND receiver_id = ?");
$stmt->bind_param("ii", $request_id, $my_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo "Errore: La richiesta non esiste o non appartiene a questo utente.";
    exit;
}

// Aggiorna lo stato della richiesta
$stmt = $conn->prepare("UPDATE chat_requests SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $request_id);
$stmt->execute();

// Se la richiesta è stata approvata, creiamo una conversazione
if ($status == 'approved') {
    // Ottieni l'ID dell'utente che ha inviato la richiesta
    $stmt = $conn->prepare("SELECT sender_id FROM chat_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->bind_result($sender_id);
    $stmt->fetch();
    $stmt->close();

    // Verifica se esiste già una conversazione tra gli utenti
    $stmt = $conn->prepare("
        SELECT id FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?)
    ");
    $stmt->bind_param("iiii", $my_id, $sender_id, $sender_id, $my_id);
    $stmt->execute();
    $stmt->store_result();

    // Se non esiste una conversazione, creala
    if ($stmt->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, 'Ciao')");
        $stmt->bind_param("ii", $my_id, $sender_id);
        $stmt->execute();
    }

    echo "La richiesta è stata approvata e la chat è stata avviata!";
} else {
    echo "Richiesta rifiutata.";
}

exit;
