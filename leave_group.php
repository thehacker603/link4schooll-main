<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];

// ✅ Accetta sia GET che POST
$group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : 0;

if ($group_id <= 0) {
    die("Errore: ID del gruppo non fornito o non valido.");
}

// Cancella dalla tabella
$stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = ? AND group_id = ?");
$stmt->bind_param("ii", $user_id, $group_id);

if ($stmt->execute()) {
    header("Location: dashboard.php");
    exit();
} else {
    die("Errore nell'esecuzione della query: " . $stmt->error);
}
?>
