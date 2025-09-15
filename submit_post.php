<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = intval($_POST['group_id']);
$content = trim($_POST['post_content']);

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Il contenuto del post non può essere vuoto.']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO group_posts (group_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Errore nella query SQL: ' . $conn->error]);
    exit();
}

$stmt->bind_param("iis", $group_id, $user_id, $content);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Errore durante l\'inserimento del post.']);
}
?>