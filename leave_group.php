<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

// Verifica che `group_id` sia presente nella richiesta POST
if (!isset($_POST['group_id']) || empty($_POST['group_id'])) {
    die("Errore: ID del gruppo non fornito.");
}

$group_id = intval($_POST['group_id']); // Assicurati che sia un numero intero

// Verifica se l'utente è membro del gruppo
$stmt_check = $conn->prepare("SELECT * FROM group_members WHERE user_id = ? AND group_id = ?");
$stmt_check->bind_param("ii", $user_id, $group_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    die("Errore: Non sei membro di questo gruppo.");
}

// Rimuove l'utente dal gruppo nella tabella group_members
$stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = ? AND group_id = ?");
$stmt->bind_param("ii", $user_id, $group_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Reindirizza alla dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        die("Errore: impossibile abbandonare il gruppo. Il record non è stato trovato.");
    }
} else {
    die("Errore: impossibile eseguire la query per abbandonare il gruppo.");
}
?>
