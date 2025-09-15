<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    $reply_id = intval($_POST['reply_id']);

    // Verifica se l'utente è un capo del gruppo
    $stmt = $conn->prepare("
        SELECT g.id 
        FROM replies r
        JOIN posts p ON r.post_id = p.id
        JOIN groups g ON p.group_id = g.id
        JOIN group_members gm ON g.id = gm.group_id
        WHERE r.id = ? AND gm.user_id = ? AND gm.is_leader = 1
    ");
    $stmt->bind_param("ii", $reply_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Elimina la risposta
        $stmt_delete = $conn->prepare("DELETE FROM replies WHERE id = ?");
        $stmt_delete->bind_param("i", $reply_id);
        if ($stmt_delete->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione della risposta.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Non hai i permessi per eliminare questa risposta.']);
    }
}
?>