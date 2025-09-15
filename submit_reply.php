<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $post_id = intval($_POST['post_id']);
    $content = trim($_POST['reply_content']);

    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Il contenuto della risposta non può essere vuoto.']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO replies (content, user_id, post_id) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Errore nella query SQL: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("sii", $content, $user_id, $post_id);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'username' => $_SESSION['username'], // Assicurati che il nome utente sia salvato nella sessione
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}
?>