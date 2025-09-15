<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $group_id = intval($_POST['group_id']);
    $user_id = $_SESSION['user_id'];

    // Verifica se l'utente è il capo del gruppo
    $stmt = $conn->prepare("
        SELECT id FROM groups 
        WHERE id = ? AND creator_id = ?
    ");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Genera un token univoco
        $token = bin2hex(random_bytes(16));

        // Salva il token nel database
        $stmt_insert = $conn->prepare("
            INSERT INTO group_invitations (group_id, token) 
            VALUES (?, ?)
        ");
        $stmt_insert->bind_param("is", $group_id, $token);

        if ($stmt_insert->execute()) {
            $invite_link = "http://151.55.137.205/link4schooll-main/join_group.php?token=" . $token;
            echo json_encode(['success' => true, 'invite_link' => $invite_link]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore durante la generazione del link di invito.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Non hai i permessi per generare un link di invito per questo gruppo.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida.']);
}
?>